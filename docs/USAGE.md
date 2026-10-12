# Usage reports

Sign in as an admin and select **Usage** in the left sidebar. Choose Today, Last 7 days, Last 30 days or All time. The default is Last 30 days.

- **Site visits:** one visit for each anonymous browser session that opens the home page, browses/searches, or opens a published article on a reporting day. A direct article link counts too.
- **Article views:** one view of each article per anonymous browser session per day. Refreshes and repeat reads in the same session/day do not add views. Viewing two different articles adds two article views but only one site visit.
- **Articles read:** the number of different articles with a counted view in the selected period.

The article table is sorted by views for the selected period, then all-time views. Search by title and use Previous/Next for longer lists. Articles with zero views, drafts and Trash remain visible, so admins can find unused articles and historical counts. The summary cards always cover the whole site even when the article list is filtered. Published titles are used for published articles; unpublished articles use their draft title.

**Manage articles** also has an **All-time views** column. Public homepage popularity continues to use those lifetime article totals, with featured articles first.

## What the counts mean

Counts approximate browser sessions, not unique employees. Another browser/device, a cleared cookie or an expired session can count again. Returning in the same session on another day counts as another visit. All reporting days use **UTC**, matching earlier article counting. The 7- and 30-day selections include today and the preceding 6 or 29 dates; these are calendar-day totals, not a count of distinct people over the entire period.

Signed-in admins, recognized bots/link previews, requests without a user agent, HEAD requests, images, preview/admin/login pages, invalid routes and missing articles are excluded. Bot detection is a simple user-agent filter, not a guarantee that every automated request is recognized. A visit made before signing in remains counted; signed-in browsing does not add counts. Use a separate signed-out browser session to check real visitor counting.

## Existing installations

Migration **005_usage.sql** creates the new reporting tables. Site visits and date-based article statistics begin when this migration runs; the Usage page shows that time. The first day may be partial. Existing article view counts remain in all-time totals, but cannot be reconstructed by date. An article already counted for the same session/day before upgrading is not counted twice after upgrading.

Upgrades from 0.6.0 use **Application updates**: upload `SimplePHPKB-v0.7.0-update.zip`, review it, create/download a backup, confirm the backup, apply application files, then choose **Apply database changes and finish**. Open **Usage** after the update. No Apache reload or command-line SQL is needed.

## Storage and availability

Everything is stored in the existing MySQL database. No external analytics service, tracking script, IP-address log or employee identity is added. Hashed session identifiers are used only for daily deduplication; old deduplication rows are removed in small batches during subsequent reader visits. Aggregate counts are kept for reports and included in ordinary database backups.

Trashing or unpublishing an article retains its historical totals; it cannot gain new views until published again. If recording a visit fails, the article remains readable and the server error log records the failure. Counts may therefore understate usage during a database write problem.
