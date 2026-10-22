# Install or upgrade Wolf Forge Article Generator

Release: **r4.0.1** - package prepared **2026-10-22 (Asia/Manila)**. See [release notes](RELEASE_NOTES.md).

## Existing w2a-wf installation

For the packaged release, upload `wolf-forge-article-generator-r4.0.1.zip` through **Plugins > Add New > Upload Plugin** and choose the WordPress replacement option when prompted. Back up the plugin directory and database first. The ZIP keeps the `w2a-wf` folder so WordPress updates the existing installation.

Update the files in the existing `wp-content/plugins/w2a-wf` directory. Keep the directory and `word-to-article-importer.php` entry filename so WordPress preserves activation. This project has already been updated in place.

The next administrator request checks the schema version and creates/updates the history table as needed; reactivation is not required. Existing `wtai_*` settings, tables, logs, and articles remain available. The menu now reads **Wolf Forge Articles**. The plugin has a single sidebar entry with no submenus. The main `admin.php?page=word-to-article-importer` URL remains; the former `admin.php?page=word-to-article-importer-settings` URL redirects to the **Settings & Logs** tab on that same screen.

Image integration now has its own internal tab. Existing image enablement and count settings are retained. Media Library matching uses title, alt text, caption, and description together. The new GBP/Both sources are optional and require Google setup. The former field selector is no longer used. Both title and alt text must now match the article and local section; fill in missing or unrelated metadata to make an image eligible. Captions/descriptions alone do not qualify an image.

After upgrading, reload the admin screen once so the updated controls and server endpoints agree. Internal tab switches then happen without a page reload and retain the current file selection and preview text. This release uploads one document per request; old multi-file AJAX submissions receive an instruction to reload.

Within Specific Month is now the first publishing choice. Existing saved modes remain selected after upgrading. The month/year preference saves independently of the existing date and interval preferences. Reload open screens to load the named-month dropdown, separate year field, date/range shortcuts, readable labels, inline validation, and live schedule preview. Existing saved date values are retained.

New imports always remove the initial DOCX H1 and demote later H1s to H2; the theme supplies the post-title H1. Active Yoast receives SEO titles, meta descriptions, and generated focus keyphrases. Active Rank Math receives only the focus keyphrase; Rank Math SEO title, description, and slug overrides are not written. Rank Math support is additive; it does not suppress Yoast metadata. Activate your preferred SEO plugin through WordPress Plugins; the importer does not activate SEO plugins. The post slug remains a standard WordPress field shared by their editors. Existing posts and existing Yoast focus keyphrases are not changed.

