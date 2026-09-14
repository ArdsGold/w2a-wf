<?php
/**
 * Server Information Class
 * Handles server information display and upload limits
 */

if (!defined('ABSPATH')) {
    exit;
}

class WTAI_Server_Info {
    
    /**
     * Get server information
     * 
     * @return array Server information
     */
    public function get_server_info() {
        return array(
            'php_version' => phpversion(),
            'wordpress_version' => get_bloginfo('version'),
            'mysql_version' => $this->get_mysql_version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_input_vars' => ini_get('max_input_vars'),
            'timezone' => date_default_timezone_get(),
            'wordpress_timezone' => get_option('timezone_string') ?: 'UTC',
            'active_plugins' => count(get_option('active_plugins', array())),
            'theme' => wp_get_theme()->get('Name'),
        );
    }
    
    /**
     * Get MySQL version
     * 
     * @return string MySQL version
     */
    private function get_mysql_version() {
        global $wpdb;
        return $wpdb->get_var("SELECT VERSION()");
    }
    
    /**
     * Get upload limits information
     * 
     * @return array Upload limits
     */
    public function get_upload_limits() {
        $upload_max_filesize = $this->convert_to_bytes(ini_get('upload_max_filesize'));
        $post_max_size = $this->convert_to_bytes(ini_get('post_max_size'));
        $memory_limit = $this->convert_to_bytes(ini_get('memory_limit'));
        
        // Effective limit is the minimum of these
        $effective_limit = min($upload_max_filesize, $post_max_size, $memory_limit);
        
        return array(
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'effective_limit' => $this->convert_to_mb($effective_limit),
            'effective_limit_bytes' => $effective_limit,
            'max_execution_time' => ini_get('max_execution_time'),
            'is_sufficient' => $effective_limit >= 10 * 1024 * 1024, // At least 10MB
        );
    }
    
    /**
     * Convert PHP ini value to bytes
     * 
     * @param string $value PHP ini value (e.g., '10M', '256M')
     * @return int Value in bytes
     */
    private function convert_to_bytes($value) {
        $value = trim((string) $value);
        if ($value === '' || $value === '-1') return PHP_INT_MAX;
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        switch ($unit) {
            case 'g': $number *= 1024;
            case 'm': $number *= 1024;
            case 'k': $number *= 1024;
        }
        return (int) $number;
    }
    
    /**
     * Convert bytes to MB
     * 
     * @param int $bytes Value in bytes
     * @return string Value in MB
     */
    private function convert_to_mb($bytes) {
        return round($bytes / 1024 / 1024, 2) . ' MB';
    }
    
    /**
     * Check PHP extensions
     * 
     * @return array Extension status
     */
    public function check_extensions() {
        $required_extensions = array(
            'zip' => 'Preferred for .docx parsing; WordPress PclZip is used as a fallback when unavailable',
            'xml' => 'Required for document processing',
            'mbstring' => 'Recommended for text processing',
            'fileinfo' => 'Recommended for file type detection',
        );
        
        $results = array();
        
        foreach ($required_extensions as $extension => $description) {
            $results[$extension] = array(
                'loaded' => extension_loaded($extension),
                'description' => $description,
                'required' => 'xml' === $extension,
                'fallback_available' => 'zip' === $extension && class_exists('PclZip'),
            );
        }
        
        return $results;
    }
    
    /**
     * Get system status
     * 
     * @return array System status
     */
    public function get_system_status() {
        $upload_limits = $this->get_upload_limits();
        $extensions = $this->check_extensions();
        
        $status = array(
            'overall' => 'good',
            'issues' => array(),
            'warnings' => array()
        );
        
        // Check upload limits
        if (!$upload_limits['is_sufficient']) {
            $status['overall'] = 'warning';
            $status['warnings'][] = 'Upload limit is less than 10MB. Large files may fail to upload.';
        }
        
        // Check required extensions
        foreach ($extensions as $ext => $info) {
            if ('zip' === $ext && !$info['loaded']) {
                if (!empty($info['fallback_available'])) {
                    $status['warnings'][] = 'PHP ZIP is not loaded; DOCX imports will use WordPress PclZip instead.';
                } else {
                    $status['overall'] = 'error';
                    $status['issues'][] = 'Neither PHP ZIP nor WordPress PclZip is available for DOCX parsing.';
                }
            } elseif ($info['required'] && !$info['loaded']) {
                $status['overall'] = 'error';
                $status['issues'][] = "Required PHP extension '{$ext}' is not loaded: {$info['description']}";
            } elseif (!$info['loaded']) {
                $status['warnings'][] = "Recommended PHP extension '{$ext}' is not loaded: {$info['description']}";
            }
        }
        
        // Check memory limit
        $memory_limit = $this->convert_to_bytes(ini_get('memory_limit'));
        if ($memory_limit < 64 * 1024 * 1024) { // Less than 64MB
            $status['overall'] = 'warning';
            $status['warnings'][] = 'Memory limit is low. Consider increasing it for better performance.';
        }
        
        return $status;
    }
    
