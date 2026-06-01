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

CREATE TABLE IF NOT EXISTS task_instances (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    parent_task_id BIGINT NULL REFERENCES task_instances(id) ON DELETE SET NULL,
    title VARCHAR(255) NOT NULL,
    body_md TEXT NOT NULL DEFAULT '',
    start_date DATE NULL,
    end_date DATE NULL,
    status VARCHAR(32) NOT NULL CHECK (status IN ('ongoing', 'done', 'will_do', 'stale')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT task_instances_date_pair_check CHECK (
        (start_date IS NULL AND end_date IS NULL)
        OR (start_date IS NOT NULL AND end_date IS NOT NULL AND end_date >= start_date)
    ),
    CHECK (parent_task_id IS NULL OR parent_task_id <> id)
);

ALTER TABLE task_instances ALTER COLUMN start_date DROP NOT NULL;
ALTER TABLE task_instances ALTER COLUMN end_date DROP NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'task_instances_date_pair_check'
            AND conrelid = 'task_instances'::regclass
    ) THEN
        ALTER TABLE task_instances
            ADD CONSTRAINT task_instances_date_pair_check CHECK (
                (start_date IS NULL AND end_date IS NULL)
                OR (start_date IS NOT NULL AND end_date IS NOT NULL AND end_date >= start_date)
            );
    END IF;
END;
$$ LANGUAGE plpgsql;

CREATE INDEX IF NOT EXISTS task_instances_user_date_range_idx
    ON task_instances (user_id, start_date, end_date);

CREATE INDEX IF NOT EXISTS task_instances_user_inbox_idx
    ON task_instances (user_id, created_at, id)
    WHERE start_date IS NULL AND end_date IS NULL;

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

CREATE TABLE IF NOT EXISTS habits (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    habit_series_uid TEXT NOT NULL CONSTRAINT habits_series_uid_format_check CHECK (habit_series_uid ~ '^[a-f0-9]{32}$'),
    frequency_days INTEGER NOT NULL CHECK (frequency_days > 0),
    mode VARCHAR(16) NOT NULL CHECK (mode IN ('strict', 'sliding')),
    start_date DATE NOT NULL,
    end_date DATE NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CHECK (end_date IS NULL OR end_date >= start_date)
);

CREATE INDEX IF NOT EXISTS habits_user_active_idx ON habits (user_id, active);
CREATE INDEX IF NOT EXISTS habits_user_date_range_idx ON habits (user_id, start_date, end_date);

ALTER TABLE habits
    ADD COLUMN IF NOT EXISTS habit_series_uid TEXT;

UPDATE habits
SET habit_series_uid = md5(id::text || ':' || created_at::text)
WHERE habit_series_uid IS NULL;

ALTER TABLE habits
    ALTER COLUMN habit_series_uid SET NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'habits_series_uid_format_check'
    ) THEN
        ALTER TABLE habits
            ADD CONSTRAINT habits_series_uid_format_check
            CHECK (habit_series_uid ~ '^[a-f0-9]{32}$');
    END IF;
END;
$$ LANGUAGE plpgsql;

CREATE INDEX IF NOT EXISTS habits_user_series_idx ON habits (user_id, habit_series_uid, start_date);

DROP TRIGGER IF EXISTS habits_set_updated_at ON habits;

CREATE TRIGGER habits_set_updated_at
BEFORE UPDATE ON habits
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

CREATE TABLE IF NOT EXISTS habit_entries (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    habit_id BIGINT NOT NULL REFERENCES habits(id) ON DELETE CASCADE,
    performed_date DATE NOT NULL,
    status VARCHAR(16) NOT NULL CHECK (status IN ('done', 'skipped')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (habit_id, performed_date)
);

CREATE INDEX IF NOT EXISTS habit_entries_user_date_idx ON habit_entries (user_id, performed_date);
CREATE INDEX IF NOT EXISTS habit_entries_habit_date_idx ON habit_entries (habit_id, performed_date);

DROP TRIGGER IF EXISTS habit_entries_set_updated_at ON habit_entries;

CREATE TRIGGER habit_entries_set_updated_at
BEFORE UPDATE ON habit_entries
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

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
