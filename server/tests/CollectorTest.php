<?php

declare(strict_types=1);

namespace Pulso\Tests;

use PDO;
use Pulso\Collector;
use Pulso\Sites;

require_once __DIR__ . '/Fixtures.php';

final class CollectorTest extends TestCase
{
    private PDO $pdo;
    private Collector $collector;
    private string $siteId;

    public function setUp(): void
    {
        $this->pdo = $this->db();
        $this->collector = new Collector($this->pdo);
        $this->siteId = (new Sites($this->pdo))->create('Cliente', 'cliente.co')['public_id'];
    }

    /** @param array<string, mixed> $extra */
    private function send(array $extra = [], array $ctx = [], string $time = '2026-11-15 15:00:00'): array
    {
        $payload = $extra + ['v' => 1, 's' => $this->siteId, 'e' => 'pageview', 'u' => 'https://cliente.co/', 'i' => 'pv1'];
        $context = $ctx + ['ip' => '181.50.10.20', 'ua' => Fixtures::ANDROID_CHROME, 'origin' => 'https://cliente.co'];
        return $this->collector->collect($payload, $context, Fixtures::utc($time));
    }

    /** @return list<array<string, mixed>> */
    private function rows(): array
    {
        return $this->pdo->query('SELECT * FROM pulso_events ORDER BY id')->fetchAll();
    }

    public function testStoresAPageview(): void
    {
        $result = $this->send([
            'u' => 'https://www.cliente.co/productos/?utm_source=ig&utm_medium=social&utm_campaign=bf2026&email=a@b.co#x',
            'r' => 'https://l.instagram.com/?u=x',
            'w' => 412,
            'l' => 'es-CO',
            'tz' => 'America/Bogota',
        ]);
        $this->assertSame(['ok' => true, 'status' => 202], $result);

        $row = $this->rows()[0];
        $this->assertSame('pageview', $row['type']);
        $this->assertSame('/productos', $row['path'], 'query string and hash dropped, trailing slash removed');
        $this->assertSame('Instagram', $row['source']);
        $this->assertSame('ig', $row['utm_source']);
        $this->assertSame('social', $row['utm_medium']);
        $this->assertSame('bf2026', $row['utm_campaign']);
        $this->assertSame('l.instagram.com', $row['referrer_host']);
        $this->assertSame('mobile', $row['device']);
        $this->assertSame('Chrome', $row['browser']);
        $this->assertSame('Android', $row['os']);
        $this->assertSame(412, (int) $row['screen_w']);
        $this->assertSame('es-CO', $row['lang']);
        $this->assertSame('2026-11-15', substr((string) $row['local_date'], 0, 10));
        $this->assertSame(16, strlen($row['visitor_id']));
        $this->assertSame(16, strlen($row['session_id']));
    }

    public function testNeverStoresTheIpOrQueryString(): void
    {
        $this->send(['u' => 'https://cliente.co/gracias?email=secreto@correo.com&token=abc']);
        $dump = json_encode($this->rows());
        $this->assertFalse(str_contains($dump, '181.50.10.20'), 'IP stored');
        $this->assertFalse(str_contains($dump, 'secreto'), 'query string stored');
        $this->assertFalse(str_contains($dump, 'token'), 'query string stored');
    }

    public function testLocalDateUsesTheSiteTimezone(): void
    {
        // 02:00 UTC on the 16th is still 21:00 of the 15th in Bogotá
        $this->send([], [], '2026-11-16 02:00:00');
        $this->assertSame('2026-11-15', substr((string) $this->rows()[0]['local_date'], 0, 10));
    }

    public function testSameVisitorWithin30MinutesIsOneSession(): void
    {
        $this->send(['u' => 'https://cliente.co/'], [], '2026-11-15 15:00:00');
        $this->send(['u' => 'https://cliente.co/productos', 'i' => 'pv2'], [], '2026-11-15 15:20:00');
        $this->send(['u' => 'https://cliente.co/contacto', 'i' => 'pv3'], [], '2026-11-15 15:45:00');
        $rows = $this->rows();
        $this->assertSame($rows[0]['session_id'], $rows[1]['session_id']);
        $this->assertSame($rows[1]['session_id'], $rows[2]['session_id'], '25 min after the last activity, still the same visit');
    }

