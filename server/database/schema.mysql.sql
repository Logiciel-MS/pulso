-- Pulso schema for MySQL 5.7+ / MariaDB 10.3+. Tables are prefixed so they can
-- share a database with something else (shared hosting gives you few databases).

CREATE TABLE IF NOT EXISTS pulso_sites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL,
    name VARCHAR(120) NOT NULL,
    domain VARCHAR(255) NOT NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'America/Bogota',
    api_key_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_public_id (public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pulso_salts (
    day CHAR(10) NOT NULL PRIMARY KEY,
    salt CHAR(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pulso_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT UNSIGNED NOT NULL,
    type VARCHAR(12) NOT NULL,
    session_id CHAR(16) NOT NULL,
    visitor_id CHAR(16) NOT NULL,
    pageview_id VARCHAR(16) NOT NULL DEFAULT '',
    path VARCHAR(512) NOT NULL,
    name VARCHAR(64) NULL,
    props TEXT NULL,
    duration_ms INT UNSIGNED NULL,
    referrer_host VARCHAR(255) NULL,
    source VARCHAR(64) NULL,
    utm_source VARCHAR(64) NULL,
    utm_medium VARCHAR(64) NULL,
    utm_campaign VARCHAR(64) NULL,
    device VARCHAR(16) NULL,
    browser VARCHAR(32) NULL,
    os VARCHAR(32) NULL,
    screen_w SMALLINT UNSIGNED NULL,
    lang VARCHAR(16) NULL,
    tz VARCHAR(64) NULL,
    country CHAR(2) NULL,
    local_date DATE NOT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_site_date (site_id, local_date),
    KEY idx_site_visitor (site_id, visitor_id, created_at),
    KEY idx_site_session (site_id, session_id),
    KEY idx_site_pageview (site_id, pageview_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
