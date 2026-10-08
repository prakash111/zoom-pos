<?php

namespace App\Livewire\SuperAdmin\LiveChatSupport;

use App\Models\Company;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Modules\Chat\Events\ChatMessageReceivedEvent;
use Modules\Chat\Models\ChatConversation;
use Modules\Chat\Models\ChatMessage;
use Modules\Chat\Services\AiChatSuggestionService;

#[Layout('layouts.superadmin', ['title' => 'Live Chat Support'])]
class Index extends Component
{
    use WithPagination, WithFileUploads;

    #[Url(as: 'id')]
    public ?int $activeConversationId = null;

    public string $search = '';
    public string $filter = 'all'; // all, unread, active

    public string $replyMessage = '';
    public $replyAttachment = null;

    public bool $showEmojiPicker = false;
    public string $activeEmojiTab = 'quick';
    public bool $showTenantDetailsMobile = false;

    public array $aiSuggestions = [];
    public bool $isCorrectingAi = false;
    public string $aiToast = '';

    public array $emojiCategories = [
        'quick' => [
            'name'   => 'Quick Reactions',
            'icon'   => '⚡',
            'emojis' => ['👍', '👎', '👏', '🙌', '🤝', '❤️', '🔥', '🎉', '✅', '❌', '💯', '🚀'],
        ],
        'smileys' => [
            'name'   => 'Smileys & People',
            'icon'   => '😊',
            'emojis' => ['😊', '😂', '😃', '😄', '😁', '😆', '😎', '🤔', '😅', '😍', '🥳', '😉', '😇', '🤫', '😋', '😜', '🤤', '🤠', '🤩', '🥺', '😢', '😭', '👀', '🙏'],
        ],
        'business' => [
            'name'   => 'Business & Retail',
            'icon'   => '🏪',
            'emojis' => ['📦', '💰', '🧾', '🏷️', '🛒', '💳', '🏪', '🛍️', '🚚', '📋', '⚡', '🔔', '📍', '📱', '💻', '💵', '🪙', '📈', '📊', '⏰', '⏳', '💡', '🎯', '📢'],
        ],
        'symbols' => [
            'name'   => 'Symbols & Status',
            'icon'   => '⭐',
            'emojis' => ['⭐', '🌟', '✨', '💬', '📞', '🔒', '🔑', '📌', '🎁', '☕', '🍽️', '🥇', '🏆', '⚠️', '🚨', '❓', '❗', '🆗', '💪', '✌️', '👋', '🍕', '🛡️'],
        ],
    ];

    public function mount(): void
    {
        if ($this->activeConversationId) {
            $exists = ChatConversation::where('id', $this->activeConversationId)
                ->where('type', 'support')
                ->exists();
            if (!$exists) {
                $this->activeConversationId = null;
            }
        }

        if (!$this->activeConversationId) {
            $latest = ChatConversation::where('type', 'support')
                ->orderByDesc('last_message_at')
                ->first();
            $this->activeConversationId = $latest?->id;
        }

        if ($this->activeConversationId) {
            $this->markConversationAsRead($this->activeConversationId);
            $this->loadAiSuggestions();
        }
    }

    public function selectConversation(int $id): void
    {
        $this->activeConversationId = $id;
        $this->replyMessage = '';
        $this->replyAttachment = null;
        $this->showEmojiPicker = false;
        $this->showTenantDetailsMobile = false;
        $this->aiToast = '';

        $this->markConversationAsRead($id);
        $this->loadAiSuggestions();
    }

    public function markConversationAsRead(int $id): void
    {
        ChatMessage::where('conversation_id', $id)
            ->where('sender_type', '!=', 'super_admin')
            ->where(function ($q) {
                $q->whereNull('metadata->read_by_admin')
                  ->orWhere('metadata->read_by_admin', false);
            })
            ->update([
                'metadata->read_by_admin' => true,
            ]);
    }

