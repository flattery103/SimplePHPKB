# Verification for 0.7.0

The full service suite, existing HTTP workflows, Usage HTTP tests at `/` and `/kb`, concurrency checks and web installation/update lifecycle tests pass on PHP 8.3.6 / MariaDB 10.11.14. An actual 0.6.0 installation also passed the browser ZIP update to 0.7.0, including migration 005 and preservation of old counts/config/media. See [RELEASE-0.7.0.md](RELEASE-0.7.0.md) for scope and limitations.

After configuring a disposable test database with the variables described below:

```bash
php tests/run.php all
php tests/setup_config.php
python3 tests/usage_http.py
KB_TEST_BASE_PATH=/kb python3 tests/usage_http.py
KB_CONFIG=tests/artifacts/config.php php tests/concurrency.php
```

# Verification for 0.5.0

The full service suite passed 179 checks. New service checks cover disabled defaults, saved link/window/banner choices, optional-field preservation during branding-only saves, stale edits, URL schemes and internal addresses, credentials/control-character rejection, Unicode/type/length validation, atomic saves, disable/reuse, and migration replay.

Real HTTP workflows passed at both `/` and `/kb`, including reader/admin link and banner display, escaped labels and multiline plain text, safe new-window attributes, current-window selection, input retention after errors, authentication/CSRF boundaries, stale-save protection, and disabling while retaining details. A subsequent root HTTP run also verified that each feature can stay enabled independently of the other. The removed homepage heading remains absent. Existing article, logo, category, compact-mode, and search workflows also passed.

The actual 0.4.0 full release was overlaid with the 0.5.0 update ZIP. Existing configuration bytes, uploaded images/logo, published articles and working drafts, visit counts, search, branding, featured status, and an enabled compact-homepage preference were retained. Both new features defaulted to off. Replaying migrations preserved configured category images, the new header link/window choice, and banner text/enabled state. The full distribution passed fresh installation, repeated migration, and database/media backup restoration.

Changed PHP files, browser-script syntax, and final archive integrity/exclusion checks passed. The independent review found no Critical, Important, or Minor findings and independently ran 30 database-free validator probes; see REVIEW.md. The supplied browser script now covers the header link/banner and mobile visibility/overflow but remains unexecuted due to the browser limitation below. No visual rendering is claimed.

Execution used PHP 8.3.6 and MariaDB 10.11.14. Destination PHP 8.5/MySQL 8.4 and the user's server were not accessed. Historical concurrency and 5,000-article timing tests were not rerun because those paths did not change.

# Verification for 0.4.0 (historical)

The natural-language regression first failed against the previous search for “My Outlook won't start.” After implementation, the full service suite passed 138 checks and real HTTP workflows passed at `/`. New coverage includes straight/curly contractions, filler removal before the term limit, a misspelling in a question, unmatched words before/after a product name, title/keyword/body ranking, specific and general articles appearing together, literal `IT`, full IT-help questions, drive letters, malformed UTF-8 handling, category restrictions, draft/Trash/unpublish privacy, exact-title preference, repeated words, and accurate stable pagination.

The 5,000-article benchmark passed: exact password search 1.2 ms; misspelling 0.9 ms; broad employee query 29.2 ms; “My password won't work” 3.6 ms; “Please help me with an employee device qzxvunknown” 43.7 ms. These are local test measurements, not a production performance guarantee. Query bounds remain 200 characters, eight meaningful words, at most 20,000 fuzzy dictionary candidates per word, and 128 retained matches per word.

Changed PHP files passed syntax checking. Release archive CRC, required-file, and private-file exclusion checks passed. The focused review finding and its correction are recorded in REVIEW.md. Search indexing, dependencies, and schema are unchanged, so no migration or index rebuild is required from 0.3.x. Historical concurrency, installation, and upgrade suites were not rerun for this query change. Execution used PHP 8.3.6/MariaDB 10.11.14. PHP 8.5/MySQL 8.4 and browser rendering were not executed here; the existing browser limitation below applies.

# Verification for 0.3.2 (historical)

The real HTTP suite passed at `/`, including retained compact summaries, article links, category images/names, featured ordering, and the existing editing/upload workflows. Changed PHP files passed syntax checks. Rendering the actual layout template with root and `/kb` base paths confirmed `/assets/app.css?v=0.3.2` and `/kb/assets/app.css?v=0.3.2` respectively.

CSS review confirms 8 px of vertical padding in compact rows at both desktop and mobile sizes; the category figure has a fixed-width column with its icon and caption horizontally centered and the group vertically centered in the row. Text/icon sizes and article visibility are unchanged. Release archive integrity and exclusion checks passed.

