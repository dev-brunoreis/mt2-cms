ALTER TABLE news
    ADD COLUMN seo_title VARCHAR(70) NULL AFTER updated_at,
    ADD COLUMN seo_description VARCHAR(320) NULL AFTER seo_title,
    ADD COLUMN seo_og_image VARCHAR(255) NULL AFTER seo_description;

ALTER TABLE cms_events
    ADD COLUMN seo_title VARCHAR(70) NULL AFTER updated_at,
    ADD COLUMN seo_description VARCHAR(320) NULL AFTER seo_title,
    ADD COLUMN seo_og_image VARCHAR(255) NULL AFTER seo_description;

INSERT INTO settings (setting_key, setting_value)
VALUES ('seo_index_enabled', '1')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
