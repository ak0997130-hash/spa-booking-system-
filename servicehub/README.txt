STILLWATER SPA (ServiceHub) - PHP + MySQL booking website

RUN ON YOUR COMPUTER (XAMPP)
1. Copy this folder to C:\xampp\htdocs\servicehub, start Apache + MySQL in XAMPP.
2. Open http://localhost/servicehub/setup.php and click "Install database".
3. Site:  http://localhost/servicehub/      Admin: http://localhost/servicehub/admin/login.php
   Starter admin: admin@servicehub.com / password  (change it; it is refused on a live site)
4. To try sign-up without email: Admin > Verification > tick "Local test mode".

PUT IT ONLINE: read DEPLOY.md, and upload servicehub_deploy.zip (not this development folder).

OTHER DOCS: OTP_SETUP.md (email verification, testing, troubleshooting), SECURITY.md (HTTPS, encryption, database TLS, login protection).
PHOTOS: upload in Admin > Treatments, or put JPG/PNG/WebP files in assets/img/ named after the treatment
(swedish-massage.jpg ... hero.jpg, about.jpg). Defaults load from Unsplash.
