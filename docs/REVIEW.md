# SimplePHPKB 0.5.0 link/banner review

An independent read-only review of the completed feature found no Critical, Important, or Minor issue. It reviewed URL/type/UTF-8/length validation, saved-value preservation, transactional settings writes and stale-version checks, shared-layout placement and escaping, new-window opener protection, the retry-safe migration, and service/HTTP coverage. It independently passed whitespace checks and 30 database-free validator probes, including internal hostnames, IPv4/IPv6 addresses, credential rejection, Unicode boundaries, malformed types, and omitted fields.

The reviewer declined visual/mobile rendering and PHP 8.5/MySQL 8.4 execution because those environments were unavailable. Shared database suites were intentionally left to the implementation workflow, and release documents/final archives were outside the reviewed code commit. These boundaries were accepted. The implementation workflow passed 179 service checks, HTTP tests at `/` and `/kb`, a real 0.4.0 ZIP upgrade preserving prior data/preferences, fresh installation and backup restoration, and final package checks. Details and the browser limitation are in TESTING.md.

# SimplePHPKB 0.4.0 search review (historical)

An independent read-only review covered conversational query parsing, partial-match relevance, privacy/category boundaries, pagination, the regression tests, and removal of the unwanted homepage heading. It found no Critical issue and one Important issue: the stop-word list removed the technical acronym “IT” from multiword queries such as “IT help.” The reviewer independently reproduced that failure even though the initial natural-search suite passed.

The implementation now retains `it` in all queries. New regressions for “IT help,” “Can IT help?”, and “How do I get IT help?” failed before the correction and passed afterward. A separate malformed-UTF-8 regression reproduced a nullable-regex TypeError introduced by contraction normalization; falling back to the original input restores the existing tokenizer's handling of those bytes. Final implementation checks passed 138 service assertions and real HTTP workflows, with all new regressions included. No further blocking review finding remains.

The reviewer declined to judge destination PHP 8.5/MySQL 8.4 execution, unavailable browser rendering, the in-progress release documentation/final ZIP, and independently rerunning the large-corpus timing. These boundaries were accepted: execution used PHP 8.3.6/MariaDB 10.11.14, no browser rendering is claimed, and the implementation workflow verified the final archives and recorded the 5,000-article benchmark. This release changes query behavior without modifying the schema or existing index.

# SimplePHPKB 0.3.2 layout adjustment (historical)

Compact row padding is reduced to 8 px vertically. Category images and captions are centered together in their side column. The stylesheet URL now includes the release version, addressing the risk of cached styles after updates. All article content and existing compact visibility rules remain intact. This narrow presentation update did not receive a separate independent review; current validation and limitations are recorded in TESTING.md.

# SimplePHPKB 0.3.1 correction (historical)

The compact-mode behavior described in the historical 0.3.0 review below was too aggressive. Version 0.3.1 restores summaries, article arrows, section text, and regular text/icon sizes. Only featured labels and update dates are hidden, with less row padding. Settings help and upgrade directions were corrected. This narrow presentation fix did not receive a separate independent review; validation is recorded in TESTING.md.

# SimplePHPKB 0.3.0 review notes (historical)

One independent read-only review covered the category image, compact homepage, minimal search, admin settings, migration, and packaging changes. It found no Critical or Important issues. It confirmed upload validation and failure cleanup, authentication/CSRF boundaries, image privacy, category locking, migration replay, preserved homepage selection, and update exclusions.

The reviewer found one Minor issue in the supplied browser test: mobile category navigation still waited for a heading removed by the minimal-search layout. That assertion was corrected to check the selected category and retained category search scope. The browser script passed syntax checking, but execution remains blocked in this environment.

Native tests passed 110 service checks, real HTTP workflows at both `/` and `/kb`, concurrency checks, and the JavaScript editor-state regression. Upgrades from the actual 0.1.0 and 0.2.0 full ZIPs preserved configuration, article content/drafts, media, visits, search, and existing branding/logo/featured state. Category image references and the compact setting survived migration replay. Fresh installation and database/media restoration also passed. Final archive contents and checksums were rebuilt after review.

The reviewer declined to judge rendered desktop/mobile appearance and browser interactions, execution on PHP 8.5/MySQL 8.4, and final ZIP bytes before rebuilding. These remain explicit boundaries: runtime tests used PHP 8.3.6/MariaDB 10.11.14; destination browser checks are required; final ZIP checks are performed by the implementation workflow. No browser rendering or destination-server execution is claimed.

The initial homepage mode remains regular; admins opt into compact mode. Compact mode also removes excerpts, as stated in the implementation brief, while keeping category images/names, titles, and starred-first ordering. Branding controls moved under Settings with the same URL. Replaced/removed category images follow the existing retained-media policy. No additional data cleanup or external publishing is part of this release.

