<?php
/** Admin settings. */
if (!defined('ABSPATH')) exit;

class WTAI_Admin_Page {
    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings() {
        $settings = array(
            'convert_numbered_to_dotted' => array('sanitize_callback' => array($this, 'sanitize_bool')),
            'unbold_except_headings' => array('sanitize_callback' => array($this, 'sanitize_bool')),
            'auto_insert_images' => array('sanitize_callback' => array($this, 'sanitize_bool')),
            'image_matching_method' => array('sanitize_callback' => array($this, 'sanitize_match_method')),
            'max_images_per_post' => array('sanitize_callback' => array($this, 'sanitize_max_images')),
            'default_author' => array('sanitize_callback' => array($this, 'sanitize_author')),
            'default_category' => array('sanitize_callback' => array($this, 'sanitize_category')),
            'publish_schedule' => array('sanitize_callback' => array($this, 'sanitize_schedule')),
            'schedule_start_date' => array('sanitize_callback' => array($this, 'sanitize_date')),
            'schedule_end_date' => array('sanitize_callback' => array($this, 'sanitize_date')),
            'schedule_interval' => array('sanitize_callback' => array($this, 'sanitize_interval')),
        );
        foreach ($settings as $key => $args) register_setting('wtai_settings', 'wtai_' . $key, $args);
    }

    public function sanitize_bool($value) { return empty($value) ? 0 : 1; }
    public function sanitize_match_method($value) { return in_array($value, array('title','description','both'), true) ? $value : 'title'; }
    public function sanitize_max_images($value) { return min(10, max(0, absint($value))); }
    public function sanitize_author($value) { $id = absint($value); return get_user_by('id', $id) && user_can($id, 'edit_posts') ? $id : get_current_user_id(); }
    public function sanitize_category($value) { $id = absint($value); return term_exists($id, 'category') ? $id : (int) get_option('default_category', 1); }
    public function sanitize_schedule($value) { return in_array($value, array('immediate','custom_range','specific_date'), true) ? $value : 'immediate'; }
    public function sanitize_date($value) {
        $value = sanitize_text_field($value);
        $date = DateTime::createFromFormat('!Y-m-d', $value, wp_timezone());
        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }
    public function sanitize_interval($value) { return in_array($value, array('day','week','month'), true) ? $value : 'day'; }

    public function get_authors() {
        return get_users(array('capability' => 'edit_posts', 'orderby' => 'display_name', 'order' => 'ASC'));
    }
    public function get_categories() { return get_categories(array('hide_empty' => false, 'orderby' => 'name')); }
    public function get_settings() {
        return array(
            'convert_numbered_to_dotted' => (bool) get_option('wtai_convert_numbered_to_dotted', false),
            'unbold_except_headings' => (bool) get_option('wtai_unbold_except_headings', false),
            'auto_insert_images' => (bool) get_option('wtai_auto_insert_images', false),
            'image_matching_method' => get_option('wtai_image_matching_method', 'title'),
            'max_images_per_post' => (int) get_option('wtai_max_images_per_post', 3),
            'default_author' => (int) get_option('wtai_default_author', get_current_user_id()),
            'default_category' => (int) get_option('wtai_default_category', get_option('default_category', 1)),
            'publish_schedule' => get_option('wtai_publish_schedule', 'immediate'),
            'schedule_start_date' => get_option('wtai_schedule_start_date', ''),
            'schedule_end_date' => get_option('wtai_schedule_end_date', ''),
            'schedule_interval' => get_option('wtai_schedule_interval', 'day'),
        );
    }
}
