<?php

declare(strict_types=1);

namespace Pulso;

final class Config
{
    /** @param array<string, mixed> $values */
    private function __construct(private readonly array $values)
    {
    }

    public static function load(string $path): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Missing config file: {$path}. Copy config.example.php to config.php.");
        }
        $values = require $path;
        if (!is_array($values)) {
            throw new \RuntimeException('config.php must return an array');
        }
        return new self($values);
    }

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): self
    {
        return new self($values);
    }

    public function dsn(): string
    {
        return (string) ($this->values['db']['dsn'] ?? '');
    }

    public function dbUser(): ?string
    {
        return $this->values['db']['user'] ?? null;
    }

    public function dbPassword(): ?string
    {
        return $this->values['db']['password'] ?? null;
    }

    public function adminToken(): string
    {
        return (string) ($this->values['admin_token'] ?? '');
    }

    public function trustProxy(): bool
    {
        return (bool) ($this->values['trust_proxy'] ?? false);
    }
}
