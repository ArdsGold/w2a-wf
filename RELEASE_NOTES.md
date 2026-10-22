# Wolf Forge Article Generator r4.0.1

Release prepared: **2026-10-22 (Asia/Manila)**.

## Release highlights

- A background GitHub version check shows a notice above the plugin tabs only when the running version differs from the main-branch plugin header. Matching versions and failed checks stay silent. Successful checks cache for six hours; no automatic update is performed.

- Import Results cards show mini thumbnails for all plugin-inserted images, with full-image links in new tabs. Media Library/Pixabay use local thumbnails; GBP loads live with attribution. Reused imports also show previews, without persisting Google photo URLs.

- Optional Pixabay fallback finds matching photos during Submit and saves useful matches to Media Library with source credits. Configure its separate key in Settings & Logs, then enable it on Image Media Library. It defaults off, respects image placement rules, caches searches for 24 hours, limits searches/downloads, reuses downloaded photos, and preserves publishing on provider errors.

- Orange Import Results edges highlight generated articles with fewer images or internal links than requested. Complete results remain green and failed imports remain red.

- Import DOCX files from your computer, or DOCX files and native Google Docs from a public Google Drive folder. Drive offers paged listings, document selection, sequential downloads, credential-source status, and quota-error notices. This version reads files directly in the folder, exports native Google Docs to Word automatically, and excludes subfolders.
- Use **Cancel** beside Submit to stop the active browser request and remaining local/Drive queue. Completed articles and selected files remain. An interrupted server request may still finish; the results explain how to retry safely.
- Carry a default Drive key in the plugin-root **config.php** for installation on other servers. A saved Settings & Logs override takes priority, followed by the wp-config.php constant, then config.php.
- New installations enable phone linking, bullet-list conversion, body-bold cleanup, and image insertion, with **5 internal links** and **3 images** per article as maximums. Existing saved preferences are retained.
- One WordPress sidebar entry contains Generate Articles, Duplicate Blogs, Image Media Library, and Settings & Logs. Tabs switch without reloading and retain the file selection and import state.
- Named month/year controls, date/range shortcuts, inline validation, and live schedule previews show filenames, date reuse, and past-date warnings. Month/date-range overflow loops back to the first available date.
- Refactored DOCX imports strip filename numbering, remove the initial document H1, demote later H1s, link supported phone numbers, and recover safely from retries.
- Native automatic internal linking matches meaningful title phrases and saved Yoast/Rank Math keyphrases, skips ambiguous title fragments, and preserves existing links and protected markup. Destinations are published blog posts only.
- Yoast receives SEO title, description, and focus keyphrase. Rank Math receives only its focus keyphrase. Plugins Status lists these two SEO plugins plus the last Google Drive API connection result, distinguishing configured keys from verified connectivity and errors.
- Media Library matching excludes labeled logos/icons/favicons and the WordPress site logo/icon, using metadata and filenames without visual recognition. Unlabeled graphics and unclassified GBP photos cannot be reliably identified by this filter.
- Media Library, live Google Business Profile photos, or Both share one saved image count. Image controls are synchronized across Generate Articles and Image Media Library. New images require at least 100 article words between positions and stay before Conclusion; fewer are inserted when relevant spaced positions are unavailable.
- Duplicate Blogs excludes pages. Copy All Imports appears beside Submit/Cancel and above Import History with shared linked-title clipboard copying; Import History provides separate View/Preview, Edit, and reversible Trash actions.

## Install or upgrade

Upload **wolf-forge-article-generator-r4.0.1.zip** through WordPress Plugins > Add New > Upload Plugin and replace the existing installation when prompted. The archive retains **w2a-wf/** and **word-to-article-importer.php**. Existing database settings, history, logs, and posts are retained. Reload open plugin admin screens after upgrading.

This release intentionally tracks **config.php** in Git and includes the current configured file unchanged in the installation ZIP. Repository readers and ZIP recipients can read bundled credentials, and installations using the same key share its provider/project quota. Actual key values are not copied into documentation or the commit message. Site-specific overrides remain in the WordPress database. Plugin replacement can overwrite the bundled file; preserve any local edits when preparing upgrades.

See [INSTALLATION.md](INSTALLATION.md), [USAGE.md](USAGE.md), and [CHANGELOG.md](CHANGELOG.md).

## Practical limits

Drive status cannot provide an exact remaining project quota. Google Docs exports are limited to 10 MB, in addition to existing server/document limits. Reported quota errors pause the queue; retry the same selection/settings after waiting or choosing a suitable project key. Keys from the same project share project quota, and another project may not bypass file-specific download restrictions. Local uploads remain available without Drive access.

Images and internal links use maximum counts; insufficient relevant matches or safe positions can produce fewer insertions. Media Library images require contextual titles and alt text. GBP uses Google's returned photo order, up to ten photos, without visual recognition, keyword matching, or owner/customer filtering.

GBP photos remain Google-hosted with attribution and require a separately configured Places API key. Keep Wolf Forge active and exclude articles containing live photo shortcodes from full-page/CDN caches. Google failures omit affected photos while preserving article text. Existing posts are not automatically backfilled or rearranged.

AI image recognition, batch AI metadata editing, and physical image-file renaming are not included.

## Validation

- 37 standalone version-checker checks with mocked HTTP for matching/newer/older/invalid versions, prefixes, caching, redirects, bounded requests, and silent failures. The r4.0.0 implementation was live-checked against GitHub before this version bump; publishing matching r4.0.1 versions will keep the notice hidden after the next fresh check.
- 274 PHP regression checks with PHP 8.5 PclZip/ZipArchive and PHP 8.2 PclZip.
- 112 WordPress integration checks for scheduling, retries, SEO, native internal linking, media, history, and mocked live-photo rendering.
- 62 Pixabay WordPress integration checks for private credentials, search caching/filtering, real local downloads, attribution, spacing, reuse, import/retry behavior, request limits, unsafe files, and recoverable provider failures. Pixabay HTTP is mocked; live access still needs a real key.
- 45 Google Drive integration checks for public listing/download/import, credential precedence/privacy, quota errors, authorization/nonces, retries, and temporary-file cleanup. Google HTTP is mocked.
- 20 isolated private-configuration checks on PHP 8.5 and PHP 8.2, without reading or rewriting the real private configuration.
- JavaScript checks for queue behavior, autosave, tabs, history/clipboard, Google controls, date previews, cancellation, and safe retries.
- Browser checks for hidden matching/unavailable version checks, visible newer/older mismatches, thumbnail counts, full-image new-tab navigation, mobile wrapping, Pixabay key save/remove, fallback autosave/reset, Drive selection, quota pause, key overrides, retained selections across tabs/retries, local/Drive/preflight cancellation, reset defaults, and mobile layout. Earlier checks cover detailed schedule controls, clipboard behavior, and the admin icon.
- Live public-folder listing and four Google Docs exports/parser checks without creating posts.
- PHP/JavaScript syntax, documentation links/anchors, whitespace, ZIP CRC, and source-byte verification.

Live public-folder verification found four native Google Docs and exported/parsed all four successfully without creating posts. Automated checks continue to mock Google responses. Prepared for the r4.0.1 repository commit and installation ZIP. No hosted GitHub release is created by the plugin checker.
