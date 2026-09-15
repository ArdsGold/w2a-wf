<?php
/**
 * Error Handler Class
 * Handles error codes, logging, and user-friendly error messages
 */

if (!defined('ABSPATH')) {
    exit;
}

class WTAI_Error_Handler {
    
    // Error codes
    const ERR_FILE_NOT_FOUND = 'WTAI_001';
    const ERR_FILE_UPLOAD_FAILED = 'WTAI_002';
    const ERR_INVALID_FILE_TYPE = 'WTAI_003';
    const ERR_FILE_TOO_LARGE = 'WTAI_004';
    const ERR_DOCX_PARSE_FAILED = 'WTAI_005';
    const ERR_INVALID_DOCX_STRUCTURE = 'WTAI_006';
    const ERR_XML_PARSE_FAILED = 'WTAI_007';
    const ERR_TEMP_DIR_FAILED = 'WTAI_008';
    const ERR_CONTENT_PROCESSING_FAILED = 'WTAI_009';
    const ERR_IMAGE_MATCHING_FAILED = 'WTAI_010';
    const ERR_POST_CREATION_FAILED = 'WTAI_011';
    const ERR_PERMISSION_DENIED = 'WTAI_012';
    const ERR_MISSING_EXTENSION = 'WTAI_013';
    const ERR_MEMORY_LIMIT = 'WTAI_014';
    const ERR_EXECUTION_TIMEOUT = 'WTAI_015';
    const ERR_DATABASE_ERROR = 'WTAI_016';
    const ERR_INVALID_SETTINGS = 'WTAI_017';
    const ERR_SCHEDULE_CALCULATION_FAILED = 'WTAI_018';
    const ERR_MEDIA_LIBRARY_ACCESS = 'WTAI_019';
    const ERR_UNKNOWN_ERROR = 'WTAI_999';
    
    private $error_log = array();
    private $debug_mode = false;
    private $table_ready = false;
    
    public function __construct() {
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;
    }
    