    public function toggleReaction(int $messageId, string $emoji): void
    {
        if (!$this->activeConversationId) {
            return;
        }

        $message = ChatMessage::where('conversation_id', $this->activeConversationId)
            ->where('id', $messageId)
            ->first();

        if (!$message) {
            return;
        }

        $adminId = Auth::guard('platform_web')->id() ?? 'padm_admin';
        $message->toggleReaction($emoji, (string) $adminId, 'super_admin');
    }

    public function toggleEmojiPicker(): void
    {
        $this->showEmojiPicker = !$this->showEmojiPicker;
    }

    public function insertEmoji(string $emoji): void
    {
        $this->replyMessage .= $emoji;
    }

    public function clearAttachment(): void
    {
        $this->replyAttachment = null;
    }

    public function loadAiSuggestions(): void
    {
        if (!$this->activeConversationId) {
            $this->aiSuggestions = [];
            return;
        }

        $lastMessage = ChatMessage::where('conversation_id', $this->activeConversationId)
            ->where('sender_type', '!=', 'super_admin')
            ->orderByDesc('id')
            ->first();

        $text = trim((string) ($lastMessage?->message ?? ''));
        if (!empty($text)) {
            try {
                $service = app(AiChatSuggestionService::class);
                $this->aiSuggestions = $service->getQuickReplies($text);
            } catch (\Throwable $e) {
                $this->aiSuggestions = [
                    'We are checking this right now.',
                    'Please provide more details or screenshot.',
                    'Issue resolved. Let us know if anything else is needed.',
                ];
            }
        } else {
            $this->aiSuggestions = [
                'Hello! How can we assist your store today?',
                'We are checking your account details now.',
                'Everything looks good on our end.',
            ];
        }
    }

    public function useAiSuggestion(string $reply): void
    {
        $this->replyMessage = $reply;
    }

    public function autoCorrectMessage(): void
    {
        $msg = trim($this->replyMessage);
        if (empty($msg)) {
            return;
        }

        $this->isCorrectingAi = true;

        try {
            $service = app(AiChatSuggestionService::class);
            $result = $service->autoCorrect($msg);
            if (!empty($result['corrected'])) {
                $this->replyMessage = $result['corrected'];
                $this->aiToast = 'Polished with AI ✨';
            }
        } catch (\Throwable $e) {
            $this->replyMessage = ucfirst($msg);
            $this->aiToast = 'Polished with AI ✨';
        } finally {
            $this->isCorrectingAi = false;
        }
    }

    public function sendReply(): void
    {
        if (!$this->activeConversationId) {
            return;
        }

        $this->validate([
            'replyMessage'    => 'nullable|string|max:5000',
            'replyAttachment' => 'nullable|file|max:10240', // 10MB
        ]);

        $messageText = trim($this->replyMessage);

        if (empty($messageText) && !$this->replyAttachment) {
            return;
        }

        $attachmentUrl = null;
        $attachmentType = null;
        $attachmentName = null;

        if ($this->replyAttachment) {
            $attachmentName = $this->replyAttachment->getClientOriginalName();
            $path = $this->replyAttachment->store('chat_attachments', 'public');
            $attachmentUrl = asset('storage/' . $path);
            $mime = $this->replyAttachment->getMimeType() ?? '';
            $attachmentType = str_contains($mime, 'image') ? 'image' : (str_contains($mime, 'pdf') ? 'pdf' : 'document');
        }

        $adminId = Auth::guard('platform_web')->id() ?? 'padm_admin';

        $message = ChatMessage::create([
            'conversation_id' => $this->activeConversationId,
            'sender_id'       => $adminId,
            'sender_type'     => 'super_admin',
            'message'         => $messageText,
            'attachment_type' => $attachmentType,
            'attachment_url'  => $attachmentUrl,
            'attachment_name' => $attachmentName,
            'metadata'        => [
                'read_by_admin' => true,
            ],
        ]);

        // Touch conversation last_message_at
        ChatConversation::where('id', $this->activeConversationId)
            ->update(['last_message_at' => now()]);

        // Dispatch broadcast event
        try {
            event(new ChatMessageReceivedEvent($message));
        } catch (\Throwable $e) {}

        // Send Push Notification to tenant participants
        try {
            $recipientIds = \Modules\Chat\Models\ChatParticipant::where('conversation_id', $this->activeConversationId)
                ->pluck('user_id')
                ->toArray();

            if (!empty($recipientIds)) {
                $preview = !empty($messageText) ? $messageText : ($attachmentName ? "Attachment: {$attachmentName}" : 'New response from Super Admin');
                \App\Services\PushNotificationService::sendToUsers(
                    $recipientIds,
                    "Live Support Desk: New Reply",
                    $preview,
                    [
                        'type'            => 'chat',
                        'conversation_id' => (string) $this->activeConversationId,
                        'is_support'      => 'true',
                    ]
                );
            }
        } catch (\Throwable $e) {}

        $this->replyMessage = '';
        $this->replyAttachment = null;
        $this->showEmojiPicker = false;
        $this->aiToast = '';
        $this->loadAiSuggestions();

        $this->dispatch('message-sent');
    }

