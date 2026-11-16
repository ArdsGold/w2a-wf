<?php
/** Inspect automatically detected Services roots and eligible descendants. */
if (!defined('ABSPATH')) exit;
$services = WTAI_Internal_Linker::service_discovery();
$service_targets = array_values($services['targets']);
usort($service_targets, function ($a, $b) { return strnatcasecmp($a->post_title, $b->post_title) ?: ($a->ID <=> $b->ID); });
$source_labels = array(
    'menu' => __('Menu item', 'wolf-forge-article-generator'),
    'block_menu' => __('Block menu item', 'wolf-forge-article-generator'),
    'page' => __('Parent page', 'wolf-forge-article-generator'),
);
?>
<section class="wtai-card" id="wtai-services-status">
    <h3><?php _e('Services Detection', 'wolf-forge-article-generator'); ?></h3>
    <p id="wtai-services-detection-status" role="status">
        <?php echo esc_html($services['roots'] ? __('Services detected', 'wolf-forge-article-generator') : __('No Services menu item or parent page detected', 'wolf-forge-article-generator')); ?>
    </p>
    <?php if ($services['roots']): ?>
        <ul>
            <?php foreach ($services['roots'] as $service_root): ?>
                <li><?php echo esc_html($source_labels[$service_root['type']] . ': ' . $service_root['label']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <label for="wtai-service-descendants"><?php _e('Children and descendants', 'wolf-forge-article-generator'); ?> (<?php echo count($service_targets); ?>)</label>
    <select id="wtai-service-descendants" class="widefat" aria-describedby="wtai-services-help" <?php disabled(!$service_targets); ?>>
        <option value=""><?php echo esc_html($service_targets ? __('View detected link targets', 'wolf-forge-article-generator') : __('No eligible children or descendants found', 'wolf-forge-article-generator')); ?></option>
        <?php foreach ($service_targets as $service_target): ?>
            <option value="<?php echo esc_attr($service_target->ID); ?>"><?php echo esc_html(html_entity_decode(wp_strip_all_tags($service_target->post_title), ENT_QUOTES | ENT_HTML5, 'UTF-8')); ?></option>
        <?php endforeach; ?>
    </select>
    <p id="wtai-services-help" class="description"><?php echo esc_html($service_targets ? __('Automatic linking considers all listed targets. This dropdown is for inspection.', 'wolf-forge-article-generator') : __('Normal blog matching remains available. A missing Services section does not block imports.', 'wolf-forge-article-generator')); ?></p>
</section>
