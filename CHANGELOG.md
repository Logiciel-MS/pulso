# Changelog

All notable changes to this project are documented here.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versions follow [SemVer](https://semver.org/).

## [Unreleased]

## [0.1.0] - 2026-11-15

### Added

- `pulso-analytics` tracker: cookieless page views (SPA aware), time on page, automatic WhatsApp / phone / email / outbound / download / form events, custom events, `<Pulso />` React component and a `<script>` build. Respects Do Not Track, skips bots and localhost.
- PHP server: `/collect` endpoint, daily-salted visitor ids (no IPs stored), sessions, sources and UTM campaigns, devices and browsers (including Instagram/Facebook/TikTok in-app browsers).
- Stats API: visitors, sessions, bounce rate, time on page, entry and exit pages, sources, campaigns, devices, events and realtime visitors. Per-site API keys plus an admin token.
- CLI: `bin/migrate.php`, `bin/site-add.php`, `bin/sites.php`.

[Unreleased]: https://github.com/Logiciel-MS/pulso/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/Logiciel-MS/pulso/releases/tag/v0.1.0
