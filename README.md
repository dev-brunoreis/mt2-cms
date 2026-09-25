# Mt2 CMS

Metin2 site + admin. Beta — public APIs and schema can still change before `1.0.0`.

## Pick one

**You run a server.** Download the `.tar.gz` from [Releases](https://github.com/dev-brunoreis/mt2-cms/releases). Do not clone this repo.

**You change the code.** Work in this repo. Edit here, then build with [`bin/package-release.sh`](bin/package-release.sh). That build is what goes to production. Do not patch an unpacked release and treat it as the source.

## Use a release

You need a Linux host (not the Metin2 game box), PHP 8.3 FPM (`pdo_mysql`, `gd` with JPEG/PNG/WebP, `curl`, `mbstring`, `fileinfo`, `openssl`), Composer, Nginx or Apache, MySQL 8 for the CMS, and a private path to the game MySQL.

1. Unpack `mt2-cms-VERSION.tar.gz`
2. `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`
3. `cp .env.prod-example .env` and set `DB_*` (game) and `CMS_DB_*` (local CMS)
4. Point the web server at `public/` only — sample configs in [`deploy/linux/`](deploy/linux/)
5. Open `/setup`

## Develop

You need Docker + Docker Compose, Composer, and Node.js.

```bash
cp .env-example .env
composer install
npm ci && npm run build
docker compose up --build
```

Open http://localhost:8000/setup.

```bash
composer test
./bin/package-release.sh 1.0.0-beta.0
```

## Docs

Start at [docs/map.md](docs/map.md). That folder is not in the release tarball.

## Contributing

Public project. Send fixes as pull requests. The maintainer ships changes here too.

[CONTRIBUTING.md](CONTRIBUTING.md)

## License

[MIT](LICENSE)
