# SimplePHPKB 0.7.0

This release adds an admin Usage page and all-time view counts to Manage articles. It includes migration 005 and can be installed through the browser updater in 0.6.0.

## Update the development site

1. Open your existing dev site and sign in as an admin.
2. Select **Application updates** in the admin sidebar.
3. Upload **SimplePHPKB-v0.7.0-update.zip**, then choose **Upload and review**.
4. Choose **Create backup**, download the backup, and confirm that you saved it.
5. Choose **Apply application files**.
6. Choose **Apply database changes and finish**. This creates the new usage tables without resetting existing article counts.
7. Open **Usage** in the sidebar. Choose **All time** to see existing article views. Visit the site/article in a separate signed-out browser session, then reload **Usage → Today** to see the new counts.

No Apache reload, manual config edits or command-line SQL is required. Repeated reads in the same browser session/day count once. Your signed-in admin browsing does not add views. New site/date-based counts begin at upgrade time; earlier article counts only appear under All time. Reporting days use UTC. See [USAGE.md](USAGE.md).

If you have locally edited distributed templates, preserve those changes before applying any release: update packages replace application code and templates. Configuration and uploaded media are preserved.

## Validation

Verified locally with PHP 8.3.6 / MariaDB 10.11.14:

- Full service suite, including per-day/session deduplication, exclusions, UTC range boundaries, zero-view articles, pagination, historical totals and replay of migration 005.
- Existing reader/admin HTTP suite and new Usage HTTP checks at both `/` and `/kb`.
- Concurrent requests from the same and different sessions, and concurrent reads of different articles with expired deduplication rows.
- A failed article statistics write rolls back the counter transaction while the article remains readable.
- Fresh browser installation, update backups/SQL restoration, maintenance gating, interrupted file/SQL recovery, and independent recovery with a broken live Composer autoloader.
- Actual 0.6.0 distribution updated through multipart ZIP upload, downloaded backup, file application and browser SQL migration; existing config/media/lifetime counts and pre-upgrade session deduplication are preserved.
- PHP syntax, package boundaries and manifest hashes. Runtime dependencies match the existing 0.6.0 distribution.

A focused read-only review found no material issues. PHP 8.5 / MySQL 8.4 coverage is configured in GitHub Actions. Browser rendering was not visually verified in the build environment; inspect the report on your dev site before employee rollout.

The optional historical upgrade test can be run against a disposable `_test` database after building the release:

```bash
KB_BASELINE_ZIP=/path/to/SimplePHPKB-v0.6.0.zip python3 tests/usage_upgrade_http.py
```

It uses the same `KB_TEST_DSN`, `KB_TEST_USER` and `KB_TEST_PASSWORD` variables as the other HTTP tests and resets that test database.
