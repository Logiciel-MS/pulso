<?php

declare(strict_types=1);

namespace Pulso;

/**
 * Just enough User-Agent parsing for a dashboard: device type, browser and OS.
 * No giant regex database: if we don't recognize it, it's 'Otro', and that's fine.
 */
final class UserAgent
{
    private const BOTS = '/bot|crawl|spider|slurp|headless|lighthouse|pagespeed|gtmetrix|pingdom|uptime|monitor|'
        . 'curl|wget|python-|java\/|go-http|axios|node-fetch|okhttp|facebookexternalhit|whatsapp\/|preview|scan/i';

    public static function isBot(string $ua): bool
    {
        return trim($ua) === '' || (bool) preg_match(self::BOTS, $ua);
    }

    /** @return array{device: string, browser: string, os: string} */
    public static function parse(string $ua): array
    {
        return [
            'device' => self::device($ua),
            'browser' => self::browser($ua),
            'os' => self::os($ua),
        ];
    }

    private static function device(string $ua): string
    {
        if (preg_match('/iPad|Tablet|PlayBook|Silk|(Android(?!.*Mobile))/i', $ua)) {
            return 'tablet';
        }
        if (preg_match('/Mobi|iPhone|iPod|Android|Windows Phone|Opera Mini/i', $ua)) {
            return 'mobile';
        }
        return 'desktop';
    }

    private static function browser(string $ua): string
    {
        // In-app browsers first: half of the traffic from social media in Colombia lands here
        $rules = [
            '/Instagram/i' => 'Instagram (app)',
            '/FBAN|FBAV|FB_IAB/i' => 'Facebook (app)',
            '/musical_ly|BytedanceWebview|TikTok/i' => 'TikTok (app)',
            '/LinkedInApp/i' => 'LinkedIn (app)',
            '/SamsungBrowser/i' => 'Samsung Internet',
            '/Edg\//i' => 'Edge',
            '/OPR\/|Opera/i' => 'Opera',
            '/Firefox|FxiOS/i' => 'Firefox',
            '/CriOS|Chrome/i' => 'Chrome',
            '/Safari/i' => 'Safari',
        ];
        foreach ($rules as $pattern => $name) {
            if (preg_match($pattern, $ua)) {
                return $name;
            }
        }
        return 'Otro';
    }

    private static function os(string $ua): string
    {
        $rules = [
            '/iPhone|iPad|iPod/i' => 'iOS',
            '/Android/i' => 'Android',
            '/Windows/i' => 'Windows',
            '/Mac OS X|Macintosh/i' => 'macOS',
            '/CrOS/i' => 'ChromeOS',
            '/Linux/i' => 'Linux',
        ];
        foreach ($rules as $pattern => $name) {
            if (preg_match($pattern, $ua)) {
                return $name;
            }
        }
        return 'Otro';
    }
}
