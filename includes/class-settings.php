<?php
/** Shared defaults and validation for the import form and saved options. */
if (!defined('ABSPATH')) exit;

class WTAI_Settings {
    public static function defaults() {
        return array(
            'convert_numbered_to_dotted' => true,
            'unbold_except_headings' => true,
            'auto_link_phone_numbers' => true,
            'auto_insert_images' => true,
            'pixabay_fallback' => false,
            'image_source' => 'media_library',
            'gbp_profile_url' => '',
            'image_matching_method' => 'all', // Retained in older import fingerprints for safe retries.
            'max_images_per_post' => 3,
            'max_internal_links_per_post' => 5,
            'default_author' => get_current_user_id(),
            'default_category' => (int) get_option('default_category', 1),
            'publish_schedule' => 'immediate',
            'schedule_month' => '',
            'schedule_start_date' => '',
            'schedule_end_date' => '',
            'schedule_interval' => 'day',
        );
    }

    public static function sanitize($key, $value) {
        if (!is_scalar($value) && $value !== null) $value = '';
        switch ($key) {
            case 'gbp_profile_url':
                return substr(sanitize_text_field((string) $value), 0, 2048);
            case 'convert_numbered_to_dotted':
            case 'unbold_except_headings':
            case 'auto_link_phone_numbers':
            case 'auto_insert_images':
            case 'pixabay_fallback':
                return !empty($value);
            case 'max_images_per_post':
            case 'max_internal_links_per_post':
                return max(0, (int) $value);
            case 'default_author':
                $id = absint($value);
                return get_user_by('id', $id) && user_can($id, 'edit_posts') ? $id : get_current_user_id();
            case 'default_category':
                $id = absint($value);
                return term_exists($id, 'category') ? $id : (int) get_option('default_category', 1);
            case 'schedule_start_date':
            case 'schedule_end_date':
                $value = sanitize_text_field((string) $value);
                $date = DateTime::createFromFormat('!Y-m-d', $value, wp_timezone());
                return $date && $date->format('Y-m-d') === $value ? $value : '';
            case 'schedule_month':
                $value = sanitize_text_field((string) $value);
                $date = DateTime::createFromFormat('!Y-m', $value, wp_timezone());
                return $date && $date->format('Y-m') === $value ? $value : '';
        }
        $choices = array(
            'image_source' => array('media_library', 'gbp', 'both'),
            'image_matching_method' => array('all', 'title', 'description', 'both'),
            'publish_schedule' => array('specific_month', 'immediate', 'custom_range', 'specific_date'),
            'schedule_interval' => array('day', 'week', 'month'),
        );
        return isset($choices[$key]) && in_array($value, $choices[$key], true)
            ? $value : self::defaults()[$key];
    }

    public static function read() {
        $settings = array();
        foreach (self::defaults() as $key => $default) {
            $settings[$key] = self::sanitize($key, get_option('wtai_' . $key, $default));
        }
        return $settings;
    }

    public static function from_input($input) {
        $input = is_array($input) ? wp_unslash($input) : array();
        $settings = array();
        foreach (self::defaults() as $key => $default) {
            $settings[$key] = self::sanitize($key, isset($input[$key]) ? $input[$key] : $default);
        }
        return $settings;
    }

    public static function save($input) {
        $input = is_array($input) ? wp_unslash($input) : array();
        $updates = array();
        foreach (array_keys(self::defaults()) as $key) {
            if (array_key_exists($key, $input)) $updates[$key] = self::sanitize($key, $input[$key]);
        }
        foreach ($updates as $key => $value) {
            if (get_option('wtai_' . $key, null) === null) add_option('wtai_' . $key, $value);
            else update_option('wtai_' . $key, $value);
        }
        $stored = self::read();
        foreach ($updates as $key => $value) {
            if ($stored[$key] !== $value) return new WP_Error('wtai_settings_save_failed', __('Options could not be saved. Please retry.', 'wolf-forge-article-generator'));
        }
        return $stored;
    }

    public static function for_import($input) {
        $settings = self::read();
        $submitted = self::from_input($input);
        foreach (array_keys($settings) as $key) {
            if (is_array($input) && array_key_exists($key, $input)) $settings[$key] = $submitted[$key];
        }
        if ($settings['publish_schedule'] !== 'custom_range') $settings['schedule_end_date'] = '';
        if ($settings['publish_schedule'] !== 'specific_month') unset($settings['schedule_month']);
        if (in_array($settings['publish_schedule'], array('immediate', 'specific_month'), true)) {
            $settings['schedule_start_date'] = '';
            $settings['schedule_interval'] = 'day';
        }
        return $settings;
    }
}
