<?php

declare(strict_types=1);

namespace Pulso;

use PDO;

/**
 * Routes:
 *   POST /collect          beacon from the tracker (public)
 *   GET  /api/sites        all sites (admin token)
 *   GET  /api/stats        ?site=&from=YYYY-MM-DD&to=YYYY-MM-DD&limit=10 (admin token or the site's API key)
 *   GET  /health           for uptime monitors
 */
final class App
{
    private const CORS = [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Pulso-Key',
        'Access-Control-Max-Age' => '86400',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly Config $config,
    ) {
    }

    public function handle(Request $request, ?\DateTimeImmutable $now = null): Response
    {
        try {
            $response = $this->route($request, $now);
        } catch (\Throwable $e) {
            error_log('[pulso] ' . $e->getMessage());
            $response = Response::json(500, ['error' => 'internal error']);
        }
        return new Response($response->status, $response->body, $response->headers + self::CORS);
    }

    private function route(Request $request, ?\DateTimeImmutable $now): Response
    {
        if ($request->method === 'OPTIONS') {
            return new Response(204);
        }

        return match ([$request->method, $request->path]) {
            ['POST', '/collect'], ['POST', '/api/collect'] => $this->collect($request, $now),
            ['GET', '/api/sites'] => $this->sites($request),
            ['GET', '/api/stats'] => $this->stats($request, $now),
            ['GET', '/health'], ['GET', '/'] => Response::json(200, ['ok' => true, 'name' => 'pulso']),
            default => Response::json(404, ['error' => 'not found']),
        };
    }

    private function collect(Request $request, ?\DateTimeImmutable $now): Response
    {
        $payload = json_decode($request->body, true);
        if (!is_array($payload)) {
            return Response::json(400, ['error' => 'invalid json']);
        }

        $country = $this->config->trustProxy() ? $request->header('cf-ipcountry') : null;
        $result = (new Collector($this->pdo))->collect($payload, [
            'ip' => $request->ip,
            'ua' => $request->header('user-agent') ?? '',
            'origin' => $request->header('origin'),
            'country' => $country,
        ], $now);

        return $result['ok'] || $result['status'] === 202
            ? new Response(202)
            : Response::json($result['status'], ['error' => $result['error'] ?? 'error']);
    }

    private function sites(Request $request): Response
    {
        if (!$this->isAdmin($request)) {
            return Response::json(401, ['error' => 'unauthorized']);
        }
        $sites = array_map(
            static fn (array $s) => [
                'id' => $s['public_id'],
                'name' => $s['name'],
                'domain' => $s['domain'],
                'timezone' => $s['timezone'],
            ],
            (new Sites($this->pdo))->all(),
        );
        return Response::json(200, ['sites' => $sites]);
    }

    private function stats(Request $request, ?\DateTimeImmutable $now): Response
    {
        $publicId = $request->query['site'] ?? '';
        $sites = new Sites($this->pdo);
        $site = $publicId !== '' ? $sites->find($publicId) : null;

        // Same 401 for "no such site" and "wrong key": don't help people guess site ids
        if ($site === null || (!$this->isAdmin($request) && !$sites->verifyKey($publicId, $request->token()))) {
            return Response::json(401, ['error' => 'unauthorized']);
        }

        $tz = new \DateTimeZone($site['timezone']);
        $today = ($now ?? new \DateTimeImmutable('now'))->setTimezone($tz);
        $to = self::date($request->query['to'] ?? null) ?? $today->format('Y-m-d');
        $from = self::date($request->query['from'] ?? null)
            ?? (new \DateTimeImmutable($to))->modify('-29 days')->format('Y-m-d');

        if ($from > $to) {
            return Response::json(400, ['error' => 'from must be before to']);
        }
        if ((new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->days > 366) {
            return Response::json(400, ['error' => 'range too long (max 366 days)']);
        }

        $report = (new Stats($this->pdo))->report(
            $site,
            $from,
            $to,
            (int) ($request->query['limit'] ?? 10),
            $now,
        );
        return Response::json(200, [
            'site' => ['id' => $site['public_id'], 'name' => $site['name'], 'domain' => $site['domain']],
        ] + $report);
    }

    private function isAdmin(Request $request): bool
    {
        $admin = $this->config->adminToken();
        // An empty admin token never matches, so forgetting to set it doesn't open the door
        return strlen($admin) >= 16 && hash_equals($admin, $request->token());
    }

    private static function date(?string $value): ?string
    {
        if ($value === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }
}
