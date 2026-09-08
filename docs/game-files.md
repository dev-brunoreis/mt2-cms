# Game files and source configuration

The CMS reads Metin2 client/server dumps from `game/` and loads paths, proto schemas, and enum lists from JSON — no PHP edits required for a different source layout.

## Folder layout

```
game/
  config.json           # file paths, drop names, face icons
  schema/
    item.json           # item proto columns, types, APPLY, flags, admin UI
    mob.json            # mob proto columns, ranks, flags, admin UI
  client/               # unpacked client assets
    item_list.txt
    itemdesc.txt
    icon/
  db/                   # tab-delimited proto text files
    item_proto.txt
    item_names_en.txt
    mob_proto.txt
    mob_names_en.txt
  server/               # drop and spawn group files
    mob_drop_item.txt
    common_drop_item.txt
    etc.
```

Copy your source files into these folders (or point `config.json` at different relative paths).

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
| `faces` | job index → `.tga` | Character face icons |

Example: use Portuguese name files without renaming on disk:

```json
{
  "locale": "pt",
  "paths": {
    "item_names": "db/item_names_pt.txt",
    "mob_names": "db/mob_names_pt.txt"
  }
}
```

Only include keys you want to override; merge is not supported — copy the full default from the repo and edit.

## GAME_DIR (.env)

To keep dumps outside the project tree:

```env
GAME_DIR=/path/to/my-server-files
```

Relative paths resolve from the project root. Default: `game/`.

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

Add display labels in `lang/en.json` and `lang/pt-BR.json` under `admin.proto.tokens.YOUR_TOKEN`. Missing keys show the raw token.

### Extra proto columns

If your source adds columns (e.g. after `addon_type`), append them to `columns`, add to `form_tabs`, and set `defaults`. The parser truncates extra file columns or pads missing ones.

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

1. Copy client + db + server files into `game/` (or set `GAME_DIR`).
2. Adjust `config.json` paths if filenames differ.
3. Verify `schema/*.json` column order matches your proto txt.
4. Append any extra `types`, `subtypes`, or `apply_types` at the **end** of arrays.
5. Add `admin.proto.tokens.*` labels for new tokens.
6. Ensure MySQL `item_proto` / `mob_proto` match (admin txt edits sync `locale_name` only).
