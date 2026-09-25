# SimplePHPKB Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Deliver an installable internal PHP/MySQL knowledge base with anonymous reading and authenticated administration.

**Architecture:** Server-rendered PHP with PDO and small services for authentication, content, media, and search. Published revisions remain separate from working drafts. All editor assets and runtime dependencies ship in the release ZIP.

**Tech Stack:** PHP 8.3+, MySQL 8.0+/MariaDB 10.11+, HTML Purifier, locally bundled TinyMCE Community, plain CSS/JavaScript. Use GPL-2.0-or-later for the combined distribution and retain dependency notices. Pin downloaded dependency versions and checksums.

**Spec:** `docs/superpowers/specs/2026-09-24-simplephpkb-design.md`

## Global Constraints

- PHP 8.3 or later; MySQL 8.0 or later; MariaDB 10.11 or later is an alternative. Record tested versions separately from compatibility targets.
- Internal employee access, no public internet deployment; employees do not sign in.
- Admin authentication for every content mutation and unpublished resource.
- One category per article; flat category sidebar with published counts; mobile collapse.
- Homepage shows up to ten published articles ordered by qualified views.
- Draft editing does not alter the published revision; history restores into draft.
- No runtime CDNs, external editor services, or separate search service.
- Images: PNG/JPEG/WebP, maximum 10 MB. Store outside public document root.
- Ship installation, migration, backup/restore, Apache/Nginx, root/subdirectory guidance.
- Exclude real credentials, production content, media, and backups from source/release.

## Review Focus

1. Images uploaded into a new draft of an already published article must remain inaccessible anonymously until the image is in its published revision — Task 3.
2. Concurrent edits and the last-admin protection must hold transactionally — Tasks 2 and 3.
3. HTML source, pasted markup, Unicode queries, and malformed query/form types must not execute code or crash requests — Tasks 3–5.
4. Renames, category changes, trash/restore, and old revisions must not expose drafts or stale search results — Tasks 3 and 4.
5. Reverse proxy/subdirectory installs and offline editor assets must produce correct URLs without trusting arbitrary forwarded headers — Tasks 1, 5, and 6.

## File Map

- `config/config.example.php`: editable deployment settings, no real credentials.
- `src/bootstrap.php`, `src/Database.php`, `src/Support.php`: loading, PDO, migrations, URL/escape/form helpers.
- `database/001_initial.sql`: versioned schema.
- `src/Auth.php`: passwords, sessions, login throttle, admin management.
- `src/Html.php`, `src/Media.php`, `src/Articles.php`: sanitized versioned article content and protected media.
- `src/Search.php`: published-only index, partial/typo matching and ranking.
- `src/Web.php`, `public/index.php`: bounded routes and request handling.
- `templates/`: shared layout, reader, login, article editor, categories/accounts/history views.
- `public/assets/`: local CSS, JavaScript, TinyMCE.
- `bin/kb.php`: install/migrate, create admin, reset password.
- `tests/run.php`, `tests/http.py`, `tests/browser.cjs`: service integration, HTTP access controls, browser workflows.
- `README.md`, `docs/INSTALL.md`, `docs/BACKUP.md`, `deploy/`, `scripts/package.py`: deployment and release packaging.

## Task 1: Runtime, configuration, database, and installation

**Interfaces:** `Database::connect(array $config): PDO`, `Database::migrate(PDO $db): void`; `app_url(string $path = '', array $query = []): string`; `e(string $text): string`. Bootstrap exposes `$config` and `$db`.

- [ ] Prepare a branch and obtain a PHP runtime/database for real integration tests. Report actual runtime constraints; do not equate lint with execution.
- [ ] Write a test harness requiring an explicitly named test database; assert migration idempotency and UTF-8 round trips. Start with missing-schema assertions and observe failure.

```php
Database::migrate($db);
Database::migrate($db);
check((int)$db->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn() === 1, 'idempotent migration');
check(app_url('index.php', ['route'=>'article', 'id'=>7]) === '/kb/index.php?route=article&id=7', 'subdirectory URL');
```

- [ ] Create schema tables for admins, categories, articles, revisions, media, revision_media, search_terms, search_postings, article_views, and login_attempts; use InnoDB and utf8mb4. Keep media-reference and published-revision updates transactional. Do not use SQLite as proof of MySQL compatibility.
- [ ] Add example config and CLI migrate/create-admin/reset-password with hidden or stdin password entry. Public document root is `public/`; no URL rewriting is required.
- [ ] Run `php tests/run.php foundation`, confirm migration twice preserves data, and commit.

