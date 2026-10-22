<?php
/** One WordPress sidebar entry, with tabs inside the plugin screen. */
if (!defined('ABSPATH')) exit;
$views = array('generate' => 'import-page.php', 'duplicates' => 'duplicate-pages.php', 'images' => 'image-media-page.php', 'settings' => 'settings-page.php');
$tab = isset($_GET['tab']) && is_string($_GET['tab']) && isset($views[$_GET['tab']]) ? $_GET['tab'] : 'generate';
$url = admin_url('admin.php?page=word-to-article-importer');
?>
<div class="wrap wtai-import-page">
    <h1><?php _e('Wolf Forge Article Generator', 'wolf-forge-article-generator'); ?> <span class="wtai-version"><?php echo esc_html(WTAI_VERSION); ?></span></h1>
    <p class="description"><?php _e('Turn Word documents into WordPress articles. Part of the Wolf Forge publishing tools.', 'wolf-forge-article-generator'); ?></p>
    <div id="wtai-version-notice" role="status" aria-live="polite" hidden></div>
    <nav class="nav-tab-wrapper wtai-tabs" aria-label="<?php esc_attr_e('Article generator', 'wolf-forge-article-generator'); ?>">
        <a class="nav-tab <?php echo $tab === 'generate' ? 'nav-tab-active' : ''; ?>" <?php echo $tab === 'generate' ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url($url); ?>"><?php _e('Generate Articles', 'wolf-forge-article-generator'); ?></a>
        <a class="nav-tab <?php echo $tab === 'duplicates' ? 'nav-tab-active' : ''; ?>" <?php echo $tab === 'duplicates' ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url(add_query_arg('tab', 'duplicates', $url)); ?>"><?php _e('Duplicate Blogs', 'wolf-forge-article-generator'); ?></a>
        <a class="nav-tab <?php echo $tab === 'images' ? 'nav-tab-active' : ''; ?>" <?php echo $tab === 'images' ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url(add_query_arg('tab', 'images', $url)); ?>"><?php _e('Image Media Library', 'wolf-forge-article-generator'); ?></a>
        <a class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>" <?php echo $tab === 'settings' ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url(add_query_arg('tab', 'settings', $url)); ?>"><?php _e('Settings & Logs', 'wolf-forge-article-generator'); ?></a>
    </nav>
    <?php foreach ($views as $panel => $view): ?>
        <div class="wtai-tab-panel" data-wtai-panel="<?php echo esc_attr($panel); ?>" <?php echo $panel !== $tab ? 'hidden' : ''; ?>>
            <?php require WTAI_PLUGIN_DIR . 'admin/views/' . $view; ?>
        </div>
    <?php endforeach; ?>
</div>
