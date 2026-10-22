# Changelog

## r4.0.1 - 2026-10-22

- Add an administrator-only GitHub version check against the main-branch plugin header. A notice appears only for a valid version mismatch; checks are asynchronous, cached, bounded, and silent on failure, with no code downloads or automatic updates.

- Show compact thumbnails on each Import Results card for inserted Media Library, Pixabay, and live GBP images, with full-image links in new tabs, Google attribution, missing-media labels, and completed-import retry support.

- Keep the configured root `config.php` tracked in Git and included unchanged in installation ZIPs for portable installations. Remove its ignore rule and update setup/release documentation.
- Strengthen Pixabay upload permission checks and validation coverage; verify private credential precedence, file reuse, attribution, and recoverable provider failures.
- Remove the obsolete admin page class after consolidating the four-tab admin screen.
- Validate 274 regressions, 112 WordPress integration checks, 62 Pixabay checks, 45 Drive checks, 20 isolated configuration checks, 37 version-checker checks, JavaScript controls, and browser flows.

## r4.0.0 - 2026-10-22

- Add optional automatic Pixabay fallback during Submit for local-image shortages, with private credentials/defaults, 24-hour search caching, provider cooldowns, contextual placement checks, validated local downloads, deduplication, and source credits. Preview remains read-only; provider failures preserve article publishing.

Wolf Forge Article Generator replaces the Word to Article Importer branding and consolidates the importer refactor, administration, scheduling, SEO, and image features.

- Require at least 100 article words between newly inserted images across Media Library, GBP, and Both. Reserve room for remaining images and reduce the count when relevant positions cannot satisfy spacing before Conclusion. Existing posts are not rearranged.

- Add public Google Drive folder DOCX listing and sequential import, pagination/selection, quota-pause notices, private user API key overrides over a server default, bounded temporary downloads, and authenticated/nonce-protected endpoints.
- Set first-launch processing checkboxes to enabled, internal links to five, and images to three. Preserve saved preferences on activation and correctly persist false values for previously absent options.

- Add Cancel beside Submit to abort the active browser request and stop the remaining local/Drive queue. Retain completed results and selections, distinguish uncertain server outcomes from failures, and allow safe retries.

- Exclude labeled logos/icons/favicons/logotypes/logomarks and configured WordPress site branding from automatic Media Library matching. Recognize filename numbers/camel case without rejecting words such as silicon/iconic, refresh the cache after branding changes, and retain fewer-image fallback behavior.

### Administration and history

- Highlight import-result article cards with an orange left edge when image or internal-link counts fall short of their positive requested count. Preserve successful article status, green complete results, and red failures.

- Add Copy All Imports beside Submit and Cancel, reusing the Settings & Logs history list and rich/plain clipboard behavior. Synchronize button state and inline feedback across both tabs.

- Show Google Drive API connection state in Plugins Status, distinguishing a configured key from a verified connection, quota failures, access errors, and network errors. Cache the last result for one hour and update the card after Drive operations.
- Detect native Google Docs alongside Word files and export them to DOCX before importing; label their source in the list and explain export-size failures. Verify a live four-document public folder with exports/parsing and no generated posts.

- Support a portable Drive default in the private plugin-root config.php, with site override > wp-config constant > bundled file precedence. Ignore the private file in Git, include it in installation ZIPs, and preserve the owner-configured file in the private installation ZIP. Keep public/source distributions unconfigured.

- Keep one Wolf Forge Articles sidebar entry with Generate Articles, Duplicate Blogs, Image Media Library, and Settings & Logs tabs. Switch tabs without reloading, preserve selected files/previews/results and running imports, and support direct links and browser history.
- Autosave changed preferences in order, synchronize retained forms, wait for pending saves before submission/preview, and retain inactive schedule fields. Remove Save Options buttons; use Submit without an icon for generation.
- Mirror the automatic image insertion checkbox and maximum image count on Generate Articles and Image Media Library; synchronize edits immediately and save each preference once for the selected image source.
- Move reset controls, error statistics, import history, and diagnostics into Settings & Logs. Preserve the former Settings URL through a redirect.
- Show compact Yoast SEO and Rank Math SEO statuses. Add a replaceable root icon.png displayed at 20x20 without changing its source file.
- Copy completed, available imports among the ten displayed history rows as a numbered list of linked post titles, with plain-text titles/URLs as an alternative. Report copy results inline without popups.
- Give history rows separate public View/Preview, Edit, and Move to Trash actions. Retain history after Trash; check permissions, nonces, imported-post membership, and disabled-Trash settings.
- List duplicate standard posts only, excluding pages. Identify the lowest post ID as the original and offer View, Edit, and protected reversible Trash actions.

### DOCX importing and publishing

