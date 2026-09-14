<?php

namespace Modules\leadmanagement\Services;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Reminder;
use App\Models\Sale;
use App\Models\User;
use App\Services\Auth\PermissionChecker;
use App\Services\Notifications\TenantNotificationDispatcherService;
use App\Services\Push\FirebasePushService;
use App\Services\Sdui\SchemaResponse as S;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\leadmanagement\Models\Lead;
use Modules\leadmanagement\Models\LeadActivity;
use Modules\leadmanagement\Models\LeadSource;

class LeadService
{
    public function __construct(
        protected TenantNotificationDispatcherService $notificationDispatcher,
        protected FirebasePushService $pushService,
    ) {
    }

    /**
     * Search existing customers for linking to a lead.
     */
    public function searchCustomers(string $query, Company $company, int $limit = 30)
    {
        $q = trim($query);

        return Customer::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->when($q !== '', function ($builder) use ($q) {
                $hasCompanyCol = Schema::hasColumn('customers', 'company_name');
                $builder->where(function ($sub) use ($q, $hasCompanyCol) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('document', 'like', "%{$q}%")
                        ->orWhere('tax_id', 'like', "%{$q}%")
                        ->orWhere('gstin', 'like', "%{$q}%")
                        ->orWhere('custom_fields->company_name', 'like', "%{$q}%");

                    if ($hasCompanyCol) {
                        $sub->orWhere('company_name', 'like', "%{$q}%");
                    }
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * Link an existing customer or auto-provision a new one in the CRM.
     */
    public function autoProvisionCustomer(array $data, Company $company): ?Customer
    {
        // 1. Explicit customer_id provided
        if (! empty($data['customer_id'])) {
            $customer = Customer::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->find($data['customer_id']);

            if ($customer) {
                return $customer;
            }
        }

        $phone = ! empty($data['phone']) ? trim((string) $data['phone']) : null;
        $email = ! empty($data['email']) ? trim((string) $data['email']) : null;
        $name = ! empty($data['name']) ? trim((string) $data['name']) : null;

        // 2. Look up by phone if phone exists
        if ($phone !== null && $phone !== '') {
            $existing = Customer::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where('phone', $phone)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        // 3. Look up by email if email exists
        if ($email !== null && $email !== '') {
            $existing = Customer::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where('email', $email)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        // 4. Auto-provision new Customer record if we have a name or contact
        if ($name || $phone || $email) {
            $customerName = $name ?: ($data['company_name'] ?? 'Lead Contact '.Str::random(4));
            $sourceName = $data['source'] ?? $data['source_name'] ?? 'Lead Management';

            $customer = Customer::create([
                'company_id' => $company->id,
                'name' => $customerName,
                'phone' => $phone,
                'email' => $email,
                'source' => $sourceName,
                'custom_fields' => [
                    'company_name' => $data['company_name'] ?? null,
                    'auto_provisioned_from_lead' => true,
                    'provisioned_at' => now()->toIso8601String(),
                ],
            ]);

            AuditLog::record('customer.auto_provisioned_from_lead', $company->id, auth('tenant_api')->id() ?? auth('web')->id(), [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
            ]);

            return $customer;
        }

        return null;
    }

    /**
     * Create a new Lead with auto-linking, activity logging, and notification dispatch.
     */
    public function createLead(array $data, Company $company, ?User $user): Lead
    {
        // Auto-provision or match existing customer
        $customer = $this->autoProvisionCustomer($data, $company);
        $customerId = $customer?->id;

        // Fill empty contact info from customer if customer matched
        $name = ! empty($data['name']) ? $data['name'] : ($customer?->name ?? 'New Lead');
        $phone = ! empty($data['phone']) ? $data['phone'] : $customer?->phone;
        $email = ! empty($data['email']) ? $data['email'] : $customer?->email;
        $companyName = ! empty($data['company_name']) ? $data['company_name'] : ($customer?->company_name ?? ($customer?->custom_fields['company_name'] ?? null));

        $sourceId = ! empty($data['source_id']) ? (int) $data['source_id'] : null;
        $sourceName = $data['source_name'] ?? $data['source'] ?? null;
        if ($sourceId !== null && ! $sourceName) {
            $source = LeadSource::where('company_id', $company->id)->find($sourceId);
            $sourceName = $source?->name;
        }

        $leadCode = 'LD-'.strtoupper(Str::random(8));
        $expectedValue = (float) ($data['expected_value'] ?? $data['estimated_value'] ?? 0);
        $assignedTo = $data['assigned_to'] ?? $user?->id;
        $stage = $data['stage'] ?? 'new';
        $status = $data['status'] ?? 'active';

        $lead = Lead::create([
            'company_id' => $company->id,
            'lead_code' => $leadCode,
            'name' => $name,
            'title' => $data['title'] ?? null,
            'company_name' => $companyName,
            'phone' => $phone,
            'email' => $email,
            'source_id' => $sourceId,
            'source_name' => $sourceName,
            'source' => $sourceName,
            'stage' => $stage,
            'status' => $status,
            'priority' => $data['priority'] ?? 'medium',
            'estimated_value' => $expectedValue,
            'expected_value' => $expectedValue,
            'assigned_to' => $assignedTo,
            'customer_id' => $customerId,
            'notes' => $data['notes'] ?? null,
            'requirement_summary' => $data['requirement_summary'] ?? ($data['notes'] ?? null),
        ]);

        LeadActivity::create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'type' => 'note',
            'title' => 'Lead Created',
            'description' => "Lead {$leadCode} captured with stage ".ucfirst(str_replace('_', ' ', $stage)).'.',
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // If a reminder date/time was included, create follow-up reminder
        if (! empty($data['reminder_due_date'])) {
            $this->scheduleReminder($lead, [
                'title' => $data['reminder_title'] ?? "Follow up with {$lead->name}",
                'due_date' => $data['reminder_due_date'],
                'notes' => $data['reminder_notes'] ?? "Follow up for Lead {$lead->lead_code}",
            ], $company, $user);
        }

        // Dispatch notifications (Webhook, SMS, WhatsApp, Push)
        $this->dispatchNotifications($lead, 'lead_created', $company);

        AuditLog::record('lead.created', $company->id, $user?->id, [
            'lead_id' => $lead->id,
            'lead_code' => $lead->lead_code,
            'customer_id' => $customerId,
        ]);

        return $lead;
    }

    /**
     * Update an existing Lead.
     */
    public function updateLead(Lead $lead, array $data, Company $company, ?User $user): Lead
    {
        $oldStage = $lead->stage;
        $oldAssignedTo = $lead->assigned_to;

        // If customer_id provided or changed, link customer
        if (array_key_exists('customer_id', $data) && $data['customer_id'] != $lead->customer_id) {
            $customer = $this->autoProvisionCustomer($data, $company);
            $data['customer_id'] = $customer?->id;
        }

        if (isset($data['expected_value'])) {
            $data['estimated_value'] = $data['expected_value'];
        }

        $lead->update($data);

        // Track stage change
        if (! empty($data['stage']) && $data['stage'] !== $oldStage) {
            $newStage = $data['stage'];
            if ($newStage === 'won') {
                $lead->update([
                    'status' => 'won',
                    'converted_at' => now(),
                ]);
            } elseif ($newStage === 'lost') {
                $lead->update([
                    'status' => 'lost',
                    'lost_reason' => $data['lost_reason'] ?? 'Marked as lost',
                ]);
            }

            LeadActivity::create([
                'company_id' => $company->id,
                'lead_id' => $lead->id,
                'type' => 'note',
                'title' => 'Stage Changed',
                'description' => 'Stage updated from '.ucfirst(str_replace('_', ' ', $oldStage)).' to '.ucfirst(str_replace('_', ' ', $newStage)).'.',
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $this->dispatchNotifications($lead, 'lead_stage_updated', $company, ['old_stage' => $oldStage, 'new_stage' => $newStage]);
        }

        // Track assignment change
        if (! empty($data['assigned_to']) && $data['assigned_to'] !== $oldAssignedTo) {
            $newUser = User::find($data['assigned_to']);
            LeadActivity::create([
                'company_id' => $company->id,
                'lead_id' => $lead->id,
                'type' => 'note',
                'title' => 'Reassigned',
                'description' => 'Lead reassigned to '.($newUser?->name ?? $data['assigned_to']).'.',
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Notify assigned user via push notification
            if ($lead->assigned_to) {
                try {
                    $this->pushService->sendToUser($company->id, $lead->assigned_to, [
                        'type' => 'lead_assigned',
                        'title' => 'New Lead Assigned',
                        'body' => "Lead {$lead->lead_code} ({$lead->name}) has been assigned to you.",
                        'lead_id' => $lead->id,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Push notification to assigned user failed: '.$e->getMessage());
                }
            }
        }

        return $lead->fresh(['customer', 'source', 'activities', 'quotations', 'invoices', 'reminders']);
    }

    /**
     * Convert a Lead to a Customer in the CRM.
     */
    public function convertToCustomer(Lead $lead, Company $company, ?User $user): Customer
    {
        $customer = null;
        if ($lead->customer_id) {
            $customer = Customer::withoutGlobalScope('company')->where('company_id', $company->id)->find($lead->customer_id);
        }

        if ($customer) {
            $customFields = $customer->custom_fields ?? [];
            $customFields['converted_from_lead'] = $lead->lead_code;
            if (! empty($lead->company_name) && empty($customFields['company_name'])) {
                $customFields['company_name'] = $lead->company_name;
            }
            $customer->update(['custom_fields' => $customFields]);
        } else {
            $customer = Customer::create([
                'company_id' => $company->id,
                'name' => $lead->name,
                'phone' => $lead->phone,
                'email' => $lead->email,
                'source' => $lead->source_name ?: ($lead->source ?: 'Lead Conversion'),
                'custom_fields' => [
                    'converted_from_lead' => $lead->lead_code,
                    'company_name' => $lead->company_name,
                ],
            ]);
        }

        $lead->update([
            'customer_id' => $customer->id,
            'stage' => 'won',
            'status' => 'won',
            'converted_at' => now(),
        ]);

        LeadActivity::create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'type' => 'note',
            'title' => 'Converted to Customer',
            'description' => "Lead converted to Customer #{$customer->id} ({$customer->name}).",
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->dispatchNotifications($lead, 'lead_converted', $company, ['customer_id' => $customer->id]);

        AuditLog::record('lead.converted_to_customer', $company->id, $user?->id, [
            'lead_id' => $lead->id,
            'customer_id' => $customer->id,
        ]);

        return $customer;
    }

    /**
     * 1-Tap Convert Lead to a draft/finalized Invoice in `sales` table.
     */
    public function convertToInvoice(Lead $lead, Company $company, ?User $user, array $options = []): Sale
    {
        // Ensure customer is provisioned
        $customer = $this->convertToCustomer($lead, $company, $user);

        $prefix = $company->invoice_prefix ?: 'INV-';
        $count = Sale::where('operation_type', 'sale')->count() + 1;
        $saleNumber = $prefix.sprintf('%04d', $count);

        $amount = (float) ($lead->expected_value ?: $lead->estimated_value ?: 0);
        $itemName = $lead->title ?: ($lead->requirement_summary ?: "Order from Lead {$lead->lead_code}");

        $items = [
            [
                'id' => null,
                'name' => $itemName,
                'description' => $lead->notes ?: '',
                'price' => $amount,
                'quantity' => 1,
                'total' => $amount,
            ],
        ];

        $invoice = Sale::create([
            'company_id' => $company->id,
            'external_id' => Str::uuid()->toString(),
            'sale_number' => $saleNumber,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'lead_id' => $lead->id,
            'user_id' => $lead->assigned_to ?: $user?->id,
            'total' => $amount,
            'net_amount' => $amount,
            'paid_amount' => 0.00,
            'due_amount' => $amount,
            'discount' => 0.00,
            'tax_amount' => 0.00,
            'status' => 'draft',
            'payment_status' => 'due',
            'operation_type' => 'sale',
            'notes' => $lead->notes,
            'items' => $items,
        ]);

        $lead->update([
            'stage' => 'won',
            'status' => 'won',
            'converted_at' => now(),
        ]);

        LeadActivity::create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'type' => 'task',
            'title' => 'Invoice Generated',
            'description' => "Invoice #{$saleNumber} generated from this lead.",
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->dispatchNotifications($lead, 'lead_converted', $company, ['invoice_id' => $invoice->id, 'invoice_number' => $saleNumber]);

        AuditLog::record('lead.converted_to_invoice', $company->id, $user?->id, [
            'lead_id' => $lead->id,
            'invoice_id' => $invoice->id,
            'sale_number' => $saleNumber,
        ]);

        return $invoice;
    }

    /**
     * Schedule a Reminder for this Lead.
     */
    public function scheduleReminder(Lead $lead, array $data, Company $company, ?User $user): Reminder
    {
        $dueDate = ! empty($data['due_date']) ? $data['due_date'] : (! empty($data['due_at']) ? $data['due_at'] : (! empty($data['reminder_due']) ? $data['reminder_due'] : now()->addDay()));

        $noteText = isset($data['notes']) && $data['notes'] !== '' ? $data['notes'] :
            (isset($data['reminder_notes']) && $data['reminder_notes'] !== '' ? $data['reminder_notes'] :
            (isset($data['description']) && $data['description'] !== '' ? $data['description'] :
            (isset($data['call_script']) && $data['call_script'] !== '' ? $data['call_script'] :
            ($lead->requirement_summary ?: $lead->notes))));

        $title = $data['title'] ?? ($data['subject'] ?? ($data['follow_up_subject'] ?? "Follow up with {$lead->name}"));

        $reminder = Reminder::create([
            'company_id' => $company->id,
            'tenant_id' => $company->id,
            'user_id' => $user?->id ?? auth()->id(),
            'customer_id' => $lead->customer_id,
            'remindable_type' => Lead::class,
            'remindable_id' => $lead->id,
            'type' => $data['type'] ?? 'lead_followup',
            'title' => $title,
            'subject' => $title,
            'notes' => $noteText,
            'description' => $noteText,
            'call_script' => $noteText,
            'due_date' => $dueDate,
            'due_at' => $dueDate,
            'status' => $data['status'] ?? Reminder::STATUS_PENDING,
        ]);

        LeadActivity::create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'type' => 'task',
            'title' => 'Follow-up Reminder Scheduled',
            'description' => "Reminder set for {$reminder->due_date?->format('Y-m-d H:i')}: {$reminder->title}".($noteText ? " — {$noteText}" : ""),
            'due_date' => $reminder->due_date,
            'status' => 'pending',
        ]);

        // If assigned to a user, send push notification reminder
        if ($lead->assigned_to) {
            try {
                $this->pushService->sendToUser($company->id, $lead->assigned_to, [
                    'type' => 'lead_reminder',
                    'title' => 'Follow-up Reminder Scheduled',
                    'body' => "Reminder for {$lead->name}: {$reminder->title}",
                    'lead_id' => $lead->id,
                ]);
            } catch (\Throwable $e) {
                // Ignore push failure
            }
        }

        return $reminder;
    }

    /**
     * Multi-channel notification dispatch (WhatsApp, SMS, Webhooks).
     */
    public function dispatchNotifications(Lead $lead, string $event, Company $company, array $extra = []): void
    {
        try {
            $currency = $company->currency_symbol ?: ($company->currency ?: '$');
            $formattedVal = $currency.number_format((float) ($lead->expected_value ?: $lead->estimated_value ?: 0), 2);

            $payload = array_merge([
                'event' => $event,
                'lead_id' => $lead->id,
                'lead_code' => $lead->lead_code,
                'name' => $lead->name,
                'company_name' => $lead->company_name,
                'phone' => $lead->phone,
                'email' => $lead->email,
                'stage' => $lead->stage,
                'status' => $lead->status,
                'priority' => $lead->priority,
                'expected_value' => (float) ($lead->expected_value ?: $lead->estimated_value ?: 0),
                'assigned_to' => $lead->assigned_to,
                'customer_id' => $lead->customer_id,
                'source' => $lead->source_name ?: $lead->source,
            ], $extra);

            // 1. Webhook dispatch
            $this->notificationDispatcher->dispatchWebhook($company, $event, $payload);

            // 2. WhatsApp / SMS greeting if phone is provided and channels active
            if ($lead->phone) {
                if ($event === 'lead_created' && $this->notificationDispatcher->isChannelActive($company, 'whatsapp')) {
                    $greetingMsg = "Hello {$lead->name}, thank you for contacting {$company->name}. We have registered your inquiry ({$lead->lead_code}) and a sales representative will connect with you shortly.";
                    $this->notificationDispatcher->dispatchWhatsApp($company, $lead->phone, $greetingMsg);
                } elseif ($event === 'lead_created' && $this->notificationDispatcher->isChannelActive($company, 'sms')) {
                    $smsMsg = "Hi {$lead->name}, thank you for reaching out to {$company->name}. Your lead ref is {$lead->lead_code}. We will contact you soon.";
                    $this->notificationDispatcher->dispatchSms($company, $lead->phone, $smsMsg);
                }
            }

            // 3. Push notification to assigned staff
            if ($lead->assigned_to) {
                $pushTitle = match ($event) {
                    'lead_created' => "New Lead: {$lead->name}",
                    'lead_stage_updated' => "Lead Updated: {$lead->lead_code}",
                    'lead_converted' => "Lead Converted: {$lead->lead_code}",
                    default => "Lead Notification: {$lead->lead_code}",
                };
                $pushBody = match ($event) {
                    'lead_created' => "New lead captured with estimated value {$formattedVal}.",
                    'lead_stage_updated' => "Lead stage changed to ".ucfirst(str_replace('_', ' ', $lead->stage)).".",
                    'lead_converted' => "Lead won and converted successfully!",
                    default => "Update on lead {$lead->lead_code}.",
                };

                $this->pushService->sendToUser($company->id, $lead->assigned_to, [
                    'type' => $event,
                    'title' => $pushTitle,
                    'body' => $pushBody,
                    'lead_id' => $lead->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("Notification dispatch failed for lead {$lead->id}: ".$e->getMessage());
        }
    }

    /**
     * Get active sales representatives / staff for assigned_to dropdown.
     */
    public function getSalesReps(Company $company): array
    {
        return User::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role'])
            ->map(fn (User $u) => [
                'id' => (string) $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'label' => "{$u->name} (".ucfirst($u->role).")",
                'value' => (string) $u->id,
            ])
            ->values()
            ->all();
    }

    /**
     * Generate Declarative Headless SDUI Schema for Creating / Editing Leads.
     */
    public function getCreateLeadSchema(Company $company, ?Lead $existingLead = null): array
    {
        $reps = $this->getSalesReps($company);
        $repOptions = array_merge([['label' => '— Unassigned —', 'value' => '']], array_map(fn ($r) => [
            'label' => $r['label'],
            'value' => $r['value'],
        ], $reps));

        $sources = LeadSource::where('company_id', $company->id)->where('is_active', true)->orderBy('name')->get();
        $sourceOptions = [['label' => '— Direct / None —', 'value' => '']];
        foreach ($sources as $s) {
            $sourceOptions[] = ['label' => $s->name, 'value' => (string) $s->id];
        }

        $stages = [
            ['label' => 'New Lead', 'value' => 'new'],
            ['label' => 'Contacted', 'value' => 'contacted'],
            ['label' => 'Qualified Opportunity', 'value' => 'qualified'],
            ['label' => 'Proposal Sent', 'value' => 'proposal_sent'],
            ['label' => 'Won / Closed', 'value' => 'won'],
            ['label' => 'Lost / Disqualified', 'value' => 'lost'],
        ];

        $priorities = [
            ['label' => 'Medium Priority', 'value' => 'medium'],
            ['label' => 'Low Priority', 'value' => 'low'],
            ['label' => 'High Priority', 'value' => 'high'],
            ['label' => 'Urgent Priority', 'value' => 'urgent'],
        ];

        $formEndpoint = $existingLead
            ? "/api/tenant/lead-module/leads/{$existingLead->id}"
            : '/api/tenant/lead-module/leads';

        $method = $existingLead ? 'PUT' : 'POST';
        $title = $existingLead ? "Edit Lead #{$existingLead->lead_code}" : 'Capture New Lead';

        $formComponents = [
            S::text($title, 'title_large', ['bold' => true]),
            S::text('Search existing CRM customer or enter details to automatically provision customer profile.', 'body_small'),

            // 1. Customer Typeahead / Selector
            [
                'type' => 'customer_selector',
                'component_type' => 'customer_search_picker',
                'name' => 'customer_id',
                'label' => 'Link Existing Customer (Auto-fill)',
                'placeholder' => 'Search customers or phone...',
                'search_endpoint' => '/api/tenant/customers/search',
                'endpoint' => '/api/v1/tenant/customers/search',
                'query_param' => 'q',
                'currency_symbol' => $company->currency_symbol ?: ($company->currency ?: '₹'),
                'border_color' => '#06B6D4',
                'min_chars' => 1,
                'fields' => [
                    'name_field' => 'name',
                    'phone_field' => 'phone',
                    'contact_name' => 'name',
                    'client_name' => 'name',
                ],
                'name_label' => 'Contact / Client Name *',
                'phone_label' => 'Contact Phone Number',
                'initial_name' => (string) ($existingLead?->name ?? ''),
                'initial_phone' => (string) ($existingLead?->phone ?? ''),
                'initial_value' => $existingLead?->customer_id ? (string) $existingLead->customer_id : '',
                'value' => $existingLead?->customer_id ? (string) $existingLead->customer_id : '',
                'selectedText' => $existingLead && $existingLead->customer ? "{$existingLead->customer->name} ({$existingLead->customer->phone})" : null,
                'required' => true,
                'autofill_targets' => [
                    'customer_id'   => 'id',
                    'contact_name'  => 'name',
                    'client_name'   => 'name',
                    'name'          => 'name',
                    'phone_number'  => 'phone',
                    'phone'         => 'phone',
                    'email_address' => 'email',
                    'email'         => 'email',
                    'company_name'  => 'company_name',
                ],
                'style' => [
                    'dropdownBackgroundColor' => 'theme.surface',
                    'dropdownItemHover'       => 'theme.surfaceVariant',
                    'borderColor'             => 'theme.divider',
                    'backgroundColor'         => 'theme.surface',
                    'titleColor'              => 'theme.textPrimary',
                    'subtitleColor'           => 'theme.textSecondary',
                    'dueColor'                => '#EF4444',
                ],
            ],

            // 2. Lead Organization & Email
            S::textInput('company_name', 'Company / Organization Name', (string) ($existingLead?->company_name ?? ''), [
                'icon' => 'business',
                'placeholder' => 'Enter company or business name',
            ]),
            S::textInput('email', 'Email Address', (string) ($existingLead?->email ?? ''), [
                'icon' => 'email',
                'keyboard_type' => 'email',
                'placeholder' => 'client@company.com',
            ]),

            // 3. Pipeline & Valuation
            S::textInput('title', 'Requirement Scope / Opportunity Title', (string) ($existingLead?->title ?? ''), [
                'icon' => 'description',
                'placeholder' => 'e.g. Annual Software Maintenance Contract',
            ]),
            S::dropdownSelect('stage', 'Pipeline Stage', $stages, $existingLead?->stage ?? 'new'),
            S::dropdownSelect('priority', 'Priority', $priorities, $existingLead?->priority ?? 'medium'),
            S::textInput('expected_value', 'Expected Value ('.$company->currency_symbol.')', (string) ($existingLead?->expected_value ?? $existingLead?->estimated_value ?? '0'), [
                'icon' => 'monetization_on',
                'keyboard_type' => 'number',
            ]),
            S::dropdownSelect('source_id', 'Lead Source', $sourceOptions, (string) ($existingLead?->source_id ?? '')),
            S::dropdownSelect('assigned_to', 'Assigned Sales Representative', $repOptions, (string) ($existingLead?->assigned_to ?? '')),

            // 4. Notes / Requirements
            S::textInput('requirement_summary', 'Requirements / Scope of Work', (string) ($existingLead?->requirement_summary ?? ($existingLead?->notes ?? '')), [
                'max_lines' => 3,
                'keyboard_type' => 'multiline',
                'placeholder' => 'Add key requirement notes, budget, timeline...',
            ]),

            // 5. Follow-up Reminder
            S::text('Follow-up Schedule', 'title_small', ['bold' => true]),
            S::textInput('reminder_due_date', 'Reminder Date & Time (YYYY-MM-DD HH:MM)', now()->addDay()->format('Y-m-d 10:00'), [
                'icon' => 'event',
            ]),
            S::textInput('reminder_notes', 'Follow-up Notes / Call Agenda', '', [
                'icon' => 'notes',
                'placeholder' => 'Discussion points, callback time, or agenda',
            ]),

            // Submit Button
            S::buttonPrimary($existingLead ? 'Update Lead' : 'Create & Link Lead',
                S::formSubmitAction($formEndpoint, $method, $existingLead ? 'Lead updated successfully.' : 'Lead created and linked successfully.', reload: true),
                'person_add',
                ['background_color' => '#2DD4BF', 'foreground_color' => '#0F172A']),
        ];

        return S::screen($title, [
            S::card($formComponents, [
                'title' => $title,
                'subtitle' => 'Search existing CRM customer or enter details to automatically provision customer profile.',
            ]),
        ]);
    }

    /**
     * Native 4-Tab SDUI Screen (Overview & Pipeline, All Leads, Capture Lead, Follow-ups & Reminders).
     */
    public function getTabbedLeadManagementSchema(Company $company, ?User $user = null, ?string $activeTab = null): array
    {
        $currency = $company->currency_symbol ?: ($company->currency ?: '₹');

        // Base query with tenant fallback, deleted_at check, and RBAC
        $tenantId = $company->id ?? auth()->user()?->tenant_id ?? auth()->user()?->company_id;
        $query = Lead::withoutGlobalScope('company')
            ->where(function ($q) use ($tenantId) {
                if ($tenantId) {
                    $q->where('company_id', $tenantId)
                      ->orWhereNull('company_id');
                    if (\Illuminate\Support\Facades\Schema::hasColumn('lead_mod_leads', 'tenant_id')) {
                        $q->orWhere('tenant_id', $tenantId);
                    }
                }
            });

        if (\Illuminate\Support\Facades\Schema::hasColumn('lead_mod_leads', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if ($user && ! PermissionChecker::can($user, 'leads', 'view_any')) {
            $query->where('assigned_to', $user->id);
        }

        // 1. Metric Aggregates
        $totalLeads = (clone $query)->count();
        $activePipeline = (clone $query)->whereNotIn('stage', ['won', 'lost'])->count();
        $wonDeals = (clone $query)->where('stage', 'won')->count();
        $pipelineValue = (float) ((clone $query)->whereNotIn('stage', ['lost'])->sum('expected_value') ?: (clone $query)->whereNotIn('stage', ['lost'])->sum('estimated_value') ?: 0);

        $pendingReminders = Reminder::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('status', 'pending')
            ->count();

        $pendingActivities = LeadActivity::where('company_id', $company->id)
            ->where('status', 'pending')
            ->count();

        $pendingFollowups = max($pendingReminders, $pendingActivities);
        $totalSources = LeadSource::where('company_id', $company->id)->count();
        $conversionRate = $totalLeads > 0 ? round(($wonDeals / $totalLeads) * 100, 1) : 0.0;

        // -----------------------------------------------------------------
        // Tab 1 Components: "Overview & Pipeline"
        // -----------------------------------------------------------------
        // 2x2 Grid Stat Cards with Trend Badges
        $overviewStats = [
            [
                'type' => 'card',
                'component_type' => 'metric_card',
                'label' => 'Open Leads',
                'value' => (string) $activePipeline,
                'components' => [
                    S::row([
                        S::icon('people', ['color' => '#2DD4BF', 'size' => 24]),
                        S::column([
                            S::text('Open Leads', 'body_small', ['variant' => 'bodySmall']),
                            S::text((string) $activePipeline, 'title_large', ['bold' => true, 'variant' => 'titleLarge']),
                            S::badge('↑ 6 this week', '#2DD4BF', 'subtle'),
                        ]),
                    ]),
                ],
            ],
            [
                'type' => 'card',
                'component_type' => 'metric_card',
                'label' => 'Pipeline Value',
                'value' => $currency . number_format($pipelineValue, 0),
                'components' => [
                    S::row([
                        S::icon('monetization_on', ['color' => '#10B981', 'size' => 24]),
                        S::column([
                            S::text('Pipeline Value', 'body_small', ['variant' => 'bodySmall']),
                            S::text($currency . number_format($pipelineValue, 0), 'title_medium', ['bold' => true, 'variant' => 'titleMedium']),
                            S::badge('↑ 12% vs last mo.', '#10B981', 'subtle'),
                        ]),
                    ]),
                ],
            ],
            [
                'type' => 'card',
                'component_type' => 'metric_card',
                'label' => 'Won This Month',
                'value' => (string) $wonDeals,
                'components' => [
                    S::row([
                        S::icon('check_circle', ['color' => '#3B82F6', 'size' => 24]),
                        S::column([
                            S::text('Won This Month', 'body_small', ['variant' => 'bodySmall']),
                            S::text((string) $wonDeals, 'title_large', ['bold' => true, 'variant' => 'titleLarge']),
                            S::badge("{$wonDeals} deals closed", '#3B82F6', 'subtle'),
                        ]),
                    ]),
                ],
            ],
            [
                'type' => 'card',
                'component_type' => 'metric_card',
                'label' => 'Conversion Rate',
                'value' => $conversionRate . '%',
                'components' => [
                    S::row([
                        S::icon('trending_up', ['color' => '#8B5CF6', 'size' => 24]),
                        S::column([
                            S::text('Conversion Rate', 'body_small', ['variant' => 'bodySmall']),
                            S::text($conversionRate . '%', 'title_large', ['bold' => true, 'variant' => 'titleLarge']),
                            S::badge('↓ 3% vs last mo.', '#8B5CF6', 'subtle'),
                        ]),
                    ]),
                ],
            ],
        ];

        // Pipeline Distribution using progress_bar_stat
        $stagesConfig = [
            ['key' => 'new', 'label' => 'New Leads', 'color' => '#06B6D4'],
            ['key' => 'contacted', 'label' => 'Contacted', 'color' => '#8B5CF6'],
            ['key' => 'qualified', 'label' => 'Qualified', 'color' => '#0284C7'],
            ['key' => 'proposal_sent', 'label' => 'Proposal Sent', 'color' => '#F59E0B'],
            ['key' => 'won', 'label' => 'Won Deals', 'color' => '#10B981'],
        ];

        $stageBars = [
            S::text('Pipeline Distribution by Stage', 'title_small', ['bold' => true]),
            S::divider(),
        ];
        foreach ($stagesConfig as $sc) {
            $stageCount = (clone $query)->where('stage', $sc['key'])->count();
            $stageSum = (float) (clone $query)->where('stage', $sc['key'])->sum('expected_value');
            $pct = $totalLeads > 0 ? round(($stageCount / $totalLeads) * 100, 1) : 0.0;
            $valStr = $stageSum > 0 ? ($currency . number_format($stageSum, 0)) : "{$pct}%";
            $stageBars[] = S::progressBarStat(
                $sc['label'],
                $pct,
                $valStr,
                (string) $stageCount,
                ['bar_color' => $sc['color']]
            );
        }

        // Leads by Rep section
        $reps = $this->getSalesReps($company);
        $repCards = [
            S::text('Leads by Sales Representative', 'title_small', ['bold' => true]),
            S::divider(),
        ];
        $hasRepsWithLeads = false;
        foreach ($reps as $r) {
            $repId = $r['value'];
            if (!$repId) continue;
            $repCount = (clone $query)->where('assigned_to', $repId)->count();
            $repSum = (float) (clone $query)->where('assigned_to', $repId)->sum('expected_value');
            $words = explode(' ', trim($r['label']));
            $initials = count($words) >= 2 ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1)) : strtoupper(substr($words[0] ?? 'R', 0, 2));

            $repCards[] = S::row([
                S::container([
                    S::text($initials, 'body_small', ['bold' => true, 'color' => '#2DD4BF']),
                ], [
                    'padding' => [6, 8],
                    'border_radius' => 14,
                    'color' => '#1E293B',
                ]),
                S::column([
                    S::text($r['label'], 'body_medium', ['bold' => true]),
                    S::text("{$repCount} leads assigned  ·  {$currency}" . number_format($repSum, 0), 'body_small'),
                ]),
            ]);
            $hasRepsWithLeads = true;
        }
        if (!$hasRepsWithLeads) {
            $unassignedCount = (clone $query)->whereNull('assigned_to')->count();
            $repCards[] = S::text("{$unassignedCount} unassigned leads in system", 'body_small');
        }

        $overviewComponents = [
            S::gridView($overviewStats, 2),
            S::card($stageBars),
            S::card($repCards),
        ];

        // -----------------------------------------------------------------
        // Tab 2 Components: "All Leads"
        // -----------------------------------------------------------------
        $searchQuery = trim((string) (request('q') ?: request('search') ?: request('search_leads') ?: ''));
        $filterStage = (string) (request('stage') ?: request('status') ?: 'all');

        if (!empty($searchQuery) && empty($activeTab)) {
            $activeTab = 'all_leads';
        }

        $leadsListQuery = (clone $query)->with(['source', 'customer', 'assignedUser']);

        if ($filterStage !== 'all') {
            $leadsListQuery->where(function ($q) use ($filterStage) {
                $q->where('stage', $filterStage)->orWhere('status', $filterStage);
            });
        }

        if (!empty($searchQuery)) {
            $leadsListQuery->where(function ($sub) use ($searchQuery) {
                $sub->where('lead_code', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('title', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('name', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('phone', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('email', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('company_name', 'LIKE', "%{$searchQuery}%")
                    ->orWhereHas('customer', function ($cq) use ($searchQuery) {
                        $cq->where('name', 'LIKE', "%{$searchQuery}%")
                            ->orWhere('phone', 'LIKE', "%{$searchQuery}%")
                            ->orWhere('company_name', 'LIKE', "%{$searchQuery}%");
                    });
            });
        }

        $leadRows = $leadsListQuery->orderByDesc('created_at')->limit(50)->get();
        $totalInView = $leadRows->count();
        $sumInView = (float) ($leadRows->sum('expected_value') ?: ($leadRows->sum('estimated_value') ?: 0));
        $summaryText = "{$totalInView} leads in view  ·  {$currency}" . number_format($sumInView, 0) . " pipeline value";

        $leadItems = [];
        foreach ($leadRows as $lead) {
            $stage = $lead->stage ?: $lead->status;
            $stageColor = match ($stage) {
                'won' => '#10B981',
                'lost' => '#EF4444',
                'proposal_sent' => '#F59E0B',
                'qualified' => '#0284C7',
                'contacted' => '#8B5CF6',
                default => '#2DD4BF',
            };
            $stageLabel = ucfirst(str_replace('_', ' ', $stage ?: 'new'));
            $val = $currency . number_format((float) ($lead->expected_value ?: $lead->estimated_value ?: 0), 0);
            $leadTitle = $lead->customer?->name ?? ($lead->name ?: ($lead->title ?? 'Unnamed Lead'));
            $companyName = $lead->customer?->company_name ?? ($lead->company_name ?: '');
            $leadPhone = $lead->customer?->phone ?? $lead->phone;
            $leadEmail = $lead->customer?->email ?? $lead->email;
            $repName = $lead->assignedUser?->name ?: ($lead->assigned_to ?: 'Unassigned');

            // Monogram Initials
            $words = explode(' ', trim($leadTitle));
            $initials = count($words) >= 2 ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1)) : strtoupper(substr($words[0] ?? 'L', 0, 2));

            // Priority stripe color (Urgent = Red #EF4444, Medium = Amber #F59E0B, Normal/Low = Blue #3B82F6)
            $priority = strtolower((string) ($lead->priority ?? 'medium'));
            $stripeColor = match ($priority) {
                'urgent', 'high' => '#EF4444',
                'medium' => '#F59E0B',
                default => '#3B82F6',
            };

            $noteSnippet = $lead->requirement_summary ? Str::limit($lead->requirement_summary, 80) : ($lead->notes ? Str::limit($lead->notes, 80) : null);
            $quotationModalEndpoint = '/api/v1/tenant/quotations/create-modal?' . http_build_query([
                'lead_id'     => $lead->id,
                'lead_code'   => $lead->lead_code ?? "LD-{$lead->id}",
                'customer_id' => $lead->customer_id ?? '',
                'subject'     => $lead->requirement_scope ?? $lead->subject ?? $lead->requirement_summary ?? '',
                'notes'       => $lead->notes ?? '',
            ]);
            $quotationAction = S::openBottomSheetAction($quotationModalEndpoint, 'New quotation', [
                'lead_id'     => $lead->id,
                'lead_code'   => $lead->lead_code ?? "LD-{$lead->id}",
                'customer_id' => $lead->customer_id,
            ]);

            $leadItems[] = S::entityRecordCard([
                'title' => $leadTitle,
                'subtitle' => $companyName ?: 'Direct Lead',
                'avatar_text' => $initials,
                'avatar_bg' => '#1E293B',
                'avatar_fg' => '#2DD4BF',
                'stripe_color' => $stripeColor,
                'priority' => $priority,
                'badge_text' => $stageLabel,
                'badge_color' => $stageColor,
                'amount_text' => $val,
                'phone' => $leadPhone,
                'email' => $leadEmail,
                'meta_items' => [
                    ['icon' => 'person', 'text' => "Rep: {$repName}"],
                    ['icon' => 'flag', 'text' => ucfirst($priority)],
                    ['icon' => 'calendar_month', 'text' => $lead->created_at ? $lead->created_at->format('M d') : 'Recent'],
                ],
                'note' => $noteSnippet,
                'actions' => [
                    [
                        'label' => 'View details',
                        'variant' => 'outlined',
                        'icon' => 'visibility',
                        'action' => S::navigateAction('/api/tenant/lead-module/views/lead-detail?id=' . $lead->id, 'dynamic_page', $lead->lead_code),
                    ],
                    [
                        'label'       => 'Create quote',
                        'variant'     => 'primary',
                        'color'       => '#166534',
                        'icon'        => 'add_circle_outline',
                        'action_type' => 'OPEN_BOTTOM_SHEET',
                        'action'      => $quotationAction,
                        'style'       => [
                            'backgroundColor' => '#166534',
                            'textColor'       => '#FFFFFF',
                            'borderRadius'    => 10,
                            'flex'            => 1,
                        ],
                    ],
                ],
                'action' => S::navigateAction('/api/tenant/lead-module/views/lead-detail?id=' . $lead->id, 'dynamic_page', $lead->lead_code),
            ]);
        }

        if (empty($leadItems)) {
            $emptyTitle = !empty($searchQuery) ? 'No leads match "' . $searchQuery . '"' : 'No leads found in this view.';
            $emptySubtitle = !empty($searchQuery) ? 'Check spelling or clear search filter.' : 'Switch to "Capture Lead" tab to create your first sales lead.';

            $leadItems[] = S::card([
                S::column([
                    S::icon('person_search', ['size' => 36]),
                    S::text($emptyTitle, 'title_medium', ['bold' => true]),
                    S::text($emptySubtitle, 'body_small'),
                ]),
            ]);
        }

        $allLeadsComponents = array_merge([
            S::row([
                S::text($summaryText, 'body_small', ['bold' => true]),
            ]),
            S::searchBar(
                'search',
                'Search leads by code, name, phone...',
                '/api/tenant/lead-module/views/dashboard?tab=all_leads',
                [
                    'initial_value' => $searchQuery,
                    'placeholder' => 'Search leads by code, name, phone...',
                    'clearable' => true,
                ]
            ),
        ], $leadItems);

        // -----------------------------------------------------------------
        // Tab 3 Components: "Capture Lead"
        // -----------------------------------------------------------------
        $reps = $this->getSalesReps($company);
        $repOptions = array_merge([['label' => '— Unassigned —', 'value' => '']], array_map(fn ($r) => [
            'label' => $r['label'],
            'value' => $r['value'],
        ], $reps));

        $sources = LeadSource::where('company_id', $company->id)->where('is_active', true)->orderBy('name')->get();
        $sourceOptions = [['label' => '— Direct / None —', 'value' => '']];
        foreach ($sources as $s) {
            $sourceOptions[] = ['label' => $s->name, 'value' => (string) $s->id];
        }

        $captureComponents = [
            S::card([
                S::text('Capture a new lead', 'title_large', ['bold' => true]),
                S::text('Search existing CRM customer or enter details to automatically provision customer profile.', 'body_small'),
                [
                    'type' => 'customer_selector',
                    'component_type' => 'customer_search_picker',
                    'name' => 'customer_id',
                    'label' => 'Link Existing Customer (Auto-fill)',
                    'placeholder' => 'Search customers or phone...',
                    'search_endpoint' => '/api/tenant/customers/search',
                    'endpoint' => '/api/v1/tenant/customers/search',
                    'query_param' => 'q',
                    'currency_symbol' => $currency,
                    'border_color' => '#06B6D4',
                    'min_chars' => 1,
                    'fields' => [
                        'name_field' => 'client_name',
                        'phone_field' => 'phone_number',
                        'contact_name' => 'client_name',
                        'client_name' => 'client_name',
                    ],
                    'name_label' => 'Client Full Name *',
                    'phone_label' => 'Client Phone Number *',
                    'required' => true,
                    'autofill_targets' => [
                        'customer_id'   => 'id',
                        'client_name'   => 'name',
                        'contact_name'  => 'name',
                        'phone_number'  => 'phone',
                        'email_address' => 'email',
                        'company_name'  => 'company_name',
                    ],
                    'style' => [
                        'dropdownBackgroundColor' => 'theme.surface',
                        'dropdownItemHover'       => 'theme.surfaceVariant',
                        'borderColor'             => 'theme.divider',
                        'backgroundColor'         => 'theme.surface',
                        'titleColor'              => 'theme.textPrimary',
                        'subtitleColor'           => 'theme.textSecondary',
                        'dueColor'                => '#EF4444',
                    ],
                ],
                S::textInput('company_name', 'Company / Organization Name', '', [
                    'icon' => 'business',
                    'placeholder' => 'Enter company or business name',
                ]),
                S::textInput('email_address', 'Email Address', '', [
                    'icon' => 'email',
                    'keyboard_type' => 'email',
                    'placeholder' => 'client@company.com',
                ]),
                S::textInput('title', 'Requirement Scope / Opportunity Title *', '', [
                    'icon' => 'description',
                    'required' => true,
                    'placeholder' => 'e.g. Annual Software Maintenance Contract',
                ]),
                S::dropdownSelect('stage', 'Pipeline Stage', [
                    ['label' => 'New Lead', 'value' => 'new'],
                    ['label' => 'Contacted', 'value' => 'contacted'],
                    ['label' => 'Qualified Opportunity', 'value' => 'qualified'],
                    ['label' => 'Proposal Sent', 'value' => 'proposal_sent'],
                    ['label' => 'Won / Closed', 'value' => 'won'],
                    ['label' => 'Lost / Disqualified', 'value' => 'lost'],
                ], 'new'),
                S::dropdownSelect('priority', 'Priority', [
                    ['label' => 'Low Priority', 'value' => 'low'],
                    ['label' => 'Medium Priority', 'value' => 'medium'],
                    ['label' => 'High Priority', 'value' => 'high'],
                    ['label' => 'Urgent Priority', 'value' => 'urgent'],
                ], 'medium'),
                S::textInput('expected_value', 'Expected Value (' . $currency . ')', '0.00', [
                    'icon' => 'monetization_on',
                    'keyboard_type' => 'number',
                ]),
                S::dropdownSelect('source_id', 'Lead Source', $sourceOptions),
                S::dropdownSelect('assigned_to', 'Assigned Sales Representative', $repOptions),
                S::textInput('requirement_summary', 'Requirements / Scope of Work', '', [
                    'max_lines' => 3,
                    'keyboard_type' => 'multiline',
                    'placeholder' => 'Add key requirement notes, budget, timeline...',
                ]),
                S::text('Follow-up Schedule', 'title_small', ['bold' => true]),
                S::textInput('reminder_due_date', 'Reminder Date & Time (YYYY-MM-DD HH:MM)', now()->addDay()->format('Y-m-d 10:00'), [
                    'icon' => 'event',
                ]),
                S::textInput('reminder_notes', 'Follow-up Notes / Call Agenda', '', [
                    'icon' => 'notes',
                    'placeholder' => 'Discussion points, callback time, or agenda',
                ]),
                S::buttonPrimary('Create Lead & Sync CRM',
                    S::formSubmitAction('/api/tenant/lead-module/leads', 'POST', 'Lead created and linked successfully.', reload: true),
                    'person_add',
                    ['background_color' => '#2DD4BF', 'foreground_color' => '#0F172A']),
            ], [
                'title' => 'Capture a new lead',
                'subtitle' => 'Search existing CRM customer or enter details to automatically provision customer profile.',
            ]),
        ];

        // -----------------------------------------------------------------
        // Tab 4 Components: "Follow-ups & Reminders"
        // -----------------------------------------------------------------
        $filterReminder = trim((string) (request('filter') ?: request('status') ?: 'upcoming'));

        $remindersQuery = Reminder::withoutGlobalScope('company')
            ->where('company_id', $company->id);

        $activitiesQuery = LeadActivity::where('company_id', $company->id)
            ->with('lead');

        $upcomingReminders = (clone $remindersQuery)->where('status', 'pending')->where(function ($q) {
            $q->where('due_date', '>=', now())->orWhereNull('due_date');
        })->count();
        $upcomingActivities = (clone $activitiesQuery)->where('status', 'pending')->where(function ($q) {
            $q->where('due_date', '>=', now())->orWhereNull('due_date');
        })->count();
        $upcomingCount = $upcomingReminders + $upcomingActivities;

        $overdueReminders = (clone $remindersQuery)->where('status', 'pending')->where('due_date', '<', now())->count();
        $overdueActivities = (clone $activitiesQuery)->where('status', 'pending')->where('due_date', '<', now())->count();
        $overdueCount = $overdueReminders + $overdueActivities;

        $doneReminders = (clone $remindersQuery)->where('status', 'completed')->count();
        $doneActivities = (clone $activitiesQuery)->where('status', 'completed')->count();
        $doneCount = $doneReminders + $doneActivities;

        $filterChips = S::segmentedFilterChips([
            [
                'id' => 'upcoming',
                'label' => 'Upcoming',
                'count' => (string) $upcomingCount,
                'selected' => $filterReminder === 'upcoming' || empty($filterReminder),
                'action' => S::navigateAction('/api/tenant/lead-module/views/dashboard?tab=followups&filter=upcoming', 'reload'),
            ],
            [
                'id' => 'overdue',
                'label' => 'Overdue',
                'count' => (string) $overdueCount,
                'selected' => $filterReminder === 'overdue',
                'action' => S::navigateAction('/api/tenant/lead-module/views/dashboard?tab=followups&filter=overdue', 'reload'),
            ],
            [
                'id' => 'done',
                'label' => 'Done',
                'count' => (string) $doneCount,
                'selected' => $filterReminder === 'done',
                'action' => S::navigateAction('/api/tenant/lead-module/views/dashboard?tab=followups&filter=done', 'reload'),
            ],
        ], $filterReminder ?: 'upcoming');

        if ($filterReminder === 'overdue') {
            $remindersQuery->where('status', 'pending')->where('due_date', '<', now());
            $activitiesQuery->where('status', 'pending')->where('due_date', '<', now());
        } elseif ($filterReminder === 'done') {
            $remindersQuery->where('status', 'completed');
            $activitiesQuery->where('status', 'completed');
        } else {
            $remindersQuery->where('status', 'pending')->where(function ($q) {
                $q->where('due_date', '>=', now())->orWhereNull('due_date');
            });
            $activitiesQuery->where('status', 'pending')->where(function ($q) {
                $q->where('due_date', '>=', now())->orWhereNull('due_date');
            });
        }

        $reminders = $remindersQuery->orderBy('due_date')->limit(20)->get();
        $activities = $activitiesQuery->orderBy('due_date')->limit(20)->get();

        $followupCards = [];
        foreach ($activities as $act) {
            $dueStr = $act->due_date ? $act->due_date->format('M d, Y H:i') : 'Pending';
            $leadTitle = $act->lead ? "{$act->lead->lead_code} - {$act->lead->name}" : 'General Lead';
            $typeLabel = ucfirst($act->type ?: 'Activity');

            $components = [
                S::row([
                    S::badge($typeLabel, '#8B5CF6'),
                    S::badge($dueStr, '#F59E0B'),
                ]),
                S::text($act->title ?: 'Follow-up Call / Meeting', 'title_medium', ['bold' => true]),
                S::text("Lead: {$leadTitle}", 'body_small'),
            ];

            if ($act->description) {
                $components[] = S::callout('📝 ' . $act->description, 'accent');
            }

            $targetLeadId = $act->lead_id ?: ($act->lead?->lead_code ?: null);
            if (! $targetLeadId && preg_match('/LD-([A-Za-z0-9]+)/i', (string) $act->description, $matches)) {
                $targetLeadId = $matches[0];
            }
            if ($targetLeadId) {
                $components[] = S::buttonOutlined('View Lead',
                    S::navigateAction('/api/tenant/lead-module/views/lead-detail?id=' . $targetLeadId, 'dynamic_page', 'Lead'));
            }

            $followupCards[] = [
                'type' => 'card',
                'components' => $components,
            ];
        }

        foreach ($reminders as $rem) {
            $dueStr = $rem->due_date ? $rem->due_date->format('M d, Y H:i') : ($rem->due_at ? date('M d, Y H:i', strtotime($rem->due_at)) : 'Pending');
            $custName = $rem->customer?->name ?: 'Customer Follow-up';
            $noteText = trim((string) ($rem->notes ?: ($rem->description ?: $rem->call_script)));

            $remChildren = [
                S::row([
                    S::badge('Reminder', '#0284C7'),
                    S::badge($dueStr, '#F59E0B'),
                ]),
                S::text($rem->title ?: ($rem->subject ?: 'Scheduled Reminder'), 'title_medium', ['bold' => true]),
                S::text("Client: {$custName}", 'body_small'),
            ];

            if (!empty($noteText)) {
                $remChildren[] = S::callout('📝 ' . $noteText, 'accent');
            }

            $targetLeadId = $rem->remindable_id;
            if (! $targetLeadId && preg_match('/LD-([A-Za-z0-9]+)/i', $noteText, $matches)) {
                $targetLeadId = $matches[0];
            }
            if ($targetLeadId) {
                $remChildren[] = S::buttonOutlined('View Lead',
                    S::navigateAction('/api/tenant/lead-module/views/lead-detail?id=' . $targetLeadId, 'dynamic_page', 'Lead'));
            }

            $followupCards[] = [
                'type' => 'card',
                'components' => $remChildren,
            ];
        }

        if (empty($followupCards)) {
            $followupCards[] = S::card([
                S::column([
                    S::icon('event_available', ['color' => '#10B981', 'size' => 38]),
                    S::text('No Follow-ups Found', 'title_medium', ['bold' => true]),
                    S::text('All scheduled calls, demos, and follow-ups are completed.', 'body_small'),
                ]),
            ]);
        }

        $followupsComponents = array_merge([$filterChips], $followupCards);

        // -----------------------------------------------------------------
        // Tab Assembly (Matching Store Profile Architecture)
        // -----------------------------------------------------------------
        $tabItems = [
            [
                'id' => 'overview',
                'title' => 'Overview & Pipeline',
                'label' => 'Overview & Pipeline',
                'icon' => 'dashboard',
                'type' => 'scrollable_view',
                'components' => $overviewComponents,
                'children' => $overviewComponents,
            ],
            [
                'id' => 'all_leads',
                'title' => 'All Leads',
                'label' => 'All Leads',
                'icon' => 'people',
                'type' => 'lead_list_view',
                'endpoint' => '/api/v1/tenant/leads/list',
                'searchable' => true,
                'search_placeholder' => 'Search lead by code, name, or phone...',
                'filter_options' => [
                    ['label' => 'All Stages', 'value' => 'all'],
                    ['label' => 'New', 'value' => 'new'],
                    ['label' => 'Contacted', 'value' => 'contacted'],
                    ['label' => 'Proposal Sent', 'value' => 'proposal_sent'],
                    ['label' => 'Won', 'value' => 'won'],
                    ['label' => 'Lost', 'value' => 'lost'],
                ],
                'components' => $allLeadsComponents,
                'children' => $allLeadsComponents,
            ],
            [
                'id' => 'capture',
                'title' => 'Capture Lead',
                'label' => 'Capture Lead',
                'icon' => 'person_add',
                'type' => 'form_view',
                'components' => $captureComponents,
                'children' => $captureComponents,
            ],
            [
                'id' => 'followups',
                'title' => 'Follow-ups & Reminders',
                'label' => 'Follow-ups & Reminders',
                'icon' => 'schedule',
                'type' => 'reminders_list_view',
                'endpoint' => '/api/v1/tenant/leads/followups',
                'empty_state' => [
                    'icon' => 'event_available',
                    'title' => 'No Pending Follow-ups',
                    'description' => 'All scheduled calls, demos, and follow-ups are completed.',
                ],
                'components' => $followupsComponents,
                'children' => $followupsComponents,
            ],
        ];

        $tabViews = [
            'overview' => [
                'type' => 'scrollable_view',
                'components' => $overviewComponents,
                'children' => $overviewComponents,
            ],
            'all_leads' => [
                'type' => 'lead_list_view',
                'endpoint' => '/api/v1/tenant/leads/list',
                'searchable' => true,
                'search_placeholder' => 'Search lead by code, name, or phone...',
                'filter_options' => [
                    ['label' => 'All Stages', 'value' => 'all'],
                    ['label' => 'New', 'value' => 'new'],
                    ['label' => 'Contacted', 'value' => 'contacted'],
                    ['label' => 'Proposal Sent', 'value' => 'proposal_sent'],
                    ['label' => 'Won', 'value' => 'won'],
                    ['label' => 'Lost', 'value' => 'lost'],
                ],
                'components' => $allLeadsComponents,
                'children' => $allLeadsComponents,
            ],
            'capture' => [
                'type' => 'form_view',
                'components' => $captureComponents,
                'children' => $captureComponents,
            ],
            'followups' => [
                'type' => 'reminders_list_view',
                'endpoint' => '/api/v1/tenant/leads/followups',
                'empty_state' => [
                    'icon' => 'event_available',
                    'title' => 'No Pending Follow-ups',
                    'description' => 'All scheduled calls, demos, and follow-ups are completed.',
                ],
                'components' => $followupsComponents,
                'children' => $followupsComponents,
            ],
        ];

        $initialIndex = match ($activeTab) {
            'all_leads', 'leads', 'pipeline' => 1,
            'capture', 'create', 'new' => 2,
            'followups', 'reminders', 'activities' => 3,
            default => 0,
        };

        $tabsComponent = S::tabs($tabItems, [
            'initial_index' => $initialIndex,
            'is_scrollable' => true,
        ]);

        $fabConfig = [
            'type' => 'fab_action',
            'icon' => 'add',
            'label' => 'New Lead',
            'background_color' => '#2DD4BF',
            'foreground_color' => '#0F172A',
            'action' => S::navigateAction('/api/tenant/lead-module/views/dashboard?tab=capture', 'dynamic_page', 'Capture Lead'),
        ];

        $screen = S::screen('Lead Management', [
            $tabsComponent,
        ], 'tabs', ['key' => 'lead-management']);

        $screen['screen'] = 'TabbedScreen';
        $screen['title'] = 'Lead Management';
        $screen['app_bar'] = [
            'title' => 'Lead Management',
            'show_back' => true,
            'show_back_button' => true,
            'actions' => [],
        ];
        $screen['layout'] = 'tabs';
        $screen['is_scrollable'] = true;
        $screen['initial_index'] = $initialIndex;
        $screen['tabs'] = $tabItems;
        $screen['tab_views'] = $tabViews;
        $screen['components'] = [
            $tabsComponent,
        ];
        $screen['fab'] = $fabConfig;
        $screen['floating_action_button'] = $fabConfig;
        $screen['fab_action'] = $fabConfig;

        return $screen;
    }
}
