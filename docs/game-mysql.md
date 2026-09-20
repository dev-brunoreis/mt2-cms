# Game MySQL from a separate CMS host

Production layout: run the **CMS on a Linux server** (VPS/VM), not on the Metin2 game box. The game can stay on FreeBSD (including older i386 hosts). The CMS reaches game MySQL over a **private** network with a **dedicated, host-scoped** MySQL user.

Related: [deploy.md](deploy.md), [security.md](security.md), [`deploy/linux/`](../deploy/linux/).

## Architecture

```
Internet → TLS → CMS Linux (Nginx + PHP-FPM)
                    ├─ CMS_DB_*  → MySQL 8 on the CMS host (schema cms)
                    └─ DB_*      → Game MySQL on the game server (private IP only)

Players → Metin2 game host → same Game MySQL (local / jail)
```

Do **not** install the CMS on the same FreeBSD i386 game machine. Keep game and site upgrade cycles separate.

## Network lockdown (game MySQL host)

1. Bind MySQL to a **private** address (LAN, VPN, or jail IP) — not `0.0.0.0` / public NIC.
2. Firewall `3306`: allow **only** the CMS host IP (and localhost for the game).
3. Prefer WireGuard / private VLAN between CMS and game. Avoid opening MySQL on the public internet “just for the site”.
4. Adminer / phpMyAdmin must not be reachable from the internet.

Example intent (adjust for `pf` / `iptables` / cloud SG):

- Source = CMS public or VPN IP  
- Dest = game private IP : 3306  
- Default deny everything else to 3306  

## Dedicated app user

- Use a dedicated login (e.g. `mt2cms`), **not** MySQL `root` and not the game operator account.
- Scope the account to the **CMS host IP**: `'mt2cms'@'10.0.0.20'` — **not** `'%'`.
- Put the password only in the CMS `.env` (`DB_USER` / `DB_PASSWORD`).

Replace `CMS_HOST_IP` with the address MySQL will see (VPN IP if you tunnel).

```sql
-- Run as MySQL root on the GAME database server
CREATE USER 'mt2cms'@'CMS_HOST_IP' IDENTIFIED BY 'strong-password';

GRANT SELECT, INSERT, UPDATE, DELETE ON account.* TO 'mt2cms'@'CMS_HOST_IP';
GRANT SELECT, INSERT, UPDATE, DELETE ON player.* TO 'mt2cms'@'CMS_HOST_IP';
GRANT SELECT, INSERT, UPDATE, DELETE ON common.* TO 'mt2cms'@'CMS_HOST_IP';
GRANT SELECT, INSERT, UPDATE, DELETE ON log.* TO 'mt2cms'@'CMS_HOST_IP';

FLUSH PRIVILEGES;
```

### Do not grant

| Privilege / pattern | Why |
| --- | --- |
| `'mt2cms'@'%'` | Allows any host that can reach 3306 |
| `ALL PRIVILEGES` | Far more than the app needs |
| `SUPER`, `FILE`, `PROCESS`, `RELOAD`, `SHUTDOWN` | Host/OS-level abuse surface |
| `GRANT OPTION` | User could escalate |
| `CREATE` / `DROP` / `ALTER` / `INDEX` (DDL) | Schema changes are ops/migrations on the game side, not the CMS hot path |
| Other schemas | Keep game dumps / backups / unrelated DBs unreachable |

MySQL 5.6 game servers may use older `IDENTIFIED BY` syntax; the grant list is the same.

### Why these four schemas

| Schema | CMS uses it for |
| --- | --- |
| `account` | Register/login, password/email/PIN, cash credit (donate/shop), account bans |
| `player` | Characters, unstuck, rankings, economy scans |
| `common` | Admin game-data (GM list, shop, refine, …) |
| `log` | Admin log viewers |

Full CMS admin features need **DML** (`SELECT`/`INSERT`/`UPDATE`/`DELETE`) on all four. A public read-only mirror is out of scope for this guide.

## CMS database (separate)

On the **CMS Linux host**, run MySQL 8 for schema `cms` only:

```sql
CREATE USER 'cms'@'localhost' IDENTIFIED BY 'strong-cms-password';
GRANT ALL PRIVILEGES ON cms.* TO 'cms'@'localhost';
FLUSH PRIVILEGES;
```

Prefer `localhost` / socket for `CMS_DB_*` when MySQL is on the same machine as PHP. Do not reuse the game `mt2cms` user for the CMS schema.

## `.env` (CMS host)

```env
# Game MySQL (remote — private IP)
DB_HOST=10.0.0.5
DB_PORT=3306
DB_USER=mt2cms
DB_PASSWORD=strong-password

# CMS MySQL (local)
CMS_DB_HOST=127.0.0.1
CMS_DB_PORT=3306
CMS_DB_USER=cms
CMS_DB_PASSWORD=strong-cms-password
CMS_DB_NAME=cms

APP_INSTALLED=false
APP_KEY=
APP_TRUST_PROXY=1
```

`MYSQL_ROOT_PASSWORD` / `CMS_MYSQL_ROOT_PASSWORD` in `.env.prod-example` are for Compose stacks only — unused when you manage MySQL yourself.

## Verify

From the **CMS** host:

```bash
mysql -h "$DB_HOST" -P "$DB_PORT" -u mt2cms -p -e "SELECT 1; SHOW DATABASES;"
```

You should see `account` / `player` / `common` / `log` (as permitted) and be able to `SELECT` a row. From an unrelated host, TCP 3306 should be **refused** or timed out.

After the site is up: `GET /health` returns `200` with body `ok` (both DBs reachable).

## Checklist

- [ ] CMS runs on a **separate Linux** host from the game server
- [ ] Game MySQL not bound to a public interface
- [ ] Firewall allows 3306 only from the CMS IP (or VPN)
- [ ] App user is `'mt2cms'@'CMS_HOST_IP'`, not `'%'` and not `root`
- [ ] Grants are DML-only on `account` / `player` / `common` / `log`
- [ ] No `SUPER` / `FILE` / `GRANT OPTION` / DDL for the CMS user
- [ ] `CMS_DB_*` uses a dedicated `cms` user limited to `cms.*`
- [ ] `.env` passwords are strong and not the Compose `change-me-*` placeholders
- [ ] `/health` is green; random hosts cannot open game MySQL
