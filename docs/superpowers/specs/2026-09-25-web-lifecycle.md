# Browser installation and upgrades

Rob wants SimplePHPKB ready for its public GitHub repository, with installation and future upgrades available without a shell. Continue native PHP/MySQL and retain existing employee KB features. Deliver source and ready-to-upload packages plus dev-server and Git publishing instructions; Rob will put the code on his dev server and publish it.

## Installation

The unconfigured front controller shows a prerequisite check and a single setup form: database host/port/name/user/password, optional database creation, site title, base path, timezone, HTTPS cookies, first admin. Default database is `kb`. PHP 8.3+, existing extensions, bundled dependencies, writable config/storage are required. Zip is required for browser ZIP upgrades; curl is needed for release checks. Installer generates the config outside public, runs the retry-safe migrations, creates the first admin, and disables itself. Refuse an existing nonempty database. Serialize installation, record pending setup before migrations, allow the same browser session to resume after a failure, and never reopen installation when a config exists. Server/database-account provisioning remains a hosting task. The installer is reachable only on the restricted dev/internal site until claimed.

## Updates

An authenticated Updates page is independent of branding/category schema. It remains usable during pending migrations. Non-update requests return maintenance 503 while schema or file updates are pending. A shared filesystem request lock prevents cooperative requests during exclusive backups/file changes/migrations. CLI migrations remain supported.

Admins can upload a full, versioned update ZIP, review its version/file count, download a backup, apply files, and run SQL migrations in a new request. The new request loads new PHP before running migration code. All mutations require CSRF. Packages include a manifest with SHA-256 hashes, app identity, package format and minimum installed version; only approved app paths may be overwritten. No config/storage/server settings are accepted; reject traversal, duplicate paths, symlinks, oversized expansion, invalid versions, mismatched hashes, and downgrades. Manifest hashes detect corruption, not publisher identity: use trusted releases only. Persist update state before changes, retain staged files and backup on failure, permit resuming file copies and migrations. No automatic rollback of MySQL DDL. No automatic remote code download/execution.

Backup is a private ZIP of database SQL, application files, configuration and uploaded media (excludes previous backups/cache). Native PDO generates the SQL without shell tools. App migrations must remain retry-safe and keep the admin authentication tables compatible with the update controller. Single server deployment only; not shared multi-node execution. Large sites may need hosting-provider backups and increased PHP resource limits.

## GitHub check and delivery

Check `https://api.github.com/repos/flattery103/SimplePHPKB/releases/latest` from admins only, bounded timeout/response size, TLS verification, no redirects, no token required for this public repo. Cache for six hours, allow a manually rate-limited refresh, ignore drafts/prereleases and validate semantic versions/links. Distinguish no release, unreachable, update available, current/ahead. Failures never prevent KB use. Show a notice in admin and link to the release. Git tags alone do not trigger notices.

Version 0.6.0. Future update ZIPs contain runtime dependencies and a manifest. Source package includes tests/build scripts/GitHub CI; excludes installed config, content, build dependencies and repository history. The dev guide uses `kb-dev.nutelecorp.com`, `/www/kb-dev`, database `kb_dev`, domain-named Apache config, developer-owned checkout and web-writable runtime paths. Production stays untouched. Guide covers first commit/push to currently empty main and later feature branches/release assets. Do not publish from this session; user requested a walkthrough to publish from dev.

## Acceptance evidence

Exercise real HTTP install and lockout, migrations and authenticated update upload/review/apply, resume after interruption, bad CSRF/auth, malicious ZIPs, config/media preservation, SQL backup restore, cache/release status parsing, existing service and HTTP suites, fresh and overlay packages. Browser rendering and PHP 8.5/MySQL 8.4 are reported separately if unavailable.
