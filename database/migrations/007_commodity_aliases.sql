-- Alternative names/codes share the same commodity ID; existing balances stay intact.
ALTER TABLE commodities
    ADD COLUMN aliases_json LONGTEXT NULL,
    ADD CONSTRAINT ck_commodity_aliases CHECK (aliases_json IS NULL OR (JSON_VALID(aliases_json) AND JSON_TYPE(aliases_json) = 'ARRAY'));
