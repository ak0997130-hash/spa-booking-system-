# Website security: what is protected and how

Open **Admin > Security** at any time. It shows each protection below as *Good*, *Action needed* or *Info* for your server.

## At a glance
| Area | What protects it | You do |
|---|---|---|
| Login, sign-up, customer area, admin | HTTPS (encrypted traffic), forced redirect + HSTS, locked-down cookie, no caching of private pages | Live site: nothing (automatic with `APP_ENV=production`). Own PC: optional (section 1) |
| Database connection | Optional TLS between website and database; the site **refuses to run** if TLS was required but is not active | Only if the database is on another server: `DB_SSL=1` (section 2) |
| Customer data in the database | Names, phone numbers and booking notes are **encrypted (AES-256-GCM)**; a stolen database dump or backup shows unreadable text | Back up your `DATA_KEY` (section 3) |
| Passwords | Hashed with Argon2id/bcrypt (never stored or recoverable) | Nothing |
| Login attacks | 5 wrong passwords for one email (or 30 from one address) lock sign-in for 15 minutes; same for the admin; unknown emails behave the same | Nothing |
| Stolen/forgotten sessions | Automatic sign-out: admin after 30 min, customers after 2 h of inactivity, any session after 12 h | Optional tuning in `.env` |
| Injected scripts (XSS) | Strict Content-Security-Policy: only scripts from your own site run, no inline scripts; frames, plugins and foreign form targets blocked | Nothing |
| The code itself | Never sent to visitors; secrets live outside it (section 5) | Keep `.env` private |

## 1. HTTPS
- **Live site:** set `APP_ENV=production` (or `FORCE_HTTPS=1`). Every page is redirected to HTTPS, HSTS tells browsers to stay on HTTPS, the session cookie becomes a `__Host-` cookie (works only over HTTPS, only for your exact domain) and the policy upgrades any stray insecure request. Get the free SSL certificate from your host first (see `DEPLOY.md`).
- **Your own computer (optional):** XAMPP's Apache already has an HTTPS port with a self-made certificate. Open `https://localhost/servicehub/` and accept the browser's one-time warning. To force it, put `FORCE_HTTPS=1` in a `.env` file in the site folder. Do **not** set it unless that address works, or the site becomes unreachable.

## 2. Encrypted database connection (TLS)
It matters when the database is on a **different machine** than the website (some hosts and cloud databases). When both are on the same server (typical shared hosting, or XAMPP) the traffic never leaves the machine and TLS adds nothing; Admin > Security then says "Not encrypted" as *Info*, which is fine.
Settings (server environment or `.env`):
```
DB_HOST=db.example.com        # a real host name or 127.0.0.1; NOT "localhost" (that uses a socket, which cannot use TLS)
DB_SSL=1
DB_SSL_CA=/path/to/ca.pem     # the database provider's CA certificate: lets the site verify it is talking to the real server
```
Optional: `DB_SSL_VERIFY=0` (encrypt without verifying, weaker), `DB_SSL_CERT` + `DB_SSL_KEY` (client certificate). With `DB_SSL=1` the site checks that the connection really is encrypted and otherwise shows an error instead of continuing. To make the *database* insist on it, create the database user with `REQUIRE SSL`. MariaDB/MySQL server side (XAMPP: `C:\xampp\mysql\bin\my.ini`, section `[mysqld]`): `ssl-ca=...`, `ssl-cert=...`, `ssl-key=...`, then restart MySQL.

## 3. Encrypted customer data
- **Encrypted:** customer name, mobile number, and the address/notes of every booking. **Not encrypted on purpose:** the email address (it is the login name and where codes are sent), treatment, date and status. Passwords are hashed.
- **Key:** `DATA_KEY` (64 hex characters). Put it in `.env` on a live site (`php -r "echo bin2hex(random_bytes(32));"`). If none is set, a key is created and saved in `includes/local.settings.php`.
- **BACK THE KEY UP** somewhere separate from your database backups. Without it the encrypted data can never be read again. Changing the key later makes old data unreadable (Admin > Security warns about this).
- **Upgrading an existing site:** run the database update (`migrate.php` on your PC, or the files in `migrations/` in phpMyAdmin, including `003_encrypted_fields.sql`), then press **Admin > Security > Encrypt existing data** once. New customers and bookings are encrypted automatically; old rows keep working until you press it.
- **Searching in the admin** still works (it decrypts and searches the latest 1,000 customers/bookings).
- **What it does and does not stop:** it protects against leaked database dumps, backups, copied files and SQL-injection data theft. It does not protect against someone who takes over the whole server (the website must be able to decrypt to show the data).

## 4. Logins, sessions and pages
Wrong-password lockout, automatic sign-out, a fresh session id at each login, CSRF tokens on every form, the starter admin password refused on a live site, and no caching of private pages are all built in. Tuning keys (`.env`): `ADMIN_IDLE_SECONDS`, `USER_IDLE_SECONDS`, `SESSION_MAX_SECONDS`. Scripts now live in `assets/js/app.js` so pages need no inline scripts; styles may still be inline. If you add a third-party script (analytics, chat), add its address to the policy in `includes/config.php`, or set `CSP=report` to only report instead of blocking.

## 5. "Encrypted code" (about hiding the PHP source)
PHP source code is **never sent to visitors**: the server runs it and sends only the finished page. What keeps it (and your secrets) safe is access control, and that is in place: `.htaccess` rules block `.env`, `includes/`, SQL files and tests; the saved-settings file starts with an exit line so it prints nothing even if requested; secrets are in `.env`, not in code; the deploy package leaves out setup/test files.
Scrambling or "encrypting" the code does **not** make the website safer against attacks, and the usual do-it-yourself tricks (`eval(base64_decode(...))`) are exactly what malware looks like, so hosts' virus scanners delete or quarantine such files. I did not do this. If you want to hide the code from someone who has the files (a client, a hosting company), use a commercial encoder such as ionCube or SourceGuardian on the **final deploy copy**; it needs a loader on the server, many cheap hosts do not offer it, and it makes updates and debugging harder.

## 6. Honest limits
- Not a professional penetration test; no firewall/WAF; no two-factor login for the admin yet (use a long unique admin password).
- Email addresses are stored readable; customer names/phones/notes are encrypted but the key sits on the same server as the site.
- Database TLS was tested against a local MariaDB that requires TLS, not against managed cloud databases. The strict CSP was checked by scanning every page, not in every real browser.
