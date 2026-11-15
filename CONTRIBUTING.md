# Contributing

¡Bienvenido! Issues and PRs in English or Spanish are both fine.

## Setup

```bash
nvm use            # Node 22
npm install
npm test           # tracker
php server/tests/run.php   # server, SQLite in memory
```

To run the server tests against MySQL/MariaDB:

```bash
PULSO_TEST_DSN="mysql:host=127.0.0.1;dbname=pulso_test;charset=utf8mb4" \
PULSO_TEST_USER=root PULSO_TEST_PASSWORD=secret php server/tests/run.php
```

## Rules of the house

- **Privacy first.** No cookies, no IPs, no query strings, no form values, no fingerprinting beyond the daily-salted hash. If a feature needs personal data, it doesn't go in.
- **The tracker stays tiny** (~2 KB gzipped) and must never break a website: every browser API call is guarded.
- **The server stays boring:** PHP 8.1+, plain PDO, SQL that runs on MySQL, MariaDB and SQLite. No framework, no Composer dependencies at runtime (it has to run on shared hosting).
- **Commits** follow [Conventional Commits](https://www.conventionalcommits.org/).
- Add a line under `Unreleased` in `CHANGELOG.md`.

## Licenses

By contributing you agree that your contribution to `packages/tracker` is MIT and to `server` is AGPL-3.0-only.
