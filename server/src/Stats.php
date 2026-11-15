<?php

declare(strict_types=1);

namespace Pulso;

use PDO;

/**
 * The numbers for the dashboard. Every query is plain SQL that runs the same on
 * MySQL, MariaDB and SQLite (no window functions, no timezone tables), because
 * shared hosting gives you whatever MySQL version it feels like.
 *
 * Dates are local to the site's timezone and both ends are inclusive.
 */
final class Stats
{
    private const REALTIME_WINDOW = '-5 minutes';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array{id: int, timezone: string} $site
     * @return array<string, mixed>
     */
    public function report(array $site, string $from, string $to, int $limit = 10, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $range = [$site['id'], $from, $to];
        $limit = max(1, min($limit, 100));

        $sessions = (int) $this->value(
            "SELECT COUNT(DISTINCT session_id) FROM pulso_events
             WHERE site_id = ? AND local_date BETWEEN ? AND ? AND type = 'pageview'",
            $range,
        );
        $pageviews = (int) $this->value(
            "SELECT COUNT(*) FROM pulso_events WHERE site_id = ? AND local_date BETWEEN ? AND ? AND type = 'pageview'",
            $range,
        );
        $visitors = (int) $this->value(
            "SELECT COUNT(DISTINCT visitor_id) FROM pulso_events
             WHERE site_id = ? AND local_date BETWEEN ? AND ? AND type = 'pageview'",
            $range,
        );

        // Bounce = a session with exactly one page view and no events (no WhatsApp click, no form...).
        $bounces = (int) $this->value(
            "SELECT COUNT(*) FROM (
                SELECT session_id FROM pulso_events
                WHERE site_id = ? AND local_date BETWEEN ? AND ? AND type IN ('pageview', 'event')
                GROUP BY session_id
                HAVING SUM(CASE WHEN type = 'pageview' THEN 1 ELSE 0 END) = 1
                   AND SUM(CASE WHEN type = 'event' THEN 1 ELSE 0 END) = 0
            ) b",
            $range,
        );

