#!/bin/sh
# Mt2 CMS installer for FreeBSD 13.2+ (POSIX sh — no bash required).
#
# Run as root on a fresh host (or jail):
#   fetch -o /tmp/mt2-cms-install.sh \
#     https://raw.githubusercontent.com/OWNER/REPO/v0.1.0-beta.1/deploy/freebsd/install.sh
#   sh /tmp/mt2-cms-install.sh 0.1.0-beta.1
#
# Or from an extracted tree / release:
#   sh deploy/freebsd/install.sh 0.1.0-beta.1
#
# Env overrides:
#   MT2CMS_REPO=owner/repo     GitHub repo (default: set below)
#   MT2CMS_PREFIX=/usr/local/www/mt2-cms
#   MT2CMS_SERVER_NAME=cms.example.com
#   MT2CMS_SKIP_PKG=1          skip pkg install
#   MT2CMS_SKIP_DOWNLOAD=1     use existing tree at PREFIX (or MT2CMS_TARBALL)
#   MT2CMS_TARBALL=/path/to/mt2-cms-0.1.0-beta.1.tar.gz
#   MT2CMS_SKIP_SERVICES=1     do not enable/start nginx + php-fpm
#
set -eu

MT2CMS_REPO="${MT2CMS_REPO:-dev-brunoreis/mt2-cms}"
PREFIX="${MT2CMS_PREFIX:-/usr/local/www/mt2-cms}"
SERVER_NAME="${MT2CMS_SERVER_NAME:-cms.example.com}"
WWW_USER="${MT2CMS_WWW_USER:-www}"
WWW_GROUP="${MT2CMS_WWW_GROUP:-www}"
TMPDIR="${TMPDIR:-/tmp}"
WORK="$TMPDIR/mt2-cms-install.$$"

VERSION="${1:-}"
if [ -z "$VERSION" ]; then
  echo "usage: $0 VERSION" >&2
  echo "  e.g. $0 0.1.0-beta.1" >&2
  echo "  or:  $0 v0.1.0-beta.1" >&2
  exit 1
fi
case "$VERSION" in
  v*) TAG="$VERSION"; VERSION="${VERSION#v}" ;;
  *) TAG="v${VERSION}" ;;
esac

if [ "$(id -u)" -ne 0 ]; then
  echo "Run as root (su / doas / sudo)." >&2
  exit 1
fi

cleanup() {
  rm -rf "$WORK"
}
trap cleanup EXIT INT TERM

mkdir -p "$WORK"

echo "==> Mt2 CMS ${VERSION} → ${PREFIX}"
echo "    repo=${MT2CMS_REPO}  server_name=${SERVER_NAME}"

# ---------------------------------------------------------------------------
# Packages
# ---------------------------------------------------------------------------
if [ "${MT2CMS_SKIP_PKG:-0}" != "1" ]; then
  echo "==> Installing packages (pkg)"
  ASSUME_ALWAYS_YES=yes pkg install -y \
    php83 \
    php83-extensions \
    php83-pdo_mysql \
    php83-gd \
    php83-curl \
    php83-mbstring \
    php83-filter \
    php83-session \
    php83-opcache \
    php83-openssl \
    php83-fileinfo \
    nginx \
    mysql80-client \
    ca_root_nss
fi

PHP_BIN="$(command -v php || true)"
if [ -z "$PHP_BIN" ] && [ -x /usr/local/bin/php ]; then
  PHP_BIN=/usr/local/bin/php
fi
if [ -z "$PHP_BIN" ]; then
  echo "php not found on PATH after pkg install" >&2
  exit 1
fi

echo "==> Checking GD WebP"
if ! "$PHP_BIN" -r 'exit(function_exists("imagewebp") ? 0 : 1);'; then
  echo "WARNING: GD WebP missing (imagewebp). Banner variants will fail." >&2
  echo "         Rebuild/reinstall php83-gd with WebP support." >&2
fi

