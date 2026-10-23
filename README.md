# Bot CRM

Admin panel for selling and managing access to bot tools. Admins create a **username + password per product** for a
customer, see when the bot was last seen (online/offline), and extend or revoke access by hand — there is no payment flow.

Stack: Laravel 13 · PHP 8.4 · Tailwind CSS v4 · Alpine.js · Pest · MySQL (SQLite for tests).

## Setup

```bash
# create the MySQL database first (default name `bot_crm`, see DB_* in .env)
composer setup                      # install, .env, key, migrate, npm install + build
php artisan user:create             # create your first admin (prompts for details)
composer dev                        # serve + queue + vite
```

Optional demo data (`admin@example.com` and `user@example.com`, password `password`, plus sample products and bot accounts such as `oreo-demo-online`, all with password `password`):

```bash
php artisan db:seed
```

Public registration is closed. Create users from **Admin → Users** or with `php artisan user:create`
(`--name --email --password --role=admin|user`).

## Features

### Authentication
Login (remember me, rate-limited), logout, forgot/reset password. No email verification, no self-registration.

**Profile** (`/profile`, every signed-in user): view account details, edit name and email, and change the password.
A password change needs the current password, must differ from it, is rate-limited, and signs the user out of every
other browser/device while keeping the current session. Role and status can't be changed from here.

Users have a `role` (`admin`, `user`) and a `status` (`active`, `inactive`, `suspended`). Only active
users can log in, and a user who is deactivated mid-session is logged out on the next request.
Use the `role:admin` route middleware to restrict routes.

### Panel
One shared layout (`x-layouts.panel`) with per-audience wrappers (`x-layouts.admin`, `x-layouts.user`) that only
define the sidebar menu. Brand colors and light/dark surfaces are tokens in `resources/css/app.css`.
Form fields are single-purpose components in `resources/views/components/form/`
(`input`, `select`, `textarea`, `checkbox`, `radio`, `label`, `error`), alongside `x-button`, `x-badge`,
`x-table`, `x-filters` and `x-confirm-form`.

### Admin
| Area | Who | What |
|---|---|---|
| Users | admin | CRUD, search, filter by role/status. Admins cannot delete themselves or change their own role/status. |
| Products | admin | CRUD, search, active filter. A product with licenses cannot be deleted (deactivate it). |
| Licenses | admin | Issue (product, validity, username, optional password, customer, tags), edit, extend/renew, reset password, revoke/restore, delete; filter by product, tag, status (active, expiring soon, expired, revoked) and activity (online, offline, never seen). |
| Dashboard | admin | Active licenses, bots online, **licenses expiring soon**, **offline bots**. |

A license is one bot account: **product + username + password**. Usernames are lowercase and unique per product. Only a
hash of the password is stored, so after issuing a license or resetting its password the new password is shown **once**
(with a copy button); a blank password is generated.
*Extend* either adds days (presets) to the current expiry or sets a custom date (valid through the end of that day). On an already expired license, adding days renews from today.
A license's status (`active` / `expired` / `revoked`) is derived from `expires_at` and `revoked_at`.

### Leads → Email
A **Leads** menu group (currently one item, **Email**) holds phone-number leads: `number`, `password` (stored encrypted,
shown on demand), `type` (bell, insert, otp, selection, tap code) and `range` (a label for the number range). Each lead is
tagged with the supplying account's **username**, its **customer** and **when** it arrived (the username identifies the bot).

- **List:** search (number, range, username), filters by type, range, username, customer and date range, a count per
  type, bulk delete.
- **Add / edit** one lead by hand, picking the supplying account (the customer follows the account unless chosen).
- **Import** an Excel (`.xlsx`) or CSV file (`,` `;` or tab separated) — the first row holds the headers `number`,
  `password`, `type`, `range` (extra columns are ignored; `phone`, `pass`, `category` also work). Types may be written
  `Tap Code`, `sel`, `OTP code`... The import page itself only asks for the **username** (account) and **customer** the
  leads are tagged with; type, range and password all come from the sheet, and `type` is required on every row. A row that repeats a number with the same type and range is skipped; invalid rows
  are reported with their row number. Download the **example .xlsx / .csv** on the import page.
- **Export** (the *Export* button on the list) lets you pick what goes into the file: a **date** or **date range**
  (same day twice = that day only; only *From* or only *To* = open-ended; both ends inclusive), one or more **types**,
  and the **format** (`.xlsx` or `.csv`). Leave dates empty and tick no types to export everything. The list's own
  search and filters never apply. Columns: number, password, type, range, username, customer, source and date; the file
  name says what it contains, e.g. `email-leads-bell-otp-2026-03-01-to-2026-03-05-20260310-090000.xlsx`. Values that spreadsheet
  programs could run as formulas (`=…`, `@…`) are written as plain text, and the importer reads them back unchanged.
- **Bots** can push leads (see below), tagged automatically.

