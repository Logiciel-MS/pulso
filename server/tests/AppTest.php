<?php

declare(strict_types=1);

namespace Pulso\Tests;

use PDO;
use Pulso\App;
use Pulso\Config;
use Pulso\Request;
use Pulso\Sites;

require_once __DIR__ . '/Fixtures.php';

final class AppTest extends TestCase
{
    private const ADMIN = 'admin-token-de-prueba-1234567890';

    private PDO $pdo;
    private App $app;
    /** @var array{public_id: string, api_key: string} */
    private array $site;

    public function setUp(): void
    {
        $this->pdo = $this->db();
        $this->app = new App($this->pdo, Config::fromArray(['admin_token' => self::ADMIN]));
        $this->site = (new Sites($this->pdo))->create('Grado Zero', 'gradozero.co');
    }

    private function collect(string $body, array $headers = []): \Pulso\Response
    {
        $headers += ['user-agent' => Fixtures::ANDROID_CHROME, 'origin' => 'https://gradozero.co'];
        return $this->app->handle(new Request('POST', '/collect', [], $headers, $body, '181.1.1.1'), Fixtures::utc('2026-11-15 15:00:00'));
    }

    private function get(string $path, array $query = [], array $headers = []): \Pulso\Response
    {
        return $this->app->handle(new Request('GET', $path, $query, $headers), Fixtures::utc('2026-11-15 16:00:00'));
    }

    public function testCollectAcceptsTheTrackerBeacon(): void
    {
        $body = json_encode(['v' => 1, 's' => $this->site['public_id'], 'e' => 'pageview', 'u' => 'https://gradozero.co/', 'i' => 'x1']);
        $response = $this->collect($body);
        $this->assertSame(202, $response->status);
        $this->assertSame('*', $response->headers['Access-Control-Allow-Origin']);
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM pulso_events')->fetchColumn());
    }

    public function testCollectRejectsBadJson(): void
    {
        $this->assertSame(400, $this->collect('{nope')->status);
        $this->assertSame(400, $this->collect('"string"')->status);
    }

    public function testCorsPreflight(): void
    {
        $response = $this->app->handle(new Request('OPTIONS', '/api/stats'));
        $this->assertSame(204, $response->status);
        $this->assertTrue(str_contains($response->headers['Access-Control-Allow-Headers'], 'Authorization'));
    }

    public function testHealthAndNotFound(): void
    {
        $this->assertSame(200, $this->get('/health')->status);
        $this->assertSame(404, $this->get('/wp-admin')->status);
    }

    public function testSitesNeedsTheAdminToken(): void
    {
        $this->assertSame(401, $this->get('/api/sites')->status);
        $this->assertSame(401, $this->get('/api/sites', [], ['authorization' => 'Bearer ' . $this->site['api_key']])->status);

        $response = $this->get('/api/sites', [], ['authorization' => 'Bearer ' . self::ADMIN]);
        $this->assertSame(200, $response->status);
        $this->assertSame([[
            'id' => $this->site['public_id'],
            'name' => 'Grado Zero',
            'domain' => 'gradozero.co',
            'timezone' => 'America/Bogota',
        ]], $response->data()['sites']);
    }

    public function testEmptyAdminTokenNeverMatches(): void
    {
        $app = new App($this->pdo, Config::fromArray(['admin_token' => '']));
        $this->assertSame(401, $app->handle(new Request('GET', '/api/sites', [], ['authorization' => 'Bearer ']))->status);
        $this->assertSame(401, $app->handle(new Request('GET', '/api/sites', [], ['x-pulso-key' => '']))->status);
    }

    public function testStatsWithTheSiteKeyOrAdmin(): void
    {
        $query = ['site' => $this->site['public_id'], 'from' => '2026-11-01', 'to' => '2026-11-15'];
        $this->assertSame(401, $this->get('/api/stats', $query)->status);
        $this->assertSame(401, $this->get('/api/stats', $query, ['authorization' => 'Bearer pk_wrong'])->status);

        $bySiteKey = $this->get('/api/stats', $query, ['authorization' => 'Bearer ' . $this->site['api_key']]);
        $this->assertSame(200, $bySiteKey->status);
        $this->assertSame('gradozero.co', $bySiteKey->data()['site']['domain']);
        $this->assertSame(['from' => '2026-11-01', 'to' => '2026-11-15'], $bySiteKey->data()['range']);

        // X-Pulso-Key for hosts that eat the Authorization header
        $this->assertSame(200, $this->get('/api/stats', $query, ['x-pulso-key' => $this->site['api_key']])->status);
        $this->assertSame(200, $this->get('/api/stats', $query, ['authorization' => 'Bearer ' . self::ADMIN])->status);
    }

    public function testOneSiteKeyCantReadAnotherSite(): void
    {
        $other = (new Sites($this->pdo))->create('Otro', 'otro.co');
        $response = $this->get('/api/stats', ['site' => $other['public_id']], ['authorization' => 'Bearer ' . $this->site['api_key']]);
        $this->assertSame(401, $response->status);
    }

    public function testStatsDefaultsToLast30DaysInTheSiteTimezone(): void
    {
        $response = $this->get('/api/stats', ['site' => $this->site['public_id']], ['x-pulso-key' => $this->site['api_key']]);
        $this->assertSame(['from' => '2026-10-17', 'to' => '2026-11-15'], $response->data()['range']);
        $this->assertSame(30, count($response->data()['timeseries']));
    }

    public function testStatsValidatesDates(): void
    {
        $auth = ['x-pulso-key' => $this->site['api_key']];
        $base = ['site' => $this->site['public_id']];
        $this->assertSame(400, $this->get('/api/stats', $base + ['from' => '2026-11-15', 'to' => '2026-11-01'], $auth)->status);
        $this->assertSame(400, $this->get('/api/stats', $base + ['from' => '2024-01-01', 'to' => '2026-11-01'], $auth)->status);
        // invalid formats fall back to defaults instead of reaching SQL
        $bad = $this->get('/api/stats', $base + ['from' => "2026-11-01' OR 1=1", 'to' => '2026-02-30'], $auth);
        $this->assertSame(200, $bad->status);
        $this->assertSame('2026-11-15', $bad->data()['range']['to']);
    }
}
