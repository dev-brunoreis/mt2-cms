# Contributing

This is a public project. Send bug fixes and corrections as pull requests. The maintainer also commits and opens pull requests here.

Keep the "Mt2 CMS" line in the site footer. Support development: [GitHub Sponsors](https://github.com/sponsors/dev-brunoreis).

Code changes happen in this repository. If you only run a server, use the release `.tar.gz` — see the [README](README.md).

## Before you change code

1. Get the app running: [README](README.md) → Develop.
2. Read [docs/map.md](docs/map.md), then [docs/patterns.md](docs/patterns.md) and [docs/testing.md](docs/testing.md).
3. Open only the feature doc the map links. Do not grep the whole tree to learn a subsystem.

## Pull request

One change per pull request.

- Commit messages in English, imperative, conventional when it fits (`feat:`, `fix:`, `docs:`). First line under 50 characters.
- New behavior needs a unit test in `tests/Unit/`. `composer test` must pass, including `tests/Unit/Contract/SecurityContractTest.php`.
- If behavior changes, update `docs/map.md` and the linked feature doc in the same pull request.
- Admin mutations go through ACL. See [docs/acl.md](docs/acl.md).
- Do not add a new top-level folder under `src/`.
- Do not commit `.env`, `vendor/`, `dist/`, or secrets.

Build a release with `./bin/package-release.sh` only when you are cutting a production package. Do not edit an unpacked release and send that back as a pull request.