### Bot API
Two endpoints keep a bot signed in (a third, below, lets it push leads). JSON in and out, no device information:

`POST /api/v1/licenses/login` — call once when the bot starts.
`POST /api/v1/licenses/heartbeat` — call about every minute.

```json
{ "product": "oreo-bot", "username": "john123", "password": "…" }
```

Both verify the credentials, record `last_seen_at`, and tell the bot whether it may keep running. Success:
`{"valid": true, "product", "status", "expires_at"}`. Failure: `{"valid": false, "reason": …}` where reason is
`invalid_credentials` (401, same answer for an unknown user, wrong password, wrong or inactive product),
`revoked` / `expired` (403), or `too_many_attempts` (429 after 10 wrong passwords for the same username and IP; resets after 10 minutes
or on a correct password). The bot should stop on anything but `valid: true`. The `product` value is the product's *slug*.

```python
import requests, time

API = "https://crm.example.com/api/v1/licenses"
BODY = {"product": "oreo-bot", "username": "john123", "password": PASSWORD}

if not requests.post(f"{API}/login", json=BODY, timeout=10).json().get("valid"):
    raise SystemExit("Access denied")

while True:
    time.sleep(60)
    if not requests.post(f"{API}/heartbeat", json=BODY, timeout=10).json().get("valid"):
        break                      # revoked, expired or password changed
```

An account is **online** when its last heartbeat is newer than the *Offline after* setting (default 3 minutes).
If the bot crashes or the machine loses power, heartbeats stop and the account flips to *offline* on its own.
The same account may be used from several machines; nothing is bound to a device.

`POST /api/v1/leads/email` — push collected leads (max 500 per request, same credentials as above):

```json
{ "product": "oreo-bot", "username": "john123", "password": "…",
  "leads": [ { "number": "+919980966156", "password": "pw", "type": "bell", "range": "9980966000-9980966999" } ] }
```

Response: `{"valid": true, "received", "created", "duplicates", "invalid", "errors": [{"row", "message"}]}`. Bad rows are
reported per row instead of failing the batch; tags come from the authenticated account and cannot be set in the payload.
Credentials are verified exactly as for `login`/`heartbeat` (401 / 403 / 429 with the same reasons).

### Settings (Admin → Settings)
Non-technical admins configure everything from the panel; nothing needs editing in `.env`.

- **Telegram bot token** (stored encrypted, never shown again — only its last 4 characters) and **chat ID**.
- **Offline after (minutes)** and **Expiring soon (days)** thresholds.
- **Find my chat** lists chats that messaged the bot and fills in the chat ID with one click.
- **Send test message** confirms the setup works.

Saved values override config; `.env` / `config/*.php` remain only as defaults (e.g. `TELEGRAM_BOT_TOKEN`,
`TELEGRAM_CHAT_ID`, `LICENSE_ONLINE_THRESHOLD_MINUTES`, `LICENSE_EXPIRING_SOON_DAYS`) and are used when a setting is
empty or removed. Rotating `APP_KEY` makes the saved token unreadable, so re-enter it afterwards.

### Telegram notifications
Alerts are pushed by the app calling Telegram's `sendMessage` API (outbound HTTPS only) — no webhook or public URL needed.

1. On Telegram, chat with **@BotFather**, send `/newbot` and copy the token.
2. Paste it in **Admin → Settings → Bot token** and save. Send any message to your new bot (or add it to a group and post there).
3. Press **Find my chat**, click **Use this chat**, then **Save settings**.
4. Press **Send test message**.

**Bot already used elsewhere?** Telegram refuses to list chats (`getUpdates`) for a bot that has a webhook set, so
*Find my chat* shows an explanation instead. Sending messages still works — type the chat ID by hand (message
**@userinfobot** on Telegram and it replies with your ID), or give this app its own bot from @BotFather. The app
never deletes an existing webhook.

CLI equivalents: `php artisan telegram:chat-id` and `php artisan telegram:test` (they read the same saved settings).

| Event | When | How |
|---|---|---|
| Account went offline | No heartbeat for the threshold | `licenses:check-offline`, every minute (scheduler), once per outage |
| Back online | First login or heartbeat after an offline alert | Sent right after the API response |
| First login | The first time an account signs in via `login` | Sent right after the API response |
| License expiring soon | Within `LICENSE_EXPIRING_SOON_DAYS` | `licenses:check-expiring`, hourly, once per expiry (re-armed when you extend) |

If Telegram is not configured or unreachable the alerts simply stay pending and go out once it works; the bot API
and dashboard are never affected. The dashboard always lists expiring licenses and offline bots.

The alerts above depend on the scheduler: run `php artisan schedule:work` locally, and in production add the cron entry
`* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`.

### Tags
Licenses can be tagged (e.g. `vip, reseller, trial`) from the issue/edit form — comma separated, lowercased, max 10 per
license. Tags show as badges, are searchable, and the licenses list has a tag filter.

## Testing

```bash
php artisan test --compact
vendor/bin/pint
```
