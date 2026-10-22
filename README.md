# Wolf Forge Article Generator

Version: **r4.0.0**

Local release: **2026-10-22 (Asia/Manila)**

See [release notes](RELEASE_NOTES.md), [installation](INSTALLATION.md), and [usage](USAGE.md).

Authors: Macky Villafuerte, Arden Guinto

License: GPL v2 or later

Generate standard WordPress articles from DOCX documents. This is the article-import companion to **Wolf Forge Elementor Page Generator**; it uses the same Wolf Forge naming, author credits, release style, and WordPress-native admin presentation. It does not generate Elementor layouts or depend on Elementor.

## Features

- Select or drag in up to 1,000 DOCX files; documents are uploaded and processed sequentially, with progress and per-document results.
- Preserve paragraphs, headings, basic tables, mixed nested numbered/bullet lists, bold, italic, underline, strike-through, tabs, line breaks, and supported external hyperlinks.
- Optionally convert numbered lists to bullets or remove bold outside headings.
- Choose **Media Library**, **Google Business Profile**, or **Both** for article images. Public Google profile links use live Places API photos with attribution and a separately configured server-side API key.
- Automatically insert up to a saved maximum of relevant internal links to existing published blog posts, using meaningful title phrases or saved SEO keyphrases. Native matching needs no third-party linking plugin or AI. Zero disables insertion.
- Automatically link phone numbers beginning with `+` or `00`, plus formatted US/Canadian numbers such as `(216) 399-7569`; enabled by default with a saved checkbox. Preserve displayed text and existing links, and strip formatting from `tel:` destinations.
- Configure Media Library integration in its own tab: match image titles, alt text, captions, and descriptions, preview placements, and browse/edit media metadata.
- Publish immediately or schedule at 9:00 AM in the **WordPress site's timezone**, using daily, weekly, or monthly intervals.
- Reuse completed imports and resume incomplete drafts when the same file, normalized settings, and assigned publish date are retried.
- Automatically save formatting, image, author, category, and publishing preferences in WordPress options, with a visible autosave status.
- Review exact-content duplicate posts in **Duplicate Blogs**, with View, Edit, and protected Trash actions. Pages are excluded.
- Check Yoast SEO and Rank Math SEO availability and Google Drive API connection state in the compact **Plugins Status** card.
- Switch internal tabs without a page reload, preserving selected files, preview text, results, and active imports. Browser Back/Forward and direct links remain available.
- Review the ten most recent imports in **Settings & Logs**, copy completed displayed imports as a numbered list of linked post titles, and use separate View Post, Edit Post, and Move to Trash actions. Clipboard and Trash results appear inline without popups. Error statistics, diagnostics, and reset/clear tools remain in this tab.
- Retain the existing SEO metadata adapters for Yoast, Rank Math, AIOSEO legacy post metadata, and SEOPress. Active Yoast receives an SEO title, meta description, and generated title-derived focus keyphrase. Active Rank Math receives only the focus keyphrase; the importer does not write Rank Math SEO title, description, or slug overrides. Their adapters run independently, so activating Rank Math does not disable Yoast metadata. Existing non-empty focus keyphrases are preserved. The importer also assigns the standard WordPress post slug, which both SEO editors use. SEO output depends on the installed SEO plugin/version and is not a ranking guarantee.

## Requirements

- WordPress 6.0+ and PHP 7.4+.
- PHP DOM for automatic image placement, previews, and phone links. Without it, articles remain importable without those automatic enhancements.
- PHP SimpleXML and zlib; ZipArchive is preferred. WordPress's bundled PclZip is used when ZipArchive is unavailable.
- A normal WordPress MySQL/MariaDB database with named locks (`GET_LOCK`/`RELEASE_LOCK`) available. Imports fail with a retryable message if a lock cannot be obtained.
- An administrator with `manage_options` for imports and settings; media requests additionally require `upload_files`.
- DOCX files within the server upload limit. Selected XML parts must be at most **8 MiB each and 16 MiB combined**; archives may contain at most **4,096 entries**. XML document type/entity declarations are rejected.

## Start here

1. Activate the plugin and open **Wolf Forge Articles**.
2. Configure the image source and matching preview in **Image Media Library**, then choose documents, formatting, author, category, and publishing settings in **Generate Articles**. Enable image insertion and set its maximum count from either tab; these controls share one saved setting and stay synchronized.
3. Click **Submit**. Keep the page open while progress advances.
4. Open each result's **Edit Post** link to review its content and status.

