# Game files

This folder ships **paths and proto schema only** (`config.json`, `schema/`). Proto rows, item names, drop tables, client text, and icons are **not** included. Copy them from your own server and unpacked client. The site boots without them; shop and ranking then show no images, and proto admin reports missing files until the text dumps are in place.

Supported layout: the [40.250 reference serverfile and client](https://metin2.dev/topic/27610-40250-reference-serverfile-client-src-15-available-languages/) (English). Folder names inside those archives vary. Match by **filename**, then rename locale name files if needed.

Do not copy `.epk` or `.sub` packs. Unpack the client first. Do not copy map `Setting.txt` files — the CMS does not read them.

## Copy into `game/`

| From your files | Into the CMS |
| --- | --- |
| `item_proto.txt` | `db/item_proto.txt` |
| `mob_proto.txt` | `db/mob_proto.txt` |
| English item names (`item_names.txt` or `item_names_en.txt`) | `db/item_names_en.txt` |
| English mob names (`mob_names.txt` or `mob_names_en.txt`) | `db/mob_names_en.txt` |
| Unpacked `item_list.txt` | `client/item_list.txt` |
| Unpacked `itemdesc.txt` | `client/itemdesc.txt` |
| Unpacked `icon/item/*.tga` | `client/icon/item/` |
| Face TGAs (names below) | `client/icon/face/` |
| `mob_drop_item.txt` | `server/mob_drop_item.txt` |
| `common_drop_item.txt` | `server/common_drop_item.txt` |
| `etc_drop_item.txt` | `server/etc_drop_item.txt` |
| `drop_item_group.txt` | `server/drop_item_group.txt` |
| `group.txt` | `server/group.txt` |
| `group_group.txt` | `server/group_group.txt` |

On the 40.250 server archive these text files usually sit under `share/locale/english/`. On the client archive, `item_list.txt`, `itemdesc.txt`, and `icon/` appear only after you unpack the locale or root pack.

Another language: copy that locale’s name and `itemdesc` files, but keep the filenames above (or edit `config.json` and set `locale`). `config.json` is not merged — copy the whole file and change the paths you need.

Leave icons as TGA. The CMS serves PNG on request. Missing files render no image.

Face filenames (`player.job` is the race id, not the class). Ninja and shaman defaults are the female faces:

| Race | Class | Sex | File |
| --- | --- | --- | --- |
| 0 | Warrior | Male | `warrior_m.tga` |
| 1 | Ninja | Female | `assassin_w.tga` |
| 2 | Sura | Male | `sura_m.tga` |
| 3 | Shaman | Female | `shaman_w.tga` |
| 4 | Warrior | Female | `warrior_w.tga` |
| 5 | Ninja | Male | `assassin_m.tga` |
| 6 | Sura | Female | `sura_w.tga` |
| 7 | Shaman | Male | `shaman_m.tga` |
| 8 | Lycan | Male | `wolfman_m.tga` |

## Outside the project tree

Set `GAME_DIR` in `.env` to a directory with the same layout (`db/`, `client/`, `server/`, plus `config.json` and `schema/`). Relative paths resolve from the CMS root. Default is this `game/` folder.
