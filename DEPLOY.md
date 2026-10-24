# Put the website online (deployment guide)

I can't upload the site to a server for you (that needs your hosting account), so this package is prepared for it: settings come from a `.env` file, the live site hides technical errors and forces HTTPS, there is no default admin password online, and a hosting-friendly database file is included. Follow the steps below.

**Use `servicehub_deploy.zip` for the server** (it contains only what a live site needs). Keep `servicehub.zip` for working on your own computer.

## What you need
- **Hosting** with PHP 8.0+ (7.4 minimum) and MySQL/MariaDB. Any normal shared hosting with cPanel (or similar) works, as does a VPS (section 3).
- **A domain name** pointing at the hosting, with an **SSL certificate** (HTTPS). Most hosts offer free SSL (AutoSSL / Let's Encrypt).
- **An email mailbox** on your domain (for example `no-reply@yourdomain.com`). The site uses it to send verification codes.

## 1. Shared hosting / cPanel (step by step)
1. **Create the database.** cPanel > *MySQL Databases*: create a database, create a user with a strong password, add the user to the database with **All Privileges**. Write down the three names exactly (hosts add a prefix, for example `cpuser_spa` and `cpuser_spauser`).
2. **Import the tables.** On your computer unzip `servicehub_deploy.zip` and find `database_hosting.sql`. cPanel > *phpMyAdmin* > click your new database > *Import* > choose that file > *Go*. (It creates the tables and the treatments. It creates no database and no admin.)
3. **Upload the site.** cPanel > *File Manager* > open `public_html` (or a sub-folder such as `public_html/spa`). Click *Upload*, add `servicehub_deploy.zip`, then right-click it > *Extract*. In File Manager settings tick **Show Hidden Files** so you can see `.htaccess` and `.env.example`. Delete the zip and `database_hosting.sql` from the server afterwards.
4. **Choose the PHP version.** cPanel > *Select PHP Version* (or *MultiPHP Manager*): pick 8.1 or newer. Make sure `pdo_mysql` (mysqlnd) and `openssl` are ticked.
5. **Turn on HTTPS.** cPanel > *SSL/TLS Status* > run AutoSSL for your domain, then open `https://yourdomain.com` and check the padlock. Do this **before** step 6, because the site forces HTTPS in production.
6. **Create the settings file.** In File Manager copy `.env.example` to `.env` (same folder as `index.php`) and edit it:
   - `APP_ENV=production`
   - `DB_HOST=localhost` (use the host your provider gives if different), `DB_NAME`, `DB_USER`, `DB_PASS` from step 1.
   - `DATA_KEY=` 64 random hex characters (create with `php -r "echo bin2hex(random_bytes(32));"` on any computer with PHP, or an online random-hex generator). It encrypts customer names, phones and booking notes. **Keep a copy somewhere safe: without it that data is lost.** If the database is on another server, also set `DB_SSL=1` and `DB_SSL_CA` (see `SECURITY.md`).
   - `ADMIN_SETUP_KEY=` a long random phrase (12+ characters). It is used once, in step 7.
   - **Email, simplest option:** set `MAIL_DRIVER=mail` and `MAIL_FROM=no-reply@yourdomain.com` to send with PHP's `mail()` function (works on most shared hosting; it cannot confirm delivery, so test with a real inbox and check spam). **Email, more reliable option (default):** SMTP with `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION`, `MAIL_USER`, `MAIL_PASS`, `MAIL_FROM`: create the mailbox in cPanel > *Email Accounts*, then use its *Configure Email Client* details (typically host `mail.yourdomain.com`, port 465, SSL, username = the full address).
   - Behind Cloudflare or another proxy that terminates HTTPS, also set `TRUST_PROXY=1`.
7. **Create your admin account.** Open `https://yourdomain.com/admin/setup-admin.php`, enter the `ADMIN_SETUP_KEY`, your email and a strong password (10+ characters with upper-case, lower-case and a number). Then **delete the `ADMIN_SETUP_KEY` line from `.env`**. (The well-known default admin password is refused on a live site.)
8. **Make it yours.** Sign in at `/admin/login.php`:
   - *Settings > Business details*: name, phone, email, address, opening hours, home page text.
   - *Treatments*: edit prices and **upload your own photos**. The default photos are loaded from Unsplash; your own are faster and always available. For the home page and About photos, upload `hero.jpg` and `about.jpg` into `assets/img/` with File Manager.
   - *Verification > Send test email* to confirm email works.
   - *Security*: every row should say **Good** (HTTPS, cookie, key, encryption). "Info" for the database connection is normal when the database is on the same server.
9. **Test like a customer.** Open the site in a private window: register with a real email, receive the code, verify, register, log in, book a treatment, then check it appears in *Admin > Bookings*.

## 2. After launch
- **Email deliverability:** send from an address on your own domain. In cPanel > *Email Deliverability* make sure SPF and DKIM show as valid, so codes don't land in spam. A free Gmail account (smtp.gmail.com, App Password) works for testing but has daily limits; for real traffic prefer your hosting mailbox or an email-sending service.
- **Backups:** export the database from phpMyAdmin regularly (and keep a copy of `assets/img/`). Many hosts also offer automatic backups.
- **Updating the site:** upload new files over the old ones but **never overwrite `.env`**. If an update includes new files in `migrations/`, run them once in phpMyAdmin (in order). When upgrading from a version without encryption, run `003_encrypted_fields.sql`, then press **Admin > Security > Encrypt existing data** once.
- **Time zone:** bookings use India time. For another region change `date_default_timezone_set('Asia/Kolkata')` near the top of `includes/config.php`.
- **Booking capacity and time slots** (`SLOT_CAPACITY`, `SLOTS`) are at the top of `includes/config.php`.

## 3. VPS (Ubuntu) instead of shared hosting
```
sudo apt update && sudo apt install -y apache2 php php-mysql php-mbstring mariadb-server certbot python3-certbot-apache unzip
sudo mysql_secure_installation
sudo mysql -e "CREATE DATABASE spa CHARACTER SET utf8mb4; CREATE USER 'spa'@'localhost' IDENTIFIED BY 'CHANGE-ME'; GRANT ALL ON spa.* TO 'spa'@'localhost';"
sudo mysql spa < database_hosting.sql
sudo unzip servicehub_deploy.zip -d /var/www/html/        # or a virtual-host folder
sudo cp /var/www/html/.env.example /var/www/html/.env     # then edit it (section 1, step 6)
sudo a2enmod rewrite headers && sudo chown -R www-data:www-data /var/www/html && sudo chmod 640 /var/www/html/.env
```
In your Apache site config set `AllowOverride All` for the folder (the `.htaccess` files protect `.env`, SQL files and `includes/`), then `sudo systemctl restart apache2` and run `sudo certbot --apache -d yourdomain.com`. Continue at step 7 above.

**Nginx** does not read `.htaccess`. Add equivalent rules, or the secrets would be downloadable:
```
location ~ /\.(?!well-known) { deny all; }
location ~* \.(sql|md|txt)$   { deny all; }
location ^~ /includes/        { deny all; }
location ^~ /migrations/      { deny all; }
location ~* ^/assets/img/.*\.php$ { deny all; }
```

## 4. Security checklist
- [ ] `APP_ENV=production` and the padlock (HTTPS) works; Admin > Security shows no "Action needed"
- [ ] `DATA_KEY` set in `.env` and a copy stored safely away from the database backups
- [ ] `ADMIN_SETUP_KEY` removed from `.env` after step 7; your own strong admin password
- [ ] `.env` and `database_hosting.sql` are not downloadable (open `https://yourdomain.com/.env` and `/database_hosting.sql`: both must be "403 Forbidden" or "404", never the file)
- [ ] `setup.php`, `migrate.php`, `admin/reset.php` and `tests/` are not on the server (they are not in the deploy package)
- [ ] Verification email arrives; Local test mode is **off** (it is ignored in production anyway)
- [ ] Database backups scheduled

## 5. Troubleshooting
| Problem | What to do |
|---|---|
| "500 Internal Server Error" right after upload | The host may not allow some `.htaccess` rules. Rename `.htaccess` (and the ones inside `includes`, `migrations`, `assets/img`) to test; if the site then loads, ask the host to allow `AllowOverride All`, and meanwhile protect those folders another way. Also check the PHP version (step 4) and the `error_log` file in the folder. |
| "The site is temporarily unavailable" | The database settings in `.env` are wrong (name, user, password, or host). Details are written to the server's `error_log`, never shown to visitors. |
| Page is unstyled / links broken | Open the site through its real address. The site detects its folder automatically; a sub-folder install works. |
| Browser says "too many redirects" | HTTPS is not set up yet, or a proxy/CDN hides it: finish step 5, or set `TRUST_PROXY=1` (Cloudflare "Flexible SSL" causes this loop; use "Full" SSL). |
| "Database update needed" | You imported an older SQL file. Run the files in `migrations/` in phpMyAdmin, in order. |
| Code email does not arrive | Admin > Verification > *Send test email*: it shows the exact reason. With `mail()` the server may accept the message and still not deliver it: use a From address on your own domain, check SPF/DKIM and spam, or switch to SMTP. Check the mailbox password, host, port/encryption pair (465+SSL or 587+TLS), spam folder, and that your host does not block outgoing SMTP. |
| Can't sign in as admin | Add `ADMIN_SETUP_KEY` to `.env` again and repeat step 7 (it replaces the admin account). |
| Photos won't upload | The `assets/img` folder must be writable (permissions 755, owned by your account). |
