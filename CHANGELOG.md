# Changelog

## 0.7.0 — 2026-10-12

- Admin-only Usage page for site visits, article views and articles read: today, last 7 days, last 30 days and all time.
- Searchable, paginated article popularity list includes zero-view articles, drafts and Trash; Manage articles also shows all-time views.
- Count successful anonymous reader GET requests once per browser session per UTC day, with separate deduplication for each article. Exclude signed-in admins, recognized bots, HEAD requests, images, previews, login and error pages.
- Preserve existing all-time article counts and homepage popularity ordering. Retain daily aggregates while expiring old session hashes. No IP addresses, employee identities or external analytics service are added.
- Migration 005 starts site/date-based tracking on upgrade; earlier article views remain visible in all-time totals. Run it with Application updates in the browser.
- A tracking write failure is logged without blocking readers; article lifetime/daily counters update in one transaction.
- Regression coverage for counts, date boundaries, admin access, concurrent reads, browser installation/upgrades and package contents.

## 0.6.0

- Browser installation with prerequisite checks, generated private configuration, initial admin and interrupted-install recovery.
- Admin update center with native backup downloads, validated versioned ZIP uploads, separate SQL upgrade step and maintenance recovery.
- Cached GitHub stable-release notices for flattery103/SimplePHPKB.
- Runtime dependencies included in future update packages; new ready-to-run development ZIP.
- Development/publishing instructions and a PHP 8.3/8.5 + MySQL 8.4 GitHub Actions workflow.
- Existing eight-character passwords, branding, compact rows, search, category images and notices retained.

## 0.5.0 — 2026-09-24

- Add an optional shared header link with admin-configured text, HTTP/HTTPS URL, and current/new-window selection. It appears beside Sign out or Admin sign in and supports internal hostnames/IPs.
- Add an optional plain-text, multiline known-issues banner below the header on every page. Admins enable, update, or disable it in Settings.
- Default both features to disabled and retain their saved details when disabled. Add migration 004 without changing existing branding, compact preferences, articles, or media.
- Validate URL schemes, input types, lengths, and UTF-8; escape displayed text and add opener protection for new-window links. Retain authenticated/CSRF-protected saves and stale-edit protection.
- Allow the header navigation to wrap when the optional link is enabled. Preserve existing layout when it is off.

## 0.4.0 — 2026-09-24

- Support conversational questions by filtering common English filler words and normalizing contractions with straight or curly apostrophes. Preserve literal single-word searches and meaningful short terms such as drive letters.
- Return ranked partial matches when some words are absent, instead of requiring every word to match. Rank by meaningful query words matched, then title/keyword/body relevance; keep exact-title boosting, typo tolerance, category filters, and stable pagination.
- Use the strongest indexed match per query word to avoid inflating scores through multiple fuzzy/prefix variants. Query limits remain 200 characters and eight meaningful words.
- Preserve published-only search visibility and use the existing index without a schema migration or reindex.
- Remove “A GOOD PLACE TO START” from the homepage template, preserving the requested customization.

## 0.3.2 — 2026-09-24

- Reduce compact rows to 8 px of top/bottom padding on desktop and mobile.
- Center each category icon and label together, vertically in the article row and horizontally in a fixed-width side column. Long category names continue to wrap.
- Version the stylesheet URL so new releases load current styles instead of reusing a previously cached stylesheet.
- Keep article summaries, text/icon sizes, featured ordering, and the existing compact visibility settings. No database migration is needed from 0.3.0 or 0.3.1.

## 0.3.1 — 2026-09-24

- Correct compact mode to retain article summaries, navigation arrows, and section text. Hide only the featured label and update date.
- Restore regular title, category icon, and category label sizes in compact mode; reduce row padding and remove the unused space below summaries. Featured-first ordering is unchanged.
- Clarify the Settings help text and update directions. Updating from 0.3.0 needs no database migration or Apache reload.

## 0.3.0 — 2026-09-24

- Replace large article numbers with optional category images and category names underneath; use a bundled question-mark icon when no image is configured.
- Add category image upload, replacement, and removal with image validation and stale-edit protection.
- Rename the Branding admin page to Settings and add a compact-homepage checkbox. Compact rows show the icon, category name, and title, without excerpts, featured badges, or update dates; starred articles remain first.
- Use the minimal article-page search box on the homepage, category pages, and search results.
- Add retry-safe migration 003. Existing articles, branding, category names, and featured flags are retained.

## 0.2.0 — 2026-09-24

- Lower the administrator password minimum to 8 characters in account forms, server validation, and CLI create/reset. Existing passwords continue to work.
- Add admin branding settings: logo upload/removal, site title, and optional subtitle. Uploaded logos share the existing media backup directory.
- Add star/unstar controls in Manage articles. Published starred articles lead the same ten-item homepage list, followed by popular articles with no duplicates.
- Add migration 002 and an update archive that excludes configuration, uploaded media, and Apache/Nginx configuration.
- Share decoded/re-encoded image storage between screenshots and logos; release GD objects without calling PHP 8.5's deprecated `imagedestroy` function.


## 0.1.0 — 2026-09-24

Initial implementation: anonymous internal reading; authenticated admin accounts; category navigation; fuzzy search; popular articles; visual and HTML editing; protected screenshot uploads; drafts/previews; publication history and Trash; installation and backup documentation.

Validation status: backend, real HTTP, concurrency, and packaging checks are recorded in `docs/TESTING.md`. Browser automation is blocked in the build environment, so visual-editor and mobile smoke testing on the destination environment remains required before rollout.
