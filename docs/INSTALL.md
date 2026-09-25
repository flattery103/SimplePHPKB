# Install SimplePHPKB 0.6.0 in a browser

Download `SimplePHPKB-v0.6.0.zip` from the project's GitHub Releases page. It includes the PHP dependencies and local HTML editor. No Composer, Node, shell access, or editor API key is needed to run the installer.

## 1. Prepare hosting once

Your hosting provider or server administrator must supply:

- PHP 8.3+ with PDO MySQL, mbstring, DOM/XML, fileinfo, GD, ctype and iconv. Enable **zip** for browser backups/upgrades and **curl** for GitHub release checks.
- MySQL 8.0+ or MariaDB 10.11+, an empty database (suggested name `kb`), and an account with permissions on that database. The normal app and installer use the same account. Grant SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES, DROP on this database only.
- A website whose document root is the app's **`public/`** directory. Configuration and storage must be outside the served root. No rewrite rules are needed.
- Write access for the PHP/web-server account to `config/` and `storage/`. Browser file upgrades additionally need write access to the app root and its `src/`, `templates/`, `database/`, `public/`, `vendor/`, `bin/` and `docs/` directories and their files.

The app cannot install PHP, configure Apache, or create a database account without hosting privileges. A control panel can provide these; a shell is optional. Keep the unconfigured site restricted to the intended installer until setup finishes. Anyone who can reach a fresh, unconfigured site can start setup.

Recommended web PHP limits (the current update ZIP is about 3 MB):

```ini
upload_max_filesize = 40M
post_max_size = 48M
memory_limit = 256M
max_execution_time = 180
display_errors = Off
log_errors = On
```

Set these in your hosting panel or web PHP configuration. A server reload is needed only when applying server/PHP configuration changes. No Apache reload is needed for ordinary app changes.

## 2. Upload the application

Extract the release on your computer and upload the contents of `SimplePHPKB/` to your app directory using SFTP or a hosting file manager. Alternatively upload the ZIP and use the file manager's Extract operation. For Rob's existing production server, the app directory is `/www/kb` and the document root is `/www/kb/public`; do not use that directory for development.

Do **not** create `config/config.php` for a fresh browser installation. The installer writes it for you. Do not place a release ZIP or database backup inside `public/`.

## 3. Complete the browser installer

Open the site's URL. The installer checks requirements, then asks for:

1. Database host, port, name, username and password. Use the details from your hosting provider. The database must be empty. Check **Create the database** only if that account has permission to create it; database-user creation stays with the hosting provider.
2. Site title; base path (blank for a domain root); timezone, such as `America/Chicago`; and whether the site uses HTTPS. Enable the HTTPS checkbox when TLS terminates at a reverse proxy too.
3. First administrator username, display name and password (at least 8 characters, at most 72 bytes).

Choose **Install knowledge base**. This writes private configuration, runs all SQL migrations, creates the admin and General category, and takes you to sign-in. There is no default password. Settings, article creation and all future database updates are available in the browser.

If installation is interrupted, retry in the **same browser using the same details**. A private pending record prevents a second browser from claiming a partially installed database. If that browser/session is lost, a server administrator should inspect `config/setup.pending.php`, restore its saved `settings` array as `config/config.php`, and complete account recovery with the CLI if needed. For a disposable failed development install only, you may instead empty that development database and remove the pending file to restart. Never reset a database containing real KB content.

An installed site does not expose the setup form. If its configuration is lost, restore the configuration from backup. Do not delete `config/installed.lock` to reinstall a live site.

## 4. First checks

Sign in, create a category and article, publish it, then open a signed-out browser to confirm readers can view and search it. Open **Application updates** to test backup creation and download. Branding, category images, the ticket link and notices remain in Settings.

## Settings, category images, and featured articles

Admins can open **Settings** in the left sidebar to upload a PNG, JPEG, or WebP logo (up to 2 MB / 4 megapixels), change the site title, and set or clear the subtitle. The title appears in the header, browser tab, and footer; the subtitle appears below the header title. The current configuration title remains the default until branding is first saved.

Under **Settings → Homepage list**, enable **Use compact rows on the homepage** to reduce row padding while keeping titles, summaries, category images, and category names at their regular sizes. Only featured labels and update dates are hidden; starred articles still lead the same ten-entry list. The initial setting is regular mode. Category browsing and search results retain their regular detail.

Employees may enter full questions such as “My Outlook won’t start.” Search ignores common conversational words and includes partial matches, ranking articles matching more meaningful words first. Titles and **Search keywords** receive more weight than article body text. Add alternate terms in the article editor and publish when employees use different wording; no external search service is needed.

Under **Settings → Header link**, check **Show a link in the header**, set **Link text** and the full **Link URL**, and choose **Current window/tab** or **New window/tab**. The link is visible to readers and admins beside the sign-in/sign-out control.

Under **Settings → Known-issues banner**, check **Show the known-issues banner** and enter a message (plain text, up to 1,000 characters). Click **Save settings**. The banner appears below the header throughout the site until disabled. Both options default to off and retain their saved details when disabled.



In **Manage categories**, optionally upload an image when adding or editing a category. PNG, JPEG, and WebP are supported up to 2 MB / 4 megapixels. A square image around 96 × 96 pixels works well. The category name appears below the icon beside each article; categories without images show a question mark. Use the remove checkbox to return to the default.

In **Manage articles**, click the hollow star / **Feature** button beside an article. A filled star means the article is featured. Click again to remove the feature. Only published articles appear on the homepage. The existing list holds ten articles total, with featured articles first and popularity filling the remaining positions. Multiple featured articles are ordered by visits, then publication date. Search and category browsing keep their usual ordering.


## Optional command-line maintenance

Existing automation can still use `php bin/kb.php migrate`, `php bin/kb.php admin:create USERNAME`, and `php bin/kb.php admin:reset USERNAME`. Passwords are read interactively or from stdin. Run CLI maintenance while site access is restricted; browser request locks do not coordinate arbitrary CLI scripts.

## Troubleshooting

- Login returns to sign-in: check the HTTPS cookie setting. Secure cookies cannot be used over plain HTTP.
- Setup fails a requirement: correct it in the hosting panel or ask the server administrator. Database passwords are deliberately not echoed back into the form.
- Update upload is rejected: use the release's `-update.zip`, and check both PHP and reverse-proxy upload limits.
- GitHub cannot be reached: the KB and manual ZIP uploads still work. The server needs outbound HTTPS to `api.github.com` for release checks.
- A generic error: inspect the PHP/web-server error log. Errors are not printed to employee pages.

For Rob's separate dev server and GitHub publishing commands, see [DEVELOPMENT.md](DEVELOPMENT.md). For upgrades, see [UPGRADING.md](UPGRADING.md).
