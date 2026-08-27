<?php

namespace App\Support;

class HtmlSanitizer
{
    /**
     * Whitelist of allowed HTML tags for rich-text notes, descriptions, and terms.
     */
    protected static array $allowedTags = [
        'p', 'br', 'hr', 'span', 'div',
        'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'sub', 'sup',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'blockquote', 'pre', 'code',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'a', 'img',
    ];

    /**
     * Clean and sanitize an HTML string.
     */
    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // 1. Remove dangerous script, iframe, object, embed, style, applet, form, input tags with their content
        $cleaned = preg_replace('/<(script|style|iframe|object|embed|applet|form|input|button|meta|link)[^>]*?>.*?<\/\\1>/si', '', $html);
        $cleaned = preg_replace('/<(script|style|iframe|object|embed|applet|form|input|button|meta|link)[^>]*?>/si', '', $cleaned);

        // 2. Strip tags not in whitelist
        $allowedTagString = '<'.implode('><', static::$allowedTags).'>';
        $cleaned = strip_tags($cleaned, $allowedTagString);

        // 3. Remove inline event handlers (e.g. onload, onerror, onclick, onmouseover)
        $cleaned = preg_replace('/\s+on[a-z]+\s*=\s*(["\'][^"\']*["\']|[^\s>]+)/i', '', $cleaned);

        // 4. Remove javascript:, vbscript:, and harmful data: protocols from attributes
        $cleaned = preg_replace('/\b(href|src)\s*=\s*["\']\s*(javascript|vbscript|data(?!\s*:\s*image\/)):[^"\']*["\']/i', '', $cleaned);

        return trim($cleaned);
    }
}
