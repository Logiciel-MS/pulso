CREATE TABLE IF NOT EXISTS pulso_sites (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    domain TEXT NOT NULL,
    timezone TEXT NOT NULL DEFAULT 'America/Bogota',
    api_key_hash TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS pulso_salts (
    day TEXT NOT NULL PRIMARY KEY,
    salt TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS pulso_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    site_id INTEGER NOT NULL,
    type TEXT NOT NULL,
    session_id TEXT NOT NULL,
    visitor_id TEXT NOT NULL,
    pageview_id TEXT NOT NULL DEFAULT '',
    path TEXT NOT NULL,
    name TEXT NULL,
    props TEXT NULL,
    duration_ms INTEGER NULL,
    referrer_host TEXT NULL,
    source TEXT NULL,
    utm_source TEXT NULL,
    utm_medium TEXT NULL,
    utm_campaign TEXT NULL,
    device TEXT NULL,
    browser TEXT NULL,
    os TEXT NULL,
    screen_w INTEGER NULL,
    lang TEXT NULL,
    tz TEXT NULL,
    country TEXT NULL,
    local_date TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_site_date ON pulso_events (site_id, local_date);
CREATE INDEX IF NOT EXISTS idx_site_visitor ON pulso_events (site_id, visitor_id, created_at);
CREATE INDEX IF NOT EXISTS idx_site_session ON pulso_events (site_id, session_id);
CREATE INDEX IF NOT EXISTS idx_site_pageview ON pulso_events (site_id, pageview_id)