        $avgSessionMs = (float) $this->value(
            "SELECT AVG(total) FROM (
                SELECT s.session_id, COALESCE(SUM(e.duration_ms), 0) AS total
                FROM (SELECT DISTINCT session_id FROM pulso_events
                      WHERE site_id = ? AND local_date BETWEEN ? AND ? AND type = 'pageview') s
                LEFT JOIN pulso_events e
                       ON e.site_id = ? AND e.session_id = s.session_id AND e.type = 'engagement'
                GROUP BY s.session_id
            ) t",
            [...$range, $site['id']],
        );

        return [
            'range' => ['from' => $from, 'to' => $to],
            'summary' => [
                'visitors' => $visitors,
                'sessions' => $sessions,
                'pageviews' => $pageviews,
                'pages_per_session' => $sessions ? round($pageviews / $sessions, 2) : 0,
                'bounce_rate' => $sessions ? round($bounces / $sessions * 100, 1) : 0,
                'avg_session_seconds' => (int) round($avgSessionMs / 1000),
            ],
            'realtime_visitors' => (int) $this->value(
                'SELECT COUNT(DISTINCT visitor_id) FROM pulso_events WHERE site_id = ? AND created_at >= ?',
                [$site['id'], $now->modify(self::REALTIME_WINDOW)->format('Y-m-d H:i:s')],
            ),
            'timeseries' => $this->timeseries($site['id'], $from, $to),
            'pages' => $this->pages($site['id'], $from, $to, $limit),
            'entry_pages' => $this->edgePages($site['id'], $from, $to, 'MIN', $limit),
            'exit_pages' => $this->edgePages($site['id'], $from, $to, 'MAX', $limit),
            'sources' => $this->bySessionStart($site['id'], $from, $to, 'source', $limit),
            'campaigns' => $this->campaigns($site['id'], $from, $to, $limit),
            'devices' => $this->breakdown($site['id'], $from, $to, 'device', $limit),
            'browsers' => $this->breakdown($site['id'], $from, $to, 'browser', $limit),
            'os' => $this->breakdown($site['id'], $from, $to, 'os', $limit),
            'countries' => $this->breakdown($site['id'], $from, $to, 'country', $limit),
            'events' => $this->events($site['id'], $from, $to, $limit),
        ];
    }

    /** @return list<array{date: string, visitors: int, pageviews: int}> */
    private function timeseries(int $siteId, string $from, string $to): array
    {
        $rows = $this->rows(
            "SELECT local_date AS date, COUNT(DISTINCT visitor_id) AS visitors, COUNT(*) AS pageviews
             FROM pulso_events WHERE site_id = ? AND local_date BETWEEN ? AND ? AND type = 'pageview'
             GROUP BY local_date",
            [$siteId, $from, $to],
        );
        $byDate = [];
        foreach ($rows as $row) {
            $byDate[substr((string) $row['date'], 0, 10)] = $row;
        }

        // Fill the empty days with zeros so charts don't skip them
        $series = [];
        $day = new \DateTimeImmutable($from);
        $end = new \DateTimeImmutable($to);
        while ($day <= $end) {
            $key = $day->format('Y-m-d');
            $series[] = [
                'date' => $key,
                'visitors' => (int) ($byDate[$key]['visitors'] ?? 0),
                'pageviews' => (int) ($byDate[$key]['pageviews'] ?? 0),
            ];
            $day = $day->modify('+1 day');
        }
        return $series;
    }

    /** @return list<array{path: string, pageviews: int, visitors: int, avg_seconds: int}> */
    private function pages(int $siteId, string $from, string $to, int $limit): array
    {
        $rows = $this->rows(
            "SELECT p.path, COUNT(*) AS pageviews, COUNT(DISTINCT p.visitor_id) AS visitors,
                    AVG(COALESCE(g.ms, 0)) AS avg_ms
             FROM pulso_events p
             LEFT JOIN (
                SELECT pageview_id, SUM(duration_ms) AS ms FROM pulso_events
                WHERE site_id = ? AND local_date BETWEEN ? AND ? AND type = 'engagement' AND pageview_id <> ''
                GROUP BY pageview_id
             ) g ON g.pageview_id = p.pageview_id AND p.pageview_id <> ''
             WHERE p.site_id = ? AND p.local_date BETWEEN ? AND ? AND p.type = 'pageview'
             GROUP BY p.path
             ORDER BY pageviews DESC, p.path
             LIMIT {$limit}",
            [$siteId, $from, $to, $siteId, $from, $to],
        );
        return array_map(static fn (array $r) => [
            'path' => (string) $r['path'],
            'pageviews' => (int) $r['pageviews'],
            'visitors' => (int) $r['visitors'],
            'avg_seconds' => (int) round((float) $r['avg_ms'] / 1000),
        ], $rows);
    }

    /**
     * Entry pages (first page view of each session) or exit pages (last one).
     * Event ids only grow, so MIN/MAX(id) per session is first/last.
     *
     * @return list<array{path: string, sessions: int}>
     */
    private function edgePages(int $siteId, string $from, string $to, string $edge, int $limit): array
    {
        $fn = $edge === 'MAX' ? 'MAX' : 'MIN';
        $rows = $this->rows(
            "SELECT e.path, COUNT(*) AS sessions FROM pulso_events e
             JOIN (
                SELECT {$fn}(id) AS id FROM pulso_events
                WHERE site_id = ? AND local_date BETWEEN ? AND ? AND type = 'pageview'
                GROUP BY session_id
             ) edge ON edge.id = e.id
             GROUP BY e.path ORDER BY sessions DESC, e.path LIMIT {$limit}",
            [$siteId, $from, $to],
        );
        return array_map(static fn (array $r) => ['path' => (string) $r['path'], 'sessions' => (int) $r['sessions']], $rows);
    }

    /**
     * Where sessions came from: the source of their first page view.
     *
     * @return list<array{name: string, sessions: int}>
     */
    private function bySessionStart(int $siteId, string $from, string $to, string $column, int $limit): array
    {
        $rows = $this->rows(
            "SELECT COALESCE(e.{$column}, 'Directo') AS name, COUNT(*) AS sessions FROM pulso_events e
             JOIN (
                SELECT MIN(id) AS id FROM pulso_events
                WHERE site_id = ? AND local_date BETWEEN ? AND ? AND type = 'pageview'
                GROUP BY session_id
             ) first_view ON first_view.id = e.id
             GROUP BY COALESCE(e.{$column}, 'Directo') ORDER BY sessions DESC, name LIMIT {$limit}",
            [$siteId, $from, $to],
        );
        return array_map(static fn (array $r) => ['name' => (string) $r['name'], 'sessions' => (int) $r['sessions']], $rows);
    }

    /** @return list<array{source: ?string, medium: ?string, campaign: string, sessions: int}> */
    private function campaigns(int $siteId, string $from, string $to, int $limit): array
    {
        $rows = $this->rows(
            "SELECT e.utm_source, e.utm_medium, e.utm_campaign, COUNT(*) AS sessions FROM pulso_events e
             JOIN (
                SELECT MIN(id) AS id FROM pulso_events
                WHERE site_id = ? AND local_date BETWEEN ? AND ? AND type = 'pageview'
                GROUP BY session_id
             ) first_view ON first_view.id = e.id
             WHERE e.utm_campaign IS NOT NULL
             GROUP BY e.utm_source, e.utm_medium, e.utm_campaign ORDER BY sessions DESC LIMIT {$limit}",
            [$siteId, $from, $to],
        );
        return array_map(static fn (array $r) => [
            'source' => $r['utm_source'],
            'medium' => $r['utm_medium'],
            'campaign' => (string) $r['utm_campaign'],
            'sessions' => (int) $r['sessions'],
        ], $rows);
    }

    /** @return list<array{name: string, sessions: int}> */
    private function breakdown(int $siteId, string $from, string $to, string $column, int $limit): array
    {
        $rows = $this->rows(
            "SELECT {$column} AS name, COUNT(DISTINCT session_id) AS sessions FROM pulso_events
             WHERE site_id = ? AND local_date BETWEEN ? AND ? AND type = 'pageview' AND {$column} IS NOT NULL
             GROUP BY {$column} ORDER BY sessions DESC, name LIMIT {$limit}",
            [$siteId, $from, $to],
        );
        return array_map(static fn (array $r) => ['name' => (string) $r['name'], 'sessions' => (int) $r['sessions']], $rows);
    }

    /** @return list<array{name: string, count: int, sessions: int}> */
    private function events(int $siteId, string $from, string $to, int $limit): array
    {
        $rows = $this->rows(
            "SELECT name, COUNT(*) AS total, COUNT(DISTINCT session_id) AS sessions FROM pulso_events
             WHERE site_id = ? AND local_date BETWEEN ? AND ? AND type = 'event'
             GROUP BY name ORDER BY total DESC, name LIMIT {$limit}",
            [$siteId, $from, $to],
        );
        return array_map(static fn (array $r) => [
            'name' => (string) $r['name'],
            'count' => (int) $r['total'],
            'sessions' => (int) $r['sessions'],
        ], $rows);
    }

    /** @param list<mixed> $params */
    private function value(string $sql, array $params): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * @param list<mixed> $params
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
