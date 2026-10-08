<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Store;
use App\Models\User;
use App\Models\TenantSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Modules\Chat\Models\ChatConversation;
use Modules\Chat\Models\ChatMessage;
use Modules\Chat\Models\ChatParticipant;
use Modules\Chat\Models\PromotionalBroadcast;

class DemoChatSeeder extends Seeder
{
    public const DEFAULT_PASSWORD = 'demo1234';

    public function run(?Company $targetCompany = null): void
    {
        $companies = $targetCompany
            ? collect([$targetCompany])
            : Company::query()->withoutGlobalScopes()
                ->where(function ($query) {
                    $query->where('is_demo', true)
                        ->orWhereIn('slug', [
                            'retail', 'restaurant', 'pharmacy', 'salon', 'repair', 'allmodules',
                            'tenant-demo-all-enterprise', 'tenant-demo-retail', 'tenant-demo-restaurant',
                            'tenant-demo-pharmacy', 'tenant-demo-salon', 'tenant-demo-repairs',
                            'demo-retail', 'demo-restaurant',
                        ]);
                })
                ->get();

        // 1. Ensure Super Admin Promotional Broadcasts are seeded
        $this->seedPromotionalBroadcasts();

        // 2. Seed each demo company
        foreach ($companies as $company) {
            $this->seedCompanyChat($company);
        }

        $this->command?->info("Demo chat and live support successfully initialized for {$companies->count()} workspaces.");
    }

    public function seedCompanyChat(Company $company): void
    {
        // 1. Enable Chat in licensed modules
        $licensed = is_array($company->licensed_modules) ? $company->licensed_modules : [];
        if (! in_array('chat', $licensed, true)) {
            $licensed[] = 'chat';
            $company->forceFill([
                'licensed_modules' => array_values(array_unique($licensed)),
            ])->save();
        }

        // 2. Enable tenant settings for chat & AI
        if (Schema::hasTable('tenant_settings')) {
            $enabledModules = $licensed;
            DB::table('tenant_settings')->updateOrInsert(
                ['tenant_id' => $company->id, 'key' => 'enabled_modules'],
                ['value' => json_encode(array_values(array_unique($enabledModules))), 'updated_at' => now()]
            );

            DB::table('tenant_settings')->updateOrInsert(
                ['tenant_id' => $company->id, 'key' => 'enable_chat_promotions'],
                [
                    'value' => '1',
                    'enable_chat_promotions' => true,
                    'updated_at' => now(),
                ]
            );

            DB::table('tenant_settings')->updateOrInsert(
                ['tenant_id' => $company->id, 'key' => 'enable_ai_reply'],
                [
                    'value' => '1',
                    'enable_ai_reply' => true,
                    'updated_at' => now(),
                ]
            );
        }

        // 3. Find admin user and primary store
        $admin = User::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('role', [User::ROLE_ADMINISTRATOR, 'admin', 'owner'])
            ->first();

        if (! $admin) {
            $admin = User::query()->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->first();
        }

        if (! $admin) {
            return;
        }

        $store = Store::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->first();

        $storeId = $store?->id ?? 1;

        // Ensure admin has store assigned and online status
        if ($store && ! $admin->current_store_id) {
            $admin->forceFill(['current_store_id' => $store->id])->save();
            $admin->stores()->syncWithoutDetaching([$store->id]);
        }
        $admin->forceFill(['last_seen_at' => now()])->save();
        Cache::put('user-is-online-'.$admin->id, true, now()->addHours(24));

        // 4. Provision store-specific staff roster and conversations
        $vertical = strtolower((string) ($company->pos_mode ?? $company->store_type ?? 'retail'));
        $slug = (string) $company->slug;

        $staffRoster = $this->getRosterForCompany($slug, $vertical, $company->id, $storeId);

        foreach ($staffRoster as $memberData) {
            $staff = User::query()->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('email', $memberData['email'])
                ->first();

            if (! $staff) {
                $staff = User::create([
                    'company_id' => $company->id,
                    'name' => $memberData['name'],
                    'login' => $memberData['email'],
                    'email' => $memberData['email'],
                    'password' => Hash::make(self::DEFAULT_PASSWORD),
                    'pin_code' => $memberData['pin_code'],
                    'role' => $memberData['role'],
                    'status' => 'active',
                    'is_demo' => true,
                    'current_store_id' => $storeId,
                    'email_verified_at' => now(),
                    'last_seen_at' => $memberData['is_online'] ? now() : now()->subMinutes(rand(10, 45)),
                ]);
            } else {
                $staff->forceFill([
                    'name' => $memberData['name'],
                    'role' => $memberData['role'],
                    'current_store_id' => $storeId,
                    'status' => 'active',
                    'is_demo' => true,
                    'last_seen_at' => $memberData['is_online'] ? now() : ($staff->last_seen_at ?? now()->subMinutes(20)),
                ])->save();
            }

            if ($store) {
                $staff->stores()->syncWithoutDetaching([$store->id]);
            }

            // Set live presence
            if ($memberData['is_online']) {
                Cache::put('user-is-online-'.$staff->id, true, now()->addHours(24));
            } else {
                Cache::forget('user-is-online-'.$staff->id);
            }

            // Create or sync direct conversation between admin and staff
            $this->seedDirectConversation($company->id, $storeId, $admin, $staff, $memberData['messages']);
        }
    }

