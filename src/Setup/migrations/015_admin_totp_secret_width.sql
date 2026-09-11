-- Encrypted TOTP secrets (enc.v1. + AES-GCM) exceed VARCHAR(64)

ALTER TABLE admins
    MODIFY COLUMN totp_secret VARCHAR(255) NULL DEFAULT NULL;
