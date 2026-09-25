# Third-party components

SimplePHPKB 0.5.0 is distributed under GPL-2.0-or-later.

| Component | Pinned version | License | Source |
| --- | --- | --- | --- |
| TinyMCE Community | 8.9.2 | GPL-2.0-or-later option | https://github.com/tinymce/tinymce |
| HTML Purifier | 4.19.1 | LGPL-2.1-or-later | https://github.com/ezyang/htmlpurifier |

TinyMCE is self-hosted using its GPL mode. Only bundled Community plugins are enabled. The editor's attribution is retained. Its license notice is at `public/assets/tinymce/license.md`. The upstream npm archive contains the distributed JavaScript source and is pinned by SHA-512 in `scripts/fetch_editor.py`.

HTML Purifier is pinned in `composer.lock`; its PHP source and license are included under `vendor/ezyang/htmlpurifier/` in the full release. Composer's generated loader and its license are included under `vendor/composer/`.

Neither component is fetched from a CDN at application runtime. No premium editor plugins or cloud API keys are used. Review the included license texts when redistributing or modifying the project.