    private static function get_error_catalog() {
        static $errors = null;
        if (null === $errors) {
            $errors = array(
            self::ERR_FILE_NOT_FOUND => array(
                'message' => __('File not found or inaccessible', 'word-to-article-importer'),
                'description' => __('The specified file could not be found or is not accessible. Please check the file path and permissions.', 'word-to-article-importer'),
                'severity' => 'error',
                'recoverable' => false
            ),
            self::ERR_FILE_UPLOAD_FAILED => array(
                'message' => __('File upload failed', 'word-to-article-importer'),
                'description' => __('The file could not be uploaded. This may be due to server configuration or file size limits.', 'word-to-article-importer'),
                'severity' => 'error',
                'recoverable' => true
            ),
            self::ERR_INVALID_FILE_TYPE => array(
                'message' => __('Invalid file type', 'word-to-article-importer'),
                'description' => __('Only .docx files are supported. Please ensure your file is in the correct format.', 'word-to-article-importer'),
                'severity' => 'error',
                'recoverable' => true
            ),
            self::ERR_FILE_TOO_LARGE => array(
                'message' => __('File size exceeds limit', 'word-to-article-importer'),
                'description' => __('The file is too large to upload. Please check your server upload limits or compress the file.', 'word-to-article-importer'),
                'severity' => 'error',
                'recoverable' => true
            ),
            self::ERR_DOCX_PARSE_FAILED => array(
                'message' => __('Document parsing failed', 'word-to-article-importer'),
                'description' => __('The .docx file could not be parsed. The file may be corrupted or use unsupported features.', 'word-to-article-importer'),
                'severity' => 'error',
                'recoverable' => false
            ),
            self::ERR_INVALID_DOCX_STRUCTURE => array(
                'message' => __('Invalid document structure', 'word-to-article-importer'),
                'description' => __('The .docx file has an invalid structure. It may not be a valid Word document.', 'word-to-article-importer'),
                'severity' => 'error',
                'recoverable' => false
            ),
            self::ERR_XML_PARSE_FAILED => array(
                'message' => __('XML parsing failed', 'word-to-article-importer'),
                'description' => __('The document XML could not be parsed. This may indicate file corruption.', 'word-to-article-importer'),
                'severity' => 'error',
                'recoverable' => false
            ),
            self::ERR_TEMP_DIR_FAILED => array(
                'message' => __('Temporary directory creation failed', 'word-to-article-importer'),
                'description' => __('Could not create temporary directory for file processing. Check server permissions.', 'word-to-article-importer'),
                'severity' => 'error',
                'recoverable' => true
            ),
            self::ERR_CONTENT_PROCESSING_FAILED => array(
                'message' => __('Content processing failed', 'word-to-article-importer'),
                'description' => __('The content could not be processed according to the specified settings.', 'word-to-article-importer'),
                'severity' => 'error',
                'recoverable' => true
            ),
            self::ERR_IMAGE_MATCHING_FAILED => array(
                'message' => __('Image matching failed', 'word-to-article-importer'),
                'description' => __('Could not match images from media library. The import will continue without images.', 'word-to-article-importer'),
                'severity' => 'warning',
                'recoverable' => true
            ),
            self::ERR_POST_CREATION_FAILED => array(
                'message' => __('Post creation failed', 'word-to-article-importer'),
                'description' => __('The WordPress post could not be created. Check user permissions and database status.', 'word-to-article-importer'),
                'severity' => 'error',
                'recoverable' => true
            ),
            self::ERR_PERMISSION_DENIED => array(
                'message' => __('Permission denied', 'word-to-article-importer'),
                'description' => __('You do not have sufficient permissions to perform this action.', 'word-to-article-importer'),
                'severity' => 'error',
                'recoverable' => false
            ),
            self::ERR_MISSING_EXTENSION => array(
                'message' => __('Required PHP extension missing', 'word-to-article-importer'),
                'description' => __('A required PHP extension is not loaded. Please contact your server administrator.', 'word-to-article-importer'),
                'severity' => 'error',
                'recoverable' => false
            ),
            self::ERR_MEMORY_LIMIT => array(
                'message' => __('Memory limit exceeded', 'word-to-article-importer'),
                'description' => __('The operation exceeded the PHP memory limit. Try increasing the memory limit or processing smaller files.', 'word-to-article-importer'),
                'severity' => 'error',
                'recoverable' => true
            ),
            self::ERR_EXECUTION_TIMEOUT => array(
                'message' => __('Execution timeout', 'word-to-article-importer'),
                'description' => __('The operation took too long and timed out. Try increasing the execution time limit or processing smaller files.', 'word-to-article-importer'),
                'severity' => 'error',
                'recoverable' => true
            ),
            self::ERR_DATABASE_ERROR => array(
                'message' => __('Database error', 'word-to-article-importer'),
                'description' => __('A database error occurred. Please check your database connection and try again.', 'word-to-article-importer'),
                'severity' => 'error',
                'recoverable' => true
            ),
            self::ERR_INVALID_SETTINGS => array(
                'message' => __('Invalid settings', 'word-to-article-importer'),
                'description' => __('The provided settings are invalid. Please check your configuration.', 'word-to-article-importer'),
                'severity' => 'error',
                'recoverable' => true
            ),
            self::ERR_SCHEDULE_CALCULATION_FAILED => array(
                'message' => __('Schedule calculation failed', 'word-to-article-importer'),
                'description' => __('Could not calculate the publishing schedule. Posts will be published immediately.', 'word-to-article-importer'),
                'severity' => 'warning',
                'recoverable' => true
            ),
            self::ERR_MEDIA_LIBRARY_ACCESS => array(
                'message' => __('Media library access failed', 'word-to-article-importer'),
                'description' => __('Could not access the media library. Image auto-insertion will be disabled.', 'word-to-article-importer'),
                'severity' => 'warning',
                'recoverable' => true
            ),
            self::ERR_UNKNOWN_ERROR => array(
                'message' => __('Unknown error occurred', 'word-to-article-importer'),
                'description' => __('An unexpected error occurred. Please try again or contact support.', 'word-to-article-importer'),
                'severity' => 'error',
                'recoverable' => true
            )
        );
        
        }
        return $errors;
    }

    public function get_error_info($error_code, $context = array()) {
        $errors = self::get_error_catalog();
        $error_info = isset($errors[$error_code]) ? $errors[$error_code] : $errors[self::ERR_UNKNOWN_ERROR];

        if (!empty($context)) {
            $error_info['context'] = $context;
        }

        if ($this->debug_mode) {
            $error_info['debug'] = array(
                'timestamp' => current_time('mysql'),
                'backtrace' => $this->get_backtrace(),
                'server_info' => $this->get_server_debug_info(),
            );
        }

        return $error_info;
    }

    /**
     * Get error information for an error code.
     *
     * @param string $error_code Error code.
     * @param array  $context   Additional context.
     * @return array Error information.
     */
    /**
     * Log an error
     * 
     * @param string $error_code Error code
     * @param array $context Additional context
     * @return bool Success status
     */
    public function log_error($error_code, $context = array()) {
        $error_info = $this->get_error_info($error_code, $context);
        
        $this->error_log[] = array(
            'code' => $error_code,
            'info' => $error_info,
            'timestamp' => current_time('mysql')
        );
        
        // Log to WordPress debug log if debug mode is enabled
        if ($this->debug_mode) {
            error_log(sprintf(
                '[WTAI] %s: %s | Context: %s',
                $error_code,
                $error_info['message'],
                json_encode($context)
            ));
        }
        
        // Log to database
        $this->log_to_database($error_code, $error_info);
        
        return true;
    }
    
    /**
     * Log error to database
     * 
     * @param string $error_code Error code
     * @param array $error_info Error information
     * @return bool Success status
     */
    private function log_to_database($error_code, $error_info) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wtai_error_log';
        $this->ensure_table();
        
