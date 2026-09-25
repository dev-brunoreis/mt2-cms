#!/bin/bash
set -euo pipefail

DUMPS_DIR="${DUMPS_DIR:-/dumps}"
shopt -s nullglob
sql=( "${DUMPS_DIR}"/*.sql )

if [ "${#sql[@]}" -eq 0 ]; then
  echo "No fixture dumps found; skipping import (production mode)."
  exit 0
fi

missing=0
for db in account common log player; do
  dump="${DUMPS_DIR}/${db}.sql"
  if [ ! -f "${dump}" ]; then
    echo "warning: missing ${dump}; skipping ${db}. A working CMS+game stack needs account, common, log, and player." >&2
    missing=1
    continue
  fi
  echo "Importing ${db} from ${dump}"
  mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" "${db}" < "${dump}"
done

if [ "${missing}" -eq 1 ]; then
  echo "Import finished with missing dumps. Copy all four SQL files before the first volume init." >&2
else
  echo "Metin2 databases imported."
fi