The supplied screenshot suggests an older stylesheet was cached (larger category captions and misaligned title columns); this inference was not confirmed on the destination server. Versioned stylesheet URLs now ensure each release requests its own stylesheet. Browser rendering was not executed here; the existing browser limitation below still applies. Execution used PHP 8.3.6 and MariaDB 10.11.14. No schema or dependency changes were made, and historical service/concurrency/install/upgrade tests were not rerun for this presentation fix.

# Verification for 0.3.1 (historical)

The updated HTTP regression failed against 0.3.0 because compact rows omitted summaries and article links. After the correction, the real HTTP suite passed at `/`, verifying identical summaries in regular/compact modes, restored article links, hidden featured labels/update dates, retained category images/names, featured-first ordering, and unchanged regular browse details. Existing upload and editing HTTP workflows also passed.

Changed PHP files passed syntax checks. The browser test was updated and passed JavaScript syntax checking, but was not executed: the browser limitation below still applies. No rendered desktop/mobile validation is claimed. CSS review confirms compact overrides now reduce row padding and remove only the unused summary bottom margin, without changing text/icon sizes.

Release archive CRC, required-file, and excluded-private-data checks passed. There are no dependency or schema changes from 0.3.0. Runtime execution used PHP 8.3.6 and MariaDB 10.11.14; the destination server was not accessed. The historical service, concurrency, fresh-installation, and older-upgrade results below were not rerun for this presentation-only patch.

# Verification for 0.3.0 (historical)

## Results recorded in this build environment

- PHP 8.3.6 (Ubuntu security-patched package built September 2, 2026), MariaDB 10.11.14.
- PHP syntax checks across application, CLI, and templates.
- Database service tests: migration idempotency, UTF-8, authentication/throttling, CSRF, session revocation, last-admin protection, HTML cleanup, drafts, publication/revision recovery, image privacy, categories, search, ranking, view deduplication, and pagination.
- Real HTTP tests: reader access, login, invalid/array-shaped requests, draft/preview/publish, stale-edit retention, multipart image upload, forged-file rejection, image privacy, unpublish/Trash/restore, category/admin creation. Run at both `/` and `/kb`.
- Parallel PHP processes: one of two concurrent last-admin disables is rejected; one of two simultaneous saves against the same article version is rejected.
- JavaScript event-state regression: recovered text from a failed save remains marked unsaved before/after editor initialization; successful-save pages start clean; edits made while the editor loads remain protected. This is a Node test of the actual handlers, not a substitute for browser testing.
- Baseline 0.1.0 measurement with 5,000 published test articles: exact and typo queries measured about 1 ms; a broad query matching all articles measured about 54 ms in this environment. These are test measurements, not a production performance guarantee. Typo expansion considers at most 20,000 dictionary terms per token and retains 128 matches; queries use at most eight words and 200 characters.

MySQL 8.x and newer PHP branches are compatibility targets, but were not executed here. All database execution used MariaDB 10.11.14. Apache/Nginx configurations are examples to validate against the destination server.

## 0.3.0 additions

The service suite passed 110 checks. New checks cover the initial layout default, saved compact preference, stale category edits, forged upload boundaries, missing-file fallback, and migration replay without losing settings/image references. Real HTTP tests passed at `/` and `/kb`: category image creation/upload/replacement/removal, 2 MB and 4-megapixel limits, forged-file rejection, CSRF/authentication, stale edits, the public image endpoint and default icon, compact preference persistence, hidden detail, and retained featured-first ordering. Browse rows retain their regular details. Existing article/image privacy and editor save paths also passed.

Both actual 0.1.0 and 0.2.0 full ZIPs were upgraded with the update archive. Configuration, article images, publications, private working drafts, visit counts, fuzzy search, and existing 0.2.0 branding/logo/featured flags survived. Migration replay preserved category image references and compact settings. The full distribution passed fresh installation and database/media backup restoration. Editor event-state and concurrency regressions passed; PHP files passed syntax checks.

The browser test now includes category upload and compact-homepage assertions/screenshots, but remains unexecuted here for the limitation below.

## 0.2.0 additions

The service suite passed 100 checks, including eight-character creation/reset/login, Unicode character counting, branding persistence and optimistic locking, featured-first ordering, no duplicate homepage entries, and hiding drafts/Trash even when starred. HTTP workflows passed at `/` and `/kb`, including branded text escaping, real logo upload/replacement/removal, forged/oversized upload rejection, feature/unfeature, and authorization/CSRF checks.

The actual 0.1.0 full ZIP was installed, then overlaid with the 0.2.0 update ZIP. Configuration bytes, uploaded image bytes, publications, working drafts, views, and search survived. Repeated migration and recovery from a partially recorded migration preserved new branding and stars. An eight-character CLI password reset worked after the upgrade.

