# SEO

Public meta, robots, sitemap. Settings hub tab `seo` (`/admin/settings?tab=seo`). ACL: `settings/seo/view`, `settings/seo/edit`. Migration `022_seo.sql`. Image uploads: `SeoImageUploadService`.

## Runtime

`SeoService::forPage()` builds title, description, canonical, og/twitter, robots, JSON-LD. Controllers pass overrides (news/event title, excerpt). Auth and payment-return paths in `SeoService::PRIVATE_PATHS` are noindex.

| Route | Job |
| --- | --- |
| `GET /robots.txt` | `SeoController::robots` |
| `GET /sitemap.xml` | Published news + events + static public paths (not `/admin`, not account) |

Twig shell reads the `seo` array from the public layout. Do not `|raw` user-supplied meta; `SeoService` sanitizes excerpts via `HtmlSanitizer`.
