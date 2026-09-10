-- One-time flag: do not re-insert default roles when admin_roles is empty (user deleted them)

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('admin_roles_defaults_seeded', '1');