# ---------------------------------------------------------------------------
# Download / extract release
# ---------------------------------------------------------------------------
extract_tarball() {
  _tar="$1"
  _sum="$2"
  if [ -n "$_sum" ] && [ -f "$_sum" ]; then
    echo "==> Verifying checksum"
    (
      cd "$(dirname "$_tar")"
      sha256 -c "$(basename "$_sum")"
    )
  else
    echo "WARNING: no .sha256 file — skipping verify" >&2
  fi

  echo "==> Extracting to ${PREFIX}"
  mkdir -p "$(dirname "$PREFIX")"
  STAGE="$WORK/extract"
  mkdir -p "$STAGE"
  tar -xzf "$_tar" -C "$STAGE"
  # Expect single top-level directory mt2-cms-VERSION
  TOP="$(find "$STAGE" -maxdepth 1 -type d ! -path "$STAGE" | head -n 1)"
  if [ -z "$TOP" ]; then
    echo "tarball had no top-level directory" >&2
    exit 1
  fi

  if [ -d "$PREFIX" ] && [ -f "$PREFIX/.env" ]; then
    echo "==> Preserving existing .env"
    cp "$PREFIX/.env" "$WORK/.env.preserve"
  fi
  if [ -d "$PREFIX/var" ]; then
    echo "==> Preserving existing var/"
    mkdir -p "$WORK/var.preserve"
    cp -a "$PREFIX/var/." "$WORK/var.preserve/" 2>/dev/null || true
  fi
  if [ -d "$PREFIX/public/uploads" ]; then
    echo "==> Preserving existing public/uploads/"
    mkdir -p "$WORK/uploads.preserve"
    cp -a "$PREFIX/public/uploads/." "$WORK/uploads.preserve/" 2>/dev/null || true
  fi

  rm -rf "$PREFIX"
  mv "$TOP" "$PREFIX"

  if [ -f "$WORK/.env.preserve" ]; then
    cp "$WORK/.env.preserve" "$PREFIX/.env"
  fi
  if [ -d "$WORK/var.preserve" ]; then
    mkdir -p "$PREFIX/var"
    cp -a "$WORK/var.preserve/." "$PREFIX/var/"
  fi
  if [ -d "$WORK/uploads.preserve" ]; then
    mkdir -p "$PREFIX/public/uploads"
    cp -a "$WORK/uploads.preserve/." "$PREFIX/public/uploads/"
  fi
}

if [ "${MT2CMS_SKIP_DOWNLOAD:-0}" = "1" ] && [ -z "${MT2CMS_TARBALL:-}" ]; then
  if [ ! -d "$PREFIX/public" ]; then
    echo "MT2CMS_SKIP_DOWNLOAD=1 but ${PREFIX} is not an app tree" >&2
    exit 1
  fi
  echo "==> Using existing tree at ${PREFIX}"
elif [ -n "${MT2CMS_TARBALL:-}" ]; then
  TARBALL="$MT2CMS_TARBALL"
  SUMFILE="${TARBALL}.sha256"
  [ -f "$SUMFILE" ] || SUMFILE=""
  extract_tarball "$TARBALL" "$SUMFILE"
else
  BASE="https://github.com/${MT2CMS_REPO}/releases/download/${TAG}"
  NAME="mt2-cms-${VERSION}.tar.gz"
  echo "==> Downloading ${BASE}/${NAME}"
  fetch -o "$WORK/$NAME" "${BASE}/${NAME}"
  if fetch -o "$WORK/${NAME}.sha256" "${BASE}/${NAME}.sha256" 2>/dev/null; then
    :
  else
    rm -f "$WORK/${NAME}.sha256"
  fi
  # sha256 -c expects the filename listed in the checksum file to exist in cwd
  extract_tarball "$WORK/$NAME" "$WORK/${NAME}.sha256"
fi

# ---------------------------------------------------------------------------
# Writable dirs + ownership
# ---------------------------------------------------------------------------
echo "==> Runtime directories + ownership (${WWW_USER}:${WWW_GROUP})"
cd "$PREFIX"
mkdir -p \
  var/sessions var/rate-limit var/cache var/uploads/tickets \
  var/downloads var/backups \
  public/uploads/banners public/uploads/logo public/uploads/seo
chown -R "${WWW_USER}:${WWW_GROUP}" var public/uploads
chmod 0750 var var/sessions var/rate-limit

if [ ! -f .env ]; then
  echo "==> Creating .env from .env.prod-example (edit passwords/hosts next)"
  cp .env.prod-example .env
  # Rewrite Docker service names if still present from the example
  sed -i '' \
    -e 's/^DB_HOST=game$/DB_HOST=127.0.0.1/' \
    -e 's/^CMS_DB_HOST=mysql$/CMS_DB_HOST=127.0.0.1/' \
    -e 's/^APP_TRUST_PROXY=0$/APP_TRUST_PROXY=1/' \
    .env 2>/dev/null || true
  chmod 0640 .env
  chown "root:${WWW_GROUP}" .env