See [Installation](INSTALLATION.md) for upgrades and [Usage](USAGE.md) for scheduling, title rules, retry behavior, and troubleshooting.

## Title and content rules

A descriptive filename becomes the post title. Leading numeric list markers such as `1.`, `001.`, `2)`, `(3)`, and `[4]` are removed first, so `1. Article Title.docx` generates **Article Title**. Spaces, underscores, and hyphens are then normalized. Meaningful numbers such as `2026 Marketing Trends`, `10 Tips`, and `3.14 Explained` are preserved. The cleaned title supplies the WordPress slug and supported SEO title/keyphrase generation; source filenames and import history keep their original names. An empty or numeric filename falls back to the document's first supported heading. If neither provides a title, the importer uses "Imported Article."

The first DOCX H1 is removed from the body regardless of whether it matches the filename-derived post title. Subsequent H1 headings become H2, leaving no H1 in imported body content. Numeric/empty filenames can still use the document heading as the post title before it is removed. The theme should render that post title once as the page H1; the plugin does not rewrite theme markup. Documents with no supported body after title removal are rejected.

Numbered lists remain ordered unless conversion is enabled. Basic table header rows become HTML header cells. External `http`, `https`, `mailto`, and `tel` hyperlinks retain their destinations; unsafe or unsupported destinations retain only their text.

## Google Business Profile photos

The **Image Media Library** tab now offers **Media Library**, **Google Business Profile**, and **Both**. The default remains Media Library. Set the existing image maximum (default three), enable insertion, paste a public Google Maps/profile link, and configure a Google Places API key. Use **Check Profile Photos** to verify the resolved business before importing. Source and profile preferences autosave; API credentials use separate Save API Key / Remove API Key controls.

GBP uses all photos returned by Places API (New), up to Google's limit of ten. There is no owner/customer filtering, visual recognition, keyword matching, or invented descriptive metadata for GBP images. Media Library images retain strict title/alt matching. Both sources share one placement plan and maximum; all new image positions remain before Conclusion. Insufficient photos/blocks or Google failures can result in fewer images; Both can retain qualifying local images if Google is unavailable.

Articles store only a shortcode containing the stable place ID and ordinal photo slot. They never store Google photo resource names, expiring image URLs, attribution responses, or downloaded photo files. On rendering, the plugin fetches fresh Google metadata and photo URLs, displays the official Google Maps logo and supplied photo-author attribution, and links to the source photo. The profile's current photo order can change what appears in a slot. Generic alt text identifies the source profile without claiming to describe the image.

Google requests use the server-side key in a header. The key is excluded from settings snapshots, import history, posts, front-end markup, and AJAX responses. Store it through the private credential control or define `WTAI_GOOGLE_PLACES_API_KEY` in wp-config.php (which takes precedence). Resetting normal options does not remove the separately stored key; use Remove API Key. Removing it prevents existing live photos from rendering.

