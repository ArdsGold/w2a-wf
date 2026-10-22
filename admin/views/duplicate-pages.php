<?php
/** Duplicate post management, using core edit, preview, and Trash behavior. */
if (!defined('ABSPATH')) exit;
$page = isset($_GET['duplicate_page']) && is_scalar($_GET['duplicate_page']) ? absint($_GET['duplicate_page']) : 1;
$duplicates = WTAI_Duplicate_Pages::listing($page);
$url = admin_url('admin.php?page=word-to-article-importer&tab=duplicates');
?>
<div class="wtai-duplicates">
    <h2><?php _e('Duplicate Blogs', 'wolf-forge-article-generator'); ?></h2>
    <p><?php _e('Posts with identical, non-empty content are grouped together. The lowest post ID is kept as the original; additional copies appear below. Titles and slugs may differ.', 'wolf-forge-article-generator'); ?></p>
    <p class="description"><?php _e('This compares stored article content, not Elementor layouts or other metadata. Empty content, revisions, and items already in Trash are excluded. Pages are excluded. Restore trashed copies from Posts Trash.', 'wolf-forge-article-generator'); ?></p>
    <?php if (isset($_GET['duplicate_trashed']) && $_GET['duplicate_trashed'] === '1'): ?>
        <div class="notice notice-success"><p><?php _e('Duplicate moved to Trash.', 'wolf-forge-article-generator'); ?></p></div>
    <?php endif; ?>
    <?php if (is_wp_error($duplicates)): ?>
        <div class="notice notice-error"><p><?php echo esc_html($duplicates->get_error_message()); ?></p></div>
    <?php else: ?>
        <p><?php echo esc_html(sprintf(__('Duplicates found: %d', 'wolf-forge-article-generator'), $duplicates['total'])); ?></p>
        <div class="wtai-duplicate-table">
            <table class="widefat striped">
                <thead><tr>
                    <th scope="col"><?php _e('Duplicate', 'wolf-forge-article-generator'); ?></th>
                    <th scope="col"><?php _e('Original', 'wolf-forge-article-generator'); ?></th>
                    <th scope="col"><?php _e('Type / Status', 'wolf-forge-article-generator'); ?></th>
                    <th scope="col"><?php _e('Actions', 'wolf-forge-article-generator'); ?></th>
                </tr></thead>
                <tbody>
                <?php if (!$duplicates['items']): ?>
                    <tr><td colspan="4"><?php _e('No duplicate posts found.', 'wolf-forge-article-generator'); ?></td></tr>
                <?php endif; ?>
                <?php foreach ($duplicates['items'] as $copy): ?>
                    <?php
                    if (!current_user_can('read_post', $copy->ID)) continue;
                    $view = in_array($copy->post_status, array('draft', 'pending', 'future'), true) ? get_preview_post_link($copy->ID) : get_permalink($copy->ID);
                    $edit = get_edit_post_link($copy->ID, 'raw');
                    $original_edit = get_edit_post_link($copy->original_id, 'raw');
                    $original_title = current_user_can('read_post', $copy->original_id) ? (get_the_title($copy->original_id) ?: __('Untitled', 'wolf-forge-article-generator')) : __('Original', 'wolf-forge-article-generator');
                    $type = get_post_type_object($copy->post_type);
                    $status = get_post_status_object($copy->post_status);
                    ?>
                    <tr data-duplicate-id="<?php echo (int) $copy->ID; ?>">
                        <td><strong><?php echo esc_html($copy->post_title ?: __('Untitled', 'wolf-forge-article-generator')); ?></strong><br><span class="description">#<?php echo (int) $copy->ID; ?> &middot; <?php echo esc_html(get_post_field('post_name', $copy->ID)); ?></span></td>
                        <td><?php if ($original_edit): ?><a href="<?php echo esc_url($original_edit); ?>"><?php echo esc_html($original_title); ?></a><?php else: ?><?php echo esc_html($original_title); ?><?php endif; ?><br><span class="description">#<?php echo (int) $copy->original_id; ?></span></td>
                        <td><?php echo esc_html(($type ? $type->labels->singular_name : $copy->post_type) . ' / ' . ($status ? $status->label : $copy->post_status)); ?></td>
                        <td><div class="wtai-duplicate-actions">
                            <?php if ($view): ?><a class="button" href="<?php echo esc_url($view); ?>" target="_blank" rel="noopener"><?php _e('View', 'wolf-forge-article-generator'); ?></a><?php endif; ?>
                            <?php if ($edit): ?><a class="button" href="<?php echo esc_url($edit); ?>"><?php _e('Edit', 'wolf-forge-article-generator'); ?></a><?php endif; ?>
                            <?php if (current_user_can('delete_post', $copy->ID)): ?>
                                <form class="wtai-duplicate-trash-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <input type="hidden" name="action" value="wtai_trash_duplicate">
                                    <input type="hidden" name="post_id" value="<?php echo (int) $copy->ID; ?>">
                                    <input type="hidden" name="duplicate_page" value="<?php echo (int) $duplicates['page']; ?>">
                                    <?php wp_nonce_field('wtai_trash_duplicate_' . $copy->ID); ?>
                                    <button type="submit" class="button wtai-trash-duplicate"><?php _e('Trash', 'wolf-forge-article-generator'); ?></button>
                                </form>
                            <?php endif; ?>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($duplicates['pages'] > 1): ?>
            <nav class="tablenav bottom" aria-label="<?php esc_attr_e('Duplicate blogs pagination', 'wolf-forge-article-generator'); ?>">
                <?php echo wp_kses_post(paginate_links(array('base' => str_replace('999999999', '%#%', add_query_arg('duplicate_page', 999999999, $url)), 'format' => '', 'current' => $duplicates['page'], 'total' => $duplicates['pages']))); ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