New imports also enable phone linking by default. The saved checkbox on Generate Articles supports `+`/`00` prefixes followed by 7-15 digits including the country code, also recognizes formatted US/Canadian numbers such as `(216) 399-7569` (without adding a country code), preserves the displayed text and prefix, and leaves existing links unchanged. Disable it before importing to retain plain phone text. Existing posts are not backfilled. See [phone-number examples and exclusions](USAGE.md#phone-numbers).

New imports strip leading filename list markers such as `1.` before creating the title, slug, and supported SEO values. Existing completed imports are reused without changing their titles; source files are not renamed.

Preferences now use autosave without a Save Options button. Wait for **Options saved.** before leaving. A subsequent edit retries failed saves; Submit and image preview also wait for settings to save. Cleanup removes obsolete helpers and styles without deleting stored posts, settings, history, or logs.

## New installation

1. Use the prepared `wolf-forge-article-generator-r4.0.1.zip`, or package the `w2a-wf` folder as a ZIP, omitting `.git`, tests, and local development artifacts.
2. In WordPress, open **Plugins > Add New > Upload Plugin** and select the ZIP, or copy the folder directly into `wp-content/plugins/`.
3. Activate **Wolf Forge Article Generator**.
4. Open **Wolf Forge Articles** and choose publishing/formatting preferences on **Generate Articles** and optional image insertion/count on either **Generate Articles** or **Image Media Library**. The checkbox and count stay synchronized; choose the image source in Image Media Library. Changes save automatically; wait for "Options saved." before leaving.
5. Test one simple DOCX, review the generated article, then import a larger selection.

Wolf Forge Elementor Page Generator can be installed alongside this plugin. Neither plugin requires the other.

## Server requirements

- WordPress 6.0+, PHP 7.4+, SimpleXML, and zlib.
- PHP DOM for automatic image placement, previews, phone links, and internal links; missing DOM leaves imports available without those automatic enhancements.
- PHP ZipArchive, or WordPress's bundled PclZip fallback.
- Standard WordPress MySQL/MariaDB access including named locks.
- Sufficient PHP memory and execution time for one document. Sequential uploads reduce batch timeouts but do not make a single oversized document unlimited.

The import screen shows upload limits, extensions, and the site's publishing timezone. The maximum selected batch is 1,000 documents. Each upload must fit the server's file/request limits; selected DOCX XML parts are capped at 8 MiB each and 16 MiB combined, with at most 4,096 archive entries.

Use WP-Cron or an existing system cron arrangement for scheduled publication. WordPress timezone settings determine dates and times; the operating system or browser timezone does not override them.

## Data retention and rollback

Deactivation preserves posts, settings, logs, and history. There is no automatic uninstall data deletion. Clearing logs/history from the Settings & Logs tab affects those records only.

Back up the plugin and database before deployment to another site. Older plugin code without recovery support does not understand the current recovery markers, so complete or resolve interrupted imports before reverting code. Duplicate detection does not retroactively identify posts imported by earlier releases.

## Admin menu icon

The plugin root contains a replaceable PNG named `icon.png`. The plugin displays it inside a 20x20-pixel admin-menu box on every WordPress admin screen, preserving its aspect ratio. You can supply a larger source image, such as 40x40 or 128x128, without manually resizing the file. The existing activation file, `word-to-article-importer.php`, sets `Plugin_Icon = "icon.png"` and uses its plugin URL as the WordPress admin menu icon. Replace the PNG to change the artwork; keep its filename or update that pointer. The original PNG is not modified. No alternate activation file is required.

## Developer checks

See [README validation commands](README.md#validation). Configure the PHP CLI to use the local site's database port and required extensions when running integration checks; the shell's PHP configuration can differ from the web server's.

Automatic Media Library matching excludes logo/icon/favicons identified by metadata/filenames and the configured WordPress site branding attachments. The keyword index rebuilds automatically after relevant metadata or site branding changes; the new cache version applies this rule immediately after upgrading. Unlabeled graphics cannot be identified visually by this filter.

Image placement stops before the first Conclusion heading. The selected image count is spaced across the preceding content, subject to relevant images, safe block boundaries, and a minimum of 100 article words between newly inserted images. Insufficient room results in fewer images; existing posts are not rearranged.

Settings & Logs now provides Copy All Imports for the completed imports displayed in its ten-row history. Rich text pasting preserves numbered linked post titles; plain text includes URLs. View Post opens the post or preview, Edit Post opens the editor, and Move to Trash is reversible through WordPress Posts > Trash. Copy and Trash report inline without popups. See [usage](USAGE.md) for availability and permission details.

Automatic internal linking is available under Generate Articles > Content Processing. Its saved maximum defaults to 5 on first launch; enter 0 to disable it. Only matching published blog posts qualify. Wolf Forge matches complete titles, meaningful two-to-five-word title phrases, and saved Yoast/Rank Math keyphrases. Ambiguous title fragments are skipped. No third-party linking plugin, license, or AI service is needed. See [internal linking usage](USAGE.md#automatic-internal-linking).

## Google Business Profile photo setup

The optional live GBP source uses the official Google Places API (New); no Google account sign-in, website scraping, or photo download is performed. Media Library remains the default and requires no Google setup.

1. Create/select a Google Cloud project, enable **Places API (New)**, and enable billing. Create an API key and restrict it to Places API (New) and your server's public IP where appropriate. Browser referrer restrictions are not suitable for these server-side requests. See [Google API-key setup](https://developers.google.com/maps/documentation/places/web-service/get-api-key).
2. Open **Wolf Forge Articles > Image Media Library**, select GBP or Both, paste the public Google Maps business link, and save the key with **Save API Key**. **Check Profile Photos** resolves the link and displays the business name and a live sample. Verify the business before importing. Ambiguous name searches and CID-only links may require a Maps link with `query_place_id`.
3. Enable image insertion and choose the existing maximum. Google returns at most ten photos. GBP uses all returned photos without owner/customer classification or keyword matching; Media Library matching remains contextual. Preview and import use the same before-Conclusion spacing.
4. Exclude articles containing `[wtai_gbp_photo ...]` from full-page/CDN caches. The shortcode marks pages with `DONOTCACHEPAGE`, but external caches need their own exclusions. Every uncached render fetches current Google data; configure Google billing limits and monitoring for your expected traffic. Failed requests omit affected images without breaking text.
5. Provide publicly accessible website terms and a privacy policy containing the Google Maps terms/privacy disclosures required by [Google's Places API policies](https://developers.google.com/maps/documentation/places/web-service/policies). Keep the generated Google Maps and author attribution visible. Photos are fetched live and can change with Google's returned order.

The credential is stored in the private, non-autoloaded `wtai_google_places_api_key` WordPress option; protect database access and backups. It is never included in import settings/history, front-end markup, or AJAX responses. Alternatively define `WTAI_GOOGLE_PLACES_API_KEY` in wp-config.php; this overrides the stored key and must be changed there. Places photo credentials are not bundled; the private Drive configuration described below is separate. Resetting ordinary settings retains the credential; Remove API Key deletes the stored key and stops existing live photos from loading.

Only the user-supplied profile URL and permitted place ID are persisted for lookup. Photo resources, URLs, business-name responses, and author metadata are reused only within the current PHP request. Posts store a place ID and photo ordinal in a shortcode, not Google photo files or expiring references. Official attribution artwork is bundled unchanged from [Google's attribution assets](https://developers.google.com/static/maps/documentation/images/Google_Maps_Attribution_Assets.zip).

Keep Wolf Forge active to render its Google photo shortcodes. Deactivating the plugin preserves stored content but leaves those shortcodes without their renderer.

## Public Google Drive folders

Enable **Google Drive API** in your Google Cloud project. To include a default key in your private installation package, edit **w2a-wf/config.php** once:

```php
<?php
if (!defined('ABSPATH')) exit;
return array(
    'google_drive_api_key' => 'YOUR_GOOGLE_DRIVE_API_KEY',
);
```

ZIP the **w2a-wf/** folder with this configured file inside, then use that same private ZIP on your other servers. No wp-config.php edit is required on those servers. The repository intentionally tracks `config.php`, and the installation ZIP includes the current configured file unchanged. Repository readers and ZIP recipients can read the bundled key, and servers using it share project quota. Keep a separate backup when editing this file. Key restrictions must permit the intended servers. Plugin replacement can overwrite this file; retain it when building each private upgrade package. Settings overrides remain in the WordPress database across replacements.

Alternatively, define a default outside the plugin in wp-config.php before WordPress loads; this takes precedence over the bundled file:

```php
define('WTAI_GOOGLE_DRIVE_API_KEY', 'YOUR_SERVER_DRIVE_API_KEY');
```

Alternatively, or to override the server default, use **Settings & Logs > Google Drive API Key > Save Override**. The saved override takes precedence; **Use Server Default** removes it. Keys stay server-side in a separate non-autoloaded WordPress option and are not displayed or exported into normal settings/history. Protect database backups and restrict the key to Google Drive API and suitable server IPs where supported; browser-referrer restrictions are unsuitable for these server requests. This release intentionally includes the configured file in both Git and the installation ZIP; use a saved override when a site needs a different key. The Google Places key used for GBP images is independent.

Choose **Public Google Drive folder** on Generate Articles and provide a public Viewer folder link. Public DOCX downloads must be permitted by their owners. The integration imports DOCX files and native Google Docs directly within that folder, pages listings, and streams bounded temporary downloads. Native Docs are exported to Word first; no OAuth connection, subfolder traversal, or background sync is implemented. Google export output is limited to 10 MB; see [Google export limits](https://developers.google.com/workspace/drive/api/reference/rest/v3/files/export). The Plugins Status card shows the last verified API response separately from key configuration. The existing server upload-size and archive/XML limits also apply.

Google quotas belong to the Cloud project and can vary. The admin displays credential source and reported quota/access failures; it cannot display an authoritative remaining-quota balance. A quota response pauses the import and retains the selection for retry, with a link to the key override and Cloud quota console. Another key on the same project is not a separate quota, and a new project may not resolve file-specific download restrictions. See [Google Drive usage limits](https://developers.google.com/workspace/drive/api/guides/limits).

For local validation, run `tests/drive-integration.php` with the same WordPress PHP extensions and database connection as `tests/integration.php`. It mocks all Drive HTTP, creates temporary posts and credentials, and restores them.

Configuration precedence: a saved site override wins, followed by the wp-config.php constant, then the plugin-local config.php. **Use Server Default** removes the database override and returns to the highest available default. Run `php -n tests/config-checks.php` from the plugin directory to verify fallback behavior using an isolated temporary configuration, without reading or modifying your real key.

## Pixabay fallback setup

1. Get your key from the [Pixabay API documentation](https://pixabay.com/api/docs/) while signed into your Pixabay account.
2. Open **Wolf Forge Articles > Settings & Logs > Pixabay Image Fallback**, enter the key, and select **Save Key**. It is stored separately from Google credentials, is not autoloaded, and is never displayed after saving. Configured means a key is present; a real search during Submit validates access.
3. On **Image Media Library**, choose **Media Library** or **Both**, enable automatic image insertion, set the maximum, and enable **Download matching Pixabay photos when local images are insufficient**. Wait for **Options saved.**
4. Submit your documents as usual. Review the inserted/requested image count and inline Pixabay result. Previews use existing media only and never call Pixabay.

For a portable default, add a separate `'pixabay_api_key' => 'YOUR_PIXABAY_KEY'` entry to the array returned by the existing private plugin-root `config.php`, preserving any `google_drive_api_key` entry. Alternatively define `WTAI_PIXABAY_API_KEY` in wp-config.php. Precedence is saved site override, wp-config constant, then private plugin config. **Remove Override** restores the available default; remove the default too if you want the credential completely unconfigured. Reset Options disables the fallback but preserves its credential. Keys are excluded from ordinary settings, history, posts, and AJAX responses. Bundled configuration is tracked in Git and included in the installation ZIP, so its values are readable to repository readers and ZIP recipients.

Outbound HTTPS to pixabay.com and cdn.pixabay.com and WordPress media uploads must work. This uses [Pixabay's API](https://pixabay.com/api/docs/) with 24-hour search caching and rate-limit cooldowns, and stores selected files locally rather than hotlinking. Search terms contain article/section keywords. Each article is bounded to three searches and five download attempts; there is no background image crawler. Pixabay's [Content License](https://pixabay.com/service/license-summary/) applies to the selected photos. Source, photographer, and license references are retained.

## GitHub version checker

No key or setup is needed for the public ArdsGold/w2a-wf repository. Allow outbound HTTPS to raw.githubusercontent.com if you want version checks to work. Opening Wolf Forge Articles starts an administrator-only background check of the Version header on `main`; matching versions remain silent. A valid mismatch displays both versions and the repository link. Successful results are cached six hours and failures stay silent with a 15-minute retry backoff. The checker does not install updates. Keep the main PHP file's Version header and `WTAI_VERSION` in sync when preparing future versions.
