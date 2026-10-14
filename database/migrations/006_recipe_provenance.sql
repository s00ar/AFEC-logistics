-- Source identity for repeatable catalogue loads; operator edits are preserved.
ALTER TABLE facility_recipes
    ADD COLUMN source_kind VARCHAR(20) NOT NULL DEFAULT 'manual',
    ADD COLUMN source_key VARCHAR(190) NULL,
    ADD COLUMN source_snapshot_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    ADD UNIQUE KEY uq_recipe_source (source_key),
    ADD CONSTRAINT ck_recipe_source_kind CHECK (source_kind IN ('manual', 'afec', 'community'));

INSERT INTO permission_locks (name) VALUES ('recipe_catalogue');
