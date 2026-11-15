<?php

declare(strict_types=1);

namespace Pulso;

/**
 * Turns 'l.instagram.com' or utm_source=ig into 'Instagram', so the dashboard shows
 * one row per channel instead of twenty hostnames.
 */
final class Referrer
{
    private const HOSTS = [
        '/(^|\.)google\./' => 'Google',
        '/(^|\.)bing\.com$/' => 'Bing',
        '/(^|\.)duckduckgo\.com$/' => 'DuckDuckGo',
        '/(^|\.)yahoo\./' => 'Yahoo',
        '/(^|\.)(facebook\.com|fb\.me|fb\.com)$/' => 'Facebook',
        '/(^|\.)instagram\.com$/' => 'Instagram',
        '/(^|\.)(t\.co|twitter\.com|x\.com)$/' => 'X (Twitter)',
        '/(^|\.)(linkedin\.com|lnkd\.in)$/' => 'LinkedIn',
        '/(^|\.)tiktok\.com$/' => 'TikTok',
        '/(^|\.)(youtube\.com|youtu\.be)$/' => 'YouTube',
        '/(^|\.)(whatsapp\.com|wa\.me)$/' => 'WhatsApp',
        '/(^|\.)(chatgpt\.com|openai\.com)$/' => 'ChatGPT',
        '/(^|\.)perplexity\.ai$/' => 'Perplexity',
        '/(^|\.)pinterest\./' => 'Pinterest',
    ];

    private const UTM_ALIASES = [
        'ig' => 'Instagram',
        'instagram' => 'Instagram',
        'fb' => 'Facebook',
        'facebook' => 'Facebook',
        'meta' => 'Facebook',
        'google' => 'Google',
        'tiktok' => 'TikTok',
        'tt' => 'TikTok',
        'whatsapp' => 'WhatsApp',
        'wa' => 'WhatsApp',
        'linkedin' => 'LinkedIn',
        'youtube' => 'YouTube',
        'email' => 'Email',
        'newsletter' => 'Email',
    ];

    public static function host(?string $referrer): ?string
    {
        if (!$referrer) {
            return null;
        }
        $host = parse_url($referrer, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }
        return self::stripWww(strtolower($host));
    }

    /** utm_source wins over the referrer: if the client tagged the link, they know better. */
    public static function source(?string $referrerHost, ?string $utmSource): string
    {
        if ($utmSource !== null && $utmSource !== '') {
            $key = strtolower(trim($utmSource));
            return self::UTM_ALIASES[$key] ?? mb_substr(trim($utmSource), 0, 64);
        }
        if ($referrerHost === null) {
            return 'Directo';
        }
        foreach (self::HOSTS as $pattern => $name) {
            if (preg_match($pattern, $referrerHost)) {
                return $name;
            }
        }
        return mb_substr($referrerHost, 0, 64);
    }

    public static function stripWww(string $host): string
    {
        return (string) preg_replace('/^www\./i', '', $host);
    }
}
