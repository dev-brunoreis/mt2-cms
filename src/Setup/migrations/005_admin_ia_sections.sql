-- Collapse legacy ACL section IDs into IA-aligned sections (logs, store, news).

INSERT INTO acl_role_sections (role, section_id)
SELECT DISTINCT rs.role, 'logs'
FROM acl_role_sections rs
WHERE rs.section_id LIKE 'log-%'
  AND NOT EXISTS (
    SELECT 1 FROM acl_role_sections x
    WHERE x.role = rs.role AND x.section_id = 'logs'
  );

INSERT INTO acl_admin_sections (admin_id, section_id)
SELECT DISTINCT rs.admin_id, 'logs'
FROM acl_admin_sections rs
WHERE rs.section_id LIKE 'log-%'
  AND NOT EXISTS (
    SELECT 1 FROM acl_admin_sections x
    WHERE x.admin_id = rs.admin_id AND x.section_id = 'logs'
  );

INSERT INTO acl_role_sections (role, section_id)
SELECT DISTINCT rs.role, 'store'
FROM acl_role_sections rs
WHERE rs.section_id IN ('item-shop', 'item-shop-categories', 'item-shop-orders')
  AND NOT EXISTS (
    SELECT 1 FROM acl_role_sections x
    WHERE x.role = rs.role AND x.section_id = 'store'
  );

INSERT INTO acl_admin_sections (admin_id, section_id)
SELECT DISTINCT rs.admin_id, 'store'
FROM acl_admin_sections rs
WHERE rs.section_id IN ('item-shop', 'item-shop-categories', 'item-shop-orders')
  AND NOT EXISTS (
    SELECT 1 FROM acl_admin_sections x
    WHERE x.admin_id = rs.admin_id AND x.section_id = 'store'
  );

INSERT INTO acl_role_sections (role, section_id)
SELECT DISTINCT rs.role, 'news'
FROM acl_role_sections rs
WHERE rs.section_id IN ('news-comments', 'news-settings')
  AND NOT EXISTS (
    SELECT 1 FROM acl_role_sections x
    WHERE x.role = rs.role AND x.section_id = 'news'
  );

INSERT INTO acl_admin_sections (admin_id, section_id)
SELECT DISTINCT rs.admin_id, 'news'
FROM acl_admin_sections rs
WHERE rs.section_id IN ('news-comments', 'news-settings')
  AND NOT EXISTS (
    SELECT 1 FROM acl_admin_sections x
    WHERE x.admin_id = rs.admin_id AND x.section_id = 'news'
  );

DELETE FROM acl_role_sections
WHERE section_id LIKE 'log-%'
   OR section_id IN ('news-comments', 'news-settings', 'item-shop', 'item-shop-categories', 'item-shop-orders');

DELETE FROM acl_admin_sections
WHERE section_id LIKE 'log-%'
   OR section_id IN ('news-comments', 'news-settings', 'item-shop', 'item-shop-categories', 'item-shop-orders');
