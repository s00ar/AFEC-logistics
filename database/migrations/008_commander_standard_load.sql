-- Optional individual load; existing identities and ledger entries remain unchanged.
ALTER TABLE commanders
    ADD COLUMN standard_load_quantity BIGINT NULL,
    ADD COLUMN load_version INT UNSIGNED NOT NULL DEFAULT 1,
    ADD CONSTRAINT ck_commander_standard_load CHECK (standard_load_quantity IS NULL OR standard_load_quantity > 0);
