# Economy

Admin **Game → Economy** (`/admin/game/economy`). ACL: `game/economy/view`, `edit`, `mass`. CMS tables from migrations `018_economy.sql` + `019_economy_intelligence.sql`. Game gold/item logs stay in the `log` schema; the CMS stores census, yang daily, ingested trades, watches, and alerts.

## Tick

`php bin/economy-tick.php` → `EconomyTickService::run()` (lock `var/economy-tick.lock`).

Each run: player/safebox/guild yang census, daily yang + money-log rollup, trade ingest from gold/item logs, anomaly alerts (outlier band, Discord via `DiscordWebhookService` when configured).

Without a frequent cron the admin KPIs go stale (UI flags last-ok older than ~2h).

## Admin UI

| Route | Job | ACL |
| --- | --- | --- |
| `GET /admin/game/economy` | Market KPIs, charts, unacked alerts, item grid | view (via `adminView('economy')`) |
| `GET …/players`, `…/players/{id}` | Per-player economy | view |
| `GET …/{id}` | Item detail | view |
| `POST …/{id}/watch` | Watch / unwatch an item | `game/economy/edit` |
| `POST …/alerts/{id}/ack` | Ack alert | `game/economy/edit` |
| `POST …/mass` | Grid mass (`runMassActions`) | `game/economy/mass` |

Demo seed (dev): `bin/economy-seed-demo.php`.
