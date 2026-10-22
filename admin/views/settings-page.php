<?php
/** Settings maintenance, import history, and diagnostics. */
if (!defined('ABSPATH')) exit;
?>
<div class="wtai-settings-page">
    <div class="wtai-container">
        <div class="wtai-main-content">
            <section class="wtai-card wtai-settings-tools">
                <h3><?php _e('Pixabay Image Fallback', 'wolf-forge-article-generator'); ?></h3>
                <p><?php _e('Enable the optional fallback on Image Media Library, then Submit articles normally. Photos are downloaded to your own Media Library, not hotlinked. This key is separate from Google Drive and Google Places.', 'wolf-forge-article-generator'); ?></p>
                <label for="wtai-pixabay-api-key"><?php _e('Pixabay API key override', 'wolf-forge-article-generator'); ?></label>
                <input type="password" id="wtai-pixabay-api-key" autocomplete="new-password" spellcheck="false" value="">
                <p><button type="button" class="button" id="wtai-pixabay-save-key"><?php _e('Save Key', 'wolf-forge-article-generator'); ?></button> <button type="button" class="button" id="wtai-pixabay-remove-key"><?php _e('Remove Override', 'wolf-forge-article-generator'); ?></button></p>
                <p id="wtai-pixabay-key-status" role="status" aria-live="polite"><?php echo esc_html(WTAI_Pixabay::status()['message']); ?></p>
                <p class="description"><?php _e('Saved keys are never displayed and survive Reset Options. Optional defaults: WTAI_PIXABAY_API_KEY in wp-config.php or pixabay_api_key in the pluginâ€™s private config.php. Removing an override restores that default, if configured.', 'wolf-forge-article-generator'); ?> <a href="https://pixabay.com/api/docs/" target="_blank" rel="noopener"><?php _e('Get a Pixabay API key', 'wolf-forge-article-generator'); ?></a></p>
            </section>
            <section class="wtai-card wtai-settings-tools">
                <h3><?php _e('Google Drive API Key', 'wolf-forge-article-generator'); ?></h3>
                <p><?php _e('Public-folder imports use the default from the plugin’s private config.php, or WTAI_GOOGLE_DRIVE_API_KEY in wp-config.php, unless you save an override below. Enable Google Drive API in the key’s Cloud project. This is separate from Google Business Profile image credentials.', 'wolf-forge-article-generator'); ?></p>
                <label for="wtai-drive-api-key"><?php _e('Your Google Drive API key override', 'wolf-forge-article-generator'); ?></label>
                <input type="password" id="wtai-drive-api-key" autocomplete="new-password" spellcheck="false" value="">
                <p><button type="button" class="button" id="wtai-drive-save-key"><?php _e('Save Override', 'wolf-forge-article-generator'); ?></button> <button type="button" class="button" id="wtai-drive-remove-key"><?php _e('Use Server Default', 'wolf-forge-article-generator'); ?></button></p>
                <p id="wtai-drive-key-status" role="status" aria-live="polite"></p>
                <p class="description"><?php _e('Saved keys are not displayed. The override takes effect on the next Drive request and survives Reset Options. A different key on the same Cloud project does not provide a separate project quota; a file download limit may still apply with another project.', 'wolf-forge-article-generator'); ?></p>
            </section>
            <div class="wtai-card wtai-settings-tools" id="wtai-settings">
                <h3><?php _e('Settings & Logs', 'wolf-forge-article-generator'); ?></h3>
                <p><?php _e('Formatting and publishing preferences save on Generate Articles; image preferences save on Image Media Library.', 'wolf-forge-article-generator'); ?></p>
                <p data-wtai-options-status role="status" aria-live="polite"></p>
                <p><button type="button" class="button" id="wtai-reset-settings"><?php _e('Reset Options to Defaults', 'wolf-forge-article-generator'); ?></button></p>
                <p><button type="button" class="button" id="wtai-clear-history"><?php _e('Clear Import History', 'wolf-forge-article-generator'); ?></button></p>
                <p><button type="button" class="button" id="wtai-view-error-stats"><?php _e('View Error Statistics', 'wolf-forge-article-generator'); ?></button></p>
                <p><button type="button" class="button" id="wtai-clear-error-logs"><?php _e('Clear Error Logs', 'wolf-forge-article-generator'); ?></button></p>
                <p class="description"><?php _e('Clearing logs or history does not delete articles.', 'wolf-forge-article-generator'); ?></p>
                <div id="wtai-error-stats-section" style="display:none;">
                    <h4><?php _e('Error Statistics', 'wolf-forge-article-generator'); ?></h4>
                    <div id="wtai-error-stats-content"></div>
                </div>
            </div>

            <div class="wtai-card">
                <h3><?php _e('Import History', 'wolf-forge-article-generator'); ?></h3>
                <p><button type="button" class="button" id="wtai-copy-imports" data-wtai-copy-imports disabled><?php _e('Copy All Imports', 'wolf-forge-article-generator'); ?></button></p>
                <p class="description"><?php _e('Copy completed imports shown below as a numbered list of linked post titles. Rich text destinations retain clickable titles; plain text includes the URLs.', 'wolf-forge-article-generator'); ?></p>
                <p id="wtai-history-status" data-wtai-copy-status role="status" aria-live="polite"></p>
                <div id="wtai-import-history"><p><?php _e('Loading recent imports...', 'wolf-forge-article-generator'); ?></p></div>
            </div>
        </div>
        <aside class="wtai-sidebar">
            <div class="wtai-card"><?php echo (new WTAI_Server_Info())->render_server_info(); ?></div>
        </aside>
    </div>
</div>
