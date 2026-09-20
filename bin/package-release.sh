#!/bin/sh
# Build a bare-metal release tarball (no Docker). POSIX sh (Linux CI / hosts).
# Usage: ./bin/package-release.sh [VERSION]
# Env: SKIP_ASSETS=1 — skip npm ci/build when public/ assets are already built
#      SKIP_COMPOSER=1 — skip composer install when vendor/ is already populated
set -eu

ROOT="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

VERSION="${1:-}"
if [ -z "$VERSION" ]; then
  if command -v git >/dev/null 2>&1 && git rev-parse --git-dir >/dev/null 2>&1; then
    VERSION="$(git describe --tags --exact-match 2>/dev/null || git describe --tags 2>/dev/null || true)"
  fi
fi
if [ -z "$VERSION" ]; then
  echo "usage: $0 VERSION" >&2
  echo "  e.g. $0 1.2.0   (or tag v1.2.0 and omit the arg)" >&2
  exit 1
fi

# Strip leading v from tags
case "$VERSION" in
  v*) VERSION="${VERSION#v}" ;;
esac

NAME="mt2-cms-${VERSION}"
OUT_DIR="${OUT_DIR:-$ROOT/dist}"
STAGE="$OUT_DIR/$NAME"
ARCHIVE="$OUT_DIR/${NAME}.tar.gz"
CHECKSUM="$OUT_DIR/${NAME}.tar.gz.sha256"

mkdir -p "$OUT_DIR"
rm -rf "$STAGE"
mkdir -p "$STAGE"

if [ "${SKIP_COMPOSER:-0}" != "1" ]; then
  composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts
fi

if [ "${SKIP_ASSETS:-0}" != "1" ]; then
  npm ci
  npm run build
fi

# Copy release tree
for path in \
  src \
  public \
  themes \
  lang \
  bin \
  vendor \
  deploy \
  docs \
  composer.json \
  composer.lock \
  LICENSE \
  README.md \
  .env.prod-example
do
  if [ ! -e "$path" ]; then
    echo "missing required path: $path" >&2
    exit 1
  fi
  cp -R "$path" "$STAGE/"
done

# Empty writable stubs (do not ship session/rate-limit data)
mkdir -p \
  "$STAGE/var/sessions" \
  "$STAGE/var/rate-limit" \
  "$STAGE/var/cache" \
  "$STAGE/var/uploads/tickets" \
  "$STAGE/var/downloads" \
  "$STAGE/var/backups" \
  "$STAGE/public/uploads/banners" \
  "$STAGE/public/uploads/logo" \
  "$STAGE/public/uploads/seo"
# Keep directories, drop any accidental contents under var/
find "$STAGE/var" -type f -delete 2>/dev/null || true
printf '%s\n' '*' '!.gitignore' > "$STAGE/var/.gitignore" 2>/dev/null || true
touch "$STAGE/var/sessions/.gitkeep" \
  "$STAGE/public/uploads/.gitkeep"

# Ensure package script itself is executable in the tree
chmod +x "$STAGE/bin/"*.sh "$STAGE/bin/"*.php 2>/dev/null || true

rm -f "$ARCHIVE" "$CHECKSUM"
tar -czf "$ARCHIVE" -C "$OUT_DIR" "$NAME"

# Portable checksum: sha256sum (Linux) or sha256 -r (FreeBSD)
if command -v sha256sum >/dev/null 2>&1; then
  (cd "$OUT_DIR" && sha256sum "${NAME}.tar.gz" > "${NAME}.tar.gz.sha256")
elif command -v sha256 >/dev/null 2>&1; then
  (cd "$OUT_DIR" && sha256 -r "${NAME}.tar.gz" | awk '{print $1 "  " $2}' > "${NAME}.tar.gz.sha256")
else
  echo "warning: no sha256sum/sha256; skipping checksum file" >&2
fi

rm -rf "$STAGE"

echo "Wrote $ARCHIVE"
if [ -f "$CHECKSUM" ]; then
  echo "Wrote $CHECKSUM"
fi
