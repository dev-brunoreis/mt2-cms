# Notifications

In-app inbox for **players** (not admin ACL). Table `cms_notifications` (migration `017_notifications.sql`). Service: `NotificationService`. Repo: `NotificationRepository`.

## Types

| Constant | When |
| --- | --- |
| `payment_credited` | Cash credited |
| `payment_failed` | Checkout/webhook failed |
| `payment_expired` | Pending expired |
| `payment_cancelled` | Cancelled |
| `account_banned` | Ban/block |
| `item_sent` | Item award / shop send |

Pushes are keyed by `ref` so the same event is not duplicated.

## Player UI

| Route | Job |
| --- | --- |
| `GET /account/notifications` | List (`requireAuth`) |
| `POST …/{id}/read` | Mark one read (CSRF) |
| `POST …/read-all` | Mark all read (CSRF) |

Admin Discord webhooks (`DiscordWebhookService`, settings community/discord) are a **separate** channel — do not send player inbox rows there unless a feature explicitly does.