    public function pollMessages(): void
    {
        // Polling hook for wire:poll to refresh messages and unread states
        if ($this->activeConversationId) {
            $this->markConversationAsRead($this->activeConversationId);
        }
    }

    public function getConversations()
    {
        $query = ChatConversation::where('type', 'support')
            ->with([
                'tenant.subscriptions',
                'store',
                'participants.user',
                'latestMessage.sender',
            ])
            ->orderByDesc('last_message_at');

        if (!empty($this->search)) {
            $s = trim($this->search);
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                  ->orWhereHas('tenant', function ($tq) use ($s) {
                      $tq->where('name', 'like', "%{$s}%")
                         ->orWhere('email', 'like', "%{$s}%")
                         ->orWhere('phone', 'like', "%{$s}%");
                  })
                  ->orWhereHas('participants.user', function ($uq) use ($s) {
                      $uq->where('name', 'like', "%{$s}%")
                         ->orWhere('email', 'like', "%{$s}%");
                  });
            });
        }

        if ($this->filter === 'unread') {
            $query->whereHas('messages', function ($mq) {
                $mq->where('sender_type', '!=', 'super_admin')
                   ->where(function ($sq) {
                       $sq->whereNull('metadata->read_by_admin')
                          ->orWhere('metadata->read_by_admin', false);
                   });
            });
        }

        return $query->get();
    }

    public function getActiveConversation()
    {
        if (!$this->activeConversationId) {
            return null;
        }

        return ChatConversation::where('id', $this->activeConversationId)
            ->where('type', 'support')
            ->with([
                'tenant.plan',
                'tenant.subscriptions.plan',
                'store',
                'participants.user',
                'messages' => function ($mq) {
                    $mq->with(['sender:id,name,role,email,phone', 'platformAdmin:id,name,role'])
                       ->orderBy('created_at', 'asc');
                },
            ])
            ->first();
    }

    public function render()
    {
        $conversations = $this->getConversations();
        $activeConversation = $this->getActiveConversation();

        // Extract tenant details
        $tenant = $activeConversation?->tenant;
        $initiatorUser = $activeConversation?->participants
            ?->pluck('user')
            ?->filter()
            ?->first(fn($u) => $u->role !== 'superadmin');

        $currentSubscription = $tenant?->subscriptions()
            ?->orderByDesc('started_at')
            ?->first();

        $activePlan = $tenant?->plan ?? $currentSubscription?->plan;

        return view('livewire.superadmin.live-chat-support.index', [
            'conversations'       => $conversations,
            'activeConversation'  => $activeConversation,
            'tenant'              => $tenant,
            'initiatorUser'       => $initiatorUser,
            'currentSubscription' => $currentSubscription,
            'activePlan'          => $activePlan,
        ]);
    }
}
