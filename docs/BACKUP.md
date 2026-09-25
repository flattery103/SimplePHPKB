# Backups and recovery

## Browser backup

Open **Application updates → Create backup → Download backup**. No shell tools are used. The private ZIP contains `database.sql` and a `SimplePHPKB/` folder with application code, dependencies, configuration, and stored images. It includes admin password hashes and database credentials; keep it private and off the web root.

The backup uses a database transaction and an exclusive application request lock. It covers the app's tables and data, not database server accounts, custom triggers, routines, or events. Use a hosting/provider backup for those additions or very large installations. External scripts and other nodes must not write to the database/storage while backing up.

The server retains the latest ZIP at `storage/updates/backup.zip`, outside the public directory. Download a dated copy each time. Previous ZIPs are not retained on the server. Backups/cache are excluded from backup recursion. Upgrades preserve the pre-update ZIP until another backup/update starts.

## Restore using a hosting control panel

1. Restrict access to the site and preserve the failed installation for investigation.
2. Extract the saved backup on your computer or using the file manager.
3. Restore its `SimplePHPKB/` files into a clean app directory; the document root remains `public/`. Do not overlay old code on newer code because obsolete files may remain.
4. Create a new empty database and account. Import `database.sql` through the hosting database panel (such as phpMyAdmin). Increase panel import limits or ask the hosting provider for a large database.
5. Update `config/config.php` for the restored database and storage directory. Keep the previous base path to preserve stored image URLs. The PHP account must be able to read the configuration and write storage; use INSTALL.md for web-update permissions.
6. Open `index.php?route=updates`, sign in, and apply pending migrations only if the restored application requires them.
7. Verify a published image/article, a private draft, search and admin editing before reopening access.

The backup does not recreate the MySQL service account or web-server configuration. Restore code and database together. Replacing only old PHP files does not undo schema changes.

## Optional CLI backup

Existing hosting backups or `mysqldump --single-transaction kb` plus a matching file/config backup remain supported. Restrict application and external writes during the combined snapshot. Store backups outside `public/` and never commit them to GitHub.

Publication history and Trash are retained indefinitely. Unreferenced images remain stored. There is no permanent-delete or automatic image-purge feature.
