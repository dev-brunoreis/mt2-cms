#!/bin/bash
set -euo pipefail

DUMPS_DIR="${DUMPS_DIR:-/dumps}"

for db in account common log player; do
  dump="${DUMPS_DIR}/${db}.sql"
  if [ ! -f "${dump}" ]; then
    echo "Missing dump: ${dump}" >&2
    exit 1
  fi
  echo "Importing ${db} from ${dump}"
  mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" "${db}" < "${dump}"
done

echo "Metin2 databases imported."
