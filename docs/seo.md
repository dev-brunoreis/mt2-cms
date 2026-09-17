# SEO

Public meta, robots, sitemap. Settings hub tab `seo` (`/admin/settings?tab=seo`). ACL: `settings/seo/view`, `settings/seo/edit`. Migration `022_seo.sql`. Image uploads: `SeoImageUploadService`.

Admin news and event forms have a **SEO tab** (`?tab=seo`) for per-item title, description, and OG image. News OG upload: `POST /admin/content/news/posts/upload`. Event OG upload: `POST /admin/content/events/upload`.

## Runtime

`SeoService::forPage()` builds title, description, canonical, og/twitter, robots, JSON-LD. Controllers pass overrides (news/event title, excerpt, OG image). Auth and payment-return paths in `SeoService::PRIVATE_PATHS` are noindex.

| Route | Job |
| --- | --- |
| `GET /robots.txt` | `SeoController::robots` |
| `GET /sitemap.xml` | Published news + events + static public paths (not `/admin`, not account) |

Twig shell reads the `seo` array from the public layout. Do not `|raw` user-supplied meta; `SeoService` sanitizes excerpts via `HtmlSanitizer`.