The API requires Places API (New) and billing. Profile checks, previews, and uncached article views may incur Google charges. Metadata/HTML reuse lasts only for the current PHP request, including repeated shortcode processing by other plugins; there is no persistent Google photo cache. Pages using the shortcode set `DONOTCACHEPAGE`. Exclude them from external full-page/CDN caches too, otherwise cached HTML may retain expired Google URLs. Google/API outages omit the affected photo instead of breaking article text. See [Google photo setup and notices](INSTALLATION.md#google-business-profile-photo-setup) and [usage](USAGE.md#google-business-profile-image-source).

**Copy All Imports** beside Submit/Cancel and in Settings & Logs copies the same displayed completed history as numbered linked titles, with shared inline feedback.

Use **Cancel** beside Submit to stop the active browser upload/request and prevent remaining documents from being sent. Completed articles and the selection are kept. An interrupted request may still finish on the server; check Import History and retry the same selection/settings to reuse completed articles.

## Google Drive document source

On Generate Articles, choose **Public Google Drive folder**, paste a public Viewer folder URL, and select **Load Documents**. Select documents and use Submit with the existing scheduling, SEO, formatting, images, and internal-link options. This version lists downloadable DOCX files and native Google Docs directly inside the folder, with pagination and a maximum of 1,000 loaded documents per batch. Google Docs are exported to DOCX automatically before the normal importer runs. Shortcuts and subfolders are excluded. Google Docs exports have a 10 MB output limit, and the existing server/document limits still apply.

To carry one Drive key between servers, enter it once in the plugin root `config.php` and build/copy your private installation ZIP with that file included. Key precedence is: saved Settings & Logs override, `WTAI_GOOGLE_DRIVE_API_KEY` in wp-config.php, then the plugin-local `config.php`. An empty configuration leaves Drive unconfigured. The private file is ignored by Git but included in installation ZIPs; keep configured ZIPs private. Servers using the key share its project quota. Plugin replacement can overwrite this file, so retain it when preparing upgrades. Administrators can save a separate private override in Settings & Logs; the override takes precedence and can be removed with **Use Server Default**. Enable Google Drive API and configure suitable API/IP restrictions for server requests. Keys are not returned to the browser, included in normal settings/history, or copied into posts. The Places photo key is separate.

The Generate Articles status reports the selected credential source and Google access/quota errors, not an exact remaining quota counter. The Plugins Status card also shows Google Drive API as Not configured, Configured (not checked), Connected, Rate or download limit reached, Access or API key error, or Connection error. It reflects the last API response cached for up to one hour and updates after listing/importing or changing credentials; having a key alone does not mean a connection has been verified. On a reported quota limit, the queue pauses and retains its selection; wait and retry or configure a key from another Cloud project. Keys in the same project share project quotas, and changing projects does not bypass file-specific download restrictions. Retry the same selection/settings so completed articles can be reused. Temporary downloads are bounded by the server file-size limit, validated as DOCX, and deleted after processing. See [Drive setup](INSTALLATION.md#public-google-drive-folders).

## Automatic internal links

On **Generate Articles > Content Processing**, set **Maximum internal links per article** to a positive number. The option autosaves; its first-launch default is **5**, and **0** disables insertion. The importer links case-insensitive phrases matching a published blog post's complete title, a contiguous two-to-five-word title fragment, or a complete saved Yoast/Rank Math focus keyphrase. Title fragments require at least two content words and cannot start/end with common English connective, question, or generic guide words. Punctuation separates title fragments. Shared fragments pointing to multiple destinations are skipped; an exact title or explicit SEO keyphrase remains eligible. It prefers longer, more specific phrases and preserves visible text and inline formatting around a complete phrase. It does not generate anchor text or rewrite sentences.

Destinations must be existing published, password-free blog posts on this site. Pages, drafts, scheduled/private posts, the source article, off-site URLs, and already-linked destinations are excluded. Each target receives at most one new link, and each paragraph, list item, or table cell receives at most one. Headings, existing links (including phone links), image figures/captions, and code are preserved. Single-word or split-format phrases are skipped. Fewer links are inserted if safe relevant matches are insufficient, with an inserted/requested count shown in import results.

These are ordinary WordPress content links inserted by Wolf Forge itself. No third-party linking plugin, external service, or AI is used. Matching is literal and deterministic; it does not infer synonyms or understand meaning. The connective-word filter is English; other languages still receive literal Unicode phrase matching and may need more careful review.

The feature applies to new imports. Completed retries retain their existing content. Changing a positive maximum changes import identity; zero preserves fingerprints from versions without this setting. Existing posts are not backfilled. Published posts generated earlier in a batch can become destinations for later articles; future-scheduled posts cannot.

## Phone links

The saved **Automatically link phone numbers** checkbox is enabled by default on Generate Articles. New imports link formatted numbers with an explicit `+` or `00` country-code prefix: `+1 (202) 555-0123` becomes `tel:+12025550123`, and `0063 917 123 4567` becomes `tel:00639171234567`. Formatted US/Canadian numbers also qualify: `(216) 399-7569`, `216-399-7569`, `216.399.7569`, and `216 399 7569` become `tel:2163997569`; an optional formatted leading `1` is retained. Bare ten-digit strings are skipped. Visible text and the prefix stay unchanged. Spaces, parentheses, square brackets, dots, and dashes are removed from the destination. Existing hyperlinks, HTML attributes, code, and preformatted text are skipped.

Matching recognizes a whole number in one text fragment, with 7-15 ASCII digits after the international prefix (including the country code; its first digit must be 1-9), and does not infer local country codes, verify assigned numbers, or distinguish mobile from landline numbers. Extensions are not added to the destination, and numbers split across separately formatted fragments are left unchanged. Existing posts are not backfilled. Turning this option on/off changes import identity, as other generation settings do. See [recognized phone formats and exclusions](USAGE.md#phone-numbers) for examples, prefix spacing, Unicode separators, and limits.

## Scheduling

Choose a named month and separate year, use This month/Next month or Today/Tomorrow shortcuts, and select inclusive 7-day/30-day ranges. Native date inputs include readable date labels and inline range errors. A read-only live preview lists actual publication dates for selected filenames, using the server scheduler, with clear warnings for overflow and past times. Before files are selected it shows a labeled three-article example. Existing saved ISO preferences and 9:00 AM WordPress-timezone behavior are preserved.

- **Within Specific Month:** the first publishing option. Pick a month and year; a batch up to the number of days is evenly spread from day 1 through the last day (one article uses day 1). Larger batches use each day in order, then repeat from day 1. Times remain 9:00 AM in the WordPress timezone.
- **Publish Immediately:** create the article now.
- **Starting on a Date:** assign one article per selected interval, starting at 9:00 AM on the start date. No end date is required.
- **Between Two Dates:** build dates from the start through the inclusive end using the chosen interval, then repeat those dates for additional documents. A daily November 1-3 range gives documents 1-4 November 1, 2, 3, and 1, all at 9:00 AM. Several posts can share a publish time. Invalid or reversed dates are rejected before uploading.
- **Monthly:** keep the starting day when possible and clamp shorter months. January 31 -> February 28/29 -> March 31.
- **Past dates:** publish immediately with the assigned past date. Future articles use WordPress scheduled publishing and depend on WP-Cron or a configured system cron.

## Recovery and duplicate protection

The browser submits one document per request, so a large selection does not have to finish within one PHP request. It is a browser-driven queue, not an unattended background job. Closing the page stops further uploads.

Each new import uses a SHA-256 fingerprint of the uploaded file bytes, normalized options, and its assigned publish date. A database connection lock serializes identical imports. A recovery marker is stored in the draft's `post_content_filtered` field in the same database row as the post, before SEO/history work and final publication.

- Retrying an identical completed import returns its existing post without overwriting edits.
- A failure before final publication retains the draft; retry the same selection and settings to resume it.
- A lost network response stops the queue. Retry the original full selection in its original order so scheduled documents keep the same assigned dates.
- A duplicate in Trash must be restored or permanently deleted before importing it again.
- Changing file bytes, normalized settings, or assigned publish date creates a distinct import. Renaming identical file bytes alone does not create a distinct import.
- Duplicate protection applies to imports with recovery fingerprints; older imports without a fingerprint are not retroactively matched. The Duplicate Blogs tab can still list exact-content copies.
- Clearing history or error logs does not delete posts or their recovery markers.

## Structure

```text
word-to-article-importer.php          Bootstrap, admin menus, AJAX authorization/validation
icon.png                            Replaceable admin-menu icon, displayed at 20x20
includes/class-settings.php         Shared defaults and sanitization
includes/class-scheduler.php        Date calculation and publish status
includes/class-importer.php         One-document import, locking, draft recovery, history
includes/class-duplicate-pages.php  Duplicate content listing and safe Trash checks
includes/class-docx-parser.php      Bounded DOCX/XML parsing
includes/class-content-processor.php Title preparation and formatting cleanup
includes/class-image-matcher.php    Media scoring and safe word-percentage placement
includes/class-gbp-photos.php       Public-link resolution and live Google photo rendering
includes/class-google-drive.php    Public Word/Google Docs listing, private keys, bounded downloads/exports
includes/class-internal-linker.php  Title-phrase/keyphrase matching and safe internal anchors
includes/class-media-index.php      Cached metadata vocabulary and TF-IDF vectors
includes/class-seo.php              SEO metadata adapters
includes/class-error-handler.php    Error catalog, logs, statistics
includes/class-server-info.php      Server and extension diagnostics
admin/                              Tab views and native WordPress author/category queries
assets/                             Admin JavaScript and CSS
tests/                             Generated fixtures and regression/integration checks
```

The `w2a-wf` directory, `word-to-article-importer.php` activation file, `WTAI_*` classes/constants, `wtai_*` options/AJAX actions/tables, and the main admin page URL are deliberately retained for upgrades. The former Settings URL redirects to the **Settings & Logs** tab. The visible brand and translation domain are `Wolf Forge Article Generator` / `wolf-forge-article-generator`.

## Supported boundaries

This importer produces HTML in standard posts. It does not extract embedded DOCX pictures, create featured images, custom post types, Elementor content, Word themes, fonts, page layouts, footnotes, comments, internal bookmarks, or embedded objects. Advanced table merges/nesting, list numbering restarts/custom counter styles, and inherited/custom Word styles may require manual editing. Accepted inserted text is read; deleted revisions are omitted, rather than reproducing Word's review UI.

Automatic Media Library matching excludes the configured WordPress site logo/site icon and files with logo, icon, favicon, logotype, or logomark labels (including plurals) in their title, alt text, caption, description, or filename. Filename separators, numbered suffixes, and camel-case names such as CompanyLogo are recognized. Words such as silicon and iconic are not excluded. This is a metadata filter, not visual recognition: unlabeled graphics can still qualify. Google Business Profile photos do not provide equivalent classification metadata, so this filter applies to the local-library part of Media Library/Both. Fewer eligible photos produce fewer insertions rather than logo/icon substitutes.

Image matching uses deterministic TF-IDF/cosine scoring across all four metadata fields, with no AI, external service, or fixed stopword list. Unicode word tokens match exactly after case/accent normalization; synonyms and related concepts are not inferred. Titles and alt text have weight 3, captions 2, descriptions 1. Article titles and headings receive extra weight. Common library terms carry less statistical weight, but generic shared words can still match; review the preview and maintain descriptive metadata.

For Media Library matching, the maximum defaults to three and accepts any non-negative integer; zero disables insertion. Actual output is bounded by relevant, renderable, unique images and safe article blocks. For N inserted images, targets are 1/N through N/N of the word count before the first **Conclusion** heading (or the full article if absent; three: 33%, 67%, 100%). Both a descriptive media title and alt text must independently match the article and the destination paragraph or its active section headings. Missing or unrelated title/alt text excludes an image; a matching caption or description alone cannot qualify it. Newly inserted images must have at least 100 article words between their positions (excluding image captions). The planner reserves room for the remaining images; fewer images are inserted if relevant positions cannot satisfy this gap. Existing article images are not repositioned. Context takes priority over percentages: images stay in a matching section, so actual positions can move substantially from their targets. No new images are inserted at or after Conclusion, including later sections. The requested count is spread over the preceding content, subject to relevant images and safe blocks. Images snap to complete block boundaries without splitting lists or tables. The plugin inserts inside standard post content; the theme controls the surrounding post layout and no fixed template image slots are required. WordPress supplies native image markup and available responsive sizes; stored alt text and plain-text captions are preserved. Existing articles are not backfilled.

The metadata vocabulary is cached for up to one hour, invalidated when attachments or relevant metadata change, and can be refreshed manually. Image preferences save independently of publishing preferences. Each batch freezes its effective settings at preflight; media edits can still affect later documents in that batch.

Import Results use an orange left edge for articles with fewer images or internal links than requested. Complete results stay green and failed imports stay red; disabled or zero-count features do not trigger a warning.

## Validation

Run the dependency-free regression checks from the WordPress root:

```sh
php -n wp-content/plugins/w2a-wf/tests/run.php
php -n wp-content/plugins/w2a-wf/tests/config-checks.php
node wp-content/plugins/w2a-wf/tests/admin.test.js
```

The PHP checks generate temporary DOCX archives and clean them up. Run once with PHP ZIP enabled and once without it to verify the PclZip fallback. SimpleXML, DOM, Phar, and zlib are needed for these generated test fixtures.

For a **local/test WordPress installation only**, using PHP configured for that site's database:

```sh
php wp-content/plugins/w2a-wf/tests/integration.php
php wp-content/plugins/w2a-wf/tests/drive-integration.php
```

Integration checks create temporary published/draft/future posts and attachment records, simulate failures, verify retries/locking/media matches, and remove their records in a `finally` block. Google HTTP responses are mocked and temporary preferences/credentials are restored. They require an existing administrator and initialize the plugin schema. They do not edit existing content or change the site timezone. The private configuration checks use an isolated temporary directory and do not read or rewrite your real config.php. Optional browser checks run with `python wp-content/plugins/w2a-wf/tests/drive-browser.py` when Playwright and Chromium are installed; browser fixtures restore preferences and remove their temporary authenticated session.

## Changes in r4.0.0

The initial DOCX H1 is now always removed; later H1s become H2. Rank Math is listed in Plugins Status alongside Yoast; Yoast receives its SEO fields and keyphrase, while Rank Math receives only its keyphrase when active. Existing posts and manual Yoast keyphrases are preserved.

This release consolidates the Wolf Forge rebrand and refactor, persistent tabs/settings, scheduling/parser fixes, import-history and duplicate-post tools, SEO, native internal linking, contextual Media Library images, live GBP photos, public Drive imports, portable private Drive configuration, cancellation, and the requested first-launch defaults. AI recognition and batch AI metadata editing are not implemented. See [CHANGELOG.md](CHANGELOG.md) for release details and upgrade notes.
