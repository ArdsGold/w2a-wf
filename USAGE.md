# Use Wolf Forge Article Generator

Release: **r4.0.0**

The four plugin tabs switch in place without reloading WordPress. Selected files, image preview text, and import results stay available while switching tabs; an active import continues. Browser Back/Forward selects the matching tab. Files and preview text are still lost if you actually refresh or leave the page. Without JavaScript, tab links open normally. Duplicate pagination and Trash actions continue using their normal WordPress page requests.

## Generate articles

1. Open **Wolf Forge Articles** as an administrator.
2. Click or keyboard-activate the upload area, or drag in DOCX files. Remove unwanted files before starting. Up to 1,000 files can be selected.
3. Choose formatting cleanup, author, and category. Configure optional images in **Image Media Library** before importing. Preferences save automatically for the next visit.
4. Choose immediate publication or a date-based schedule.
5. Click **Submit** and keep the page open. Controls are disabled while the selected documents are processed in order.
6. Review per-file results. An **orange left edge** marks an article with fewer images or internal links than requested; the article is still generated successfully. Complete results use green edges and failed imports use red. Disabled features and zero requested counts do not trigger orange. Existing articles are explicitly marked as reused. Use **Edit Post** to inspect each article.

Files must be `.docx`; `.doc`, password-protected files, and malformed archives are unsupported. Embedded Word pictures are not imported.

## Formatting and titles

- Use Word's heading styles and real list formatting for predictable results.
- Descriptive filenames become post titles after leading list numbering is removed: `1. Article Title.docx` becomes **Article Title**. Prefixes such as `001.`, `2)`, `(3)`, and `[4]` are also recognized.
- Meaningful numbers stay in titles: `2026 Marketing Trends`, `10 Tips`, and `3.14 Explained` are preserved. Numeric or empty filenames (including those left empty by removing numbering) fall back to the first supported heading.
- Cleaned titles also supply the generated WordPress slug and SEO values. The source filename and history entry retain the original name. Existing completed imports are reused without renaming their posts.
- The initial DOCX H1 is always removed from the article body, including when it differs from the filename. Later H1s become H2, so the body has no H1. Your theme should display the post title once as its H1; review custom layouts separately.
- Leave **Convert numbered lists** unchecked to preserve ordered lists. Check it to turn ordered lists into bullets.
- **Remove bold except headings** removes body bold while retaining heading bold.
- External web, email, and telephone links are preserved when their URLs use supported schemes.
- Review complex tables, custom styles, numbering restarts, and unusual Word formatting after import.

## Google Business Profile image source

1. In **Image Media Library**, select **Google Business Profile** or **Both** under **Image source**.
2. Paste the public business link. Supported links include `maps.app.goo.gl`/`g.page` redirects to supported Google Maps pages, named `/maps/place/Business...` links, and links with a `query_place_id` or `q=place_id:...` parameter. Name-only lookups may be ambiguous; use a place-ID link when asked. Non-Google URLs, credentials in URLs, custom ports, and non-HTTPS links are rejected.
3. Enter your Places API (New) key and choose **Save API Key**, then **Check Profile Photos**. Verify the displayed business is correct. The key is never read back; a replacement can be entered later.
4. Enable automatic images and set **Maximum Images per Article**. Wait for **Options saved.**, then preview or submit articles.

GBP uses all photos returned by Google, with no owner/customer classification or keyword matching. The API returns up to ten photos, not the business's complete photo archive. GBP alt text names the source, not guessed picture contents. Media Library matching still requires descriptive contextual titles and alt text. Both shares the existing maximum and safe block spacing; images stay before Conclusion. If Google fails, articles still import; Both can use relevant library images.

Images remain Google-hosted. Stored shortcodes contain a place ID and numbered slot, while current photo URLs and author attribution are fetched during rendering. Photos can change when Google's photo order changes. No Google photos or expiring photo references are copied into the Media Library or stored in article HTML. The author credit, source link, and official Google Maps logo appear beside the photo.

The browser requests photo files from Google. The WordPress server sends profile/photo API requests, which can incur billing. Exclude articles containing these shortcodes from page/CDN caches so stale URLs do not persist; normal WordPress cache plugins receive the `DONOTCACHEPAGE` signal when the shortcode runs. If the key is removed, invalid, or restricted incorrectly, live images can disappear while article text remains. The saved source affects new imports; existing article shortcodes continue to use their original place ID. Changing source/profile changes import identity, while the default Media Library selection preserves older fingerprints.