## Browser validation limitation

Automated Chrome testing could not start because this workspace prohibits the UNIX socket operation required by Chrome. The available remote browser also cannot access the local application. No desktop/mobile screenshot or successful browser editor workflow is claimed for this build.

**Before employee rollout, run the supplied browser test or manually check visual editing, HTML source editing, image upload/paste, saving after uploads, mobile category navigation, branding/logo display, category images, regular/compact homepage rows, star buttons, and the unsaved-change warning on the target environment.** The real HTTP upload/publication paths were tested independently, but that does not verify the editor's browser interactions.

## Reproduce service and HTTP tests

Tests destroy and recreate tables only in a database whose name ends `_test`. Use dedicated empty databases, never a production copy containing important information. PHP must include the normal application extensions. Python 3 is needed for HTTP/package tests.

Create two disposable databases and export test credentials in your shell:

```bash
export KB_TEST_DSN='mysql:host=127.0.0.1;port=3306;dbname=simplephpkb_test;charset=utf8mb4'
export KB_WEB_TEST_DSN='mysql:host=127.0.0.1;port=3306;dbname=simplephpkb_web_test;charset=utf8mb4'
export KB_TEST_USER='your_test_database_user'
read -rs KB_TEST_PASSWORD
export KB_TEST_PASSWORD
php tests/setup_config.php
php tests/run.php all
php tests/run.php benchmark
node tests/editor_state.cjs
python3 tests/http_test.py
KB_TEST_BASE_PATH=/kb python3 tests/http_test.py
KB_CONFIG="$PWD/tests/artifacts/config.php" php tests/concurrency.php
```

The service test account needs schema creation/drop privileges within those test databases. HTTP tests run PHP's local development server and stop it on completion. Test fixtures use explicitly synthetic accounts and article text; they are not installed in a normal application installation.

## Browser test on a suitable developer machine

From the source archive, with dependencies/editor installed and the test configuration prepared:

```bash
npm install --no-save playwright
npx playwright install chromium
node tests/browser.cjs
```

The script opens the local test server, blocks external browser requests, and exercises reader navigation/search, admin sign-in, visual editor initialization, draft/preview/publish, HTML source editing, and mobile categories. It records screenshots under `tests/artifacts/`. `KB_CHROMIUM` may point to an existing compatible Chrome executable. Screenshot paste and browser-native unsaved-change dialogs should additionally be checked manually until the browser test is extended to cover those interactions.

## Release/restore checks

`python3 tests/package_test.py` checks the full ZIP for required assets and excluded private files. `python3 tests/install_test.py` installs the extracted package into a temporary directory and verifies database setup and a backup/restore round trip. These test scripts are in the source archive; generated configurations, uploaded fixtures, and test database contents are excluded.

To reproduce the ZIP upgrade test, set `KB_INSTALL_TEST_DSN` to a disposable database ending `_install_test`, place the original complete 0.2.0 ZIP at `dist/SimplePHPKB-v0.2.0.zip` (or set `KB_BASELINE_ZIP` to it), build current archives, and run `python3 tests/upgrade_test.py`.

To verify a direct upgrade from 0.1.0, set `KB_BASELINE_ZIP` to that full ZIP and run the same upgrade test.

## Version 0.6.0 browser lifecycle checks

Use a disposable `_test` database only. The tests reset tables. With dependencies installed:

```bash
export KB_TEST_DSN='mysql:host=127.0.0.1;port=3306;dbname=simplephpkb_test;charset=utf8mb4'
export KB_TEST_USER='test_account'
# Set KB_TEST_PASSWORD in your local environment; never commit it.
php tests/lifecycle_unit.php
python3 tests/lifecycle_http.py
php tests/run.php all
php tests/setup_config.php
python3 tests/http_test.py
KB_TEST_BASE_PATH=/kb python3 tests/http_test.py
python3 scripts/package.py
python3 tests/package_test.py
```

The lifecycle tests use a temporary application copy and HTTP server on port 8094. They cover installation, partial-install ownership, nonempty database rejection, backup SQL restore (including numeric ids, Unicode, quotes and null bytes), auth/CSRF, database maintenance, ZIP upload, normal apply and recovery from interrupted Composer files and failed SQL. Unit tests exercise release caching/status and malformed ZIP confinement.

The GitHub workflow repeats the core/lifecycle/HTTP/package checks on PHP 8.3 and 8.5 with MySQL 8.4. It must actually run after the first push; a workflow file is not proof of a passing GitHub job. On your dev server, also check desktop/mobile appearance, installer field labels, backup download, settings, search, article publishing and the local WYSIWYG editor.
