<?php

declare(strict_types=1);

namespace Pulso;

final class Request
{
    /**
     * @param array<string, string> $query
     * @param array<string, string> $headers lowercase names
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $headers = [],
        public readonly string $body = '',
        public readonly string $ip = '',
    ) {
    }

    public static function fromGlobals(bool $trustProxy): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }
        // Apache on shared hosting likes to eat the Authorization header; .htaccess passes it back here.
        if (!isset($headers['authorization']) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers['authorization'] = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($trustProxy) {
            $forwarded = $headers['cf-connecting-ip'] ?? explode(',', $headers['x-forwarded-for'] ?? '')[0];
            if (filter_var(trim($forwarded), FILTER_VALIDATE_IP)) {
                $ip = trim($forwarded);
            }
        }

        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        // Works whether Pulso lives at the domain root or in a subfolder (/pulso/collect)
        $base = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            '/' . trim($path, '/'),
            array_map('strval', array_filter($_GET, 'is_scalar')),
            $headers,
            (string) file_get_contents('php://input', false, null, 0, 8192),
            $ip,
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** 'Authorization: Bearer xxx' or 'X-Pulso-Key: xxx' (some hosts strip Authorization). */
    public function token(): string
    {
        $auth = $this->header('authorization') ?? '';
        if (preg_match('/^Bearer\s+(\S+)$/i', $auth, $m)) {
            return $m[1];
        }
        return trim($this->header('x-pulso-key') ?? '');
    }
}
