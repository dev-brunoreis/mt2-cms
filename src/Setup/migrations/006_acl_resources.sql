-- ACL resource tables (Magento-style hierarchical permissions)

CREATE TABLE IF NOT EXISTS acl_role_resources (
    role VARCHAR(32) NOT NULL,
    resource_id VARCHAR(128) NOT NULL,
    PRIMARY KEY (role, resource_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS acl_admin_resources (
    admin_id INT UNSIGNED NOT NULL,
    resource_id VARCHAR(128) NOT NULL,
    PRIMARY KEY (admin_id, resource_id),
    CONSTRAINT fk_acl_admin_resources_admin
        FOREIGN KEY (admin_id) REFERENCES admins (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
