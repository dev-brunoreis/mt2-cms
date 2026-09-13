INSERT INTO settings (setting_key, setting_value)
VALUES ('paypal_enabled', '1')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

INSERT INTO settings (setting_key, setting_value)
VALUES ('mp_enabled', '1')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
