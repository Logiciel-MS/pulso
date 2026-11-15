<?php

declare(strict_types=1);

namespace Pulso\Tests;

use Pulso\Sites;

final class SitesTest extends TestCase
{
    public function testCreatesSiteAndOnlyStoresKeyHash(): void
    {
        $pdo = $this->db();
        $sites = new Sites($pdo);
        $created = $sites->create('Grado Zero', 'https://www.GradoZero.co/');

        $this->assertTrue((bool) preg_match('/^gradoz_[a-f0-9]{10}$/', $created['public_id']), $created['public_id']);
        $this->assertTrue(str_starts_with($created['api_key'], 'pk_'));

        $site = $sites->find($created['public_id']);
        $this->assertSame('gradozero.co', $site['domain']);
        $this->assertSame('America/Bogota', $site['timezone']);

        $stored = $pdo->query('SELECT api_key_hash FROM pulso_sites')->fetchColumn();
        $this->assertFalse(str_contains((string) $stored, $created['api_key']), 'raw key must not be stored');
        $this->assertTrue($sites->verifyKey($created['public_id'], $created['api_key']));
        $this->assertFalse($sites->verifyKey($created['public_id'], 'pk_wrong'));
        $this->assertFalse($sites->verifyKey($created['public_id'], ''));
    }

    public function testRejectsBadInput(): void
    {
        $sites = new Sites($this->db());
        $this->assertThrows(\InvalidArgumentException::class, fn () => $sites->create('X', 'not a domain'));
        $this->assertThrows(\Exception::class, fn () => $sites->create('X', 'cliente.co', 'Mars/Olympus'));
    }

    public function testHostBelongs(): void
    {
        $this->assertTrue(Sites::hostBelongs('cliente.co', 'cliente.co'));
        $this->assertTrue(Sites::hostBelongs('www.cliente.co', 'cliente.co'));
        $this->assertTrue(Sites::hostBelongs('tienda.cliente.co', 'cliente.co'));
        $this->assertFalse(Sites::hostBelongs('falsocliente.co', 'cliente.co'));
        $this->assertFalse(Sites::hostBelongs('cliente.co.evil.com', 'cliente.co'));
    }
}
