<?php

declare(strict_types=1);

namespace Pulso;

use PDO;

/**
 * Receives one beacon from the tracker, validates it and stores one row.
 * Every check here assumes the payload is hostile: it comes from the internet.
 */
final class Collector
{
    private const TYPES = ['pageview', 'event', 'engagement'];
    private const SESSION_TIMEOUT = '-30 minutes';
    private const MAX_DURATION_MS = 30 * 60 * 1000;

    private readonly Sites $sites;
    private readonly Salt $salt;

    public function __construct(private readonly PDO $pdo)
    {
        $this->sites = new Sites($pdo);
        $this->salt = new Salt($pdo);
    }

    /**
     * @param array<string, mixed> $payload decoded JSON from the tracker
     * @param array{ip: string, ua: string, origin?: ?string, country?: ?string} $context
     * @return array{ok: bool, status: int, error?: string}
     */
    public function collect(array $payload, array $context, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if (UserAgent::isBot($context['ua'])) {
            // Answer 202 anyway: bots don't need to know they were ignored.
            return ['ok' => false, 'status' => 202, 'error' => 'bot'];
        }

        $type = $payload['e'] ?? null;
        $siteId = $payload['s'] ?? null;
        $url = $payload['u'] ?? null;
        if (($payload['v'] ?? null) !== 1 || !is_string($siteId) || !in_array($type, self::TYPES, true)) {
            return self::fail(400, 'invalid payload');
        }
        if (!is_string($url) || strlen($url) > 2048 || !preg_match('#^https?://#i', $url)) {
            return self::fail(400, 'invalid url');
        }

        $site = $this->sites->find(substr($siteId, 0, 32));
        if ($site === null) {
            return self::fail(404, 'unknown site');
        }

        // The page has to belong to the site, and so does the Origin when the browser sends it.
        // Stops someone from pasting your site id somewhere else and polluting the stats.
        $host = (string) parse_url($url, PHP_URL_HOST);
        $originHost = isset($context['origin']) && $context['origin'] ? parse_url($context['origin'], PHP_URL_HOST) : null;
        if (!Sites::hostBelongs($host, $site['domain'])
            || (is_string($originHost) && !Sites::hostBelongs($originHost, $site['domain']))) {
            return self::fail(403, 'domain mismatch');
        }

        $path = self::path($url);
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $utm = static fn (string $key) => isset($query[$key]) && is_string($query[$key]) && $query[$key] !== ''
            ? mb_substr($query[$key], 0, 64)
            : null;

        $referrerHost = Referrer::host(self::str($payload['r'] ?? null, 2048));
        if ($referrerHost !== null && Sites::hostBelongs($referrerHost, $site['domain'])) {
            $referrerHost = null; // internal navigation is not a source
        }
        $utmSource = $utm('utm_source');

        $ua = UserAgent::parse($context['ua']);
        $salt = $this->salt->forDay($now);
        $visitorId = substr(hash('sha256', $salt . $site['id'] . $context['ip'] . $context['ua']), 0, 16);
        $sessionId = $this->sessionFor($site['id'], $visitorId, $now);

        $props = null;
        if ($type === 'event' && isset($payload['p']) && is_array($payload['p'])) {
            $props = self::props($payload['p']);
        }
        $name = $type === 'event' ? self::str($payload['n'] ?? null, 64) : null;
        if ($type === 'event' && ($name === null || $name === '')) {
            return self::fail(400, 'event without name');
        }

        $duration = null;
        if ($type === 'engagement') {
            $d = $payload['d'] ?? null;
            if (!is_int($d) || $d <= 0) {
                return self::fail(400, 'invalid duration');
            }
            $duration = min($d, self::MAX_DURATION_MS);
        }

        $pageviewId = self::str($payload['i'] ?? null, 16) ?? '';
        if (!preg_match('/^[a-z0-9]*$/i', $pageviewId)) {
            $pageviewId = '';
        }

        $country = $context['country'] ?? null;
        $country = is_string($country) && preg_match('/^[A-Z]{2}$/', $country) && $country !== 'XX' && $country !== 'T1'
            ? $country
            : null;
        $screen = $payload['w'] ?? null;

        $localDate = $now->setTimezone(new \DateTimeZone($site['timezone']))->format('Y-m-d');

        $stmt = $this->pdo->prepare(
            'INSERT INTO pulso_events (site_id, type, session_id, visitor_id, pageview_id, path, name, props,'
            . ' duration_ms, referrer_host, source, utm_source, utm_medium, utm_campaign, device, browser, os,'
            . ' screen_w, lang, tz, country, local_date, created_at)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $isPageview = $type === 'pageview';
        $stmt->execute([
            $site['id'],
            $type,
            $sessionId,
            $visitorId,
            $pageviewId,
            $path,
            $name,
            $props === null ? null : json_encode($props, JSON_UNESCAPED_UNICODE),
            $duration,
            $isPageview ? $referrerHost : null,
            $isPageview ? Referrer::source($referrerHost, $utmSource) : null,
            $isPageview ? $utmSource : null,
            $isPageview ? $utm('utm_medium') : null,
            $isPageview ? $utm('utm_campaign') : null,
            $ua['device'],
            $ua['browser'],
            $ua['os'],
            is_int($screen) && $screen > 0 && $screen < 20000 ? $screen : null,
            self::str($payload['l'] ?? null, 16),
            self::str($payload['tz'] ?? null, 64),
            $country,
            $localDate,
            $now->format('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'status' => 202];
    }

    /** Same visitor, active in the last 30 minutes -> same session. */
    private function sessionFor(int $siteId, string $visitorId, \DateTimeImmutable $now): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT session_id FROM pulso_events WHERE site_id = ? AND visitor_id = ? AND created_at >= ?'
            . ' ORDER BY id DESC LIMIT 1',
        );
        $stmt->execute([$siteId, $visitorId, $now->modify(self::SESSION_TIMEOUT)->format('Y-m-d H:i:s')]);
        $existing = $stmt->fetchColumn();
        return is_string($existing) ? $existing : bin2hex(random_bytes(8));
    }

    /** '/productos/?utm_source=ig#fotos' -> '/productos'. Query strings often carry emails and tokens. */
    private static function path(string $url): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
        $path = rawurldecode($path);
        if ($path !== '/' ) {
            $path = rtrim($path, '/');
        }
        return mb_substr($path === '' ? '/' : $path, 0, 512);
    }

    /**
     * @param array<mixed> $props
     * @return array<string, string|int|float|bool>|null
     */
    private static function props(array $props): ?array
    {
        $clean = [];
        foreach ($props as $key => $value) {
            if (count($clean) >= 10) {
                break;
            }
            $k = mb_substr((string) $key, 0, 32);
            if (is_string($value)) {
                $clean[$k] = mb_substr($value, 0, 256);
            } elseif (is_int($value) || is_float($value) || is_bool($value)) {
                $clean[$k] = $value;
            }
        }
        return $clean ?: null;
    }

    private static function str(mixed $value, int $max): ?string
    {
        return is_string($value) && $value !== '' ? mb_substr($value, 0, $max) : null;
    }

    /** @return array{ok: false, status: int, error: string} */
    private static function fail(int $status, string $error): array
    {
        return ['ok' => false, 'status' => $status, 'error' => $error];
    }
}
