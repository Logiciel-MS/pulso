# Pulso

**Privacy-friendly web analytics you host yourself.** Built by [Logiciel MS](https://logicielms.com) to know how people move through our clients' websites: where they come from, where they land, where they leave, and whether they ended up writing on WhatsApp.

> **En español:** Pulso es nuestra analítica web propia, sin cookies. Instalas un componente en el sitio del cliente (React, Next.js, Vite o una etiqueta `<script>`), los datos llegan a tu servidor PHP + MySQL (funciona en hosting compartido como Hostinger) y consultas visitas, páginas de entrada y salida, fuentes de tráfico, campañas y clics a WhatsApp, llamadas y formularios. Guía de instalación del servidor en español: [server/README.md](./server/README.md).

## What you get

- **Visitors, sessions, page views, bounce rate and real time on page** (only while the tab is visible).
- **Entry and exit pages:** where people land and where they leave.
- **Sources and campaigns:** Google, Instagram, Facebook, TikTok, ChatGPT... and your `utm_*` links.
- **Conversions out of the box:** clicks to **WhatsApp**, phone and email, form submissions, downloads and outbound links. No setup.
- **Custom events:** `track('agregar_al_carrito', { valor: 120000 })` or `data-pulso-event="cotizar"` in the HTML.
- **Devices and browsers,** including Instagram, Facebook and TikTok in-app browsers.
- **One server, many sites:** each client gets its own API key, you get an admin token to see them all.

## Privacy by design

- **No cookies, no localStorage, no fingerprinting.** No consent banner needed for Pulso itself.
- **IPs are never stored.** Unique visitors are counted with `hash(daily random salt + site + IP + user agent)`; the salt is deleted after a day, so nobody can link a visitor back to an IP or across days.
- **No query strings** (they often carry emails and tokens), **no form values**, no phone numbers from `tel:` links.
- Respects **Do Not Track / Global Privacy Control**, ignores bots and localhost.

Mention the analytics in each site's privacy policy anyway; in Colombia that's what Ley 1581 de 2012 expects. (We're not lawyers.)

## How it fits together

```
client's website                      your hosting (PHP + MySQL)
┌──────────────────────┐   beacon    ┌──────────────────────────┐
│ <Pulso siteId=... /> │ ──────────► │ POST /collect            │
│ pulso-analytics 2 KB │             │ pulso_events (MySQL)     │
└──────────────────────┘             │ GET /api/stats ◄── you   │
                                     └──────────────────────────┘
```

| Folder                                   | What                                                                          | License  |
| ---------------------------------------- | ----------------------------------------------------------------------------- | -------- |
| [`packages/tracker`](./packages/tracker) | `pulso-analytics` on npm: the 2 KB tracker, React component, `<script>` build | MIT      |
| [`server`](./server)                     | Collector, stats API, CLI. PHP 8.1+, MySQL/MariaDB, zero dependencies         | AGPL-3.0 |

## Quick start

**1. Server** (once): upload `server/`, create `config.php`, run `php bin/migrate.php`, then for each client:

```bash
php bin/site-add.php "Grado Zero" gradozero.co
#  siteId:  gradoz_ce17cf7de0
#  API key: pk_...   (shown once)
```

Step-by-step for Hostinger: [server/README.md](./server/README.md).

**2. Client site:**

```bash
npm install pulso-analytics
```

```tsx
// Next.js: app/layout.tsx  |  Vite: src/main.tsx or App.tsx
import { Pulso } from 'pulso-analytics/react';

<Pulso siteId="gradoz_ce17cf7de0" endpoint="https://analytics.logicielms.com/collect" />;
```

**3. Numbers:**

```bash
curl "https://analytics.logicielms.com/api/stats?site=gradoz_ce17cf7de0&from=2026-11-01&to=2026-11-30" \
  -H "Authorization: Bearer pk_..."
```

More in the [tracker README](./packages/tracker/README.md) and the [server README](./server/README.md).

## Roadmap

- [ ] Dashboard (agency view with all sites + client view)
- [ ] Monthly PDF report per client, by email
- [ ] Uptime monitoring and alerts
- [ ] Core Web Vitals per page
- [ ] Funnels and goals
- [ ] Daily aggregates for very large sites

## License

Open core: the tracker is [MIT](./packages/tracker/LICENSE), the server is [AGPL-3.0](./server/LICENSE). See [LICENSE](./LICENSE).

© 2026 [Logiciel MS](https://logicielms.com)
