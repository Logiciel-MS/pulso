# pulso-analytics

[![npm](https://img.shields.io/npm/v/pulso-analytics)](https://www.npmjs.com/package/pulso-analytics)
[![license](https://img.shields.io/npm/l/pulso-analytics)](./LICENSE)

The tracker for [Pulso](https://github.com/Logiciel-MS/pulso), privacy-friendly web analytics you host yourself. ~2 KB gzipped, no cookies, no dependencies.

> **En español:** el script de analítica de Pulso para React, Next.js y Vite. Cuenta visitas (también en navegación SPA), tiempo en página, y automáticamente los clics a WhatsApp, llamadas, correos, formularios y descargas. Sin cookies.

```bash
npm install pulso-analytics
```

## React / Next.js / Vite

```tsx
import { Pulso } from 'pulso-analytics/react';

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="es">
      <body>
        {children}
        <Pulso siteId="gradoz_ce17cf7de0" endpoint="https://analytics.logicielms.com/collect" />
      </body>
    </html>
  );
}
```

`<Pulso />` renders nothing, works in Next.js App Router layouts (it's marked `'use client'`), and follows client-side navigation (Next.js, React Router, TanStack Router) on its own.

## Without React

```ts
import { init } from 'pulso-analytics';

init({ siteId: 'gradoz_ce17cf7de0', endpoint: 'https://analytics.logicielms.com/collect' });
```

Or with a script tag (WordPress, plain HTML). The file is served by your Pulso server:

```html
<script defer src="https://analytics.logicielms.com/pulso.js" data-site="gradoz_ce17cf7de0"></script>
```

## What's tracked automatically

| Event            | When                                                              | Props                                                        |
| ---------------- | ----------------------------------------------------------------- | ------------------------------------------------------------ |
| page view        | first load and every client-side navigation                       | –                                                            |
| time on page     | when leaving the page / hiding the tab (only visible time counts) | –                                                            |
| `whatsapp_click` | links to `wa.me`, `api.whatsapp.com`, `whatsapp://`               | –                                                            |
| `phone_click`    | `tel:` links                                                      | – (never the number)                                         |
| `email_click`    | `mailto:` links                                                   | – (never the address)                                        |
| `form_submit`    | any form submission                                               | `form`: `data-pulso-form`, `id` or `name` (never the values) |
| `file_download`  | links to PDF, ZIP, DOCX, XLSX...                                  | `ext`                                                        |
| `outbound_click` | links to other sites                                              | `host`                                                       |

## Custom events

```ts
import { track } from 'pulso-analytics';

track('agregar_al_carrito', { producto: 'Camisa', valor: 120000 });
```

Or without JavaScript:

```html
<button data-pulso-event="cotizar" data-pulso-plan="pro">Cotizar</button>
<!-- -> track('cotizar', { plan: 'pro' }) -->
```

Props: up to 10, strings (max 256 chars), numbers or booleans.

## Options

| Option              | Default |                                                                            |
| ------------------- | ------- | -------------------------------------------------------------------------- |
| `siteId`            | –       | From `php bin/site-add.php` on your server                                 |
| `endpoint`          | –       | `https://your-pulso-server/collect`                                        |
| `autoPageviews`     | `true`  | Set `false` and call `pageview()` yourself                                 |
| `autoEvents`        | `true`  | `false`, or pick: `{ whatsapp, phone, email, outbound, downloads, forms }` |
| `respectDoNotTrack` | `true`  | Honors Do Not Track and Global Privacy Control                             |
| `trackLocalhost`    | `false` | Your dev sessions don't pollute the stats                                  |
| `debug`             | `false` | Logs every payload to the console                                          |

## Privacy

No cookies, no localStorage, no fingerprinting. The tracker sends the page URL, referrer, screen width, language and timezone. The server drops the query string and never stores IPs. Visitors with Do Not Track, bots and automated browsers are ignored.

## License

MIT © [Logiciel MS](https://logicielms.com)