        // Insert error log
        $result = $wpdb->insert(
            $table_name,
            array(
                'error_code' => $error_code,
                'error_message' => $error_info['message'],
                'error_context' => json_encode($error_info),
                'severity' => $error_info['severity']
            ),
            array('%s', '%s', '%s', '%s')
        );
        
        return $result !== false;
    }
    

    private function ensure_table() {
        if ($this->table_ready) return;
        global $wpdb;
        $table_name = $wpdb->prefix . 'wtai_error_log';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            error_code varchar(20) NOT NULL,
            error_message text NOT NULL,
            error_context longtext,
            severity varchar(20) NOT NULL DEFAULT 'error',
            created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY error_code (error_code),
            KEY created (created)
        ) {$charset_collate};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        $this->table_ready = true;
    }

    /**
     * Get recent error logs
     * 
     * @param int $limit Number of errors to retrieve
     * @return array Error logs
     */
    public function get_recent_errors($limit = 10) {
        global $wpdb;
        $this->ensure_table();
        
        $table_name = $wpdb->prefix . 'wtai_error_log';
        
        $errors = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY created DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
        
        return $errors;
    }
    
    /**
     * Clear error logs
     * 
     * @return bool Success status
     */
    public function clear_error_logs() {
        global $wpdb;
        $this->ensure_table();
        
        $table_name = $wpdb->prefix . 'wtai_error_log';
        $result = $wpdb->query("TRUNCATE TABLE $table_name");
        
        $this->error_log = array();
        
        return $result !== false;
    }
    
    /**
     * Get error statistics
     * 
     * @return array Error statistics
     */
    public function get_error_stats() {
        global $wpdb;
        $this->ensure_table();
        
        $table_name = $wpdb->prefix . 'wtai_error_log';
        
        $stats = array(
            'total' => 0,
            'by_code' => array(),
            'by_severity' => array(),
            'recent' => array()
        );
        
        // Total errors
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // By error code
        $by_code = $wpdb->get_results(
            "SELECT error_code, COUNT(*) as count FROM $table_name GROUP BY error_code",
            ARRAY_A
        );
        
        foreach ($by_code as $row) {
            $stats['by_code'][$row['error_code']] = $row['count'];
        }
        
        // By severity
        $by_severity = $wpdb->get_results(
            "SELECT severity, COUNT(*) as count FROM $table_name GROUP BY severity",
            ARRAY_A
        );
        
        foreach ($by_severity as $row) {
            $stats['by_severity'][$row['severity']] = $row['count'];
        }
        
        // Recent errors
        $stats['recent'] = $this->get_recent_errors(5);
        
        return $stats;
    }
    
    /**
     * Get backtrace for debugging
     * 
     * @return array Backtrace information
     */
    private function get_backtrace() {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        
        $formatted = array();
        foreach ($backtrace as $trace) {
            $formatted[] = array(
                'file' => isset($trace['file']) ? $trace['file'] : 'unknown',
                'line' => isset($trace['line']) ? $trace['line'] : 0,
                'function' => isset($trace['function']) ? $trace['function'] : 'unknown',
                'class' => isset($trace['class']) ? $trace['class'] : ''
            );
        }
        
        return $formatted;
    }
    
    /**
     * Get server debug information
     * 
     * @return array Server information
     */
    private function get_server_debug_info() {
        return array(
            'php_version' => phpversion(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'time' => microtime(true),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        );
    }
    
    /**
     * Format error for display
     * 
     * @param string $error_code Error code
     * @param array $context Additional context
     * @return string Formatted error message
     */
    public function format_error($error_code, $context = array()) {
        $error_info = $this->get_error_info($error_code, $context);
        
        $formatted = sprintf(
            '[%s] %s: %s',
            $error_code,
            $error_info['message'],
            $error_info['description']
        );
        
        if ($this->debug_mode && isset($error_info['debug'])) {
            $formatted .= "\n\nDebug Information:\n";
            $formatted .= "Timestamp: " . $error_info['debug']['timestamp'] . "\n";
            $formatted .= "Memory Usage: " . $this->format_bytes($error_info['debug']['server_info']['memory_usage']) . "\n";
        }
        
        return $formatted;
    }
    
    /**
     * Format bytes to human readable format
     * 
     * @param int $bytes Bytes
     * @return string Formatted size
     */
    private function format_bytes($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
    
    /**
     * Check if error is recoverable
     * 
     * @param string $error_code Error code
     * @return bool Recoverable status
     */
    public function is_recoverable($error_code) {
        $error_info = $this->get_error_info($error_code);
        return $error_info['recoverable'];
    }
    
    /**
     * Get error severity
     * 
     * @param string $error_code Error code
     * @return string Severity level
     */
    public function get_severity($error_code) {
        $error_info = $this->get_error_info($error_code);
        return $error_info['severity'];
    }
}