## Task 2: Admin authentication and account management

**Interfaces:** `Auth::__construct(PDO $db)`, `Auth::login(string $username, string $password, string $ip): bool`, `Auth::current(): ?array`, `Auth::logout(): void`, `Auth::saveAdmin(array $data, ?int $id): int`; helpers `csrf_token(): string`, `verify_csrf(mixed $token): void`.

- [ ] Write tests for valid/invalid login, throttle, inactive accounts, session invalidation after password changes, missing/wrong CSRF, and last-admin protection. Observe failures.

```php
check(!$auth->login('admin', 'wrong', '127.0.0.1'), 'incorrect password rejected');
check($auth->login('admin', $testPassword, '127.0.0.1'), 'valid login');
expect_error(fn() => $auth->saveAdmin(['active'=>0], $lastAdmin), 'last active admin');
```

- [ ] Use `password_hash`, `password_verify`, session regeneration, per-request account/session-version checks, bounded login throttling, and a transaction locking active-admin rows when disabling accounts.
- [ ] Add CSRF and POST-only checks to every mutating route. Never accept login redirects to an external URL.
- [ ] Run `php tests/run.php auth`, then all existing service tests, and commit.

## Task 3: Drafts, publication, revision recovery, media, and categories

**Interfaces:** `Articles::create(int $adminId): int`, `Articles::save(int $id, array $data, int $expectedVersion, int $adminId, bool $publish): void`, `Articles::get(int $id, bool $admin = false): ?array`, `Articles::trash(int $id, int $version): void`, `Articles::restore(int $id, int $version): void`, `Articles::restoreRevision(int $id, int $revisionId, int $version, int $adminId): void`; `Html::clean(string $html, int $articleId): string`; `Media::upload(int $articleId, array $file, int $adminId): array`; `Media::find(string $id, bool $admin): ?array`.

- [ ] Write failing integration tests for draft/published separation, stale save rejection, historical restore into draft, trash/restore, category reassignment, server-side HTML cleanup, forged image types, and draft image privacy for published articles.

```php
$articles->save($id, $original, 1, $adminId, true);
$articles->save($id, $changed, 2, $adminId, false);
check($articles->get($id)['title'] === $original['title'], 'draft is private');
check($media->find($draftImageId, false) === null, 'draft-only image is private');
expect_error(fn() => $articles->save($id, $changed, 1, $adminId, true), 'stale edit');
```

- [ ] Implement row-lock/version checks, immutable publication snapshots, draft fields, and transactional visibility/index changes. Revisions keep category/title/keywords/content/editor/timestamp. Category deletion checks draft and revision references and requires reassignment where needed.
- [ ] Configure HTML Purifier with supported formatting only. Validate canonical media references against the owning article, record image membership in each published revision, and forbid scripts/iframes/event handlers/external image sources/data images in saved content. Display sanitizer feedback when content is changed.
- [ ] Validate uploads with MIME detection and real image decoding; enforce byte/pixel limits and random server filenames. Serve only images referenced by the currently published revision to anonymous readers; signed-in admins can access draft images. Use no-store media responses so unpublishing takes effect on later requests.
- [ ] Run `php tests/run.php content`, then the full service suite, and commit.

## Task 4: Search, reader pages, popularity, and category navigation

**Interfaces:** `Search::index(int $articleId, ?array $published): void`, `Search::query(string $q, ?int $categoryId, int $page = 1): array`; `Articles::listing(?int $categoryId, int $page, bool $popular = false): array`; `Articles::recordView(int $id, string $sessionKey, string $agent, bool $admin): void`.

- [ ] Write failing tests for exact/partial/typo matches, title ranking, keywords, punctuation and Unicode, category restriction, published-only results, stable pagination, and repeated/admin/robot visit exclusion.

```php
check($search->query('pasword', null)['items'][0]['id'] === $passwordArticleId, 'fuzzy typo');
check($search->query('wireless', $otherCategory)['total'] === 0, 'category scope');
$articles->recordView($id, 'same-session', 'Mozilla/5.0', false);
$articles->recordView($id, 'same-session', 'Mozilla/5.0', false);
check((int)$articles->get($id)['view_count'] === 1, 'daily deduplication');
```

