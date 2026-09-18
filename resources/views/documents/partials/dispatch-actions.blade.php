@php
    $previewIsQuote = $isQuotation ?? ($sale->operation_type === 'quotation');
    $previewType = $previewIsQuote ? 'quotation' : 'invoice';
    $previewDelivery = app(\App\Services\Invoice\InvoiceDeliveryService::class);
    $previewMessage = $previewIsQuote
        ? $previewDelivery->buildQuotationWhatsAppMessage($sale)
        : $previewDelivery->buildInvoiceWhatsAppMessage($sale);
    $previewPhone = $sale->customer?->phone ?: ($sale->customer_phone ?: '');
    $previewEmail = $sale->customer?->email ?: ($sale->customer_email ?: '');
@endphp

@foreach (['whatsapp' => 'WhatsApp', 'email' => 'Email', 'sms' => 'SMS'] as $previewChannel => $previewLabel)
    @php
        $previewConfigured = auth('web')->check() && match ($previewChannel) {
            'whatsapp' => \App\Services\DispatchChannelService::isWhatsAppConfigured($sale->company_id),
            'email' => \App\Services\DispatchChannelService::isEmailConfigured($sale->company_id),
            'sms' => \App\Services\DispatchChannelService::isSmsConfigured($sale->company_id),
        };
        $previewRecipient = $previewChannel === 'email' ? $previewEmail : $previewPhone;
    @endphp
    @if ($previewConfigured)
        <button type="button" class="btn {{ $previewChannel === 'whatsapp' ? 'btn-whatsapp' : 'btn-secondary' }}"
                onclick="previewDispatchDocument({{ Js::from($previewChannel) }}, {{ Js::from($previewRecipient) }})">
            {{ __('Send via :channel', ['channel' => $previewLabel]) }}
        </button>
    @else
        <a class="btn {{ $previewChannel === 'whatsapp' ? 'btn-whatsapp' : 'btn-secondary' }}"
           href="{{ \App\Services\Notifications\DeviceMessageService::appUrl($previewChannel, $previewRecipient, $previewMessage, ucfirst($previewType).' #'.$sale->sale_number) }}"
           target="_self" rel="noopener noreferrer">
            {{ __('Open :channel & Send', ['channel' => $previewLabel]) }}
        </a>
    @endif
@endforeach

@if (auth('web')->check())
    <script>
        async function previewDispatchDocument(channel, recipient) {
            recipient = recipient || prompt(channel === 'email' ? 'Recipient email address' : 'Recipient phone number with country code');
            if (!recipient) return;
            try {
                const response = await fetch({{ Js::from(route('tenant.dispatch.document', ['type' => $previewType, 'id' => $sale->id])) }}, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json', 'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || {{ Js::from(csrf_token()) }}
                    },
                    body: JSON.stringify({ channel, recipient })
                });
                const result = await response.json();
                if (!response.ok || result.success === false) throw new Error(result.error || result.message || 'Dispatch failed.');
                if (result.status === 'manual_link' && result.url) window.location.href = result.url;
                else alert(result.message || 'Document sent.');
            } catch (error) {
                alert(error.message || 'Dispatch failed.');
            }
        }
    </script>
@endif
