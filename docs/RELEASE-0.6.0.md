# SimplePHPKB 0.6.0 validation

This release adds browser installation, private SQL/file backup downloads, uploaded ZIP updates, browser migrations and cached GitHub release checks. It includes a ready-to-run development bundle and GitHub publishing instructions.

Verified locally on PHP 8.3.6 / MariaDB 10.11.14:

- Service suite and HTTP reader/admin suite at both `/` and `/kb`.
- Browser installer succeeds, rejects nonempty databases/invalid input/CSRF, and restricts interrupted-setup resume to the original browser.
- ZIP upload/review/apply, protected configuration/media, private backups and SQL restoration preserving numeric ids, Unicode, quotes and null bytes.
- Maintenance during schema/file changes and retry after failed SQL.
- A deliberately inconsistent live Composer autoloader does not prevent the independent recovery controller from resuming an interrupted file update.
- Invalid paths, unexpected files, bad hashes, ZIP symlinks and release/cache response handling.
- PHP syntax and archive contents/checksums.

A fresh reviewer identified the interrupted-Composer recovery defect before release; the regression failed before the independent recovery snapshot was added and passed afterward. No other material review findings remained.

PHP 8.5/MySQL 8.4 execution, hosted GitHub CI and visual browser rendering were not performed in this environment. The included CI targets those PHP/MySQL combinations after the first push; validate appearance and behavior on the dev server before employee rollout.

No GitHub code or releases were published from the build environment. The repository was public and empty when checked. Git commands in DEVELOPMENT.md are for the owner to run from the dev server.
