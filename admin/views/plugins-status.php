<?php
/** Compact plugin availability card on Generate Articles. */
if (!defined('ABSPATH')) exit;
$plugin_statuses = WTAI_SEO::plugin_statuses();
$drive_status = WTAI_Google_Drive::status();
$labels = array(
    'active' => __('Active', 'wolf-forge-article-generator'),
    'inactive' => __('Inactive', 'wolf-forge-article-generator'),
    'not_installed' => __('Not installed', 'wolf-forge-article-generator'),
);
?>
<div class="wtai-card" id="wtai-plugins-status">
    <h3><?php _e('Plugins Status', 'wolf-forge-article-generator'); ?></h3>
    <?php foreach ($plugin_statuses as $name => $status): ?>
        <p><strong><?php echo esc_html($name); ?>:</strong> <span<?php echo $status === 'active' ? ' class="wtai-status-good"' : ''; ?>><?php echo esc_html($labels[$status]); ?></span></p>
    <?php endforeach; ?>
    <p><strong><?php _e('Google Drive API:', 'wolf-forge-article-generator'); ?></strong> <span id="wtai-drive-api-status" role="status" aria-live="polite"<?php echo $drive_status['connection'] === 'connected' ? ' class="wtai-status-good"' : ''; ?>><?php echo esc_html($drive_status['connection_label']); ?></span></p>
</div>