    private function seedDirectConversation(string|int $tenantId, int $storeId, User $admin, User $staff, array $messages): void
    {
        // Check if conversation already exists
        $conversation = ChatConversation::where('tenant_id', $tenantId)
            ->where('type', 'direct')
            ->whereHas('participants', fn ($q) => $q->where('user_id', $admin->id))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $staff->id))
            ->first();

        if (! $conversation) {
            $conversation = ChatConversation::create([
                'tenant_id' => $tenantId,
                'store_id' => $storeId,
                'type' => 'direct',
                'title' => $staff->name,
                'last_message_at' => now(),
            ]);

            ChatParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $admin->id,
                'last_read_at' => now()->subMinutes(1),
            ]);

            ChatParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $staff->id,
                'last_read_at' => now(),
            ]);
        }

        // Only insert sample messages if conversation is empty
        if ($conversation->messages()->count() === 0) {
            $baseTime = now()->subMinutes(count($messages) * 5);

            foreach ($messages as $index => $msg) {
                $senderId = ($msg['from'] === 'admin') ? $admin->id : $staff->id;
                $senderType = 'user';
                $msgTime = (clone $baseTime)->addMinutes($index * 4);

                ChatMessage::create([
                    'conversation_id' => $conversation->id,
                    'sender_id'       => $senderId,
                    'sender_type'     => $senderType,
                    'message'         => $msg['text'] ?? '',
                    'attachment_type' => $msg['attachment_type'] ?? null,
                    'attachment_url'  => $msg['attachment_url'] ?? null,
                    'attachment_name' => $msg['attachment_name'] ?? null,
                    'created_at'      => $msgTime,
                    'updated_at'      => $msgTime,
                ]);
            }

            $conversation->update(['last_message_at' => now()]);
        }
    }

    private function getRosterForCompany(string $slug, string $vertical, string|int $companyId, int $storeId): array
    {
        if (str_contains($slug, 'restaurant') || $vertical === 'restaurant') {
            return [
                [
                    'name' => 'Chef Antonio Rossi',
                    'email' => 'chef.antonio@demo.com',
                    'role' => User::ROLE_MANAGER,
                    'pin_code' => '1234',
                    'is_online' => true,
                    'messages' => [
                        ['from' => 'staff', 'text' => 'Good morning! Kitchen prep for lunch service is on schedule.'],
                        ['from' => 'admin', 'text' => 'Morning Chef! Any low stock items before the weekend rush?'],
                        ['from' => 'staff', 'text' => 'Running low on fresh basil and truffle oil for tonight\'s special dinner service.'],
                        ['from' => 'admin', 'text' => 'Vendor delivery is confirmed for 3:30 PM today.'],
                        ['from' => 'staff', 'text' => 'Could you please approve the table 4 pasta substitution on KOT?'],
                    ],
                ],
                [
                    'name' => 'Maya Lin',
                    'email' => 'maya.cafe@demo.com',
                    'role' => User::ROLE_CASHIER,
                    'pin_code' => '2345',
                    'is_online' => true,
                    'messages' => [
                        ['from' => 'staff', 'text' => 'Barista station restocked with specialty beans and oat milk.'],
                        ['from' => 'admin', 'text' => 'Great Maya, thank you.'],
                        ['from' => 'staff', 'text' => 'Customer is asking if the cold brew is eligible for the combo discount?'],
                    ],
                ],
                [
                    'name' => 'David Kumar',
                    'email' => 'david.cafe@demo.com',
                    'role' => User::ROLE_SALESPERSON,
                    'pin_code' => '3456',
                    'is_online' => false,
                    'messages' => [
                        ['from' => 'admin', 'text' => 'David, please verify tables 7 through 12 are clean.'],
                        ['from' => 'staff', 'text' => 'All set! Patio dining section is open.'],
                    ],
                ],
            ];
        }

        if (str_contains($slug, 'pharmacy') || $vertical === 'pharmacy') {
            return [
                [
                    'name' => 'Dr. Rohan Mehta',
                    'email' => 'rohan.pharmacy@demo.com',
                    'role' => User::ROLE_MANAGER,
                    'pin_code' => '1111',
                    'is_online' => true,
                    'messages' => [
                        ['from' => 'admin', 'text' => 'Good morning Dr. Rohan, did the cold-chain vaccine log verify ok?'],
                        ['from' => 'staff', 'text' => 'Yes, temperature logged steady at 4°C. Everything within compliance range.'],
                        ['from' => 'admin', 'text' => 'Has the supplier delivered the Amoxicillin batch?'],
                        ['from' => 'staff', 'text' => 'Yes, verified lot numbers and expiry dates. Need approval on controlled substance log.'],
                    ],
                ],
                [
                    'name' => 'Sunita Rao',
                    'email' => 'sunita.pharmacy@demo.com',
                    'role' => User::ROLE_CASHIER,
                    'pin_code' => '2222',
                    'is_online' => false,
                    'messages' => [
                        ['from' => 'staff', 'text' => 'Counter 2 cash drawer opened and float verified.'],
                        ['from' => 'admin', 'text' => 'Thanks Sunita, please keep patient queue moving.'],
                    ],
                ],
            ];
        }

        if (str_contains($slug, 'repair') || $vertical === 'repair_technician') {
            return [
                [
                    'name' => 'Alex Morgan',
                    'email' => 'alex.repairs@demo.com',
                    'role' => User::ROLE_TECHNICIAN,
                    'pin_code' => '1111',
                    'is_online' => true,
                    'messages' => [
                        ['from' => 'staff', 'text' => 'Morning! Diagnostic complete on Ticket #TK-108 (iPhone 14 screen replacement).'],
                        ['from' => 'admin', 'text' => 'Customer approved the cost estimate. You can proceed with assembly.'],
                        ['from' => 'staff', 'text' => 'Screen replacement on Job #TK-402 is done, running final diagnostics.'],
                        ['from' => 'admin', 'text' => 'Awesome. Please notify customer once QC test passes.'],
                        ['from' => 'staff', 'text' => 'Do we have extra thermal paste in stock at workbench 2?'],
                    ],
                ],
                [
                    'name' => 'Samira Khan',
                    'email' => 'samira.repairs@demo.com',
                    'role' => User::ROLE_SALESPERSON,
                    'pin_code' => '2222',
                    'is_online' => false,
                    'messages' => [
                        ['from' => 'staff', 'text' => 'Intake checklist completed for 3 new devices today.'],
                        ['from' => 'admin', 'text' => 'Great work Samira.'],
                    ],
                ],
            ];
        }

        if (str_contains($slug, 'salon') || $vertical === 'service_booking') {
            return [
                [
                    'name' => 'Elena Rostova',
                    'email' => 'elena@example.test',
                    'role' => User::ROLE_SALESPERSON,
                    'pin_code' => '1111',
                    'is_online' => true,
                    'messages' => [
                        ['from' => 'admin', 'text' => 'Hi Elena, station 1 is sanitized for your 2 PM VIP appointment.'],
                        ['from' => 'staff', 'text' => 'VIP client confirmed for bridal hairstyle package at 2 PM.'],
                        ['from' => 'admin', 'text' => 'All set. Pre-booked the premium organic conditioning kit.'],
                        ['from' => 'staff', 'text' => 'Client wants to add a scalp massage treatment. Can we extend the chair time by 20 mins?'],
                    ],
                ],
                [
                    'name' => 'Marcus Chen',
                    'email' => 'marcus@example.test',
                    'role' => User::ROLE_SALESPERSON,
                    'pin_code' => '2222',
                    'is_online' => false,
                    'messages' => [
                        ['from' => 'admin', 'text' => 'Marcus, how are the appointments looking for tomorrow?'],
                        ['from' => 'staff', 'text' => 'Fully booked after 11 AM.'],
                    ],
                ],
                [
                    'name' => 'Sophie Martin',
                    'email' => 'sophie.salon@demo.com',
                    'role' => User::ROLE_CASHIER,
                    'pin_code' => '3333',
                    'is_online' => true,
                    'messages' => [
                        ['from' => 'staff', 'text' => 'Front desk reception opened and appointment calendar synced.'],
                        ['from' => 'admin', 'text' => 'Thank you Sophie.'],
                    ],
                ],
            ];
        }

        if (str_contains($slug, 'enterprise') || str_contains($slug, 'all')) {
            return [
                [
                    'name' => 'Zara Ahmed',
                    'email' => 'zara.enterprise@demo.com',
                    'role' => User::ROLE_CASHIER,
                    'pin_code' => '1001',
                    'is_online' => true,
                    'messages' => [
                        ['from' => 'admin', 'text' => 'Hi Zara, could you verify register 1 float?'],
                        ['from' => 'staff', 'text' => 'Float verified! Register 1 is ready.'],
                        ['from' => 'staff', 'text' => 'Shipment arrives at 5 PM today.'],
                    ],
                ],
                [
                    'name' => 'Arjun Kapoor',
                    'email' => 'arjun.enterprise@demo.com',
                    'role' => User::ROLE_MANAGER,
                    'pin_code' => '1111',
                    'is_online' => true,
                    'messages' => [
                        ['from' => 'staff', 'text' => 'All POS registers across retail and dining areas synced.'],
                        ['from' => 'admin', 'text' => 'Great. Let\'s monitor cash drawer handovers today.'],
                        ['from' => 'staff', 'text' => 'Photo · Stock sheet.jpg', 'attachment_type' => 'image', 'attachment_name' => 'Stock sheet.jpg'],
                    ],
                ],
                [
                    'name' => 'Elena Silva',
                    'email' => 'elena.enterprise@demo.com',
                    'role' => User::ROLE_SALESPERSON,
                    'pin_code' => '1212',
                    'is_online' => true,
                    'messages' => [
                        ['from' => 'admin', 'text' => 'Elena, do we have the supplier batch invoices ready?'],
                        ['from' => 'staff', 'text' => 'Yes, attached below for signoff.', 'attachment_type' => 'document', 'attachment_name' => 'Invoice_Batch_329.pdf'],
                    ],
                ],
                [
                    'name' => 'Marcus Tan',
                    'email' => 'marcus.enterprise@demo.com',
                    'role' => User::ROLE_SALESPERSON,
                    'pin_code' => '1313',
                    'is_online' => false,
                    'messages' => [
                        ['from' => 'admin', 'text' => 'Marcus, please send an audio memo on customer feedback.'],
                        ['from' => 'staff', 'text' => 'Voice message 0:14', 'attachment_type' => 'audio', 'attachment_name' => 'voice_014.m4a'],
                    ],
                ],
                [
                    'name' => 'Rohan Sharma',
                    'email' => 'rohan.enterprise@demo.com',
                    'role' => User::ROLE_CASHIER,
                    'pin_code' => '1414',
                    'is_online' => false,
                    'messages' => [
                        ['from' => 'admin', 'text' => 'Refund request for invoice #1092 has been authorized.'],
                        ['from' => 'staff', 'text' => 'Thanks for approving!'],
                    ],
                ],
            ];
        }

        // Default Retail Mart
        return [
            [
                'name' => 'Zara Ahmed',
                'email' => 'zara.retail@demo.com',
                'role' => User::ROLE_CASHIER,
                'pin_code' => '1001',
                'is_online' => true,
                'messages' => [
                    ['from' => 'admin', 'text' => 'Hi Zara, could you verify register 1 float?'],
                    ['from' => 'staff', 'text' => 'Float verified! Register 1 is ready.'],
                    ['from' => 'staff', 'text' => 'Shipment arrives at 5 PM today.'],
                ],
            ],
            [
                'name' => 'Arjun Kapoor',
                'email' => 'arjun.retail@demo.com',
                'role' => User::ROLE_MANAGER,
                'pin_code' => '1111',
                'is_online' => true,
                'messages' => [
                    ['from' => 'admin', 'text' => 'Good morning Arjun! Weekend footfall is expected to be high.'],
                    ['from' => 'staff', 'text' => 'Photo · Stock sheet.jpg', 'attachment_type' => 'image', 'attachment_name' => 'Stock sheet.jpg'],
                ],
            ],
            [
                'name' => 'Elena Silva',
                'email' => 'elena.retail@demo.com',
                'role' => User::ROLE_SALESPERSON,
                'pin_code' => '1212',
                'is_online' => true,
                'messages' => [
                    ['from' => 'admin', 'text' => 'Elena, do we have the supplier batch invoices ready?'],
                    ['from' => 'staff', 'text' => 'Yes, attached below for signoff.', 'attachment_type' => 'document', 'attachment_name' => 'Invoice_Batch_329.pdf'],
                ],
            ],
            [
                'name' => 'Marcus Tan',
                'email' => 'marcus.retail@demo.com',
                'role' => User::ROLE_SALESPERSON,
                'pin_code' => '1313',
                'is_online' => false,
                'messages' => [
                    ['from' => 'admin', 'text' => 'Marcus, please send an audio memo on customer feedback.'],
                    ['from' => 'staff', 'text' => 'Voice message 0:14', 'attachment_type' => 'audio', 'attachment_name' => 'voice_014.m4a'],
                ],
            ],
            [
                'name' => 'Rohan Sharma',
                'email' => 'rohan.retail@demo.com',
                'role' => User::ROLE_CASHIER,
                'pin_code' => '1414',
                'is_online' => false,
                'messages' => [
                    ['from' => 'admin', 'text' => 'Refund request for invoice #1092 has been authorized.'],
                    ['from' => 'staff', 'text' => 'Thanks for approving!'],
                ],
            ],
        ];
    }

    private function seedPromotionalBroadcasts(): void
    {
        if (! Schema::hasTable('promotional_broadcasts')) {
            return;
        }

        PromotionalBroadcast::firstOrCreate(
            ['title' => '🚀 ZooM POS v3.4 Feature Release & Offline Engine Guide'],
            [
                'message' => 'Explore our all-new offline sales synchronization, multi-store stock transfers, and automated GST invoice templates. Download the operational manual below.',
                'banner_image_url' => 'https://saas.zoomnearby.com/marketing/assets/images/dashboard-preview.png',
                'pdf_url' => 'https://saas.zoomnearby.com/docs/manual.pdf',
                'cta_label' => 'Explore Features',
                'cta_url' => 'https://saas.zoomnearby.com',
                'is_active' => true,
                'expires_at' => now()->addDays(30),
            ]
        );

        PromotionalBroadcast::firstOrCreate(
            ['title' => '⚡ Operational Best Practices: Shift Reconciliation'],
            [
                'message' => 'Remember to reconcile cash registers before shift handovers and perform end-of-day Z-Reports directly from the POS Drawer menu.',
                'banner_image_url' => 'https://saas.zoomnearby.com/marketing/assets/images/chat-pos-mockup.png',
                'pdf_url' => null,
                'cta_label' => 'Read Checklist',
                'cta_url' => 'https://saas.zoomnearby.com',
                'is_active' => true,
                'expires_at' => now()->addDays(45),
            ]
        );
    }
}
