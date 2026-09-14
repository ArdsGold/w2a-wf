<?php
/**
 * Settings Page View
 * Plugin settings and configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

$admin_page = new WTAI_Admin_Page();
$settings = $admin_page->get_settings();
$authors = $admin_page->get_authors();
$categories = $admin_page->get_categories();
?>

<div class="wrap wtai-settings-page">
    <h1><?php _e('Word to Article Importer Settings', 'word-to-article-importer'); ?></h1>
    
    <form method="post" action="options.php">
        <?php settings_fields('wtai_settings'); ?>
        
        <div class="wtai-settings-container">
            <div class="wtai-settings-section">
                <h2><?php _e('Content Processing Defaults', 'word-to-article-importer'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wtai_convert_numbered_to_dotted"><?php _e('Convert Numbered to Dotted Lists', 'word-to-article-importer'); ?></label>
                        </th>
                        <td>
                            <input type="hidden" name="wtai_convert_numbered_to_dotted" value="0" />
                            <input type="checkbox" name="wtai_convert_numbered_to_dotted" id="wtai_convert_numbered_to_dotted" value="1" <?php checked($settings['convert_numbered_to_dotted']); ?> />
                            <p class="description"><?php _e('Default setting for converting numbered lists to bullet points', 'word-to-article-importer'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wtai_unbold_except_headings"><?php _e('Remove Bold Except Headings', 'word-to-article-importer'); ?></label>
                        </th>
                        <td>
                            <input type="hidden" name="wtai_unbold_except_headings" value="0" />
                            <input type="checkbox" name="wtai_unbold_except_headings" id="wtai_unbold_except_headings" value="1" <?php checked($settings['unbold_except_headings']); ?> />
                            <p class="description"><?php _e('Default setting for removing bold formatting from non-heading text', 'word-to-article-importer'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wtai_auto_insert_images"><?php _e('Auto-insert Images', 'word-to-article-importer'); ?></label>
                        </th>
                        <td>
                            <input type="hidden" name="wtai_auto_insert_images" value="0" />
                            <input type="checkbox" name="wtai_auto_insert_images" id="wtai_auto_insert_images" value="1" <?php checked($settings['auto_insert_images']); ?> />
                            <p class="description"><?php _e('Default setting for automatic image insertion', 'word-to-article-importer'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wtai_image_matching_method"><?php _e('Image Matching Method', 'word-to-article-importer'); ?></label>
                        </th>
                        <td>
                            <select name="wtai_image_matching_method" id="wtai_image_matching_method">
                                <option value="title" <?php selected($settings['image_matching_method'], 'title'); ?>><?php _e('By Image Title', 'word-to-article-importer'); ?></option>
                                <option value="description" <?php selected($settings['image_matching_method'], 'description'); ?>><?php _e('By Image Description', 'word-to-article-importer'); ?></option>
                                <option value="both" <?php selected($settings['image_matching_method'], 'both'); ?>><?php _e('By Title AND Description', 'word-to-article-importer'); ?></option>
                            </select>
                            <p class="description"><?php _e('Default method for matching images to content', 'word-to-article-importer'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wtai_max_images_per_post"><?php _e('Maximum Images per Post', 'word-to-article-importer'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="wtai_max_images_per_post" id="wtai_max_images_per_post" value="<?php echo esc_attr($settings['max_images_per_post']); ?>" min="0" max="10" />
                            <p class="description"><?php _e('Default maximum number of images to insert per post (0-10)', 'word-to-article-importer'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="wtai-settings-section">
                <h2><?php _e('Default Post Settings', 'word-to-article-importer'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wtai_default_author"><?php _e('Default Author', 'word-to-article-importer'); ?></label>
                        </th>
                        <td>
                            <select name="wtai_default_author" id="wtai_default_author">
                                <?php foreach ($authors as $author): ?>
                                    <option value="<?php echo $author->ID; ?>" <?php selected($settings['default_author'], $author->ID); ?>>
                                        <?php echo esc_html($author->display_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Default author for imported posts', 'word-to-article-importer'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wtai_default_category"><?php _e('Default Category', 'word-to-article-importer'); ?></label>
                        </th>
                        <td>
                            <select name="wtai_default_category" id="wtai_default_category">
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category->term_id; ?>" <?php selected($settings['default_category'], $category->term_id); ?>>
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Default category for imported posts', 'word-to-article-importer'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="wtai-settings-section">
                <h2><?php _e('Scheduling Defaults', 'word-to-article-importer'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wtai_publish_schedule"><?php _e('Default Publish Schedule', 'word-to-article-importer'); ?></label>
                        </th>
                        <td>
                            <select name="wtai_publish_schedule" id="wtai_publish_schedule">
                                <option value="immediate" <?php selected($settings['publish_schedule'], 'immediate'); ?>><?php _e('Publish Immediately', 'word-to-article-importer'); ?></option>
                                <option value="custom_range" <?php selected($settings['publish_schedule'], 'custom_range'); ?>><?php _e('Schedule Over Date Range', 'word-to-article-importer'); ?></option>
                                <option value="specific_date" <?php selected($settings['publish_schedule'], 'specific_date'); ?>><?php _e('Start from Specific Date', 'word-to-article-importer'); ?></option>
                            </select>
                            <p class="description"><?php _e('Default scheduling behavior for bulk imports', 'word-to-article-importer'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wtai_schedule_start_date"><?php _e('Default Start Date', 'word-to-article-importer'); ?></label>
                        </th>
                        <td>
                            <input type="date" name="wtai_schedule_start_date" id="wtai_schedule_start_date" value="<?php echo esc_attr($settings['schedule_start_date']); ?>" />
                            <p class="description"><?php _e('Default start date for scheduled posts', 'word-to-article-importer'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wtai_schedule_end_date"><?php _e('Default End Date', 'word-to-article-importer'); ?></label>
                        </th>
                        <td>
                            <input type="date" name="wtai_schedule_end_date" id="wtai_schedule_end_date" value="<?php echo esc_attr($settings['schedule_end_date']); ?>" />
                            <p class="description"><?php _e('Default end date for scheduled posts (for date range scheduling)', 'word-to-article-importer'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wtai_schedule_interval"><?php _e('Default Interval', 'word-to-article-importer'); ?></label>
                        </th>
                        <td>
                            <select name="wtai_schedule_interval" id="wtai_schedule_interval">
                                <option value="day" <?php selected($settings['schedule_interval'], 'day'); ?>><?php _e('Daily', 'word-to-article-importer'); ?></option>
                                <option value="week" <?php selected($settings['schedule_interval'], 'week'); ?>><?php _e('Weekly', 'word-to-article-importer'); ?></option>
                                <option value="month" <?php selected($settings['schedule_interval'], 'month'); ?>><?php _e('Monthly', 'word-to-article-importer'); ?></option>
                            </select>
                            <p class="description"><?php _e('Default interval between scheduled posts', 'word-to-article-importer'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="wtai-settings-section">
                <h2><?php _e('Advanced Options', 'word-to-article-importer'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php _e('Import History', 'word-to-article-importer'); ?></label>
                        </th>
                        <td>
                            <button type="button" class="button" id="wtai-clear-history"><?php _e('Clear Import History', 'word-to-article-importer'); ?></button>
                            <p class="description"><?php _e('Remove all records of previous imports', 'word-to-article-importer'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php _e('Error Logs', 'word-to-article-importer'); ?></label>
                        </th>
                        <td>
                            <button type="button" class="button" id="wtai-view-error-stats"><?php _e('View Error Statistics', 'word-to-article-importer'); ?></button>
                            <button type="button" class="button" id="wtai-clear-error-logs"><?php _e('Clear Error Logs', 'word-to-article-importer'); ?></button>
                            <p class="description"><?php _e('View and manage error logs for troubleshooting', 'word-to-article-importer'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php _e('Reset Settings', 'word-to-article-importer'); ?></label>
                        </th>
                        <td>
                            <button type="button" class="button" id="wtai-reset-settings"><?php _e('Reset to Defaults', 'word-to-article-importer'); ?></button>
                            <p class="description"><?php _e('Reset all settings to their default values', 'word-to-article-importer'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="wtai-settings-section" id="wtai-error-stats-section" style="display: none;">
                <h2><?php _e('Error Statistics', 'word-to-article-importer'); ?></h2>
                <div id="wtai-error-stats-content"></div>
            </div>
        </div>
        
        <?php submit_button(__('Save Settings', 'word-to-article-importer')); ?>
    </form>
</div>
