<?php
/**
 * Import Page View
 * Main interface for uploading and importing .docx files
 */

if (!defined('ABSPATH')) {
    exit;
}

$admin_page = new WTAI_Admin_Page();
$settings = $admin_page->get_settings();
$authors = $admin_page->get_authors();
$categories = $admin_page->get_categories();

$server_info = new WTAI_Server_Info();
?>

<div class="wrap wtai-import-page">
    <h1><?php _e('Word to Article Importer', 'word-to-article-importer'); ?></h1>
    
    <div class="wtai-container">
        <div class="wtai-main-content">
            <div class="wtai-card">
                <h2><?php _e('Import Documents', 'word-to-article-importer'); ?></h2>
                
                <form id="wtai-import-form" enctype="multipart/form-data">
                    <?php wp_nonce_field('wtai_import_nonce', 'wtai_nonce'); ?>
                    
                    <!-- File Upload Section -->
                    <div class="wtai-form-section">
                        <h3><?php _e('Select Documents', 'word-to-article-importer'); ?></h3>
                        <div class="wtai-file-upload">
                            <input type="file" name="documents[]" id="wtai-documents" multiple accept=".docx" />
                            <div class="wtai-file-drop-zone" id="wtai-file-drop-zone">
                                <span class="dashicons dashicons-upload"></span>
                                <p><?php _e('Drag & drop .docx files here or click to browse', 'word-to-article-importer'); ?></p>
                                <p class="wtai-small-text"><?php _e('Multiple files supported for bulk import', 'word-to-article-importer'); ?></p>
                            </div>
                        </div>
                        <div id="wtai-file-list" class="wtai-file-list"></div>
                    </div>
                    
                    <!-- Content Processing Options -->
                    <div class="wtai-form-section">
                        <h3><?php _e('Content Processing', 'word-to-article-importer'); ?></h3>
                        
                        <div class="wtai-form-group">
                            <label>
                                <input type="checkbox" name="settings[convert_numbered_to_dotted]" id="wtai-convert-lists" <?php checked($settings['convert_numbered_to_dotted']); ?> />
                                <?php _e('Convert numbered lists to dotted (bullet) lists', 'word-to-article-importer'); ?>
                            </label>
                            <p class="description"><?php _e('Transform all numbered lists (1, 2, 3...) into bullet points', 'word-to-article-importer'); ?></p>
                        </div>
                        
                        <div class="wtai-form-group">
                            <label>
                                <input type="checkbox" name="settings[unbold_except_headings]" id="wtai-unbold-text" <?php checked($settings['unbold_except_headings']); ?> />
                                <?php _e('Remove bold formatting except from headings', 'word-to-article-importer'); ?>
                            </label>
                            <p class="description"><?php _e('Keep headings bold but remove bold from regular text content', 'word-to-article-importer'); ?></p>
                        </div>
                        
                        <div class="wtai-form-group">
                            <label>
                                <input type="checkbox" name="settings[auto_insert_images]" id="wtai-auto-images" <?php checked($settings['auto_insert_images']); ?> />
                                <?php _e('Automatically insert relevant images from media library', 'word-to-article-importer'); ?>
                            </label>
                            <p class="description"><?php _e('Match images based on content, headings, and keywords', 'word-to-article-importer'); ?></p>
                        </div>
                        
                        <div class="wtai-form-group wtai-image-matching-options" style="display: <?php echo $settings['auto_insert_images'] ? 'block' : 'none'; ?>;">
                            <label for="wtai-image-matching-method"><?php _e('Image Matching Method:', 'word-to-article-importer'); ?></label>
                            <select name="settings[image_matching_method]" id="wtai-image-matching-method">
                                <option value="title" <?php selected($settings['image_matching_method'], 'title'); ?>><?php _e('By Image Title', 'word-to-article-importer'); ?></option>
                                <option value="description" <?php selected($settings['image_matching_method'], 'description'); ?>><?php _e('By Image Description', 'word-to-article-importer'); ?></option>
                                <option value="both" <?php selected($settings['image_matching_method'], 'both'); ?>><?php _e('By Title AND Description', 'word-to-article-importer'); ?></option>
                            </select>
                            <p class="description"><?php _e('Choose how images are matched to your content', 'word-to-article-importer'); ?></p>
                        </div>
                        
                        <div class="wtai-form-group wtai-image-matching-options" style="display: <?php echo $settings['auto_insert_images'] ? 'block' : 'none'; ?>;">
                            <label for="wtai-max-images"><?php _e('Maximum Images per Post:', 'word-to-article-importer'); ?></label>
                            <input type="number" name="settings[max_images_per_post]" id="wtai-max-images" value="<?php echo esc_attr($settings['max_images_per_post']); ?>" min="0" max="10" />
                            <p class="description"><?php _e('Maximum number of images to automatically insert (0-10)', 'word-to-article-importer'); ?></p>
                        </div>
                    </div>
                    
                    <!-- Publish Settings -->
                    <div class="wtai-form-section">
                        <h3><?php _e('Publish Settings', 'word-to-article-importer'); ?></h3>
                        
                        <div class="wtai-form-group">
                            <label for="wtai-default-author"><?php _e('Default Author:', 'word-to-article-importer'); ?></label>
                            <select name="settings[default_author]" id="wtai-default-author">
                                <?php foreach ($authors as $author): ?>
                                    <option value="<?php echo $author->ID; ?>" <?php selected($settings['default_author'], $author->ID); ?>>
                                        <?php echo esc_html($author->display_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="wtai-form-group">
                            <label for="wtai-default-category"><?php _e('Default Category:', 'word-to-article-importer'); ?></label>
                            <select name="settings[default_category]" id="wtai-default-category">
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category->term_id; ?>" <?php selected($settings['default_category'], $category->term_id); ?>>
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="wtai-form-group">
                            <label for="wtai-publish-schedule"><?php _e('Publish Schedule:', 'word-to-article-importer'); ?></label>
                            <select name="settings[publish_schedule]" id="wtai-publish-schedule">
                                <option value="immediate" <?php selected($settings['publish_schedule'], 'immediate'); ?>><?php _e('Publish Immediately', 'word-to-article-importer'); ?></option>
                                <option value="custom_range" <?php selected($settings['publish_schedule'], 'custom_range'); ?>><?php _e('Schedule Over Date Range', 'word-to-article-importer'); ?></option>
                                <option value="specific_date" <?php selected($settings['publish_schedule'], 'specific_date'); ?>><?php _e('Start from Specific Date', 'word-to-article-importer'); ?></option>
                            </select>
                        </div>
                        
                        <div class="wtai-schedule-options" style="display: <?php echo in_array($settings['publish_schedule'], array('custom_range', 'specific_date')) ? 'block' : 'none'; ?>;">
                            <div class="wtai-form-group">
                                <label for="wtai-schedule-start-date"><?php _e('Start Date:', 'word-to-article-importer'); ?></label>
                                <input type="date" name="settings[schedule_start_date]" id="wtai-schedule-start-date" value="<?php echo esc_attr($settings['schedule_start_date']); ?>" />
                            </div>
                            
                            <div class="wtai-form-group wtai-end-date-field" style="display: <?php echo $settings['publish_schedule'] === 'custom_range' ? 'block' : 'none'; ?>;">
                                <label for="wtai-schedule-end-date"><?php _e('End Date:', 'word-to-article-importer'); ?></label>
                                <input type="date" name="settings[schedule_end_date]" id="wtai-schedule-end-date" value="<?php echo esc_attr($settings['schedule_end_date']); ?>" />
                                <p class="description"><?php _e('Documents will be scheduled according to the selected interval until the end date', 'word-to-article-importer'); ?></p>
                            </div>
                            
                            <div class="wtai-form-group">
                                <label for="wtai-schedule-interval"><?php _e('Interval Between Posts:', 'word-to-article-importer'); ?></label>
                                <select name="settings[schedule_interval]" id="wtai-schedule-interval">
                                    <option value="day" <?php selected($settings['schedule_interval'], 'day'); ?>><?php _e('Daily', 'word-to-article-importer'); ?></option>
                                    <option value="week" <?php selected($settings['schedule_interval'], 'week'); ?>><?php _e('Weekly', 'word-to-article-importer'); ?></option>
                                    <option value="month" <?php selected($settings['schedule_interval'], 'month'); ?>><?php _e('Monthly', 'word-to-article-importer'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="wtai-form-actions">
                        <button type="submit" class="button button-primary button-large" id="wtai-import-button">
                            <span class="dashicons dashicons-upload"></span>
                            <?php _e('Import Documents', 'word-to-article-importer'); ?>
                        </button>
                        <span class="spinner" id="wtai-spinner"></span>
                    </div>
                </form>
            </div>
            
            <!-- Import Results -->
            <div class="wtai-card wtai-results-card" id="wtai-results" style="display: none;">
                <h2><?php _e('Import Results', 'word-to-article-importer'); ?></h2>
                <div id="wtai-results-content"></div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="wtai-sidebar">
            <div class="wtai-card">
                <h3><?php _e('Quick Guide', 'word-to-article-importer'); ?></h3>
                <ol>
                    <li><?php _e('Select one or more .docx files', 'word-to-article-importer'); ?></li>
                    <li><?php _e('Configure content processing options', 'word-to-article-importer'); ?></li>
                    <li><?php _e('Set up publish scheduling if needed', 'word-to-article-importer'); ?></li>
                    <li><?php _e('Click "Import Documents" to process', 'word-to-article-importer'); ?></li>
                </ol>
            </div>
            
            <div class="wtai-card">
                <h3><?php _e('Server Information', 'word-to-article-importer'); ?></h3>
                <?php echo $server_info->render_server_info(); ?>
            </div>
            
            <div class="wtai-card">
                <h3><?php _e('Media Library', 'word-to-article-importer'); ?></h3>
                <p><?php _e('Browse your media library to see available images for auto-insertion:', 'word-to-article-importer'); ?></p>
                <input type="text" id="wtai-media-search" placeholder="<?php _e('Search images...', 'word-to-article-importer'); ?>" />
                <div id="wtai-media-preview" class="wtai-media-preview"></div>
                <button type="button" class="button" id="wtai-load-media"><?php _e('Load Images', 'word-to-article-importer'); ?></button>
            </div>
            
            <div class="wtai-card">
                <h3><?php _e('Import History', 'word-to-article-importer'); ?></h3>
                <div id="wtai-import-history">
                    <p><?php _e('No recent imports', 'word-to-article-importer'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
