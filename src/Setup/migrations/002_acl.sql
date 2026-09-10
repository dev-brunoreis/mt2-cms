-- ACL tables and admin custom-permission flag

CREATE TABLE IF NOT EXISTS acl_role_sections (
    role VARCHAR(32) NOT NULL,
    section_id VARCHAR(64) NOT NULL,
    PRIMARY KEY (role, section_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS acl_admin_sections (
    admin_id INT UNSIGNED NOT NULL,
    section_id VARCHAR(64) NOT NULL,
    PRIMARY KEY (admin_id, section_id),
    CONSTRAINT fk_acl_admin_sections_admin
        FOREIGN KEY (admin_id) REFERENCES admins (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