    public function testInactivityStartsANewSession(): void
    {
        $this->send([], [], '2026-11-15 15:00:00');
        $this->send(['u' => 'https://cliente.co/blog'], [], '2026-11-15 15:31:00');
        $rows = $this->rows();
        $this->assertSame($rows[0]['visitor_id'], $rows[1]['visitor_id']);
        $this->assertFalse($rows[0]['session_id'] === $rows[1]['session_id']);
    }

    public function testDifferentPeopleAreDifferentVisitors(): void
    {
        $this->send();
        $this->send([], ['ip' => '190.85.1.1']);
        $this->send([], ['ua' => Fixtures::IPHONE_INSTAGRAM]);
        $ids = array_unique(array_column($this->rows(), 'visitor_id'));
        $this->assertSame(3, count($ids));
    }

    public function testVisitorIdChangesEveryDayAndOldSaltsAreDeleted(): void
    {
        $this->send([], [], '2026-11-15 15:00:00');
        $this->send([], [], '2026-11-16 15:00:00');
        $this->send([], [], '2026-11-17 15:00:00');
        $rows = $this->rows();
        $this->assertFalse($rows[0]['visitor_id'] === $rows[1]['visitor_id'], 'same person, new day, new id');
        $days = $this->pdo->query('SELECT day FROM pulso_salts ORDER BY day')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['2026-11-16', '2026-11-17'], $days, 'salts older than yesterday are gone');
    }

    public function testInternalReferrerIsNotASource(): void
    {
        $this->send(['r' => 'https://www.cliente.co/otra-pagina']);
        $row = $this->rows()[0];
        $this->assertSame(null, $row['referrer_host']);
        $this->assertSame('Directo', $row['source']);
    }

    public function testStoresEventsWithCleanProps(): void
    {
        $result = $this->send([
            'e' => 'event',
            'n' => 'agregar_al_carrito',
            'p' => ['valor' => 120000, 'producto' => 'Camisa', 'nested' => ['x' => 1]],
        ]);
        $this->assertSame(true, $result['ok']);
        $row = $this->rows()[0];
        $this->assertSame('agregar_al_carrito', $row['name']);
        $this->assertSame(['valor' => 120000, 'producto' => 'Camisa'], json_decode($row['props'], true));
        $this->assertSame(null, $row['source'], 'only page views carry the source');
    }

    public function testStoresEngagementCapped(): void
    {
        $this->send(['e' => 'engagement', 'd' => 99_999_999]);
        $this->assertSame(1_800_000, (int) $this->rows()[0]['duration_ms']);
    }

    public function testRejectsGarbage(): void
    {
        $cases = [
            [['v' => 2], 400],
            [['e' => 'hack'], 400],
            [['u' => 'javascript:alert(1)'], 400],
            [['u' => str_repeat('a', 3000)], 400],
            [['s' => 'no_existe'], 404],
            [['e' => 'event'], 400],
            [['e' => 'engagement', 'd' => -5], 400],
            [['e' => 'engagement', 'd' => '100'], 400],
        ];
        foreach ($cases as [$extra, $status]) {
            $this->assertSame($status, $this->send($extra)['status'], json_encode($extra));
        }
        $this->assertSame([], $this->rows());
    }

    public function testRejectsOtherDomains(): void
    {
        $this->assertSame(403, $this->send(['u' => 'https://otrositio.com/'])['status']);
        $this->assertSame(403, $this->send([], ['origin' => 'https://otrositio.com'])['status']);
        $this->assertSame(202, $this->send(['u' => 'https://tienda.cliente.co/'], ['origin' => 'https://tienda.cliente.co'])['status']);
    }

    public function testIgnoresBotsQuietly(): void
    {
        $result = $this->send([], ['ua' => Fixtures::GOOGLEBOT]);
        $this->assertSame(202, $result['status']);
        $this->assertSame([], $this->rows());
    }

    public function testCountryOnlyWhenValid(): void
    {
        $this->send([], ['country' => 'CO']);
        $this->send([], ['country' => 'XX']);
        $this->send([], ['country' => '<script>']);
        $this->assertSame(['CO', null, null], array_column($this->rows(), 'country'));
    }
}