- [ ] Build a normalized word index over the published revision with title/keyword/body weights. Retrieve exact and prefix terms via index; use bounded edit-distance candidates by token length. Cap input length and token count; use placeholders for every SQL value. Query final results by published revision and current visibility even if an index entry is stale.
- [ ] Create homepage, paginated article/category/search lists, snippets, article body, last-updated date, copy link, empty states, and published counts. Popularity uses database uniqueness on article/session hash/day.
- [ ] Seed 5,000 test articles, time exact/typo/category queries, record dataset/latency and limits. Delete benchmark data only from the guarded test database.
- [ ] Run `php tests/run.php search` and `php tests/run.php benchmark`, then the full service suite, and commit.

## Task 5: Admin interface and local WYSIWYG integration

**Interfaces:** Reader routes `home`, `browse`, `article`, `media`; admin routes `login`, `logout`, `admin`, `edit`, `preview`, `history`, `categories`, `admins`, `upload`; use `index.php?route=...`. All IDs/version values are validated scalar positive integers. Mutations are POST with CSRF.

- [ ] Write HTTP/browser tests for anonymous denial, login, new article, screenshot upload/paste, source editing, draft/preview/publish, revision recovery, categories/admin management, phone sidebar, unsaved-change warning, malformed array inputs, and offline operation. Observe missing-route failures before creating routes/templates.

```javascript
await page.route('**/*', route => {
  const host = new URL(route.request().url()).hostname;
  return ['127.0.0.1', 'localhost'].includes(host) ? route.continue() : route.abort();
});
await page.goto(base + '/index.php?route=login');
await page.getByLabel('Username').fill('admin');
await page.getByLabel('Password', {exact:true}).fill(testPassword);
await page.getByRole('button', {name:'Sign in'}).click();
await page.getByRole('button', {name:'New article'}).click();
await page.waitForFunction(() => window.tinymce?.activeEditor?.initialized);
```

- [ ] Build an accessible, responsive interface with slate/navy navigation, teal accents, legible article typography, clearly labeled controls and inline errors. Preserve posted text after validation/save errors and do not replace stale version tokens silently.
- [ ] Bundle TinyMCE locally with `license_key: 'gpl'`, `plugins: 'lists link image table code codesample searchreplace'`, local skins/content CSS, and authenticated CSRF-protected uploads. Await pending uploads before form submission and keep unsaved-change protection if an upload fails.
- [ ] Run `python3 tests/http.py` and `node tests/browser.cjs`. Inspect desktop/mobile screenshots, browser console, and external request log; fix concrete issues and rerun affected cases. Commit after all existing tests pass.

## Task 6: Installation documentation, release package, and final verification

**Interfaces:** `php bin/kb.php migrate`, `php bin/kb.php admin:create USERNAME`, `php bin/kb.php admin:reset USERNAME`; `python3 scripts/package.py` writes a versioned ZIP without local configuration/data.

- [ ] Write a package test asserting required assets/notices/docs exist and config/media/backups/test secrets are absent. Initially run it against the missing archive and observe failure.

```python
with zipfile.ZipFile(archive) as z:
    names=set(z.namelist())
    assert 'SimplePHPKB/public/index.php' in names
    assert 'SimplePHPKB/config/config.php' not in names
    assert 'SimplePHPKB/public/assets/tinymce/tinymce.min.js' in names
    assert 'SimplePHPKB/vendor/autoload.php' in names
```

- [ ] Document dependencies, HTTPS/internal routing, first admin, least-privilege DB account, writable storage/cache, upload size, correct public root, subdirectory base path, migrations, backups and restore. Supply Apache/Nginx configurations with direct PHP entrypoint handling and no access to application source.
- [ ] Create clean-room test DB and installation from extracted ZIP. Exercise CLI setup, reader/editor, and database/media backup restore. Verify root and `/kb` URLs. Record actual tested PHP/DB/browser versions and any untested compatibility target.
- [ ] Run PHP lint and the service/HTTP/browser/package suites; inspect the final diff and receive one independent whole-branch review. Fix material findings with regression tests.
- [ ] Save the final source/distribution ZIP and concise install guide as deliverables. Do not publish a GitHub repository or deploy an employee site without a specific request.

## Evidence and execution notes

The initial workspace contains only the approved design. PHP, Composer, MySQL/MariaDB binaries are not initially installed; Node/Python and Playwright packages are present. Runtime provisioning is necessary for execution evidence. A failed dependency/network provisioning attempt must be reported accurately; do not claim tests ran if they could not run.

Official references checked while planning:
- https://www.tiny.cloud/docs/tinymce/latest/license-key/
- https://www.tiny.cloud/docs/tinymce/latest/file-image-upload/
- https://github.com/ezyang/htmlpurifier
- https://packagist.org/packages/ezyang/htmlpurifier
- https://www.php.net/supported-versions.php
