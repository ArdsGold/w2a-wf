<?php
/** Image preferences, metadata browser, and non-destructive matching preview. */
if (!defined('ABSPATH')) exit;
$settings = WTAI_Settings::read();
?>
<div class="wtai-image-page">
    <h2><?php _e('Image Integration', 'wolf-forge-article-generator'); ?></h2>
    <p><?php _e('Choose contextual Media Library images, live Google Business Profile photos, or both.', 'wolf-forge-article-generator'); ?></p>
    <div class="wtai-container">
        <div class="wtai-main-content">
            <form id="wtai-image-settings-form" class="wtai-card">
                <h3><?php _e('Image Options', 'wolf-forge-article-generator'); ?></h3>
                <div class="wtai-form-group">
                    <label for="wtai-image-source"><?php _e('Image source', 'wolf-forge-article-generator'); ?></label>
                    <select id="wtai-image-source" name="settings[image_source]">
                        <?php foreach (array('media_library' => __('Media Library', 'wolf-forge-article-generator'), 'gbp' => __('Google Business Profile', 'wolf-forge-article-generator'), 'both' => __('Both', 'wolf-forge-article-generator')) as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['image_source'], $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="wtai-gbp-options">
                    <div class="wtai-form-group"><label for="wtai-gbp-profile"><?php _e('Public Google Maps / business profile link', 'wolf-forge-article-generator'); ?></label><input type="url" class="widefat" id="wtai-gbp-profile" name="settings[gbp_profile_url]" value="<?php echo esc_attr($settings['gbp_profile_url']); ?>" placeholder="https://maps.app.goo.gl/..."></div>
                    <p class="description"><?php _e('Use all photos returned by Google, without owner/customer filtering or keyword matching. Google supplies up to 10 photos; photos and their order can change. Their alt text identifies the profile source rather than guessing picture contents.', 'wolf-forge-article-generator'); ?></p>
                    <div class="wtai-form-group"><label for="wtai-google-api-key"><?php _e('Google Places API key', 'wolf-forge-article-generator'); ?></label><input type="password" class="widefat" id="wtai-google-api-key" autocomplete="new-password" placeholder="<?php esc_attr_e('Enter a new key; the saved value is never displayed', 'wolf-forge-article-generator'); ?>"></div>
                    <p><button type="button" class="button" id="wtai-save-google-key"><?php _e('Save API Key', 'wolf-forge-article-generator'); ?></button> <button type="button" class="button" id="wtai-remove-google-key"><?php _e('Remove API Key', 'wolf-forge-article-generator'); ?></button> <button type="button" class="button" id="wtai-check-google-profile"><?php _e('Check Profile Photos', 'wolf-forge-article-generator'); ?></button></p>
                    <p id="wtai-google-status" role="status" aria-live="polite"><?php echo esc_html(WTAI_GBP_Photos::has_key() ? __('Google API key configured.', 'wolf-forge-article-generator') : __('Google API key not configured.', 'wolf-forge-article-generator')); ?></p>
                    <div id="wtai-google-preview"></div>
                    <p class="description"><?php _e('Enable Places API (New) and billing in your Google Cloud project. Restrict the key to this API and your server IP where supported. Photo checks, previews, and article views make live Google API requests and may incur charges. The key stays server-side and is excluded from import settings and history.', 'wolf-forge-article-generator'); ?> <a href="https://developers.google.com/maps/documentation/places/web-service/get-api-key" target="_blank" rel="noopener"><?php _e('Google API setup', 'wolf-forge-article-generator'); ?></a></p>
                    <p class="description"><?php _e('Google photos remain hosted by Google, with attribution. Exclude articles using these photos from full-page/CDN caching so expiring photo URLs are refreshed. Your site must provide the Google Maps terms/privacy notices described in INSTALLATION.md.', 'wolf-forge-article-generator'); ?></p>
                </div>
                <p><label><input type="checkbox" id="wtai-auto-images" name="settings[auto_insert_images]" value="1" <?php checked($settings['auto_insert_images']); ?>> <?php _e('Automatically insert matching images into new articles', 'wolf-forge-article-generator'); ?></label></p>
                <div class="wtai-form-group">
                    <label for="wtai-max-images"><?php _e('Maximum Images per Article', 'wolf-forge-article-generator'); ?></label>
                    <input type="number" id="wtai-max-images" name="settings[max_images_per_post]" min="0" step="1" value="<?php echo esc_attr($settings['max_images_per_post']); ?>">
                    <p class="description"><?php _e('Default: 3. Enter any non-negative whole number; 0 inserts none. The actual count is limited by relevant images and safe block boundaries.', 'wolf-forge-article-generator'); ?></p>
                </div>
                <p><?php _e('Images stay before the Conclusion heading and target equal portions of the preceding word count: three target 33%, 67%, and 100%. Newly inserted images stay at least 100 article words apart. Context takes priority: each image follows a complete paragraph or block whose text or section headings match both its title and alt text. Positions may shift, and fewer images may be inserted.', 'wolf-forge-article-generator'); ?></p>
                <p><?php _e('Media Library images require a descriptive title and alt text matching the article and placement context. Images labeled as logos, icons, favicons, logotypes, or logomarks in their metadata or filenames are excluded, along with the WordPress site logo and site icon. This uses labels, not visual recognition. GBP photos use the profile photo order instead. Captions and descriptions support ranking but cannot qualify an image alone. Original media metadata is preserved. Images stay inside the post content; your theme controls the surrounding post layout.', 'wolf-forge-article-generator'); ?></p>
                <p><label><input type="checkbox" id="wtai-pixabay-fallback" name="settings[pixabay_fallback]" value="1" <?php checked($settings['pixabay_fallback']); ?>> <?php _e('Download matching Pixabay photos when local images are insufficient', 'wolf-forge-article-generator'); ?></label></p>
                <p class="description"><?php _e('Optional; off by default. Add your Pixabay API key in Settings & Logs. During Submit, Media Library and Both sources may use up to 3 photo searches and 5 downloads per article. Images must match the article and placement context; spacing and Conclusion rules still apply. Previews never search or download Pixabay photos. Search terms are sent to Pixabay; downloaded photos remain in Media Library for reuse, with source credits.', 'wolf-forge-article-generator'); ?></p>
                <p data-wtai-options-status role="status" aria-live="polite"><?php _e('Options saved.', 'wolf-forge-article-generator'); ?></p>
            </form>
            <section class="wtai-card">
                <h3><?php _e('Preview Image Matching', 'wolf-forge-article-generator'); ?></h3>
                <p><?php _e('Paste an article to preview matches and placement. Separate plain-text paragraphs with blank lines, or paste article HTML. This does not create or update posts.', 'wolf-forge-article-generator'); ?></p>
                <div class="wtai-form-group"><label for="wtai-preview-title"><?php _e('Article Title', 'wolf-forge-article-generator'); ?></label><input type="text" id="wtai-preview-title" maxlength="500"></div>
                <div class="wtai-form-group"><label for="wtai-preview-content"><?php _e('Article Text or HTML', 'wolf-forge-article-generator'); ?></label><textarea id="wtai-preview-content" rows="12" maxlength="200000"></textarea></div>
                <button type="button" class="button button-primary" id="wtai-preview-images"><?php _e('Preview Images', 'wolf-forge-article-generator'); ?></button>
                <p id="wtai-preview-status" role="status" aria-live="polite"></p>
                <div id="wtai-image-report"></div>
                <div id="wtai-article-preview" class="wtai-article-preview"></div>
            </section>
        </div>
        <aside class="wtai-sidebar">
            <section class="wtai-card">
                <h3><?php _e('Browse Image Metadata', 'wolf-forge-article-generator'); ?></h3>
                <label for="wtai-media-search"><?php _e('Search titles, alt text, captions, and descriptions', 'wolf-forge-article-generator'); ?></label>
                <input type="search" id="wtai-media-search" class="widefat">
                <p><button type="button" class="button" id="wtai-load-media"><?php _e('Reload Library', 'wolf-forge-article-generator'); ?></button> <button type="button" class="button" id="wtai-refresh-index"><?php _e('Refresh Keyword Index', 'wolf-forge-article-generator'); ?></button></p>
                <p id="wtai-media-status" role="status" aria-live="polite"></p>
                <div id="wtai-media-preview" class="wtai-metadata-browser"></div>
                <div class="wtai-media-pagination"><button type="button" class="button" id="wtai-media-prev"><?php _e('Previous', 'wolf-forge-article-generator'); ?></button> <button type="button" class="button" id="wtai-media-next"><?php _e('Next', 'wolf-forge-article-generator'); ?></button></div>
                <p class="description"><?php _e('The index refreshes after attachment metadata changes and expires after one hour. Edit media to improve matches; the matcher cannot recognize the picture itself.', 'wolf-forge-article-generator'); ?></p>
            </section>
        </aside>
    </div>
</div>
