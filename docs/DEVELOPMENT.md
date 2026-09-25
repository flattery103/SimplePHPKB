# Rob's development server and GitHub workflow

Use a separate dev site. These examples use:

| Item | Development | Existing production |
| --- | --- | --- |
| Hostname | `kb-dev.nutelecorp.com` | `kb.nutelecorp.com` |
| Application directory | `/www/kb-dev` | `/www/kb` |
| Apache document root | `/www/kb-dev/public` | `/www/kb/public` |
| Database | `kb_dev` | `kb` |
| Apache file | `kb-dev.nutelecorp.com.conf` | `kb.nutelecorp.com.conf` |

The dev hostname is a suggested new name. Create its internal DNS record pointing to the dev server, or add it to your workstation hosts file for testing. Do not point it at the production application/database. GitHub stores code and release downloads; your PHP site runs on the dev/production server.

## 1. Prepare the development server

Run this section as **root**. On your previously checked Ubuntu server, PHP/MySQL, the usual PHP modules and unzip are already present. Add Git, GitHub CLI, curl and zip if missing:

```bash
apt update
apt install git gh php-curl php-zip
php -m | grep -Ei '^(pdo_mysql|mbstring|dom|fileinfo|gd|ctype|iconv|curl|zip)$'
```

On a completely new Ubuntu server, also install Apache, PHP's Apache handler, MySQL and the required extensions using distribution-default package names (not `php8.3-*`). Your existing server uses PHP 8.5.

```bash
apt install apache2 libapache2-mod-php php-mysql php-mbstring php-xml php-gd mysql-server unzip
```

Configure the **web** PHP limits, not only CLI limits. With your Apache PHP 8.5 installation:

```bash
cat > /etc/php/8.5/apache2/conf.d/99-simplephpkb.ini <<'INI'
upload_max_filesize = 40M
post_max_size = 48M
memory_limit = 256M
max_execution_time = 180
display_errors = Off
log_errors = On
INI
```

If your new dev server has a different PHP version or uses PHP-FPM, adjust that configuration path and reload that PHP service. The server reload below applies this one-time configuration change.

## 2. Place the dev bundle

Upload **SimplePHPKB-v0.6.0-dev.zip** to `/home/robadmin/`. It includes source, tests, build scripts, the GitHub CI workflow, and the already-downloaded runtime dependencies. It contains no site configuration, credentials or employee content.

As root:

```bash
unzip /home/robadmin/SimplePHPKB-v0.6.0-dev.zip -d /home/robadmin/kb-dev-import
install -d -o robadmin -g www-data -m 2775 /www/kb-dev
sudo -u robadmin git clone https://github.com/flattery103/SimplePHPKB.git /www/kb-dev
cp -a /home/robadmin/kb-dev-import/SimplePHPKB/. /www/kb-dev/
chown -R robadmin:www-data /www/kb-dev
usermod -aG www-data robadmin
find /www/kb-dev -path /www/kb-dev/.git -prune -o -type d -exec chmod 2775 {} +
find /www/kb-dev -path /www/kb-dev/.git -prune -o -type f -exec chmod 0664 {} +
chmod 700 /www/kb-dev/.git
```

An empty-repository warning from `git clone` is expected for your first import. These commands assume `/www/kb-dev` was empty. If it already has files, choose a new directory rather than overwriting it blindly.

The web account can write app files so you can test web upgrades. The set-group-ID directories keep shared group ownership. On a dev checkout, web upgrades will modify tracked source; inspect and reconcile those changes before committing. New development should be made on Git branches. If your editor cannot modify a newly deployed file, restore developer ownership on this dev directory with the `chown` command above. The installer-generated config is private and intentionally not committed.

## 3. Create an isolated dev database account

As root, open MySQL:

```bash
mysql
```

Run the following SQL, replacing the sample password with one you choose:

```sql
CREATE DATABASE kb_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'kb_dev'@'127.0.0.1' IDENTIFIED BY 'REPLACE_WITH_YOUR_DEV_DB_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES, DROP
    ON kb_dev.* TO 'kb_dev'@'127.0.0.1';
EXIT;
```

Use this account in the installer, not the MySQL root account. A hosting control panel can perform the same database/account creation without a shell. The account has access only to `kb_dev`; it does not need access to production `kb`.

## 4. Add the domain-named Apache virtual host

For initial HTTP testing on your restricted internal dev network, run as root:

```bash
cat > /etc/apache2/sites-available/kb-dev.nutelecorp.com.conf <<'APACHE'
<VirtualHost *:80>
    ServerName kb-dev.nutelecorp.com
    DocumentRoot /www/kb-dev/public
    <Directory /www/kb-dev/public>
        Options -Indexes -Includes
        AllowOverride None
        DirectoryIndex index.php
        Require all granted
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/kb-dev.nutelecorp.com-error.log
    CustomLog ${APACHE_LOG_DIR}/kb-dev.nutelecorp.com-access.log combined
</VirtualHost>
APACHE
a2ensite kb-dev.nutelecorp.com.conf
apache2ctl configtest && systemctl reload apache2
```

This reload is for the new virtual host and PHP configuration. Code/CSS changes do not need Apache reloads. Do not disable or modify the production virtual host.

