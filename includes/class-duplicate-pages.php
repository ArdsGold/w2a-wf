<?php
/** Live duplicate-content lookup; original posts are never listed as copies. */
if (!defined('ABSPATH')) exit;

class WTAI_Duplicate_Pages {
    private static function matches_sql() {
        global $wpdb;
        // Group once in SQL rather than loading every article body into PHP.
        // Exact content is deliberate: similar titles or numbered slugs alone
        // do not make a page safe to classify as a duplicate.
        return "FROM {$wpdb->posts} AS copy
            INNER JOIN (
                SELECT SHA2(post_content, 256) AS content_hash, MIN(ID) AS original_id
                FROM {$wpdb->posts}
                WHERE post_type = 'post'
                    AND post_status IN ('publish', 'future', 'draft', 'pending', 'private')
                    AND TRIM(post_content) <> ''
                GROUP BY SHA2(post_content, 256)
                HAVING COUNT(*) > 1
            ) AS duplicates ON SHA2(copy.post_content, 256) = duplicates.content_hash
            WHERE copy.ID <> duplicates.original_id
                AND copy.post_type = 'post'
                AND copy.post_status IN ('publish', 'future', 'draft', 'pending', 'private')";
    }

    public static function listing($page = 1) {
        global $wpdb;
        $matches = self::matches_sql();
        $total = (int) $wpdb->get_var('SELECT COUNT(*) ' . $matches);
        if ($wpdb->last_error) return new WP_Error('wtai_duplicates_query', __('Duplicate posts could not be loaded.', 'wolf-forge-article-generator'));
        $pages = max(1, (int) ceil($total / 20));
        $page = min($pages, max(1, (int) $page));
        $items = $wpdb->get_results($wpdb->prepare(
            'SELECT copy.ID, copy.post_title, copy.post_type, copy.post_status, copy.post_date, duplicates.original_id '
            . $matches . ' ORDER BY copy.ID DESC LIMIT 20 OFFSET %d',
            ($page - 1) * 20
        ));
        if ($wpdb->last_error) return new WP_Error('wtai_duplicates_query', __('Duplicate posts could not be loaded.', 'wolf-forge-article-generator'));
        return array('items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page);
    }

    public static function trash($post_id) {
        global $wpdb;
        $post_id = absint($post_id);
        if (!current_user_can('manage_options') || !current_user_can('delete_post', $post_id)) {
            return new WP_Error('wtai_duplicate_permission', __('You cannot trash this item.', 'wolf-forge-article-generator'));
        }
        if (defined('EMPTY_TRASH_DAYS') && (int) EMPTY_TRASH_DAYS === 0) {
            return new WP_Error('wtai_trash_disabled', __('WordPress Trash is disabled. Enable it before moving duplicates to Trash.', 'wolf-forge-article-generator'));
        }
        $duplicate = $wpdb->get_var($wpdb->prepare('SELECT copy.ID ' . self::matches_sql() . ' AND copy.ID = %d LIMIT 1', $post_id));
        if ($wpdb->last_error) return new WP_Error('wtai_duplicates_query', __('The duplicate could not be verified. Please retry.', 'wolf-forge-article-generator'));
        if (!$duplicate) return new WP_Error('wtai_not_duplicate', __('This item is no longer a duplicate. Refresh the list before trying again.', 'wolf-forge-article-generator'));
        if (!wp_trash_post($post_id)) return new WP_Error('wtai_duplicate_trash', __('The duplicate could not be moved to Trash.', 'wolf-forge-article-generator'));
        return true;
    }
}