fi

# ---------------------------------------------------------------------------
# Nginx + PHP-FPM snippets
# ---------------------------------------------------------------------------
echo "==> Installing Nginx / PHP-FPM / php.ini snippets"
SNIP_NGINX="$PREFIX/deploy/freebsd/nginx.conf.snippet"
SNIP_FPM="$PREFIX/deploy/freebsd/php-fpm-pool.conf.snippet"
SNIP_INI="$PREFIX/deploy/freebsd/php.ini.snippet"

if [ -f "$SNIP_NGINX" ]; then
  sed -e "s/cms\\.example\\.com/${SERVER_NAME}/g" \
      -e "s|/usr/local/www/mt2-cms|${PREFIX}|g" \
      "$SNIP_NGINX" > /usr/local/etc/nginx/mt2-cms.conf
  # Ensure http{} includes our vhost when using stock FreeBSD nginx.conf
  if [ -f /usr/local/etc/nginx/nginx.conf ] \
    && ! grep -q 'mt2-cms.conf' /usr/local/etc/nginx/nginx.conf; then
    if grep -q 'include.*/usr/local/etc/nginx/\*\.conf' /usr/local/etc/nginx/nginx.conf; then
      :
    else
      echo "NOTE: add inside http { }:  include /usr/local/etc/nginx/mt2-cms.conf;" >&2
    fi
  fi
fi

if [ -f "$SNIP_FPM" ]; then
  mkdir -p /usr/local/etc/php-fpm.d
  sed -e "s|/usr/local/www/mt2-cms|${PREFIX}|g" \
      -e "s/^user = www$/user = ${WWW_USER}/" \
      -e "s/^group = www$/group = ${WWW_GROUP}/" \
      -e "s/^listen.owner = www$/listen.owner = ${WWW_USER}/" \
      -e "s/^listen.group = www$/listen.group = ${WWW_GROUP}/" \
      "$SNIP_FPM" > /usr/local/etc/php-fpm.d/mt2-cms.conf
fi

if [ -f "$SNIP_INI" ]; then
  mkdir -p /usr/local/etc/php
  # php83 typically reads /usr/local/etc/php.ini — drop a conf.d style file if supported
  if [ -d /usr/local/etc/php ]; then
    cp "$SNIP_INI" /usr/local/etc/php/mt2-cms-hardening.ini
  fi
  # Also try conf.d used by some builds
  if [ -d /usr/local/etc/php/conf.d ]; then
    cp "$SNIP_INI" /usr/local/etc/php/conf.d/mt2-cms-hardening.ini
  fi
fi

# ---------------------------------------------------------------------------
# Services
# ---------------------------------------------------------------------------
if [ "${MT2CMS_SKIP_SERVICES:-0}" != "1" ]; then
  echo "==> Enabling nginx + php-fpm"
  sysrc nginx_enable=YES >/dev/null
  # FreeBSD php83 uses php_fpm rc script
  if [ -x /usr/local/etc/rc.d/php-fpm ]; then
    sysrc php_fpm_enable=YES >/dev/null
    service php-fpm restart || service php-fpm start
  elif [ -x /usr/local/etc/rc.d/php83-fpm ]; then
    sysrc php83_fpm_enable=YES >/dev/null
    service php83-fpm restart || service php83-fpm start
  else
    echo "WARNING: php-fpm rc script not found — start FPM manually" >&2
  fi
  service nginx restart || service nginx start
fi

cat <<EOF

========================================================================
Mt2 CMS ${VERSION} installed at ${PREFIX}

NEXT (manual — needs your MySQL passwords):

  1) Edit database credentials:
       ee ${PREFIX}/.env
     Set DB_* and CMS_DB_* (dedicated users, not root). Keep APP_TRUST_PROXY=1
     behind TLS.

  2) Create MySQL users/schemas if needed, then migrate:
       cd ${PREFIX} && ${PHP_BIN} bin/migrate.php

  3) Open http://${SERVER_NAME}/setup  (or your host) and create the admin.

  4) Confirm:  fetch -qo - http://127.0.0.1/health

  5) Add TLS + HSTS on your reverse proxy.

  6) Crons (see docs/deploy-freebsd.md):
       payments-process.php  every minute
       economy-tick.php      every 15 minutes
       backup-dbs.sh         daily (off-server BACKUP_DIR)

Full checklist: ${PREFIX}/docs/deploy-freebsd.md
========================================================================
EOF
