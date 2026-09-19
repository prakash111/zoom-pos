<?php

namespace App\Services\Notifications;

class DeviceMessageService
{
    public static function url(string $channel, string $recipient, string $message, string $subject = ''): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $recipient);
        $digits = ltrim(preg_replace('/\D+/', '', $recipient), '0');

        return match ($channel) {
            'whatsapp' => (strlen($digits) >= 10 && strlen($digits) <= 15
                ? 'https://wa.me/'.$digits.'?text='
                : 'https://api.whatsapp.com/send?text=').rawurlencode($message),
            'sms' => 'sms:'.$phone.'?body='.rawurlencode($message),
            'email' => 'mailto:'.rawurlencode(trim($recipient)).'?subject='.rawurlencode($subject).'&body='.rawurlencode($message),
        };
    }

    public static function appUrl(string $channel, string $recipient, string $message, string $subject = ''): string
    {
        if ($channel !== 'whatsapp') {
            return self::url($channel, $recipient, $message, $subject);
        }

        $digits = ltrim(preg_replace('/\D+/', '', $recipient), '0');
        $phone = strlen($digits) >= 10 && strlen($digits) <= 15 ? 'phone='.$digits.'&' : '';

        return 'whatsapp://send?'.$phone.'text='.rawurlencode($message);
    }

    public static function prepare(string $channel, string $recipient, string $message, string $subject = ''): array
    {
        $url = self::appUrl($channel, $recipient, $message, $subject);

        return [
            'success' => true,
            'status' => 'manual_link',
            'channel' => $channel,
            'provider' => 'device',
            'url' => $url,
            'web_url' => self::url($channel, $recipient, $message, $subject),
            $channel.'_url' => $url,
            'action' => ['type' => 'OPEN_URL', 'url' => $url],
            'message' => 'Message prepared. Complete sending in your '.match ($channel) {
                'whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'email',
            }.' app.',
        ];
    }

    public static function plainText(string $html): string
    {
        $html = preg_replace('/<(style|script)\b[^>]*>.*?<\/\1>/is', '', $html);
        $html = preg_replace('/<a\b[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>(.*?)<\/a>/is', '$2 ($1)', $html);
        $html = preg_replace('/<(?:br\s*\/?|\/p|\/div|\/h[1-6])>/i', "\n", $html);

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