**Remove API Key** disconnects live Google photos. **Reset Options** restores the Media Library source but retains the separate credential. See [installation/setup](INSTALLATION.md#google-business-profile-photo-setup) for billing, key restrictions, and website terms/privacy requirements.

## Cancel an import

**Cancel** is beside **Submit** and is enabled while a batch is running. It stops the current browser upload/request and prevents further documents from being sent, for both computer uploads and Google Drive imports. Cancelling during option saving or preflight prevents document requests from starting. Completed articles and selected files remain available.

The results identify any interrupted document: its server outcome is unknown, rather than counted as failed. Cancelling cannot roll back an article already created or guarantee that PHP processing stops. Check **Settings & Logs > Import History** after the current server request has had time to finish. Retry with the same selection, settings, and dates so the existing retry safeguards can reuse completed imports. A new Submit starts a fresh batch; Cancel is disabled when idle.

## Import from a public Google Drive folder

1. Set your folder to **Anyone with the link / Viewer**, permitting document downloads.
2. In Generate Articles choose **Public Google Drive folder**, paste the folder URL, and click **Load Documents**. The last successfully loaded folder is remembered.
3. Use **Load More** when available, then select documents individually or use **Select all loaded documents**. Only checked files count toward the schedule. Files are listed in natural filename order. This version supports DOCX files and native Google Docs directly in the folder, but not nested folders. Google Docs are marked in the list, exported to Word automatically, and assigned a .docx filename for normal title cleanup and generation. Their final export size is checked on download; Google limits export output to 10 MB. Up to 1,000 documents can be loaded in a batch. Use smaller folders to process more.
4. Set generation options, check the live date preview, and click **Submit**. Downloads and imports run sequentially. The status and result rows explain failures.

The default can be bundled in the plugin root `config.php` once before creating your private ZIP; it then travels with that ZIP to other servers. A `WTAI_GOOGLE_DRIVE_API_KEY` constant in wp-config.php takes precedence over the bundled key. A Settings & Logs override has highest priority. Keep the configured ZIP private; servers using the key share project quotas. To use another project, open **Settings & Logs > Google Drive API Key**, enter a key, and click **Save Override**. **Use Server Default** removes that override; if no default exists, Drive becomes unconfigured. Key inputs never reveal saved credentials. The override survives Reset Options and is separate from the GBP photo key.

Google may enforce request or file-download limits. Generate Articles displays the safe error and pauses on quota errors, retaining the loaded selection. Open Settings & Logs using the inline link or tab without losing that selection. Wait and retry, or save a suitable key from another Cloud project, then retry the **same selection and schedule**. Completed articles are reused through existing content/settings/date fingerprints. Changing the selection, settings, dates, or source content can create distinct imports. A different key in the same project shares its project quota; a file-specific download limit may persist. No exact remaining project quota can be inferred from these requests; use **View Cloud quotas** for authoritative project usage.

Folder/file access errors are distinct from quota errors. Verify public permissions, allowed downloads, Drive API enablement, and key restrictions. Invalid DOCX documents do not block other files; network/quota problems pause the batch. Local computer uploads continue to work without a Google key.

## Automatic internal linking

1. Open **Generate Articles > Content Processing**.
2. Enter a positive **Maximum internal links per article** and wait for **Options saved.** The first-launch default is **5**; **0** disables this feature.
3. Submit documents. Results show how many internal links were inserted against your maximum, with a message when fewer matches are available.

Wolf Forge matches existing article text to a complete destination post title, a contiguous two-to-five-word phrase within that title, or a complete saved Yoast/Rank Math keyphrase. Rank Math's comma-separated keywords are considered separately. Matches ignore case and allow whitespace differences; single-word anchors are skipped. Title fragments must contain at least two content words and cannot start/end with common English connective/question words or generic words such as best, top, guide, and tips. Fragments do not cross punctuation. Longer phrases take priority, with exact title/keyphrase matches preferred over equally long fragments. For example, **drain cleaning** can link to **Emergency Drain Cleaning in Cleveland**. A fragment shared by multiple destination posts is skipped; a complete title or explicit SEO keyphrase remains eligible. Explicit matches shared by several targets use the lowest post ID as a deterministic tie-breaker. This is literal matching, without AI, synonym inference, or stemming; the English word filter is a conservative heuristic, not semantic understanding.

Only published, password-free posts on this site qualify. Pages, draft/private/future posts, self-links, external targets, and destinations already linked in the article are excluded. One new link is permitted per destination and per paragraph, list item, or table cell. The importer preserves anchor text, headings, existing links, figures, captions, code, and whole-phrase formatting. Phrases split across separate formatting elements are skipped. The count is a maximum; unrelated links are never added to meet it.

Wolf Forge performs insertion itself using standard WordPress anchors. No external linking plugin, license, API, or sitewide scan is required. This feature does not modify third-party plugin settings.

The saved maximum persists across tabs, reloads, and sessions, and resetting options restores it to five. This affects new imports; existing completed imports are reused without modifying their links. Changing a positive maximum can create a distinct import. In a batch, posts published earlier can become targets for later documents, while scheduled posts remain excluded until published.

## Phone numbers

**Automatically link phone numbers** is a saved checkbox under **Generate Articles > Content Processing**, enabled by default. Turn it off to keep new numbers as ordinary text. Wait for **Options saved.** before leaving; the choice persists across tab switches, reloads, and login sessions.

International numbers start with **`+` or `00`** and contain **7-15 ASCII digits after that prefix**, including the country code. The first country-code digit must be 1-9. Spaces are allowed between the prefix and country code. Formatting may use spaces (including non-breaking/thin spaces), parentheses, square brackets, dots, and dashes, including Unicode dashes.

| Displayed number | Generated destination |
|---|---|
| `(216) 399-7569` | `tel:2163997569` |
| `216-399-7569` | `tel:2163997569` |
| `216.399.7569` | `tel:2163997569` |
| `216 399 7569` | `tel:2163997569` |
| `1 (216) 399-7569` | `tel:12163997569` |
| `+1 (202) 555-0123` | `tel:+12025550123` |
| `+44 20 7946 0958` | `tel:+442079460958` |
| `+ 44 20 7946 0958` | `tel:+442079460958` |
| `0063 917 123 4567` | `tel:00639171234567` |
| `00 44 20 7946 0958` | `tel:00442079460958` |
| `+63.917.123.4567` | `tel:+639171234567` |
| `+81 [90] 1234-5678` | `tel:+819012345678` |

Formatted US/Canadian numbers are also supported, with ten digits and an optional formatted leading `1`. Both the area code and exchange must start with 2-9. Use parentheses around the area code or spaces, dots, or dashes between groups; bare ten-digit strings are skipped to avoid numeric IDs.

The displayed text stays unchanged. Only the destination loses formatting; its original `+` or `00` prefix is retained. The plugin does not convert `00` to `+` or add a country code.

The generator skips:

- Other local formats such as `0917 123 4567`, bare strings such as `2163997569`, and US/Canadian candidates with invalid area/exchange prefixes.
- Candidates with fewer than 7 or more than 15 digits after the prefix.
- Existing hyperlinks, including existing `tel:` links; they are not replaced or nested.
- Numbers in HTML attributes, code, preformatted text, scripts, and styles.
- Numbers split across separate text fragments, such as `<strong>+44</strong> 20 7946 0958`. Formatting around the entire number is supported.

This is formatting-based detection for both mobile and landline numbers. It does not verify assigned country codes, whether a number exists, or whether it belongs to a mobile network. Extensions are not included in the destination. PHP DOM is required; without it, imports continue without new phone links. Existing posts are not backfilled. Changing this checkbox changes import identity, so importing the same file with a different setting can create a distinct article.

## Media Library images

On **Generate Articles** or **Image Media Library**, enable **Automatically insert matching images into new articles** and set **Maximum Images per Article**. Both tabs share the same saved checkbox and count, update immediately when either is edited, and use the image source selected in Image Media Library (Media Library, GBP, or Both). It defaults to three and accepts any non-negative integer, with no ten-image cap. Zero inserts none. Newly inserted images require at least 100 article words between positions, excluding image captions. Matching and the before-Conclusion boundary remain required; fewer images are inserted if there is insufficient room. This applies to Media Library, GBP, and Both for new imports and previews; existing posts are not rearranged. Wait for **Options saved.** before leaving. If saving fails, change a setting to retry; Submit and image preview also retry pending saves.

Automatic Media Library matching excludes the configured WordPress site logo/site icon and files with logo, icon, favicon, logotype, or logomark labels (including plurals) in their title, alt text, caption, description, or filename. Filename separators, numbered suffixes, and camel-case names such as CompanyLogo are recognized. Words such as silicon and iconic are not excluded. This is a metadata filter, not visual recognition: unlabeled graphics can still qualify. Google Business Profile photos do not provide equivalent classification metadata, so this filter applies to the local-library part of Media Library/Both. Fewer eligible photos produce fewer insertions rather than logo/icon substitutes.

Upload images through WordPress Media Library and fill in their titles, alt text, captions, and descriptions. The metadata browser searches all four fields, shows their values, and links to **Edit Media**. Use its pagination to browse additional images. **Reload Library** fetches the current listing; **Refresh Keyword Index** rebuilds the cached vocabulary. Normal attachment and metadata edits automatically invalidate the index; it also expires after one hour.

Matching uses exact, normalized Unicode words and weighted TF-IDF/cosine scoring without AI or a fixed stopword list. Image titles and alt text have weight 3, captions 2, descriptions 1. The full article contributes keywords, with extra weight for its title and headings. Common library words have less statistical influence. Both title and alt text must be filled in and independently match the article and the nearby paragraph or active section headings. A caption/description match alone is insufficient. Neither field is rewritten to force a match. Synonyms are not inferred, so use descriptive metadata with the actual words found in your documents. Generic shared words can still produce weak matches; inspect the preview.

Images are unique within an article and target equal portions of the word count **before the first Conclusion heading** (the full article when absent): three images at approximately **33%, 67%, and 100%**, four at **25%, 50%, 75%, and 100%**. No new images are inserted in Conclusion or any following section. The requested count is redistributed over the preceding content rather than dropping images that would have appeared later. Heading matching ignores capitalization, inline formatting, and trailing punctuation. Context takes priority over spacing: images stay in their relevant sections, even if that moves them away from a percentage target. Placement snaps to complete paragraphs or other safe blocks without splitting sentences, lists, or tables. Images are inserted inside standard post content; the theme continues to control the surrounding post layout. Fewer images are inserted if relevant, renderable images or safe insertion points are insufficient; percentages are recalculated using the number actually selected.

Paste a title and article text/HTML into **Preview Image Matching**, then click **Preview Images**. Separate plain-text paragraphs with blank lines. The preview displays matched terms, target/actual percentages, and the resulting article without creating or updating posts. Preview text is not saved. Native WordPress image markup uses available responsive sizes, stored alt text, and captions. These are inline images; no featured image is set and attachment ownership is unchanged.

The Generate Articles tab summarizes the saved image options. A batch captures its effective settings before upload; image settings changed elsewhere do not change that batch's count or enablement. Media metadata edits can still change matches for later documents. Missing DOM support or a matching failure leaves the article importable without new images. Existing posts are not modified automatically.

## Publishing schedules

All scheduled times are **9:00 AM in the WordPress timezone displayed on the form**.

Month mode uses a named **Month** dropdown and separate four-digit **Year** field, with **This month** and **Next month** shortcuts. Year edits commit when you leave the field. Start/end dates keep native calendar controls and show a readable weekday/month/date underneath. **Today** and **Tomorrow** use the WordPress date; **7-day range** and **30-day range** set an inclusive range from your chosen start (or today if empty). The end date must be on or after the start, with an inline error for reversed ranges.

The live **Schedule preview** uses the same server calculation as imports. Before selecting files, it shows an example for three articles. After selection, it shows dates with filenames in upload order; larger batches show the first five and last article. It explains repeated dates and warns when selected times have already passed and will publish immediately. The preview is read-only and creates no posts. It updates after date/mode/interval or file changes, and stale responses cannot replace a newer selection. Submit still performs final validation before uploading.

| Mode | Behavior |
|---|---|
| Within Specific Month | Pick a month/year and spread articles evenly from its first through last day; if documents exceed its days, loop back to day 1. |
| Publish Immediately | Publish each document as its request completes. |
| Starting on a Date | Schedule daily, weekly, or monthly from the selected start date; no end date needed. |
| Between Two Dates | Repeat the interval dates inside the inclusive range when documents outnumber the available dates. |

**Within Specific Month** appears first in the dropdown. Choose the month and year; start/end dates and the interval selector are hidden and ignored for this mode. Three documents in November use November 1, 16, and 30; four use November 1, 11, 20, and 30. A single document uses day 1. If you select 31 documents for November, documents 1-30 use days 1-30 and document 31 returns to day 1. February uses 28 or 29 days as appropriate. Overflow documents share the existing 9:00 AM publish times.

A daily range of November 1-3 schedules documents 1, 2, and 3 at 9:00 AM on November 1, 2, and 3. Document 4 returns to November 1, document 5 to November 2, and so on. Multiple posts can share the same date and time; no extra hourly slots are created. A one-day range puts all documents on that date. Weekly/monthly modes repeat only their interval dates within the range, even when the end date is between intervals.

This applies to new imports; existing scheduled posts are not rescheduled. Identical files with identical effective settings and assigned dates still reuse their existing article. Distinct documents sharing a publish date remain separate posts.

Monthly schedules retain the original day when possible: January 31, February 28/29, March 31. Daily/weekly dates keep local clock time across daylight-saving transitions. Past dates publish with their assigned past timestamp. Future publication depends on WordPress cron execution.

## Interrupted imports and retries

Leave the original selection, order, and settings intact and click **Submit** again. Already completed documents are reused; incomplete drafts are resumed. A network failure stops remaining uploads rather than silently proceeding with an uncertain result.

If the page was closed, reselect the same original files in the same order and use the same settings. This preserves each file's scheduled date. Changing file contents, options, or assigned dates starts a different import and may create another article. A renamed byte-identical file alone is still considered the same import.

If a result identifies a retained draft, do not create a manual copy before retrying. A duplicate in Trash must be restored or permanently deleted before reimporting. Older imports without recovery markers are not automatically fingerprinted.

## Duplicate Blogs

Open **Wolf Forge Articles > Duplicate Blogs** to review additional copies of matching content. This is a tab within the existing plugin screen, not a sidebar submenu.

The list compares the exact stored content of non-empty standard posts across the site, including existing copies. Titles and slugs can differ. Within each matching group, the lowest post ID is treated as the original and is not listed as a duplicate. Pages, revisions, auto-drafts, empty content, and items already in Trash are excluded. A page matching a post does not count toward duplicate detection. Elementor layout JSON and other metadata are not compared; visually similar pages or numbered slugs alone do not qualify.

Each row shows its original, item type/status, and actions:

- **View:** opens the published URL or an editor preview for an unpublished item.
- **Edit:** opens the standard WordPress editor.
- **Trash:** asks for confirmation and moves that copy to WordPress Trash. The original remains intact. Restore it through the standard Posts Trash screen.

Results are paginated at 20 copies per page. Items disappear from the list when trashed or edited so their content is no longer duplicated. Trash requests recheck permissions and current duplicate status, so stale listings cannot remove an item that no longer matches. If WordPress Trash is disabled, the action is refused rather than permanently deleting the item.

This tab manages existing copies. It does not create duplicate imports or change the importer's existing reuse/recovery behavior.

## Plugins status

The compact **Plugins Status** card on **Generate Articles** lists **Yoast SEO** and **Rank Math SEO**, each as **Active**, **Inactive**, or **Not installed**. Network activation is included. The same card lists **Google Drive API** separately: **Not configured**, **Configured (not checked)**, **Connected**, **Rate or download limit reached**, **Access or API key error**, or **Connection error**. This reflects the last Google response, cached for up to one hour, and updates after Drive requests and key changes. A saved key is not labeled Connected until Google responds successfully.

This checks plugin availability only. It does not install or activate plugins or calculate SEO scores. Active Yoast receives an SEO title, meta description, and generated focus keyphrase, derived from the post title with support from headings/body text. Active Rank Math receives only the generated focus keyphrase. The importer does not write Rank Math SEO title, meta description, or slug overrides; existing values are untouched. Rank Math can still display title/description from its own default templates and read the shared WordPress post slug. They work independently: either can be used alone, and neither suppresses the other integration. Existing non-empty focus keyphrases are preserved. The article slug is the standard WordPress post slug derived from its title, with WordPress handling uniqueness; it appears in the SEO editor without a separate plugin-specific slug field. Existing posts are not backfilled or rewritten. Review the selected SEO plugin in the post editor. Article generation works without these plugins.

## Settings and logs

Publishing and formatting preferences remain on **Generate Articles**. Reset controls, error logs/statistics, import history, and server diagnostics now have their own **Settings & Logs** tab. Image source, metadata, and previews are on **Image Media Library**; its insertion checkbox and maximum image count are also available on **Generate Articles**, synchronized across both tabs. There are no sidebar submenu items. Autosave submits only changed fields, so saving image preferences does not write unchanged publishing preferences. Reset updates both forms immediately, including their hidden tabs.

Formatting, image options, author, category, publish mode, dates, and interval save automatically to the site's WordPress options. These are shared site preferences for administrators. Wait for **Options saved.** before leaving. If autosave fails, change a setting to retry. Submit and image preview also retry pending saves before continuing. File selections are excluded and must be chosen again after a reload. Importing also waits for pending saves.

Changing to immediate publication hides the date controls but retains both dates and the chosen interval for later. Specific-date mode keeps the inactive range end without using it. Within Specific Month requires a valid month/year and preserves it when switching modes. The other scheduled modes require a start date; range mode also requires a valid end date. Incomplete preferences may be saved while you are configuring them, but generation requires a complete, valid schedule.

Autosaves run in order, so an older response cannot replace a newer choice. Reset restores defaults in the database and reports success on **Settings & Logs**. Switch to the publishing or image tab to see its defaults immediately. Clearing logs/history and resetting options ask for confirmation. Clearing records does not delete articles or disable duplicate protection. The former Settings URL redirects to **Settings & Logs**.

Import history shows the ten most recent records. A `prepared` entry means a draft was saved before a publication step completed; a `completed` entry records a finished import. The post's current status is authoritative if you edit it later.

**Copy All Imports** is available beside **Submit** and **Cancel** on Generate Articles and above Import History on Settings & Logs. Both buttons share the same completed-history list and show clipboard feedback inline on both tabs. The buttons are disabled when there are no eligible imports or a batch is running. Copying includes only the completed imports in those displayed rows whose posts are available. The numbered list uses each current post title as a clickable link. Pasting into a rich text editor retains embedded URLs; plain text destinations receive numbered titles with URLs. Drafts and scheduled posts use preview URLs. Missing and trashed posts are excluded. Clipboard failures appear inline; no popup is opened.

Each available import has **View Post** (public post or unpublished preview), **Edit Post**, and, when allowed, **Move to Trash**. These actions respect WordPress permissions. Move to Trash updates the history without reloading the page or showing a popup, keeps the history record, and never permanently deletes the post. Restore posts through **Posts > Trash**. If WordPress Trash is disabled, this action is unavailable. Clearing import history still removes only history records, not posts.

## Troubleshooting

| Symptom | What to check |
|---|---|
| Upload rejected | DOCX extension, actual archive contents, server upload limits, and decompressed XML limits. |
| No supported body text | Image-only, empty, or unsupported Word content; add ordinary body paragraphs. |
| Schedule rejected | Select a valid month/year for month mode, or a valid start date and (for range mode) an end date on or after it. Extra documents repeat month/range dates. |
| Scheduled post appears late | WordPress timezone and WP-Cron/system cron execution. |
| Document already importing | Another request holds its database lock; retry after that request completes. If persistent, verify MySQL/MariaDB named-lock support. |
| Draft retained after failure | Resolve the reported error, then retry the same file/settings. The same draft should be resumed. |
| No Google photos | API key configured, Places API (New) enabled, billing/key restrictions, correct unambiguous business link, available Google photos, and image maximum/enablement. Check the inline status. |
| No images inserted | Matching enabled, image maximum above zero, and both descriptive titles and alt text matching the article and local section; PHP DOM availability; the preview report. |
| Wrong title or formatting | Filename/title rules, native Word heading/list styles, and the documented parser limitations. |
| Broken UI after upgrade | Reload the page to load r4.0.0 assets; verify the updated plugin folder is the active one. |

The browser queue cannot continue after the page closes. Server memory/execution limits still apply to an individual document. See [README](README.md) for architecture, requirements, limitations, and test commands.
