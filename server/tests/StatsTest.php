<?php

declare(strict_types=1);

namespace Pulso\Tests;

use PDO;
use Pulso\Collector;
use Pulso\Sites;
use Pulso\Stats;

require_once __DIR__ . '/Fixtures.php';

/**
 * A small, realistic morning on a client's site:
 *
 *  Ana (Android, from Google):     /  ->  /productos  -> clicks WhatsApp.     Comes back 40 min later to /blog.
 *  Beto (iPhone, Instagram ad):    /productos?utm_campaign=black-friday, leaves after 5s.
 *  Caro (Windows, typed the URL):  /contacto -> sends the form -> /
 */
final class StatsTest extends TestCase
{
    private PDO $pdo;
    /** @var array{id: int, timezone: string} */
    private array $site;
    private array $report;

    public function setUp(): void
    {
        $this->pdo = $this->db();
        $created = (new Sites($this->pdo))->create('Cliente', 'cliente.co');
        $this->site = (new Sites($this->pdo))->find($created['public_id']);

        $c = new Collector($this->pdo);
        $ana = ['ip' => '181.1.1.1', 'ua' => Fixtures::ANDROID_CHROME];
        $beto = ['ip' => '181.2.2.2', 'ua' => Fixtures::IPHONE_INSTAGRAM];
        $caro = ['ip' => '181.3.3.3', 'ua' => Fixtures::WINDOWS_CHROME];
        $s = $created['public_id'];
        $hit = static function (array $who, string $time, array $p) use ($c, $s): void {
            $result = $c->collect($p + ['v' => 1, 's' => $s], $who, Fixtures::utc("2026-11-15 {$time}"));
            if (!$result['ok']) {
                throw new \RuntimeException('fixture failed: ' . json_encode($result));
            }
        };

        $hit($ana, '15:00:00', ['e' => 'pageview', 'u' => 'https://cliente.co/', 'i' => 'a1', 'r' => 'https://www.google.com/']);
        $hit($ana, '15:00:30', ['e' => 'engagement', 'u' => 'https://cliente.co/', 'i' => 'a1', 'd' => 30000]);
        $hit($ana, '15:00:31', ['e' => 'pageview', 'u' => 'https://cliente.co/productos', 'i' => 'a2']);
        $hit($ana, '15:01:00', ['e' => 'event', 'u' => 'https://cliente.co/productos', 'i' => 'a2', 'n' => 'whatsapp_click']);
        $hit($ana, '15:01:31', ['e' => 'engagement', 'u' => 'https://cliente.co/productos', 'i' => 'a2', 'd' => 60000]);

        $hit($beto, '15:05:00', [
            'e' => 'pageview',
            'u' => 'https://cliente.co/productos?utm_source=ig&utm_medium=social&utm_campaign=black-friday',
            'i' => 'b1',
            'r' => 'https://l.instagram.com/',
        ]);
        $hit($beto, '15:05:05', ['e' => 'engagement', 'u' => 'https://cliente.co/productos', 'i' => 'b1', 'd' => 5000]);

        $hit($caro, '15:10:00', ['e' => 'pageview', 'u' => 'https://cliente.co/contacto', 'i' => 'c1']);
        $hit($caro, '15:10:40', ['e' => 'event', 'u' => 'https://cliente.co/contacto', 'i' => 'c1', 'n' => 'form_submit', 'p' => ['form' => 'contacto']]);
        $hit($caro, '15:11:00', ['e' => 'pageview', 'u' => 'https://cliente.co/', 'i' => 'c2']);
        $hit($caro, '15:11:10', ['e' => 'engagement', 'u' => 'https://cliente.co/', 'i' => 'c2', 'd' => 10000]);

        $hit($ana, '15:45:00', ['e' => 'pageview', 'u' => 'https://cliente.co/blog', 'i' => 'a3']);

        $this->report = (new Stats($this->pdo))->report($this->site, '2026-11-14', '2026-11-15', 10, Fixtures::utc('2026-11-15 15:46:00'));
    }

    public function testSummary(): void
    {
        $this->assertSame([
            'visitors' => 3,
            'sessions' => 4,
            'pageviews' => 6,
            'pages_per_session' => 1.5,
            'bounce_rate' => 50.0, // Beto and Ana's second visit
            'avg_session_seconds' => 26, // (90 + 5 + 10 + 0) / 4
        ], $this->report['summary']);
    }

    public function testRealtime(): void
    {
        $this->assertSame(1, $this->report['realtime_visitors']);
    }

    public function testTimeseriesFillsEmptyDays(): void
    {
        $this->assertSame([
            ['date' => '2026-11-14', 'visitors' => 0, 'pageviews' => 0],
            ['date' => '2026-11-15', 'visitors' => 3, 'pageviews' => 6],
        ], $this->report['timeseries']);
    }

    public function testPagesWithTimeOnPage(): void
    {
        $this->assertSame([
            ['path' => '/', 'pageviews' => 2, 'visitors' => 2, 'avg_seconds' => 20],
            ['path' => '/productos', 'pageviews' => 2, 'visitors' => 2, 'avg_seconds' => 33],
            ['path' => '/blog', 'pageviews' => 1, 'visitors' => 1, 'avg_seconds' => 0],
            ['path' => '/contacto', 'pageviews' => 1, 'visitors' => 1, 'avg_seconds' => 0],
        ], $this->report['pages']);
    }

    public function testEntryAndExitPages(): void
    {
        $this->assertSame([
            ['path' => '/', 'sessions' => 1],
            ['path' => '/blog', 'sessions' => 1],
            ['path' => '/contacto', 'sessions' => 1],
            ['path' => '/productos', 'sessions' => 1],
        ], $this->report['entry_pages']);

        $this->assertSame([
            ['path' => '/productos', 'sessions' => 2],
            ['path' => '/', 'sessions' => 1],
            ['path' => '/blog', 'sessions' => 1],
        ], $this->report['exit_pages']);
    }

    public function testSourcesAndCampaigns(): void
    {
        $this->assertSame([
            ['name' => 'Directo', 'sessions' => 2],
            ['name' => 'Google', 'sessions' => 1],
            ['name' => 'Instagram', 'sessions' => 1],
        ], $this->report['sources']);

        $this->assertSame([
            ['source' => 'ig', 'medium' => 'social', 'campaign' => 'black-friday', 'sessions' => 1],
        ], $this->report['campaigns']);
    }

    public function testBreakdowns(): void
    {
        $this->assertSame([['name' => 'mobile', 'sessions' => 3], ['name' => 'desktop', 'sessions' => 1]], $this->report['devices']);
        $this->assertSame([['name' => 'Chrome', 'sessions' => 3], ['name' => 'Instagram (app)', 'sessions' => 1]], $this->report['browsers']);
        $this->assertSame([], $this->report['countries']);
    }

    public function testConversions(): void
    {
        $this->assertSame([
            ['name' => 'form_submit', 'count' => 1, 'sessions' => 1],
            ['name' => 'whatsapp_click', 'count' => 1, 'sessions' => 1],
        ], $this->report['events']);
    }

    public function testOtherSitesDontLeakIn(): void
    {
        $other = (new Sites($this->pdo))->create('Otro', 'otro.co');
        $otherSite = (new Sites($this->pdo))->find($other['public_id']);
        $report = (new Stats($this->pdo))->report($otherSite, '2026-11-14', '2026-11-15');
        $this->assertSame(0, $report['summary']['pageviews']);
        $this->assertSame([], $report['pages']);
    }
}