Use your organization's trusted HTTPS certificate before broader access. For plain HTTP testing, leave the installer's HTTPS checkbox unchecked; turn `secure_cookies` on in `config/config.php` when HTTPS is enabled. If your organization terminates HTTPS at a proxy, enable the checkbox from the beginning.

## 5. Run the installer and check the application

Open **http://kb-dev.nutelecorp.com/**. Fill in:

| Installer field | Value |
| --- | --- |
| Database host | `127.0.0.1` |
| Port | `3306` |
| Database name | `kb_dev` |
| Database username | `kb_dev` |
| Database password | The one you set above |
| Create database | Unchecked; it already exists |
| Site title | `SimplePHPKB Dev` |
| Base path | Leave blank |
| Timezone | `America/Chicago` |
| HTTPS | Unchecked for the HTTP example; checked if actually using HTTPS |
| Admin | Your chosen username and password, minimum 8 characters |

Click **Install knowledge base**, then sign in. Create a test article, publish it, search for it, upload an image, and test branding. Open **Application updates**, create and download a backup. Before your first GitHub Release is published, no update will be available; a bare commit is not a release.

There are no new SQL files in 0.6.0 beyond migrations 001–004. To validate actual database updates, use the included isolated test suite or a later test release; do not invent SQL changes in production.

## 6. Commit and push from your dev server

Switch to the developer account in a new login so group membership is refreshed:

```bash
sudo -iu robadmin
cd /www/kb-dev
git config user.name "Rob Campbell"
git config user.email "YOUR_GITHUB_COMMIT_EMAIL"
gh auth login --hostname github.com --git-protocol https --web --scopes workflow
gh auth setup-git
```

Follow the GitHub device-code instructions on your workstation browser. Use your GitHub account; do not put tokens into source files or command history. Use a verified email or your GitHub-provided private commit email. The workflow scope allows the initial CI workflow file to be pushed.

Review and create the first commit:

```bash
git status --short
git add .
git diff --cached --stat
git diff --cached --name-only
git commit -m "Initial SimplePHPKB release with browser installation and updates"
git branch -M main
git push -u origin main
```

Before the commit, ensure the staged list does **not** contain `config/config.php`, `storage/` content, ZIPs, SQL backups, `vendor/`, or `public/assets/tinymce/`. Their ignore rules are included. `config/config.example.php`, numbered SQL migrations and `storage/.gitkeep` are expected. Do not use `git add -f` on ignored private files.

If the repository received commits since you cloned it, stop and fetch/reconcile those changes instead of force-pushing. No remote commits or release publication are performed by the supplied app or by this walkthrough until you run these commands.

## 7. Publish a GitHub Release when dev testing passes

From the dev checkout as robadmin:

```bash
python3 scripts/package.py
python3 tests/package_test.py
git tag -a v0.6.0 -m "SimplePHPKB 0.6.0"
git push origin v0.6.0
```

Open **https://github.com/flattery103/SimplePHPKB/releases/new**, select `v0.6.0`, enter release notes, attach the generated files from `/www/kb-dev/dist/`, and publish it as the latest stable release:

- `SimplePHPKB-v0.6.0.zip` — new installations.
- `SimplePHPKB-v0.6.0-update.zip` — one-time bridge for old installations; subsequent releases of this format are browser upgrades.
- `SimplePHPKB-v0.6.0-source.zip` — source without installed dependencies.
- `SimplePHPKB-v0.6.0-dev.zip` — ready-to-run source and dependencies.
- `SHA256SUMS.txt` — checksums.

Use the project's attached ZIP assets for installation; GitHub's automatically generated source archives do not include runtime dependencies. ZIP build output stays out of Git history. Publishing a release, not merely pushing code, is what makes version checks useful. The initial 0.6.0 site correctly shows current after 0.6.0 is published; a later stable version will show an update notice. Wait up to six hours for cached checks or use **Check again** after its one-minute cooldown.

## 8. Later development

```bash
cd /www/kb-dev
git switch main
git pull --ff-only
git switch -c feature/describe-the-change
# Edit and test the code.
git add path/to/changed-file.php
git commit -m "Explain the change"
git push -u origin feature/describe-the-change
```

Open a pull request on GitHub and let the included CI run. CI is configured for PHP 8.3/8.5 against MySQL 8.4, but its first real GitHub execution happens after you push. After review and merge, pull main, bump `KB_VERSION` in `src/init.php`, update CHANGELOG.md, add a numbered retry-safe SQL migration if needed, rebuild/test packages, and publish the next versioned release. Existing installs discover that release and upload its update ZIP from the browser.

When cloning the source on a new machine instead of using the dev bundle:

```bash
composer install --no-dev --prefer-dist
python3 scripts/fetch_editor.py
```

These download pinned dependencies; they need internet access. For local automated tests, use only a disposable database with a name ending `_test`, never `kb` or `kb_dev`. Follow TESTING.md; the test runners intentionally reset their database.

## References

- [GitHub Releases API](https://docs.github.com/en/rest/releases/releases#get-the-latest-release)
- [GitHub CLI authentication](https://cli.github.com/manual/gh_auth_login)
- [GitHub CLI Git configuration](https://cli.github.com/manual/gh_auth_setup-git)
