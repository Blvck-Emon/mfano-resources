-- ============================================================================
-- Mfano Bora Resources Portal - Database Schema
-- Refactored: PostgreSQL -> SQLite
-- ============================================================================
-- Translation notes (Postgres -> SQLite):
--   SERIAL PRIMARY KEY           -> INTEGER PRIMARY KEY AUTOINCREMENT
--   TIMESTAMP WITH TIME ZONE     -> TEXT (ISO-8601), default via STRFTIME
--   BOOLEAN DEFAULT FALSE        -> INTEGER (0/1) with CHECK constraint
--   ON DELETE CASCADE            -> unchanged, requires PRAGMA foreign_keys=ON
--   uuid-ossp extension           -> dropped (unused; ids are AUTOINCREMENT)
--   GIN/tsvector full-text index -> FTS5 virtual table (resources_fts) below
--   RETURNING *                   -> still supported by modern SQLite (3.35+),
--                                    kept in api/*.php but with a lastInsertId
--                                    fallback for portability across PHP builds
-- ============================================================================

PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;

-- --------------------------------------------------------------------------
-- Application settings / key-value store (for admin_api_key, feature flags)
-- Simple key/value store for small application secrets and flags[cite: 16].
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL UNIQUE,
    value      TEXT,
    created_at TEXT NOT NULL DEFAULT (STRFTIME('%Y-%m-%dT%H:%M:%fZ', 'now')),
    updated_at TEXT NOT NULL DEFAULT (STRFTIME('%Y-%m-%dT%H:%M:%fZ', 'now'))
);

-- 1. Categories Table
CREATE TABLE IF NOT EXISTS categories (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL UNIQUE,
    slug        TEXT NOT NULL UNIQUE,
    description TEXT,
    created_at  TEXT NOT NULL DEFAULT (STRFTIME('%Y-%m-%dT%H:%M:%fZ', 'now'))
);

-- 2. Sub-Categories Table
CREATE TABLE IF NOT EXISTS sub_categories (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
    name        TEXT NOT NULL,
    slug        TEXT NOT NULL UNIQUE,
    description TEXT,
    created_at  TEXT NOT NULL DEFAULT (STRFTIME('%Y-%m-%dT%H:%M:%fZ', 'now'))
);

-- 3. Resources Table (Core Asset Store)
-- Added vs. the Postgres version: storage_type + stored_path + checksum,
-- so the admin panel can upload a PDF straight into this backend instead
-- of only accepting an already-hosted external URL.
CREATE TABLE IF NOT EXISTS resources (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    sub_category_id  INTEGER NOT NULL REFERENCES sub_categories(id) ON DELETE CASCADE,
    title            TEXT NOT NULL,
    description      TEXT NOT NULL,
    file_url         TEXT NOT NULL,          -- public URL: external link OR /uploads/resources/<file>
    storage_type     TEXT NOT NULL DEFAULT 'external'
                        CHECK (storage_type IN ('external', 'local')),
    stored_path      TEXT,                   -- server-relative path when storage_type = 'local'
    checksum_sha256  TEXT,                   -- set for local uploads; de-dupes re-uploads
    file_size_kb     INTEGER DEFAULT 0,
    download_count   INTEGER DEFAULT 0,      -- kept in sync by trigger below
    is_featured      INTEGER DEFAULT 0 CHECK (is_featured IN (0,1)),
    is_published     INTEGER DEFAULT 1 CHECK (is_published IN (0,1)),
    publish_date     TEXT DEFAULT (DATE('now')),
    created_at       TEXT NOT NULL DEFAULT (STRFTIME('%Y-%m-%dT%H:%M:%fZ', 'now')),
    updated_at       TEXT NOT NULL DEFAULT (STRFTIME('%Y-%m-%dT%H:%M:%fZ', 'now'))
);

-- Indexes for fast filtering/searching
CREATE INDEX IF NOT EXISTS idx_sub_categories_category ON sub_categories(category_id);
CREATE INDEX IF NOT EXISTS idx_resources_sub_category  ON resources(sub_category_id);
CREATE INDEX IF NOT EXISTS idx_resources_published     ON resources(is_published);
CREATE INDEX IF NOT EXISTS idx_resources_featured      ON resources(is_featured);
CREATE INDEX IF NOT EXISTS idx_resources_checksum      ON resources(checksum_sha256);

-- updated_at auto-refresh (Postgres relied on an ON UPDATE trigger function;
-- SQLite needs an explicit AFTER UPDATE trigger to reproduce that behaviour)
CREATE TRIGGER IF NOT EXISTS trg_resources_updated_at
AFTER UPDATE ON resources
FOR EACH ROW
BEGIN
    UPDATE resources SET updated_at = STRFTIME('%Y-%m-%dT%H:%M:%fZ', 'now') WHERE id = OLD.id;
END;

-- Full-text search replacement for Postgres' tsvector/GIN index.
-- Mirrors title + description into an FTS5 index kept current by triggers.
CREATE VIRTUAL TABLE IF NOT EXISTS resources_fts USING fts5(
    title, description, content='resources', content_rowid='id'
);

CREATE TRIGGER IF NOT EXISTS trg_resources_fts_insert
AFTER INSERT ON resources
BEGIN
    INSERT INTO resources_fts(rowid, title, description) VALUES (new.id, new.title, new.description);
END;

CREATE TRIGGER IF NOT EXISTS trg_resources_fts_delete
AFTER DELETE ON resources
BEGIN
    INSERT INTO resources_fts(resources_fts, rowid, title, description)
    VALUES ('delete', old.id, old.title, old.description);
END;

CREATE TRIGGER IF NOT EXISTS trg_resources_fts_update
AFTER UPDATE ON resources
BEGIN
    INSERT INTO resources_fts(resources_fts, rowid, title, description)
    VALUES ('delete', old.id, old.title, old.description);
    INSERT INTO resources_fts(rowid, title, description) VALUES (new.id, new.title, new.description);
END;

-- ----------------------------------------------------------------------
-- 4. Download Logs (NEW) — one row per PDF download/view event.
--    resources.download_count stays as a fast denormalised counter,
--    kept in sync automatically by the trigger below, while this table
--    is the full audit trail (Task 15: Security & Privacy / Task 17: Evaluation).
-- ----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS download_logs (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    resource_id    INTEGER NOT NULL REFERENCES resources(id) ON DELETE CASCADE,
    ip_address     TEXT,
    user_agent     TEXT,
    referrer       TEXT,
    downloaded_at  TEXT NOT NULL DEFAULT (STRFTIME('%Y-%m-%dT%H:%M:%fZ', 'now'))
);

CREATE INDEX IF NOT EXISTS idx_download_logs_resource   ON download_logs(resource_id);
CREATE INDEX IF NOT EXISTS idx_download_logs_downloaded ON download_logs(downloaded_at);

CREATE TRIGGER IF NOT EXISTS trg_download_logs_increment
AFTER INSERT ON download_logs
FOR EACH ROW
BEGIN
    UPDATE resources SET download_count = download_count + 1 WHERE id = NEW.resource_id;
END;