CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(32) NOT NULL CHECK (role IN ('user', 'admin')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS users_role_idx ON users (role);

CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS users_set_updated_at ON users;

CREATE TRIGGER users_set_updated_at
BEFORE UPDATE ON users
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_key CHAR(64) PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    first_attempt_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    locked_until TIMESTAMPTZ NULL
);

CREATE INDEX IF NOT EXISTS login_attempts_locked_until_idx ON login_attempts (locked_until);

CREATE TABLE IF NOT EXISTS task_series (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    body_md TEXT NOT NULL DEFAULT '',
    starts_on DATE NOT NULL,
    ends_on DATE NULL,
    interval_count INTEGER NOT NULL DEFAULT 1 CHECK (interval_count > 0),
    interval_unit VARCHAR(16) NOT NULL CHECK (interval_unit IN ('day', 'week', 'month', 'year')),
    duration_count INTEGER NOT NULL DEFAULT 1 CHECK (duration_count > 0),
    duration_unit VARCHAR(16) NOT NULL DEFAULT 'day' CHECK (duration_unit IN ('day', 'week', 'month', 'year')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CHECK (ends_on IS NULL OR ends_on >= starts_on)
);

CREATE INDEX IF NOT EXISTS task_series_user_starts_on_idx ON task_series (user_id, starts_on);

DROP TRIGGER IF EXISTS task_series_set_updated_at ON task_series;

CREATE TRIGGER task_series_set_updated_at
BEFORE UPDATE ON task_series
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

CREATE TABLE IF NOT EXISTS task_instances (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    series_id BIGINT NULL REFERENCES task_series(id) ON DELETE SET NULL,
    parent_task_id BIGINT NULL REFERENCES task_instances(id) ON DELETE SET NULL,
    title VARCHAR(255) NOT NULL,
    body_md TEXT NOT NULL DEFAULT '',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status VARCHAR(32) NOT NULL CHECK (status IN ('ongoing', 'done', 'will_do', 'stale')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CHECK (end_date >= start_date),
    CHECK (parent_task_id IS NULL OR parent_task_id <> id)
);

CREATE UNIQUE INDEX IF NOT EXISTS task_instances_series_start_date_idx
    ON task_instances (series_id, start_date)
    WHERE series_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS task_instances_user_date_range_idx
    ON task_instances (user_id, start_date, end_date);

CREATE INDEX IF NOT EXISTS task_instances_parent_task_id_idx
    ON task_instances (parent_task_id);

DROP TRIGGER IF EXISTS task_instances_set_updated_at ON task_instances;

CREATE TRIGGER task_instances_set_updated_at
BEFORE UPDATE ON task_instances
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

CREATE TABLE IF NOT EXISTS task_links (
    source_task_id BIGINT NOT NULL REFERENCES task_instances(id) ON DELETE CASCADE,
    target_task_id BIGINT NOT NULL REFERENCES task_instances(id) ON DELETE CASCADE,
    link_type VARCHAR(32) NOT NULL DEFAULT 'related',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (source_task_id, target_task_id, link_type),
    CHECK (source_task_id <> target_task_id)
);

CREATE INDEX IF NOT EXISTS task_links_target_task_id_idx ON task_links (target_task_id);

CREATE TABLE IF NOT EXISTS tags (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (user_id, slug)
);

CREATE INDEX IF NOT EXISTS tags_user_name_idx ON tags (user_id, name);

DROP TRIGGER IF EXISTS tags_set_updated_at ON tags;

CREATE TRIGGER tags_set_updated_at
BEFORE UPDATE ON tags
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

CREATE TABLE IF NOT EXISTS notes (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    note_type VARCHAR(16) NOT NULL CHECK (note_type IN ('day', 'regular')),
    note_date DATE NULL,
    title VARCHAR(255) NULL,
    body_md TEXT NOT NULL DEFAULT '',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CHECK (
        (note_type = 'day' AND note_date IS NOT NULL)
        OR (note_type = 'regular' AND note_date IS NULL)
    )
);

CREATE UNIQUE INDEX IF NOT EXISTS notes_user_day_note_idx
    ON notes (user_id, note_date)
    WHERE note_type = 'day';

CREATE INDEX IF NOT EXISTS notes_user_type_created_at_idx ON notes (user_id, note_type, created_at);

DROP TRIGGER IF EXISTS notes_set_updated_at ON notes;

CREATE TRIGGER notes_set_updated_at
BEFORE UPDATE ON notes
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

CREATE TABLE IF NOT EXISTS task_tags (
    task_id BIGINT NOT NULL REFERENCES task_instances(id) ON DELETE CASCADE,
    tag_id BIGINT NOT NULL REFERENCES tags(id) ON DELETE CASCADE,
    PRIMARY KEY (task_id, tag_id)
);

CREATE INDEX IF NOT EXISTS task_tags_tag_id_idx ON task_tags (tag_id);

CREATE TABLE IF NOT EXISTS note_tags (
    note_id BIGINT NOT NULL REFERENCES notes(id) ON DELETE CASCADE,
    tag_id BIGINT NOT NULL REFERENCES tags(id) ON DELETE CASCADE,
    PRIMARY KEY (note_id, tag_id)
);

CREATE INDEX IF NOT EXISTS note_tags_tag_id_idx ON note_tags (tag_id);

CREATE TABLE IF NOT EXISTS attachments (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    task_id BIGINT NULL REFERENCES task_instances(id) ON DELETE CASCADE,
    note_id BIGINT NULL REFERENCES notes(id) ON DELETE CASCADE,
    original_name VARCHAR(255) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    media_type VARCHAR(16) NOT NULL CHECK (media_type IN ('photo', 'audio')),
    size_bytes BIGINT NOT NULL CHECK (size_bytes >= 0),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CHECK (
        (task_id IS NOT NULL AND note_id IS NULL)
        OR (task_id IS NULL AND note_id IS NOT NULL)
    )
);

CREATE INDEX IF NOT EXISTS attachments_user_created_at_idx ON attachments (user_id, created_at);
CREATE INDEX IF NOT EXISTS attachments_task_id_idx ON attachments (task_id);
CREATE INDEX IF NOT EXISTS attachments_note_id_idx ON attachments (note_id);