# SimplePHPKB 0.2.0 review notes (historical)

## Update review

One independent review of the 0.1.0-to-0.2.0 application changes found no Critical, Important, or actionable Minor code findings. It covered password validation, branding upload boundaries and cleanup, article image privacy, featured ordering and optimistic locking, migration replay, and update archive exclusions. The reviewer independently passed PHP syntax checks, the JavaScript editor-state regression, ZIP boundary/CRC checks, and whitespace checks. It identified stale documentation in the preliminary archives; all release archives were rebuilt after the final documentation changes.

The implementation checks passed 100 service assertions, real HTTP workflows at `/` and `/kb`, article-edit and last-admin concurrency checks, full-ZIP installation and database/media restoration, and an upgrade from the actual 0.1.0 ZIP. The upgrade preserved configuration, publications, drafts, images, and visit counts; migration replay preserved saved branding and featured flags. Additional HTTP coverage verified successful logo replacement as well as upload rejection and removal.

The homepage remains one list of ten articles: published starred articles come first, followed by popular articles. Replaced or removed logos remain stored under the existing retention policy, while only the current logo is served by the logo endpoint.

Execution used PHP 8.3.6 and MariaDB 10.11.14. The destination PHP 8.5 / MySQL 8.4 / Apache installation was unavailable for execution. Actual desktop/mobile rendering and browser editor interactions remain to be checked on the destination server; no browser validation or screenshot success is claimed. The historical browser and usability limitations below still apply.

# SimplePHPKB 0.1.0 review notes (historical)

This is an initial build for target-server validation. The application has not been deployed to an employee network or published to GitHub.

## Independent review

One independent whole-project review covered the application, database schema, templates, editor integration, deployment examples, packaging, tests, and approved design. It found no critical issue. It confirmed that anonymous images belong to the currently published revision; draft/history/Trash states remain separate from publication; and article updates and last-admin protections use transactional locks.

The important finding was loss of the unsaved-change warning after a failed save: the server preserved submitted article text, but the editor treated that recovered page as clean. This has been fixed. Failed-save pages now retain their unsaved state before and after editor initialization; successful-save pages start clean. Edits made while the editor loads also retain protection.

A focused JavaScript regression failed before the fix and passed afterward. The final native service suite passed 72 checks; real HTTP workflows passed at both `/` and `/kb`; simultaneous article-edit and last-admin protections passed. The JavaScript test exercises the real event handlers but does not verify browser dialogs. Installation, archive, and backup/restore evidence is described in TESTING.md.

## Remaining validation

Actual browser validation remains required before employee rollout: visual/source editing, screenshot upload/paste, waiting for uploads before saving, mobile category navigation, local assets with outside connections blocked, and unsaved-change dialogs. Chrome could not start in the build environment because its required UNIX socket operation is prohibited; the remote browser could not reach the local application. No browser or screenshot success is claimed.

Execution used PHP 8.3.6 and MariaDB 10.11.14. MySQL 8.x, other PHP versions, and the Apache/Nginx deployment examples require validation on the destination server.

## Rulings I made

1. **Workspace isolation:** Used a dedicated project checkout and build branch instead of a second worktree because the checkout contained no existing user code. Cost if wrong: there is no separate baseline checkout for parallel comparison.
2. **Test runtime:** Extracted native PHP/database packages locally after the system installer could not change users/groups. This required no sandbox-control changes. Cost if wrong: extra runtime setup and a narrower set of tested versions.
3. **Historical categories:** Preserved historical category names but allowed references to deleted, empty categories to become null. Cost if wrong: restoring an old publication may require selecting a current category.
4. **Browser limitation:** Deliver the initial build with explicit target-server browser checks because local Chrome and remote-browser access were blocked. Cost if wrong: editor or mobile problems may need a follow-up before rollout.
5. **Permanent deletion and image cleanup:** Kept these outside version 0.1.0, as agreed in the design. Trash, publication history, and unreferenced uploaded images remain stored. Cost if wrong: storage grows until a future retention tool or maintenance process is introduced.
6. **Moving the installation URL:** Kept the documented requirement to preserve the existing base path during restoration. Automatic migration of stored image URLs is outside this release. Cost if wrong: changing the URL prefix without migrating content can break existing images.

## Deferred minors

- Account/category validation errors can reset entered non-password fields to their saved/default values. Admins may need to re-enter those short fields. Article edit-error responses preserve submitted text.
- Save notices confirm that HTML was checked, but do not specifically identify when sanitization removed or changed unsupported markup. The editor explains supported content restrictions; admins should preview their article after saving.

These are recorded usability gaps, not silently assumed completed behavior. The remaining browser checks are a release gate, not a minor cosmetic task.
