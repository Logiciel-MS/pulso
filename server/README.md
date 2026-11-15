# Pulso server

Recibe los eventos del tracker y entrega las estadísticas. PHP 8.1+ y MySQL 5.7+ / MariaDB 10.3+, **sin dependencias**: funciona en hosting compartido (Hostinger, cPanel).

## Instalación en Hostinger (hosting compartido)

1. **Base de datos.** hPanel → _Bases de datos → MySQL_: crea una base y un usuario. Anota el nombre, el usuario y la contraseña.
2. **Subdominio.** hPanel → _Dominios → Subdominios_: crea `analytics.tudominio.com` con **carpeta personalizada** `pulso/public`.
3. **Sube los archivos.** Descarga `pulso-server-vX.Y.Z.zip` del [último release](https://github.com/Logiciel-MS/pulso/releases) y descomprímelo en `public_html/pulso/` (el `pulso.js` ya viene incluido). Lo importante: el subdominio apunta a `public/`, así `config.php`, `src/` y `bin/` nunca quedan expuestos.
4. **Configura.** Copia `config.example.php` a `config.php` y llena los datos de la base. Genera el token de administrador:
   ```bash
   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```
5. **Crea las tablas** por SSH (hPanel → _Avanzado → Acceso SSH_):
   ```bash
   cd domains/tudominio.com/public_html/pulso
   php bin/migrate.php
   ```
   ¿Sin SSH? Importa `database/schema.mysql.sql` desde phpMyAdmin.
6. **Registra cada cliente:**
   ```bash
   php bin/site-add.php "Grado Zero" gradozero.co
   ```
   Te da el `siteId` (va en el código del sitio) y la **API key** (para leer sus estadísticas; se muestra una sola vez).
7. **Comprueba:** `https://analytics.tudominio.com/health` → `{"ok":true,"name":"pulso"}`.

Activa SSL en el subdominio (hPanel → _Seguridad → SSL_): los navegadores no envían datos de un sitio HTTPS a un servidor HTTP.

## API

Todas las respuestas son JSON con CORS abierto.

| Ruta                                    | Auth                            | Qué hace                                                                                               |
| --------------------------------------- | ------------------------------- | ------------------------------------------------------------------------------------------------------ |
| `POST /collect`                         | –                               | Recibe eventos del tracker                                                                             |
| `GET /api/stats?site=&from=&to=&limit=` | API key del sitio o token admin | Reporte del rango (fechas `YYYY-MM-DD`, en la zona horaria del sitio; por defecto los últimos 30 días) |
| `GET /api/sites`                        | token admin                     | Todos los sitios                                                                                       |
| `GET /health`                           | –                               | Para monitores de disponibilidad                                                                       |

Auth: `Authorization: Bearer <key>`, o `X-Pulso-Key: <key>` si tu hosting se come el header `Authorization`.

`/api/stats` devuelve `summary` (visitantes, sesiones, páginas vistas, rebote, duración media), `realtime_visitors`, `timeseries`, `pages` (con tiempo promedio), `entry_pages`, `exit_pages`, `sources`, `campaigns`, `devices`, `browsers`, `os`, `countries` y `events` (WhatsApp, formularios, etc.).

## Cómo cuenta

- **Visitante único:** `hash(sal diaria + sitio + IP + navegador)`. La sal es aleatoria, cambia cada día y la del día anterior se borra. La IP nunca se guarda. Por eso, en rangos de varios días, una persona que vuelve otro día cuenta como otro visitante (igual que Plausible).
- **Sesión:** el mismo visitante con menos de 30 minutos de inactividad.
- **Rebote:** sesión con una sola página vista y ningún evento (un clic a WhatsApp no es rebote).
- **Tiempo en página:** solo el tiempo con la pestaña visible.
- **Fuente:** la de la primera página de la sesión; `utm_source` gana sobre el referrer.
- **País:** solo si el servidor está detrás de Cloudflare (`trust_proxy => true`).

## Desarrollo

```bash
php tests/run.php                       # SQLite en memoria
PULSO_TEST_DSN="mysql:host=127.0.0.1;dbname=pulso_test;charset=utf8mb4" \
PULSO_TEST_USER=root PULSO_TEST_PASSWORD=secret php tests/run.php
php -S localhost:8080 -t public public/index.php   # servidor local
```

## Licencia

[AGPL-3.0-only](./LICENSE) © Logiciel MS. Puedes usarlo, modificarlo y alojarlo gratis; si ofreces una versión modificada como servicio, debes publicar tus cambios.
