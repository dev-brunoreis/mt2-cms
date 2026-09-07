# Add a repository

Checklist for reading/writing Metin2 game schemas.

## 1. Class

Extend `Mt2Cms\Repository\Repository` and implement `database()`:

| Return | Schema |
| --- | --- |
| `account` | Accounts / login |
| `player` | Characters |
| `common` | GM list, etc. |
| `log` | Login logs |

`$this->db()` already calls `useDatabase()` for that schema.

## 2. Queries

- Use only prepared statements via `$this->db()->fetch|fetchAll|fetchColumn|execute`.
- Bind values with `?` (or named params). **Never** interpolate user input into SQL.
- List columns explicitly (no `SELECT *` for public/API-facing rows).
- Schema/table identifiers must be alphanumeric/`_`; `Database::quoteIdentifier()` enforces that for `USE`.

Example:

```php
return $this->reveal(
    $this->db()->fetch(
        'SELECT id, name, level FROM `player` WHERE name = ?',
        [$name],
    ),
);
```

## 3. Sensitive columns

`reveal()` / `revealAll()` strip `password`, `social_id`, `securitycode`, `email`, `ip`, `mobile`. Prefer returning through those helpers for anything that leaves the repository.

For public profiles, omit extra columns in the SQL itself (see `PlayerRepository::findPublicByName`).

## 4. Wire into the app

1. Construct the repository in `Application::__construct`.
2. Inject it into the controller in `resolveController`.
3. Do not `new Repository()` inside controllers.

## PR checklist

- [ ] Prepared statements only
- [ ] Explicit columns + `reveal()` where needed
- [ ] Injected via `Application`
- [ ] No secrets in logs or Twig context
