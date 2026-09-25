# Browser lifecycle implementation plan

**Goal:** Install and maintain SimplePHPKB from a browser and prepare GitHub publication.
**Architecture:** Separate Installer, Updates, UpdatePackage, Backup and Releases services; keep the existing reader/admin services intact. Reuse existing database migrations and auth.
**Tech stack:** PHP 8.3+, PDO MySQL, ZipArchive, cURL, Python integration tests.
**Spec:** `docs/superpowers/specs/2026-09-25-web-lifecycle.md`
**Execution:** Native, preserving the user's previous choice.

## Global constraints
- Keep eight-character passwords, current branding/compact/search behavior and removed homepage phrase.
- No Apache reload for app changes; preserve config/media; PHP 8.3 minimum.
- Database and file mutations require serialization and explicit web POST actions.

## Review focus
- Installation failures after partial DDL must not permit a second user to take over.
- Interrupted file updates must be resumable without exposing half-migrated reader pages.
- ZIP paths/symlinks and forged metadata must never overwrite secrets or escape the app.
- Backup SQL must restore multilingual and binary-containing rows without shell access.
- Empty/private/unreachable GitHub releases must not falsely report the app as current.

## Task 1: Browser installation
Files: src/Installer.php, public/index.php, templates/install.php, src/bootstrap.php, tests/lifecycle_http.py.
Interface: Installer::run(string $configFile): void; Database::pending(PDO): array.
- [x] Write HTTP test that opens an unconfigured copy and expects setup rather than 500; run and observe failure.
- [x] Implement prerequisite check, validated form, private pending config and session claim, empty-database check, retry-safe install, config publication and lockout.
- [x] Verify successful install, wrong CSRF, invalid values, existing database refusal, retry and second-session denial; run existing suite and commit.

## Task 2: Web maintenance and GitHub release check
Files: src/Updates.php, src/UpdatePackage.php, src/Backup.php, src/Releases.php, templates/updates.php, public/index.php, src/Web.php, templates/layout.php, scripts/package.py, tests/lifecycle_http.py, tests/lifecycle_unit.php.
Interface: Updates::run(): void; Updates::pending(PDO): bool; UpdatePackage::stage(string): array; Releases::check(bool): array; Backup::create(PDO,string): void.
- [x] Write tests for release parsing/cache and ZIP confinement; observe missing-feature failure.
- [x] Add bounded public GitHub check and admin notice, standalone Updates controller, native SQL/ZIP backup, staged package validation and resumable apply/migrate.
- [x] Exercise real HTTP anonymous/CSRF rejection, valid package, interruptions, SQL restore, file/config/media preservation; run regression suite and commit.

## Task 3: Release and dev workflow
Files: README.md, docs/INSTALL.md, docs/UPGRADE.md, docs/DEVELOPMENT.md, docs/BACKUP.md, .github/workflows/ci.yml, scripts/package.py, tests/package_test.py, CHANGELOG.md.
- [x] Package version 0.6.0 with dependencies and update manifest; test fresh/overlay install.
- [x] Write exact dev setup and Git publishing steps for the empty repo, plus initial 0.5→0.6 bridge update steps.
- [x] Fresh whole-change review, fix material findings with regression tests, run final checks and commit locally.
- [x] Save deliverables and provide inline dev setup/Git commands. Do not push remotely.
