# Email verification (OTP): how it works and how to test it

When someone registers, the site emails a 6-digit code and creates the account only after the server confirms it. The mobile number is collected as a normal field (format-checked, **not** verified by SMS).

## Quick start on your own computer (XAMPP)
1. Run `setup.php` once (or `migrate.php` if you have an older database), then sign in at `/admin/login.php`.
2. Open **Admin > Verification**. To just try sign-up, tick **Local test mode** and save: the code then appears on the page instead of being emailed (works only at `http://localhost`, never on a live site).
3. For real emails, fill in the Email (SMTP) form and press **Send test email**. If it fails, the page says why.

For going online, follow `DEPLOY.md`.

## Email (SMTP) settings
Gmail (testing): turn on 2-Step Verification, create an **App Password**, then host `smtp.gmail.com`, port `587`, encryption TLS, username = your address, password = the 16-character app password.
Your own domain: create a mailbox in your hosting panel and use the host/port it shows (usually `mail.yourdomain.com`, 465, SSL).
Settings can also live in `.env` (see `.env.example`), which takes priority over Admin > Verification.

## Sending with PHP mail() instead of SMTP
Besides SMTP, the code can be sent with PHP's built-in `mail()` function. Choose it in **Admin > Verification > Sending method > PHP mail() function** (or set `MAIL_DRIVER=mail` in `.env`). It needs only a **From email**; the SMTP fields are ignored. SMTP stays the default.

- **Important:** `mail()` only hands the message to the mail program of the server. It cannot tell whether the email was delivered, so after "Send test email" check the inbox and the spam folder.
- **Customers' addresses:** codes go to whatever email the customer types, including Gmail addresses. Nothing is restricted to Gmail.
- **Shared hosting:** `mail()` usually works as is. Use a From address on your own domain (for example `no-reply@yourdomain.com`) and make sure SPF/DKIM are valid, otherwise Gmail may put the code in spam or reject it. A few hosts switch `mail()` off; then use SMTP.
- **XAMPP on Windows** (`mail()` needs a sendmail program; XAMPP includes one that can relay through Gmail). Exact lines can differ between XAMPP versions:
  1. Gmail: turn on 2-Step Verification and create an **App Password** (Google Account > Security > App passwords).
  2. Edit `C:\xampp\sendmail\sendmail.ini` (section `[sendmail]`): `smtp_server=smtp.gmail.com`, `smtp_port=587`, `smtp_ssl=auto`, `auth_username=you@gmail.com`, `auth_password=<the 16-character app password>`, `force_sender=you@gmail.com`.
  3. Edit `C:\xampp\php\php.ini`, section `[mail function]`: put a `;` in front of the `SMTP=` and `smtp_port=` lines, then set `sendmail_path = "\"C:\xampp\sendmail\sendmail.exe\" -t"` and `sendmail_from = you@gmail.com`.
  4. Restart Apache in the XAMPP Control Panel. Then in Admin > Verification choose PHP mail(), enter the same Gmail address as From email, save, and send a test email. If nothing arrives, read `C:\xampp\sendmail\error.log`.
- **If sending fails** the page says "PHP mail() could not hand the message to the mail system" (customers only see a generic message, and only you, on localhost, see that detail).

## How it works
- Codes: `random_int`, 6 digits, valid **5 minutes**, **60 s** resend cooldown, **5** wrong tries then locked, single use. A new code cancels the old one and any earlier verification.
- Stored only as an HMAC-SHA256 hash keyed with `APP_SECRET` (created automatically), never in plaintext, URLs, browser storage or logs. Old challenges are deleted after a day.
- A code is bound to the exact email address **and** the browser session that requested it; registration re-checks the database in a transaction and consumes the code. Browser-sent "verified" flags are never trusted.
- Limits per hour: 20 requests per IP, 10 per session, 5 per email address; verification attempts are limited too.
- Already-registered emails get the same "code is on its way" reply but nothing is sent (no account enumeration).
- Passwords: 8+ characters with upper-case, lower-case and a number; Argon2id (or bcrypt). Sessions: HttpOnly, SameSite=Lax, Secure on HTTPS. CSRF tokens on every POST. Prepared statements everywhere. Form input is sanitised centrally (arrays, binary and oversized values are neutralised).
- On a live site (`APP_ENV=production`): HTTPS is forced, PHP errors and database details are hidden, localhost-only tools and Local test mode are off, and the default admin password is refused.

## Testing checklist
| Test | Expected |
|---|---|
| Send email code and enter it | "Email verified" appears only after the server confirms; Register enables |
| Wrong code | "Incorrect code. N attempt(s) left." Locked after 5 |
| Code older than 5 minutes | "That code has expired" |
| Reuse a code | Rejected |
| Resend within 60 s | Button disabled with countdown (API: 429) |
| Change the email after verifying | Verified badge disappears; verify again |
| Register with an already-used email | No code arrives; cannot register |
| Weak password, bad mobile number, mismatch | Field-specific errors; other inputs kept |
| Call registration directly without verifying | Refused, no account created |

Automated tests (development only): `tests/` holds mock providers and suites. Start `python3 tests/mock_providers.py`, then PHP with `MAIL_HOST=127.0.0.1 MAIL_PORT=2525 MAIL_ENCRYPTION=none MAIL_FROM=x@y.test APP_SECRET=<64 hex> OTP_LIMIT_IP=100000`, then `python3 tests/test_otp.py main` (the `N*` tests expect a pre-OTP customer `old1@x.com` / `oldpass1` with one booking). `python3 tests/audit.py` crawls every page and fuzzes every form. Do not upload `tests/` to a live server.

## Troubleshooting
| Symptom | Fix |
|---|---|
| "Setup needed" notice on sign-up (only you see it) | Open Admin > Verification, or switch on local test mode |
| "Database update needed" | Run `migrate.php` on your computer (or the `migrations/` SQL files in phpMyAdmin) |
| "Email delivery is not set up" | Fill in Admin > Verification (or `.env`) |
| "SMTP Error: Could not authenticate" | Wrong username/password (Gmail needs an App Password) |
| "SMTP Error: Could not connect" | Wrong host/port/encryption, or outgoing SMTP is blocked |
| "certificate verify failed" | The bundled `includes/cacert.pem` is used automatically; otherwise set `openssl.cafile` in php.ini (local testing only: `MAIL_VERIFY_PEER=0`) |
| "Could not write includes/local.settings.php" | Make `includes` writable, or use a `.env` file |
| Code not arriving | Check spam; use Send test email for the exact reason |
