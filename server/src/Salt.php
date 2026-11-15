<?php

declare(strict_types=1);

namespace Pulso;

use PDO;

/**
 * How we count unique visitors without cookies (same idea as Plausible/Umami):
 *
 *   visitor_id = hash(daily_salt + site + ip + user_agent)
 *
 * The salt is random, changes every day and yesterday's is deleted. So the IP is
 * never stored, and once the salt is gone nobody (not even us) can link a visitor
 * id back to an IP or to the same person on another day. That's the whole point.
 */
final class Salt
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function forDay(\DateTimeImmutable $nowUtc): string
    {
        $day = $nowUtc->format('Y-m-d');
        $salt = $this->find($day);
        if ($salt !== null) {
            return $salt;
        }

        try {
            $insert = $this->pdo->prepare('INSERT INTO pulso_salts (day, salt) VALUES (?, ?)');
            $insert->execute([$day, bin2hex(random_bytes(32))]);
        } catch (\PDOException) {
            // Another request created it first, fine, read theirs.
        }

        // Keep yesterday for sessions that cross midnight, forget everything older.
        $cutoff = $nowUtc->modify('-1 day')->format('Y-m-d');
        $this->pdo->prepare('DELETE FROM pulso_salts WHERE day < ?')->execute([$cutoff]);

        return (string) $this->find($day);
    }

    private function find(string $day): ?string
    {
        $stmt = $this->pdo->prepare('SELECT salt FROM pulso_salts WHERE day = ?');
        $stmt->execute([$day]);
        $salt = $stmt->fetchColumn();
        return $salt === false ? null : (string) $salt;
    }
}