    /**
     * Render server information HTML
     * 
     * @return string HTML output
     */
    public function render_server_info() {
        $server_info = $this->get_server_info();
        $upload_limits = $this->get_upload_limits();
        $extensions = $this->check_extensions();
        $system_status = $this->get_system_status();
        
        ob_start();
        ?>
        <div class="wtai-server-info">
            <h3><?php _e('Server Information', 'word-to-article-importer'); ?></h3>
            
            <?php if ($system_status['overall'] === 'error'): ?>
                <div class="wtai-status-message error">
                    <strong><?php _e('Critical Issues Found:', 'word-to-article-importer'); ?></strong>
                    <ul>
                        <?php foreach ($system_status['issues'] as $issue): ?>
                            <li><?php echo esc_html($issue); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php elseif ($system_status['overall'] === 'warning'): ?>
                <div class="wtai-status-message info">
                    <strong><?php _e('Warnings:', 'word-to-article-importer'); ?></strong>
                    <ul>
                        <?php foreach ($system_status['warnings'] as $warning): ?>
                            <li><?php echo esc_html($warning); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="wtai-status-message success">
                    <strong><?php _e('System Status: Good', 'word-to-article-importer'); ?></strong>
                </div>
            <?php endif; ?>
            
            <h4><?php _e('Upload Limits', 'word-to-article-importer'); ?></h4>
            <table class="wtai-info-table">
                <tr>
                    <td><?php _e('Maximum File Size', 'word-to-article-importer'); ?></td>
                    <td><?php echo esc_html($upload_limits['upload_max_filesize']); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Maximum POST Size', 'word-to-article-importer'); ?></td>
                    <td><?php echo esc_html($upload_limits['post_max_size']); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Memory Limit', 'word-to-article-importer'); ?></td>
                    <td><?php echo esc_html($upload_limits['memory_limit']); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('Effective Limit', 'word-to-article-importer'); ?></strong></td>
                    <td><strong><?php echo esc_html($upload_limits['effective_limit']); ?></strong></td>
                </tr>
                <tr>
                    <td><?php _e('Maximum Execution Time', 'word-to-article-importer'); ?></td>
                    <td><?php echo esc_html($upload_limits['max_execution_time']); ?>s</td>
                </tr>
            </table>
            
            <h4><?php _e('PHP Extensions', 'word-to-article-importer'); ?></h4>
            <table class="wtai-info-table">
                <?php foreach ($extensions as $ext => $info): ?>
                    <tr>
                        <td><?php echo esc_html($ext); ?></td>
                        <td>
                            <?php if ($info['loaded']): ?>
                                <span class="wtai-status-good"><?php _e('Loaded', 'word-to-article-importer'); ?></span>
                            <?php else: ?>
                                <span class="wtai-status-bad"><?php _e('Not Loaded', 'word-to-article-importer'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($info['description']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            
            <h4><?php _e('System Details', 'word-to-article-importer'); ?></h4>
            <table class="wtai-info-table">
                <tr>
                    <td><?php _e('PHP Version', 'word-to-article-importer'); ?></td>
                    <td><?php echo esc_html($server_info['php_version']); ?></td>
                </tr>
                <tr>
                    <td><?php _e('WordPress Version', 'word-to-article-importer'); ?></td>
                    <td><?php echo esc_html($server_info['wordpress_version']); ?></td>
                </tr>
                <tr>
                    <td><?php _e('MySQL Version', 'word-to-article-importer'); ?></td>
                    <td><?php echo esc_html($server_info['mysql_version']); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Server Software', 'word-to-article-importer'); ?></td>
                    <td><?php echo esc_html($server_info['server_software']); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Active Plugins', 'word-to-article-importer'); ?></td>
                    <td><?php echo esc_html($server_info['active_plugins']); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Active Theme', 'word-to-article-importer'); ?></td>
                    <td><?php echo esc_html($server_info['theme']); ?></td>
                </tr>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
}
