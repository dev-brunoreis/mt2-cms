#!/bin/bash
set -euo pipefail

if [ -z "${DB_PASSWORD:-}" ]; then
  echo "DB_PASSWORD not set in container; skipping app user creation (dev mode)."
  exit 0
fi

APP_USER="${DB_USER:-mt2cms}"
APP_PASS="${DB_PASSWORD}"

mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" <<EOF
CREATE USER '${APP_USER}'@'%' IDENTIFIED BY '${APP_PASS}';
GRANT SELECT, INSERT, UPDATE, DELETE ON account.* TO '${APP_USER}'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON player.* TO '${APP_USER}'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON common.* TO '${APP_USER}'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON log.* TO '${APP_USER}'@'%';
FLUSH PRIVILEGES;
EOF

echo "Game app user '${APP_USER}' created with DML grants."
