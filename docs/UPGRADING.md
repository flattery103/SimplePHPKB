# Browser upgrades

## Sites already running 0.6.0 or later

1. Sign in and select **Application updates** in the admin sidebar. A newer published GitHub release also produces an admin notice.
2. Download that release's **SimplePHPKB-vVERSION-update.zip** from `https://github.com/flattery103/SimplePHPKB/releases`.
3. Under Upload an update, select that ZIP and choose **Upload and review**. Check the displayed version.
4. Choose **Create backup**, then **Download backup**. Save the ZIP somewhere separate from the web server.
5. Confirm that you saved a backup and choose **Apply application files**. A fresh server backup is also made immediately before replacing files. During file replacement, requests receive a temporary maintenance response.
6. Choose **Apply database changes and finish**. This request loads the new PHP code and runs pending SQL migrations. Even if there are no SQL files, this button completes the update and reopens the site.
7. Check sign-in, an article/image, search and editing. Download the retained server backup if you also want the snapshot from immediately before replacement.

No CLI command or Apache reload is required. Configuration, database content and uploaded files are preserved. New update packages include PHP/editor dependencies, so these move with the release too. Local edits to distributed templates/code will be overwritten; keep them in your source-control branch and merge changes before deployment.

A server without writable application files can use SFTP or a hosting file manager to upload the new files, then visit `index.php?route=updates` to run SQL changes in the browser. Block ordinary access while manually replacing files: a file manager does not participate in the app's request locks. Keep config and storage. Take a hosting backup first; a browser backup after manual upload can only save the already-replaced code.

## One-time bridge from 0.5.0 or older

Older releases do not yet have the web updater. Upload and extract **SimplePHPKB-v0.6.0-update.zip** using your hosting file manager or SFTP, preserving `config/` and `storage/`. That ZIP contains neither private configuration nor data. Visit **`index.php?route=updates`** and sign in. Apply any pending SQL updates there. Version 0.5.0 already has all four current migrations, so it needs no new SQL for 0.6.0.

On Rob's existing server, optionally use these commands as root after uploading the ZIP to `/home/robadmin/` and taking a backup:

```bash
unzip /home/robadmin/SimplePHPKB-v0.6.0-update.zip -d /home/robadmin/kb-update-0.6.0
cp -a /home/robadmin/kb-update-0.6.0/SimplePHPKB/. /www/kb/
```

To enable future browser file updates on this production copy, grant the PHP account write access to the app code directories and top-level release files. Ownership must match your deployment policy. For a single-purpose Ubuntu site managed through the web, making `/www/kb` owned by `www-data:www-data` is one option. Never apply ownership changes to all of `/www`, and do not use permission 777. See DEVELOPMENT.md for shared developer/web-server permissions.

## If an update is interrupted

Go directly to **`index.php?route=updates`**. Sign-in works during maintenance. Use **Resume file update** or **Apply database changes and finish**. The updater keeps staged files, a durable state record and its backup. A failed operation does not reopen employee access. Resolve disk-space/permission problems in your hosting panel before retrying.

Do not delete `storage/updates/state.json` to bypass a failed update: doing so can expose mismatched code and schema. Restore the complete backup if retry cannot finish; database DDL is not automatically rolled back. See BACKUP.md.

## Release checks and limits

Checks use GitHub's public Releases API, not a comparison with the main branch. Publish a non-draft, non-prerelease release with a tag like `v0.7.0`. A commit or bare tag alone does not create an update notice. Cached checks run at most every six hours automatically; **Check again** has a one-minute cooldown. Errors and missing releases are shown as unknown/unavailable, never as proof that the site is current. No GitHub token is stored.

Only admins can install packages. All admins share the same permissions and can deploy executable PHP; accept packages only from a trusted project release. Manifest hashes detect damaged or altered contents, but are not a publisher signature. Newer-version checks, a minimum-version field, strict paths, expanded-size limits, no ZIP symlinks, and protected config/storage paths are enforced. The updater supports a single application server, not simultaneous nodes or external deployments.

The recovery controller uses a private pre-update snapshot and does not load the live Composer dependency tree while files are being replaced. If the ordinary entry URL cannot load, try **`recover.php`** at the same site root. It requires the same admin sign-in and CSRF checks. This route is active only during the file phase; otherwise it returns to Application updates. Future releases must preserve this recovery entry-point contract and keep auth tables compatible until the file phase finishes.
