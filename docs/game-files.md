# Game files and source configuration

The CMS reads Metin2 client/server files from `game/` and loads paths, proto schemas, and enum lists from JSON — no PHP edits required for a different source layout.

Git and the release tarball ship **only** `config.json`, `schema/*.json`, and empty `db/`, `client/`, and `server/` directories. Proto rows, names, drops, `item_list.txt`, `itemdesc.txt`, icons, and map settings are not in the repo (`.gitignore`). Copy them from your own files. Operator steps that also ship in the tarball: [`game/README.md`](../game/README.md).

Supported base: the [40.250 reference serverfile and client](https://metin2.dev/topic/27610-40250-reference-serverfile-client-src-15-available-languages/). Archive folders vary; match by filename. English names must end up as `item_names_en.txt` and `mob_names_en.txt` unless you edit `config.json`.

Boot after install needs the three JSON files only. Missing `itemdesc` / `item_list` / drops are empty catalogs. Proto admin throws `admin.proto.missing_files` on first use when `game/db/*.txt` is absent. `game/maps/` is not read (map labels are `lang/en.json` via `Display::map()`).

## Folder layout

```
game/
  README.md             # copy steps (ships in the release)
  config.json           # file paths, drop names, face icons
  schema/
    item.json           # item proto columns, types, APPLY, flags, admin UI
    mob.json            # mob proto columns, ranks, flags, admin UI
  client/               # operator: unpacked client text + TGA
    item_list.txt
    itemdesc.txt
    icon/
  db/                   # operator: tab-delimited proto text
    item_proto.txt
    item_names_en.txt
    mob_proto.txt
    mob_names_en.txt
  server/               # operator: drop and spawn group files
    mob_drop_item.txt
    common_drop_item.txt
    etc.
```

| From the 40.250 pack (match by filename) | Into the CMS |
| --- | --- |
| `item_proto.txt`, `mob_proto.txt` | `db/` |
| English item/mob names | `db/item_names_en.txt`, `db/mob_names_en.txt` |
| Unpacked `item_list.txt`, `itemdesc.txt` | `client/` |
| Unpacked `icon/item/*.tga` and face TGAs | `client/icon/item/`, `client/icon/face/` |
| `mob_drop_item.txt`, `common_drop_item.txt`, `etc_drop_item.txt`, `drop_item_group.txt`, `group.txt`, `group_group.txt` | `server/` |

Server text is often under `share/locale/english/`. Client text and icons exist only after unpacking `.epk` / `.sub` (those packs are not read). Do not copy map `Setting.txt` files.

Another locale: keep the filenames above, or replace `config.json` paths (no merge — copy the full default and edit). Or set `GAME_DIR` to a tree with the same layout.

## config.json

Maps logical names to files under `game/`:

| Key | Default | Purpose |
| --- | --- | --- |
| `paths.item_proto` | `db/item_proto.txt` | Admin item proto CRUD |
| `paths.item_names` | `db/item_names_en.txt` | Locale display names |
| `paths.mob_proto` | `db/mob_proto.txt` | Admin mob proto CRUD |
| `paths.mob_names` | `db/mob_names_en.txt` | Mob locale names |
| `paths.item_list` | `client/item_list.txt` | vnum → icon filename |
| `paths.itemdesc` | `client/itemdesc.txt` | Item tooltip text |
| `paths.icon_root` | `client/icon` | TGA icons (`item/`, `face/`) |
| `paths.drops` | `server` | Directory for drop txt files |
| `drops.*` | see default | Filename for each drop parser |
| `faces` | race id → `.tga` | Character face icons (`player.job` is the Metin2 race, not the class) |

`player.job` follows official `MAIN_RACE_*` order. Ninja and shaman default to **female**; the male faces are races 5 and 7:

| Race | Class | Sex | Typical TGA |
|------|-------|-----|-------------|
| 0 | Warrior | Male | `warrior_m.tga` |
| 1 | Ninja | Female | `assassin_w.tga` |
| 2 | Sura | Male | `sura_m.tga` |
| 3 | Shaman | Female | `shaman_w.tga` |
| 4 | Warrior | Female | `warrior_w.tga` |
| 5 | Ninja | Male | `assassin_m.tga` |
| 6 | Sura | Female | `sura_w.tga` |
| 7 | Shaman | Male | `shaman_m.tga` |
| 8 | Lycan | Male | `wolfman_m.tga` |

Skill path (Arahan / Partizan, Blade-Fight / Archery, …) comes from `player.skill_group`, not from the face file.

Example: point at alternate name files without renaming on disk:

```json
{
  "locale": "custom",
  "paths": {
    "item_names": "db/item_names_custom.txt",
    "mob_names": "db/mob_names_custom.txt"
  }
}
```

Merge is not supported — copy the full `config.json` from the repo and edit the paths you need.

## GAME_DIR (.env)

To keep dumps outside the project tree:

```env
GAME_DIR=/path/to/my-server-files
```

Relative paths resolve from the project root. Default: `game/`.

`bin/package-release.sh` copies `game/` into `dist/`, then deletes proto/client/server text, `game/maps/`, and unpacked `client/icon` and `client/ui` so a local dump is not packed into the tarball. Point `GAME_DIR` at a filled tree on the host if those files live outside the release.

## Icons from the client

`game/client/icon/` is empty in git and in the release (and gitignored). Shop, ranking, and player pages still load; missing files simply render no image (`item_icon()` / `face_icon()` return nothing).

Unpack the client (loose files, not a `.epk` / `.sub` pack) and copy:

| From the client | Into the CMS |
| --- | --- |
| Item icon `.tga` files | `game/client/icon/item/` |
| Face `.tga` files (see `faces` above) | `game/client/icon/face/` |
| `item_list.txt` | `game/client/item_list.txt` |
| `itemdesc.txt` (tooltips) | `game/client/itemdesc.txt` |

If `GAME_DIR` is set, use that directory instead of `game/`.

Face files must use the names in `config.json` (`warrior_m.tga`, `assassin_w.tga`, …). `player.job` is the race id `0`–`8`, not the class. Ninja and shaman defaults are the female faces.

Item lookup, in order: the filename in `item_list.txt` for that vnum, then `{vnum}.tga` zero-padded to 5 digits (`00019.tga`), then the base vnum for a `+1`…`+9` (`19` uses `00010.tga` when its own file is missing).

Leave the files as TGA. `GameIconService` decodes them on `GET /game/icon/item/{vnum}` and `GET /game/icon/face/{job}`, and caches PNG under `var/cache/icons/`. Do not put icons in the theme or in `public/`.

## schema/item.json and schema/mob.json

### Columns

The `columns` array defines proto field order. The tab parser maps columns **by index** (same as classic Metin2 proto tools). Column order must match your source files.

### Enums (item)

| Key | Used for |
| --- | --- |
| `types` | Item type select + DB index → name |
| `subtypes` | Per-type subtype lists |
| `limit_types` | `limit_type0`, `limit_type1` |
| `apply_types` | `apply_type0–2` + inventory tooltips |
| `anti_flags`, `item_flags`, `wear_flags`, `immune` | Bitmask fields |

### Enums (mob)

| Key | Used for |
| --- | --- |
| `ranks`, `types`, `battle_types`, `sizes` | Select fields |
| `ai_flags`, `race_flags`, `immune_flags` | Bitmask fields |
| `common_ranks` | `common_drop_item.txt` rank columns |

### Admin UI

- `list_columns` — proto list table
- `form_tabs` — edit form tabs and field groups
- `defaults` — new record defaults
- `value_labels` — dynamic labels for `value0–5` by item type/subtype
- `bitmask_fields` — separator and token list per field

### Adding types or APPLY attributes

**Append only at the end** of the relevant array. Array index must match the C++/MySQL enum order in your source.

```json
"apply_types": [
  "APPLY_NONE",
  "...",
  "APPLY_ANTI_PENETRATE_PCT",
  "APPLY_ATTBONUS_WOLFMAN"
]
```

Inserting in the middle shifts indices and breaks DB tooltips and admin forms.

Add display labels in `lang/en.json` under `admin.proto.tokens.YOUR_TOKEN`. Missing keys show the raw token.

### Extra proto columns

If your source adds columns (e.g. after `addon_type`), append them to `columns`, add to `form_tabs`, and set `defaults`. The parser truncates extra file columns or pads missing ones.

### Admin drop editor

The admin can edit `mob_drop_item.txt`, `etc_drop_item.txt`, and `common_drop_item.txt` under `game/server/` (or `GAME_DIR`). Writes go through `DropFileService` and `GroupTextWriter`. The game server must `/reload` or restart to pick up changes. On first save of `common_drop_item.txt`, a `.bak` copy is created beside the original.

## What stays in PHP (not configurable yet)

- Inventory paperdoll slot positions (`InventoryLayout.php`)
- Socket/metin logic and hardcoded vnums (`ItemSockets.php`)
- Client pack (`.epk`) / `.sub` icon support
- `skill_proto` admin or names

Sources that only **add** enum values at the end of existing lists work with JSON config alone.

## Loader

[`src/Game/GameProfile.php`](../src/Game/GameProfile.php) loads and caches JSON at bootstrap. Invalid JSON or missing required files throw a clear error on startup.

Services wired through the profile:

- `GameProtoService` — proto txt paths and columns
- `GameIconService` — icons, faces, `item_list`
- `MobDropService` — drop file names
- `ProtoEnums` / `ProtoSchemas` — admin forms and item tooltips

## Checklist for a new source

1. Copy client + db + server files into `game/` (or set `GAME_DIR`). See the table above and [`game/README.md`](../game/README.md). Do not commit those files.
2. Adjust `config.json` paths if filenames differ.
3. Verify `schema/*.json` column order matches your proto txt.
4. Append any extra `types`, `subtypes`, or `apply_types` at the **end** of arrays.
5. Add `admin.proto.tokens.*` labels for new tokens.
6. Ensure MySQL `item_proto` / `mob_proto` match (admin txt edits sync `locale_name` only).
