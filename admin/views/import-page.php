<?php
/**
 * Import Page View
 * Main interface for uploading and importing .docx files
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = WTAI_Settings::read();
$authors = get_users(array('capability' => 'edit_posts', 'orderby' => 'display_name', 'order' => 'ASC'));
$categories = get_categories(array('hide_empty' => false, 'orderby' => 'name'));

?>

    <div class="wtai-container">
        <div class="wtai-main-content">
            <div class="wtai-card">
                <h2><?php _e('Generate Articles', 'wolf-forge-article-generator'); ?></h2>
                
                <form id="wtai-import-form" enctype="multipart/form-data">
                    <p class="description"><?php _e('Options save automatically and are restored on your next visit. These preferences are shared by administrators of this site.', 'wolf-forge-article-generator'); ?></p>
                    <p data-wtai-options-status role="status" aria-live="polite"><?php _e('Options saved.', 'wolf-forge-article-generator'); ?></p>
                    
                    <!-- File Upload Section -->
                    <div class="wtai-form-section">
                        <h3><?php _e('Select Documents', 'wolf-forge-article-generator'); ?></h3>
                        <div class="wtai-form-group">
                            <label for="wtai-document-source"><?php _e('Document source', 'wolf-forge-article-generator'); ?></label>
                            <select id="wtai-document-source"><option value="local"><?php _e('Upload from computer', 'wolf-forge-article-generator'); ?></option><option value="drive"><?php _e('Public Google Drive folder', 'wolf-forge-article-generator'); ?></option></select>
                        </div>
                        <div id="wtai-drive-options" hidden>
                            <label for="wtai-drive-folder"><?php _e('Public folder link', 'wolf-forge-article-generator'); ?></label>
                            <input type="url" id="wtai-drive-folder" value="<?php echo esc_attr(get_option('wtai_drive_folder_url', '')); ?>" placeholder="https://drive.google.com/drive/folders/...">
                            <p><button type="button" class="button" id="wtai-drive-load"><?php _e('Load Documents', 'wolf-forge-article-generator'); ?></button> <button type="button" class="button" id="wtai-drive-more" hidden><?php _e('Load More', 'wolf-forge-article-generator'); ?></button></p>
                            <p class="description"><?php _e('Set the folder to Anyone with the link / Viewer and allow downloads. Lists DOCX files and Google Docs directly in this folder; subfolders are not imported. Google Docs are exported to Word automatically. Select up to 1,000 documents. Files download one at a time using your current generation settings.', 'wolf-forge-article-generator'); ?></p>
                            <p class="description"><?php _e('Google enforces project rate and download limits; exact remaining quota is not available here. If Google reports a limit, importing pauses and your selection stays available for retry. An API key from the same Cloud project may share its quota.', 'wolf-forge-article-generator'); ?> <a href="https://console.cloud.google.com/apis/api/drive.googleapis.com/quotas" target="_blank" rel="noopener noreferrer"><?php _e('View Cloud quotas', 'wolf-forge-article-generator'); ?></a></p>
                            <p id="wtai-drive-status" role="status" aria-live="polite"></p>
                            <p><a href="<?php echo esc_url(admin_url('admin.php?page=word-to-article-importer&tab=settings')); ?>"><?php _e('Manage Google Drive API key in Settings & Logs', 'wolf-forge-article-generator'); ?></a></p>
                            <p><label><input type="checkbox" id="wtai-drive-select-all" checked> <?php _e('Select all loaded documents', 'wolf-forge-article-generator'); ?></label></p>
                            <div id="wtai-drive-files" role="group" aria-label="<?php esc_attr_e('Google Drive documents', 'wolf-forge-article-generator'); ?>"></div>
                        </div>
                        <div class="wtai-file-upload" id="wtai-local-upload">
                            <input type="file" name="documents[]" id="wtai-documents" multiple accept=".docx" />
                            <div class="wtai-file-drop-zone" id="wtai-file-drop-zone" role="button" tabindex="0" aria-controls="wtai-documents" aria-label="<?php esc_attr_e('Choose Word documents', 'wolf-forge-article-generator'); ?>">
                                <span class="dashicons dashicons-upload"></span>
                                <p><?php _e('Drag & drop .docx files here or click to browse', 'wolf-forge-article-generator'); ?></p>
                                <p class="wtai-small-text"><?php _e('Select up to 1,000 documents. Keep this page open while articles are generated.', 'wolf-forge-article-generator'); ?></p>
                            </div>
                        </div>
                        <div id="wtai-file-list" class="wtai-file-list"></div>
                    </div>
                    
                    <!-- Content Processing Options -->
                    <div class="wtai-form-section">
                        <h3><?php _e('Content Processing', 'wolf-forge-article-generator'); ?></h3>
                        <div class="wtai-form-group">
                            <label for="wtai-max-internal-links"><?php _e('Maximum internal links per article', 'wolf-forge-article-generator'); ?></label>
                            <input type="number" min="0" step="1" name="settings[max_internal_links_per_post]" id="wtai-max-internal-links" value="<?php echo esc_attr($settings['max_internal_links_per_post']); ?>" />
                            <p class="description"><?php _e('0 disables automatic internal linking. Link to published blog posts using meaningful phrases from their titles or saved SEO keyphrases. Longer phrases take priority; ambiguous title fragments are skipped. Fewer links are added when relevant matches are unavailable.', 'wolf-forge-article-generator'); ?></p>
                        </div>
                        <div class="wtai-form-group">
                            <label><input type="checkbox" name="settings[auto_link_phone_numbers]" id="wtai-auto-phone-links" <?php checked($settings['auto_link_phone_numbers']); ?> /> <?php _e('Automatically link phone numbers', 'wolf-forge-article-generator'); ?></label>
                            <p class="description"><?php _e('Enabled by default. Link international numbers beginning with + or 00 and formatted US/Canadian numbers such as (216) 399-7569, preserve displayed text and prefix, and remove separators from the tel: destination. Existing links are left unchanged.', 'wolf-forge-article-generator'); ?></p>
                        </div>
                        
                        <div class="wtai-form-group">
                            <label>
                                <input type="checkbox" name="settings[convert_numbered_to_dotted]" id="wtai-convert-lists" <?php checked($settings['convert_numbered_to_dotted']); ?> />
                                <?php _e('Convert numbered lists to dotted (bullet) lists', 'wolf-forge-article-generator'); ?>
                            </label>
                            <p class="description"><?php _e('Transform all numbered lists (1, 2, 3...) into bullet points', 'wolf-forge-article-generator'); ?></p>
                        </div>
                        
                        <div class="wtai-form-group">
                            <label>
                                <input type="checkbox" name="settings[unbold_except_headings]" id="wtai-unbold-text" <?php checked($settings['unbold_except_headings']); ?> />
                                <?php _e('Remove bold formatting except from headings', 'wolf-forge-article-generator'); ?>
                            </label>
                            <p class="description"><?php _e('Keep headings bold but remove bold from regular text content', 'wolf-forge-article-generator'); ?></p>
                        </div>
                        
                        <div class="wtai-form-group">
                            <label><input type="checkbox" id="wtai-generate-auto-images" name="settings[auto_insert_images]" value="1" <?php checked($settings['auto_insert_images']); ?>> <?php _e('Automatically insert matching images into new articles', 'wolf-forge-article-generator'); ?></label>
                        </div>
                        <div class="wtai-form-group">
                            <label for="wtai-generate-max-images"><?php _e('Maximum Images per Article', 'wolf-forge-article-generator'); ?></label>
                            <input type="number" id="wtai-generate-max-images" name="settings[max_images_per_post]" min="0" step="1" value="<?php echo esc_attr($settings['max_images_per_post']); ?>">
                            <p class="description"><?php _e('Default: 3. Enter any non-negative whole number; 0 inserts none. These options are shared with Image Media Library and apply to its selected image source. New images stay at least 100 article words apart and before Conclusion. Fewer images may be inserted when relevant matches or spaced positions are unavailable.', 'wolf-forge-article-generator'); ?></p>
                        </div>
                        <p><a href="<?php echo esc_url(admin_url('admin.php?page=word-to-article-importer&tab=images')); ?>"><?php _e('Configure image matching and placement in Image Media Library', 'wolf-forge-article-generator'); ?></a></p>
                    </div>

                    <!-- Publish Settings -->
                    <div class="wtai-form-section">
                        <h3><?php _e('Publish Settings', 'wolf-forge-article-generator'); ?></h3>
                        
                        <div class="wtai-form-group">
                            <label for="wtai-default-author"><?php _e('Default Author:', 'wolf-forge-article-generator'); ?></label>
                            <select name="settings[default_author]" id="wtai-default-author">
                                <?php foreach ($authors as $author): ?>
                                    <option value="<?php echo $author->ID; ?>" <?php selected($settings['default_author'], $author->ID); ?>>
                                        <?php echo esc_html($author->display_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="wtai-form-group">
                            <label for="wtai-default-category"><?php _e('Default Category:', 'wolf-forge-article-generator'); ?></label>
                            <select name="settings[default_category]" id="wtai-default-category">
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category->term_id; ?>" <?php selected($settings['default_category'], $category->term_id); ?>>
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="wtai-form-group">
                            <label for="wtai-publish-schedule"><?php _e('Publish Schedule:', 'wolf-forge-article-generator'); ?></label>
                            <select name="settings[publish_schedule]" id="wtai-publish-schedule">
                                <option value="specific_month" <?php selected($settings['publish_schedule'], 'specific_month'); ?>><?php _e('Within Specific Month', 'wolf-forge-article-generator'); ?></option>
                                <option value="immediate" <?php selected($settings['publish_schedule'], 'immediate'); ?>><?php _e('Publish Immediately', 'wolf-forge-article-generator'); ?></option>
                                <option value="custom_range" <?php selected($settings['publish_schedule'], 'custom_range'); ?>><?php _e('Between Two Dates', 'wolf-forge-article-generator'); ?></option>
                                <option value="specific_date" <?php selected($settings['publish_schedule'], 'specific_date'); ?>><?php _e('Starting on a Date', 'wolf-forge-article-generator'); ?></option>
                            </select>
                        </div>
                        
                        <div class="wtai-schedule-options" data-today="<?php echo esc_attr(current_time('Y-m-d')); ?>" style="display: <?php echo $settings['publish_schedule'] !== 'immediate' ? 'block' : 'none'; ?>;">
                            <p class="description"><?php echo esc_html(sprintf(__('Publishing time: 9:00 AM (%s). Past dates publish immediately.', 'wolf-forge-article-generator'), wp_timezone()->getName())); ?></p>
                            <div class="wtai-form-group wtai-month-field" style="display: <?php echo $settings['publish_schedule'] === 'specific_month' ? 'block' : 'none'; ?>;">
                                <input type="hidden" name="settings[schedule_month]" id="wtai-schedule-month" value="<?php echo esc_attr($settings['schedule_month']); ?>" />
                                <div class="wtai-month-controls">
                                    <div><label for="wtai-month-number"><?php _e('Month', 'wolf-forge-article-generator'); ?></label><select id="wtai-month-number" aria-describedby="wtai-month-help" <?php echo $settings['publish_schedule'] === 'specific_month' ? 'required' : ''; ?>>
                                        <option value=""><?php _e('Choose a month', 'wolf-forge-article-generator'); ?></option>
                                        <?php for ($month_number = 1; $month_number <= 12; $month_number++): ?>
                                            <option value="<?php echo esc_attr(sprintf('%02d', $month_number)); ?>" <?php selected(substr($settings['schedule_month'], 5, 2), sprintf('%02d', $month_number)); ?>><?php echo esc_html(wp_date('F', gmmktime(12, 0, 0, $month_number, 15, 2026))); ?></option>
                                        <?php endfor; ?>
                                    </select></div>
                                    <div><label for="wtai-month-year"><?php _e('Year', 'wolf-forge-article-generator'); ?></label><input type="number" id="wtai-month-year" min="1" max="9999" step="1" inputmode="numeric" aria-describedby="wtai-month-help" value="<?php echo esc_attr($settings['schedule_month'] ? substr($settings['schedule_month'], 0, 4) : current_time('Y')); ?>" <?php echo $settings['publish_schedule'] === 'specific_month' ? 'required' : ''; ?>></div>
                                </div>
                                <div class="wtai-date-shortcuts"><button type="button" class="button" data-wtai-date-action="this-month"><?php _e('This month', 'wolf-forge-article-generator'); ?></button><button type="button" class="button" data-wtai-date-action="next-month"><?php _e('Next month', 'wolf-forge-article-generator'); ?></button></div>
                                <p class="description" id="wtai-month-help"><?php _e('Choose a month and a four-digit year.', 'wolf-forge-article-generator'); ?></p>
                                <p class="description"><?php _e('Spread articles evenly across this month. One article uses day 1. Extra articles loop back to day 1 when all days are used.', 'wolf-forge-article-generator'); ?></p>
                            </div>
                            <div class="wtai-form-group wtai-start-date-field" style="display: <?php echo in_array($settings['publish_schedule'], array('custom_range', 'specific_date'), true) ? 'block' : 'none'; ?>;">
                                <label for="wtai-schedule-start-date"><?php _e('Start Date:', 'wolf-forge-article-generator'); ?></label>
                                <input type="date" name="settings[schedule_start_date]" id="wtai-schedule-start-date" aria-describedby="wtai-start-date-label" <?php echo in_array($settings['publish_schedule'], array('custom_range', 'specific_date'), true) ? 'required' : ''; ?> value="<?php echo esc_attr($settings['schedule_start_date']); ?>" />
                                <p class="description" id="wtai-start-date-label"></p>
                                <div class="wtai-date-shortcuts"><button type="button" class="button" data-wtai-date-action="today"><?php _e('Today', 'wolf-forge-article-generator'); ?></button><button type="button" class="button" data-wtai-date-action="tomorrow"><?php _e('Tomorrow', 'wolf-forge-article-generator'); ?></button></div>
                            </div>
                            
                            <div class="wtai-form-group wtai-end-date-field" style="display: <?php echo $settings['publish_schedule'] === 'custom_range' ? 'block' : 'none'; ?>;">
                                <label for="wtai-schedule-end-date"><?php _e('End Date:', 'wolf-forge-article-generator'); ?></label>
                                <input type="date" name="settings[schedule_end_date]" id="wtai-schedule-end-date" aria-describedby="wtai-end-date-label wtai-range-error" <?php echo $settings['publish_schedule'] === 'custom_range' ? 'required' : ''; ?> value="<?php echo esc_attr($settings['schedule_end_date']); ?>" />
                                <p class="description" id="wtai-end-date-label"></p>
                                <p id="wtai-range-error" class="wtai-date-error" role="status"></p>
                                <div class="wtai-date-shortcuts"><button type="button" class="button" data-wtai-date-action="range-week"><?php _e('7-day range', 'wolf-forge-article-generator'); ?></button><button type="button" class="button" data-wtai-date-action="range-month"><?php _e('30-day range', 'wolf-forge-article-generator'); ?></button></div>
                                <p class="description"><?php _e('Use the selected spacing between the start and end dates. Extra articles loop back to the first available date.', 'wolf-forge-article-generator'); ?></p>
                            </div>
                            
                            <div class="wtai-form-group wtai-interval-field" style="display: <?php echo in_array($settings['publish_schedule'], array('custom_range', 'specific_date'), true) ? 'block' : 'none'; ?>;">
                                <label for="wtai-schedule-interval"><?php _e('Spacing between articles:', 'wolf-forge-article-generator'); ?></label>
                                <select name="settings[schedule_interval]" id="wtai-schedule-interval">
                                    <option value="day" <?php selected($settings['schedule_interval'], 'day'); ?>><?php _e('Every day', 'wolf-forge-article-generator'); ?></option>
                                    <option value="week" <?php selected($settings['schedule_interval'], 'week'); ?>><?php _e('Every week', 'wolf-forge-article-generator'); ?></option>
                                    <option value="month" <?php selected($settings['schedule_interval'], 'month'); ?>><?php _e('Every month', 'wolf-forge-article-generator'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div id="wtai-schedule-preview" class="wtai-schedule-preview" role="status" aria-live="polite"></div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="wtai-form-actions">
                        <button type="submit" class="button button-primary button-large" id="wtai-import-button">
                            <?php _e('Submit', 'wolf-forge-article-generator'); ?>
                        </button>
                        <button type="button" class="button button-large" id="wtai-cancel-import" disabled><?php _e('Cancel', 'wolf-forge-article-generator'); ?></button>
                        <button type="button" class="button button-large" id="wtai-generate-copy-imports" data-wtai-copy-imports disabled><?php _e('Copy All Imports', 'wolf-forge-article-generator'); ?></button>
                        <span class="spinner" id="wtai-spinner"></span>
                    </div>
                    <p id="wtai-generate-copy-status" data-wtai-copy-status role="status" aria-live="polite"></p>
                    <div id="wtai-progress" style="display:none;">
                        <progress id="wtai-import-progress" value="0" max="1" aria-label="<?php esc_attr_e('Article generation progress', 'wolf-forge-article-generator'); ?>"></progress>
                        <p id="wtai-progress-text" role="status" aria-live="polite"></p>
                    </div>
                </form>
            </div>
            
            <!-- Import Results -->
            <div class="wtai-card wtai-results-card" id="wtai-results" style="display: none;">
                <h2><?php _e('Import Results', 'wolf-forge-article-generator'); ?></h2>
                <div id="wtai-results-content"></div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="wtai-sidebar">
            <?php require WTAI_PLUGIN_DIR . 'admin/views/plugins-status.php'; ?>
            <div class="wtai-card">
                <h3><?php _e('Quick Guide', 'wolf-forge-article-generator'); ?></h3>
                <ol>
                    <li><?php _e('Select one or more .docx files', 'wolf-forge-article-generator'); ?></li>
                    <li><?php _e('Configure content processing options', 'wolf-forge-article-generator'); ?></li>
                    <li><?php _e('Set up publish scheduling if needed', 'wolf-forge-article-generator'); ?></li>
                    <li><?php _e('Click "Submit" to process', 'wolf-forge-article-generator'); ?></li>
                </ol>
            </div>
            
        </div>
    </div>
