# SimplePHPKB

A small PHP/MySQL knowledge base for internal teams. Employees can browse and search without signing in. Admins sign in to write and organize illustrated articles.

**Version 0.7.0:** admin usage reports for site visits and article popularity, with today, 7-day, 30-day and all-time totals. Existing article counts are preserved. Test on a separate development server before updating your employee site. GitHub hosts the source and release downloads; the PHP application runs on your own web server.

## Features

- Browser installer creates configuration, database tables and the first admin.
- Browser update center with backup downloads, validated ZIP uploads, SQL migrations and recovery after interrupted updates.
- Admin notices for newer stable releases from [flattery103/SimplePHPKB](https://github.com/flattery103/SimplePHPKB).
- Admin-only Usage page with site visits, article views, articles read, and a searchable popularity list, including articles with no views. All-time views also appear in Manage articles. See [USAGE.md](docs/USAGE.md) for counting rules.

- Search full questions with typo tolerance and ranked partial matches across titles, keywords, and published article text.
- One homepage list of ten articles: starred publications first, then the most-viewed articles, without duplicates.
- Admin settings for branding and compact homepage rows (titles, summaries, and category images/names retained; featured labels and update dates hidden; reduced padding).
- Optional category images replace article numbers, with a question-mark fallback.
- Minimal search boxes on homepage, browsing, and article pages.
- Optional header link for a ticket system, with custom text and current/new-window choice.
- Optional known-issues banner on every page, managed from Settings.
- Admin passwords with an eight-character minimum.
- Category sidebar with article counts and a collapsible mobile layout.
- Locally bundled TinyMCE visual editor, HTML source editing, tables, code blocks, and screenshot upload/paste.
- Private drafts and previews, publication history, restore-to-draft, and Trash.
- Stable article links, last-updated dates, individual admins, login throttling, and protection against overwriting another admin's changes.
- Core KB operation works offline. Optional release checks need outbound HTTPS to GitHub.

## Install

Download `SimplePHPKB-v0.7.0.zip`, extract/upload it, and point the web server at **`public/`**. Open the site to enter database details and create the first admin. See [INSTALL.md](docs/INSTALL.md) for hosting prerequisites and permissions. No default credentials, Composer or Node are needed on the destination server.

PHP 8.3+; MySQL 8.0+ or MariaDB 10.11+; Apache/Nginx. Required extensions: PDO MySQL, mbstring, DOM/XML, fileinfo, GD, ctype, iconv. Enable zip for web backups/upgrades and curl for release checks.

## Upgrade

From 0.6.0 onward, use **Admin → Application updates** to upload the new release's `-update.zip`, download a backup, apply files, then finish database changes. Settings and media are preserved. See [UPGRADING.md](docs/UPGRADING.md), including the one-time bridge for older installations.

## Develop and publish

The `-dev.zip` includes source, tests and bundled dependencies for a ready-to-run dev copy. [DEVELOPMENT.md](docs/DEVELOPMENT.md) walks through a separate Ubuntu dev site, first browser install, first GitHub commit/push, later feature branches and publishing release assets. `.gitignore` excludes local configuration, media, backups, dependencies and generated files. Never commit real employee content or credentials.

## Maintenance

See [docs/BACKUP.md](docs/BACKUP.md) for backups, restoration, and updates; [docs/TESTING.md](docs/TESTING.md) for verification and compatibility details; and [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md) for dependency versions/licenses.

Reader access is anonymous within the permitted network. All admins have the same permissions. Popularity counts approximate browser sessions, not distinct people. Historical publications are retained; saving a draft replaces the working draft. A restored historical revision may require selecting a category if its original category was deleted.

## Source build

The separate source archive contains application code, tests, and dependency manifests. To rebuild the full distribution:

```bash
composer install --no-dev --prefer-dist
python3 scripts/fetch_editor.py
python3 scripts/package.py
```

Developer machines need internet access for those dependency downloads. End-user operation does not. The editor download is version pinned and integrity checked. Production configuration, media, database contents, backups, and generated test artifacts are excluded from the release, source, and update archives.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE). Third-party components retain their licenses and notices. This project is independent of the PHPKB product.

## Searching with everyday wording

Employees can search with a question such as “My Outlook won’t start.” Common conversational words are ignored, and an article can appear when it matches part of the question. Articles matching more meaningful words come first; title matches carry more weight than search keywords, which carry more weight than body text. Exact title matches receive an additional boost within the same word-coverage group. Typos and partial words remain supported.

For example, a general “Outlook issues” article can be found without containing “start.” A more specific Outlook startup guide ranks ahead of it when it matches both words. Search remains keyword-based: it does not infer arbitrary synonyms or generate answers. Admins can add useful alternate words such as `email`, `mail`, `startup`, `opening`, or `freezing` in an article’s **Search keywords** field, then publish the change.

Category filters still apply. Only published content appears. Searches accept up to 200 characters and use up to eight meaningful words after common-word filtering. Short technical terms and drive letters remain supported, including `IT` in questions such as “Can IT help?” Search uses the existing index and does not require rebuilding it.

## Header link and known-issues banner

Under **Settings → Header link**, enable the link, enter its label and full HTTP/HTTPS address, and choose the current or a new window/tab. It appears beside Sign out for admins and Admin sign in for readers. Internal hostnames and IP-based web addresses are supported. The browser decides whether a new context opens as a tab or window.

Under **Settings → Known-issues banner**, enable the banner and enter a plain-text message. The notice appears below the header on every page for readers and admins; line breaks are preserved. It stays visible until an admin disables it. Both features are off initially. Disabling either keeps its text for later reuse. Click **Save settings** to apply changes.

These settings are stored in the database. The browser installer and update center apply the required migrations.