- Improve date selection with a named-month dropdown, separate year field, This month/Next month and Today/Tomorrow shortcuts, inclusive 7-day/30-day ranges, readable date labels, and inline range errors.
- Add a read-only server schedule preview with selected filenames, an example before file selection, overflow/past-date notices, and protection against stale preview responses. Preserve saved dates and existing schedule calculations.

- Separate settings, scheduling, parsing, processing, SEO, importing, media, Google photos, and internal linking into focused classes. Remove unused helpers, styles, state, the redundant admin wrapper, and the empty deactivation hook.
- Upload/process up to 1,000 DOCX documents sequentially with schedule preflight, progress, and per-document results.
- Fix Word namespace/style parsing, mixed lists, supported hyperlinks, line breaks, and basic tables. Bound archive/XML sizes, reject unsafe XML declarations, and retain the WordPress PclZip fallback.
- Strip leading filename list markers such as 1., 2), (3), and [4] when deriving titles/slugs/SEO values. Preserve meaningful numbers and original source/history filenames.
- Remove the first DOCX H1 and demote subsequent H1s to H2 so the theme supplies the page-title H1.
- Add saved default-on phone linking for international +/00 numbers and formatted US/Canadian numbers such as (216) 399-7569. Preserve visible text and prefixes; normalize tel: destinations. Skip existing links, unsupported local formats, bare ten-digit IDs, and protected code/markup.
- Make Within Specific Month the first schedule option, with persisted month/year, even spacing, leap-year handling, and overflow looping. Cycle date-range overflow back to the first interval date. Keep daily/weekly/monthly schedules at 9:00 AM in the WordPress timezone, clamp shorter months, and preserve prepared draft dates.
- Use file/settings/date fingerprints and database locks to reuse completed imports and resume retained drafts. Preserve older fingerprints when new internal-linking and image-source options remain disabled/default.

### SEO and internal links

- Give active Yoast an SEO title, meta description, and generated focus keyphrase; preserve existing non-empty keyphrases. Active Rank Math receives only its focus keyphrase, with no SEO title/description/slug overrides. Keep the normal WordPress post slug.
- Add a saved maximum for automatic internal links, first-launch default 5. Match complete titles, meaningful contiguous two-to-five-word title fragments, or saved Yoast/Rank Math keyphrases to published, password-free blog posts on this site.
- Prefer more specific anchors, preserve visible text and protected HTML, avoid self-links and already-linked destinations, and add at most one new link per paragraph/list item/table cell. Allow fewer relevant links rather than padding output.
- Keep automatic internal linking fully native; remove the external linking-plugin status check and reporting-specific integration tests. Skip ambiguous title fragments, preserve exact SEO keyphrase matching, and prefer longer anchors without rewriting article text.

### Images

- Add Media Library, Google Business Profile, and Both source choices. Retain the image maximum of three by default, allow any nonnegative count, and share one count/placement plan across sources.
- Keep all new images before the first Conclusion heading and calculate percentages over the preceding body. Preserve requested counts when sufficient images and safe positions exist; prioritize context and complete block boundaries.
- Match Media Library titles, alt text, captions, and descriptions using deterministic Unicode tokens and weighted TF-IDF/cosine scoring without AI or a fixed stopword list. Require both title and alt text to match globally and locally.
- Preserve native local image markup, responsive sizes, alt text, captions, and post layout. Add metadata search/edit links, pagination, index refresh, and non-destructive previews; invalidate the local metadata cache after relevant edits.
- Accept supported public Google Maps/profile links, store a separately configured server-side Places API key, and provide an inline profile/photo check.
- Use all photos returned by Google, up to its ten-photo limit, without owner/customer filtering or keyword matching. Store place-ID/ordinal shortcodes and retrieve current photo URLs during rendering with official Google Maps and author attribution.
- Do not persist Google photo files, photo resource names, expiring image URLs, attribution responses, or API responses. Reuse metadata/rendered fragments only within the current PHP request and mark rendered pages non-cacheable.
- Restrict profile/redirect hosts and returned media URLs. Keep credentials out of import settings/history and rendered output. Preserve article text and qualifying local images if Google fails.

### Upgrade and validation

- Keep the w2a-wf folder, word-to-article-importer.php entry filename, WTAI_* names, and wtai_* stored options/tables for upgrade compatibility. Existing posts/settings remain available; the next administrator request checks the schema.
- Refresh an open plugin screen after replacing files. Configure Google credentials, billing, notices, and cache exclusions only if enabling the optional GBP source.
- Pass 274 PHP regression checks with PclZip and ZipArchive, PHP 8.2 compatibility checks, 105 WordPress integration checks, 45 mocked Google Drive integration checks, 10 isolated private-configuration checks, JavaScript queue/settings/history/clipboard/Google-control/cancellation checks, and documented browser checks.
- Google HTTP responses are mocked in validation; no real Google lookup or billable request has been made. AI recognition and batch AI metadata editing are not implemented in this release.
