<?php

declare(strict_types=1);

namespace Pulso;

use PDO;

/**
 * @phpstan-type Site array{id: int, public_id: string, name: string, domain: string, timezone: string}
 */
final class Sites
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array{public_id: string, api_key: string}
     */
    public function create(string $name, string $domain, string $timezone = 'America/Bogota'): array
    {
        $domain = self::normalizeDomain($domain);
        if ($domain === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
            throw new \InvalidArgumentException("Invalid domain: {$domain}");
        }
        new \DateTimeZone($timezone); // throws if invalid

        $prefix = substr((string) preg_replace('/[^a-z0-9]/', '', strtolower($name)), 0, 6) ?: 'site';
        $publicId = $prefix . '_' . bin2hex(random_bytes(5));
        $apiKey = 'pk_' . bin2hex(random_bytes(24));

        $stmt = $this->pdo->prepare(
            'INSERT INTO pulso_sites (public_id, name, domain, timezone, api_key_hash, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        );
        $stmt->execute([
            $publicId,
            $name,
            $domain,
            $timezone,
            hash('sha256', $apiKey),
            gmdate('Y-m-d H:i:s'),
        ]);

        // The API key is shown once and only its hash is stored, like a password.
        return ['public_id' => $publicId, 'api_key' => $apiKey];
    }

    /** @return Site|null */
    public function find(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, public_id, name, domain, timezone FROM pulso_sites WHERE public_id = ?',
        );
        $stmt->execute([$publicId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        return $row;
    }

    public function verifyKey(string $publicId, string $apiKey): bool
    {
        $stmt = $this->pdo->prepare('SELECT api_key_hash FROM pulso_sites WHERE public_id = ?');
        $stmt->execute([$publicId]);
        $hash = $stmt->fetchColumn();
        return is_string($hash) && $apiKey !== '' && hash_equals($hash, hash('sha256', $apiKey));
    }

    /** @return list<Site> */
    public function all(): array
    {
        $rows = $this->pdo->query('SELECT id, public_id, name, domain, timezone FROM pulso_sites ORDER BY name')
            ->fetchAll();
        return array_map(static fn (array $r) => ['id' => (int) $r['id']] + $r, $rows);
    }

    public static function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = (string) preg_replace('#^https?://#', '', $domain);
        $domain = explode('/', $domain)[0];
        return Referrer::stripWww($domain);
    }

    /** cliente.co matches cliente.co, www.cliente.co and tienda.cliente.co. */
    public static function hostBelongs(string $host, string $domain): bool
    {
        $host = Referrer::stripWww(strtolower($host));
        return $host === $domain || str_ends_with($host, '.' . $domain);
    }
}
