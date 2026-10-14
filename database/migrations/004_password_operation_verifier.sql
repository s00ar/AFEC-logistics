-- Password-bearing requests use a slow verifier, never a fast digest of plaintext passwords.
ALTER TABLE operations ADD COLUMN secret_hash VARCHAR(255) NULL;
