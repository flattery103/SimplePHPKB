# SimplePHPKB — Initial Design

Date: September 24, 2026  
Status: Agreed feature scope, with proposed implementation details for review.  
Working project name: SimplePHPKB

## Purpose and agreed requirements

Build a simple PHP/MySQL knowledge base for employees on an internal network. The deployed knowledge base will not be accessible on the public internet. Employees can read published articles without signing in; admins must sign in to manage content.

The user approved fuzzy search, a homepage showing the ten most viewed articles, a left category sidebar similar to IntraBids, a WYSIWYG editor with HTML source editing, screenshot upload and paste, drafts and previews, revisions and trash, optional search keywords, permanent links, last-updated dates, and individual admin accounts. Installation and backup instructions are part of delivery.

Success means an employee can find instructions quickly, while an admin can create a useful illustrated article without writing HTML. Routine reading, editing, and searching must not depend on internet access.

## Approach

Use a standalone, server-rendered PHP application backed by MySQL, with JavaScript for editor interactions and small interface enhancements. Bundle the editor and its assets locally. Keep search in PHP/MySQL; do not require a separate search service.

Alternatives considered:

| Approach | Trade-off |
| --- | --- |
| Standalone PHP/MySQL application — proposed | Fits the requested stack and keeps installation and maintenance small. |
| Application built around a larger PHP framework | Adds structure and built-in facilities, but increases dependencies and deployment requirements for this small scope. |
| Separate search service and browser application | Provides more scaling options but adds services and operating overhead that the initial KB does not need. |

Proposed compatibility targets are PHP 8.3 or later and MySQL 8.0 or later, with MariaDB 10.11 or later as an alternative. Compatibility must be validated during implementation. Dependency versions and licenses will be checked before choosing the editor and HTML sanitization library. This document does not claim those packages have already been selected or installed.

## Reader experience

- A search field is prominent on the homepage and available on article pages.
- The default homepage shows up to ten published articles ordered by total qualified views. Ties use the most recent publication/update date, then article ID.
- The left sidebar lists categories alphabetically with published article counts. It includes All Articles and becomes collapsible on small screens.
- Category selection opens a paginated article list; All Articles lists every published article. Search can be scoped to the selected category, with the scope visible beside the search field.
- Each article displays its title, category, last-updated date, formatted content, and a copy-link action. Links use an immutable numeric article ID, so renaming an article does not break its URL.
- Empty categories and searches without results show a clear message and links back to browsing.

Use one category per article and a flat category list for the first version. Admins can rename categories; deleting a nonempty category requires moving its articles first. These are proposed simplicity defaults rather than previously specified user requirements.

Count one article visit per anonymous browser session per day. Exclude signed-in admins, previews, and known automated clients. This is an approximate popularity measure, not a count of distinct people. Popularity counts never determine search relevance.

## Search behavior

Search titles, optional keywords, and plain text extracted from article content. Prioritize exact title matches, then strong title/keyword matches, then body matches. Return short text previews and deterministic ranking.

Support partial words and common misspellings. Use indexed exact/prefix lookup with a bounded PHP typo-matching step over normalized search terms, followed by database retrieval of matching articles. Typo tolerance is conservative: no fuzzy expansion for terms of three characters or fewer; longer terms may match a small edit distance. For example, “pasword” should find an article titled “Reset your password.” Optional keywords let admins connect different terms such as “wireless” and “Wi-Fi.”

Search operates on the published version only. Draft content, revision text, and trashed articles must not appear in results, previews, counts, or search suggestions. Publishing, unpublishing, restoring, and trashing update search data with the content change. Search limits and performance will be checked against a representative set of 5,000 articles during implementation; this is a validation target, not a measured capacity claim.

## Admin workflow

Admins sign in to manage articles, categories, and admin accounts. No public registration or reader accounts. All admins have the same permissions in version one; the last active admin cannot be disabled.

An article form contains title, category, optional keywords, and the editor. The editor supports headings, emphasis, lists, links, tables, images, and code blocks, plus an HTML source toggle. Screenshots can be uploaded or pasted into the editor. Supported image types are PNG, JPEG, and WebP, with a proposed 10 MB maximum per file.

Save Draft, Preview, and Publish are explicit actions. For an already published article, saving draft edits leaves the current published version available until Publish is selected. Preview requires an admin session. Warn before leaving with unsaved changes.

Each publication preserves a revision with its editor and timestamp. Restoring a revision loads it into the draft for review before republishing. A version check prevents one admin from silently overwriting another admin's intervening save.

Trash removes an article from employee access while retaining its content, images, and history. Restoring from trash returns it to draft status. Permanent deletion and automatic image cleanup are deferred to avoid introducing destructive maintenance controls in the first release.

## Application structure and data flow

Keep request handling, authentication, article storage, search, uploads, HTML filtering, and templates in separate small modules. Use PDO with prepared database queries and versioned database migrations.

Logical records include admins, categories, articles, article revisions, media, search terms/postings, and daily view deduplication records. Store separate draft and published revision references so unpublished edits do not leak through reader routes. Store uploaded files outside the directly served document root, with media metadata tied to the owning article.

An admin save validates the request and filters HTML before persisting the draft. Publishing writes a revision, updates the published reference, and refreshes search data in a transaction. Reader requests resolve only the published reference. Image delivery checks whether the image belongs to a published article or the requester is an authenticated admin. Removing an article from publication also removes anonymous access to its images.

## Internal deployment and protection

Deploy behind the organization's internal network controls using HTTPS. Network/firewall configuration enforces internal-only reachability; a search-engine exclusion file is not an access control. Include no-index defaults as a secondary measure. The GitHub source repository and the employee KB deployment have separate visibility settings.

Use hashed passwords, secure session settings, login throttling, CSRF checks for changes, server-side authorization, and a maintained HTML sanitizer. HTML source mode allows supported content markup, not arbitrary scripts, embedded applications, or event handlers. Validate image content and filenames, and never execute uploads. Avoid external fonts, CDNs, analytics, or runtime cloud services.

Keep database credentials, uploaded images, KB content, backups, and production configuration out of the source repository. Initialize the first admin through a documented one-time local command; do not ship a default password. Provide a local command to reset an admin password without requiring email delivery.

## Error handling and delivery

Validation errors preserve the submitted form and explain the field requiring attention. Missing or unpublished articles return a generic not-found response to readers. Database and upload failures show a useful message while detailed diagnostics go to server logs outside employee access. Failed saves do not replace published content.

Deliver a versioned installation ZIP with required runtime dependencies included, an example configuration, database setup/migrations, and Apache/Nginx examples for root or subdirectory installation. Document requirements, first-admin creation, upgrades, and backup/restore of the database, media, and configuration. The application will not configure the organization's firewall automatically.

## Verification before release

Verify anonymous reading and denied admin access; draft/revision/media isolation; typo and partial-word search; category filtering; view deduplication; screenshot upload/paste; HTML filtering; revision recovery; concurrent edit detection; mobile sidebar behavior; installation; and backup restoration. Exercise the reader and editor in a browser with internet access disabled to confirm bundled assets are sufficient.

## Deferred scope

Helpful/not-helpful votes, comments, general file attachments, related articles, nested categories, SSO, granular roles, AI answers, external search services, and automated updates are outside the initial release. They can be considered separately after the basic KB is in use.
