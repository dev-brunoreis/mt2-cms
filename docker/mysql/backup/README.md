# Dev game MySQL dumps

Not shipped. Copy these four files here **before the first** `docker compose up` (MySQL imports them only when the data volume is created):

| File | Source |
| --- | --- |
| `account.sql` | 40.250 reference pack SQL, or a dump of your game `account` database |
| `common.sql` | same, `common` database |
| `log.sql` | same, `log` database |
| `player.sql` | same, `player` database (includes `item_proto` / `mob_proto`) |

Reference pack: [40.250 serverfile + client](https://metin2.dev/topic/27610-40250-reference-serverfile-client-src-15-available-languages/).

A working CMS against this Compose `game` service needs all four. Missing files are skipped with a warning; they are not imported on later restarts. To retry, remove the game MySQL volume (`docker compose down -v`) and start again.

Do not commit the `.sql` files. Production does not use this folder — point `DB_*` at the live game MySQL. See [docs/deploy.md](../../../docs/deploy.md).
