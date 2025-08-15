<?php
/**
 * Log Viewer functionality for WordPress Plugin
 * Add this code to your main plugin file or create a separate log-viewer.php file
 */

class Log_Viewer {
    
    private $log_file_path;
    private $plugin_slug;
    
    public function __construct($plugin_slug = 'ipn-adapter') {
        $this->plugin_slug = $plugin_slug;
        
        // Set log file path - you can customize this
        $upload_dir = wp_upload_dir();
        $this->log_file_path = $upload_dir['basedir'] . '/' . $plugin_slug . '/' . $plugin_slug . '.log';
        
    }

    public function init_wp_hooks() {
        // Initialize hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_refresh_log', array($this, 'ajax_refresh_log'));
        add_action('wp_ajax_clear_log', array($this, 'ajax_clear_log'));
        add_action('wp_ajax_download_log', array($this, 'ajax_download_log'));
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_submenu_page(
            'tools.php',                    // Parent menu (Tools)
            'Plugin Log Viewer',            // Page title
            'View IPN Logs',                // Menu title
            'manage_options',               // Required capability
            $this->plugin_slug . '-logs',   // Menu slug
            array($this, 'display_log_page') // Callback function
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our log viewer page
        if (strpos($hook, $this->plugin_slug . '-logs') === false) {
            return;
        }
        
        // Add inline CSS for log viewer
        wp_add_inline_style('wp-admin', $this->get_log_viewer_css());
        
        // Add inline JavaScript
        wp_add_inline_script('jquery', $this->get_log_viewer_js());
    }
    
    /**
     * Display the log viewer page
     */
    public function display_log_page() {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $log_content = $this->get_log_content();
        $log_size = $this->get_log_file_size();
        $last_modified = $this->get_log_last_modified();
        
        ?>
        <div class="wrap">
            <h1>Plugin Log Viewer</h1>
            
            <div class="log-viewer-controls">
                <button id="refresh-log" class="button button-secondary">Refresh Log</button>
                <button id="clear-log" class="button button-secondary" onclick="return confirm('Are you sure you want to clear the log file?');">Clear Log</button>
                <button id="download-log" class="button button-secondary">Download Log</button>
                
                <div class="log-info">
                    <span><strong>File Size:</strong> <span id="log-size"><?php echo esc_html($log_size); ?></span></span>
                    <span><strong>Last Modified:</strong> <span id="last-modified"><?php echo esc_html($last_modified); ?></span></span>
                </div>
            </div>
            
            <div id="log-container">
                <textarea id="log-content" readonly><?php echo esc_textarea($log_content); ?></textarea>
            </div>
            
            <div id="log-loading" style="display: none;">
                <p>Loading log content...</p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get log file content
     */
    private function get_log_content() {
        if (!file_exists($this->log_file_path)) {
            return 'Log file not found. No logs have been generated yet.';
        }
        
        // Read last 1000 lines to prevent memory issues with large log files
        $lines = $this->tail($this->log_file_path, 1000);
        return implode("\n", array_reverse($lines));
    }
    
    /**
     * Get last N lines of a file (similar to tail command)
     */
    private function tail($file, $lines = 1000) {
        $handle = fopen($file, 'r');
        if (!$handle) {
            return array();
        }
        
        $linecounter = $lines;
        $pos = -2;
        $beginning = false;
        $text = array();
        
        while ($linecounter > 0) {
            $t = ' ';
            while ($t != "\n") {
                if (fseek($handle, $pos, SEEK_END) == -1) {
                    $beginning = true;
                    break;
                }
                $t = fgetc($handle);
                $pos--;
            }
            $linecounter--;
            if ($beginning) {
                rewind($handle);
            }
            $text[$lines - $linecounter - 1] = fgets($handle);
            if ($beginning) break;
        }
        
        fclose($handle);
        return array_reverse($text);
    }
    
    /**
     * Get log file size
     */
    private function get_log_file_size() {
        if (!file_exists($this->log_file_path)) {
            return '0 B';
        }
        
        $size = filesize($this->log_file_path);
        return $this->format_bytes($size);
    }
    
    /**
     * Get log file last modified date
     */
    private function get_log_last_modified() {
        if (!file_exists($this->log_file_path)) {
            return 'Never';
        }
        
        return date('Y-m-d H:i:s', filemtime($this->log_file_path));
    }
    
    /**
     * Format bytes to human readable format
     */
    private function format_bytes($size, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
    
    /**
     * AJAX handler for refreshing log content
     */
    public function ajax_refresh_log() {
        // Check permissions and nonce
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('log_viewer_nonce', 'nonce');
        
        $response = array(
            'content' => $this->get_log_content(),
            'size' => $this->get_log_file_size(),
            'last_modified' => $this->get_log_last_modified()
        );
        
        wp_send_json_success($response);
    }
    
    /**
     * AJAX handler for clearing log file
     */
    public function ajax_clear_log() {
        // Check permissions and nonce
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('log_viewer_nonce', 'nonce');
        
        if (file_exists($this->log_file_path)) {
            file_put_contents($this->log_file_path, '');
        }
        
        wp_send_json_success(array(
            'message' => 'Log file cleared successfully',
            'content' => '',
            'size' => '0 B',
            'last_modified' => date('Y-m-d H:i:s')
        ));
    }
    
    /**
     * AJAX handler for downloading log file
     */
    public function ajax_download_log() {
        // Check permissions and nonce
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('log_viewer_nonce', 'nonce');
        
        if (!file_exists($this->log_file_path)) {
            wp_die('Log file not found');
        }
        
        // Set headers for file download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $this->plugin_slug . '-log-' . date('Y-m-d-H-i-s') . '.txt"');
        header('Content-Length: ' . filesize($this->log_file_path));
        
        // Output file content
        readfile($this->log_file_path);
        exit;
    }
    
    /**
     * CSS for log viewer
     */
    private function get_log_viewer_css() {
        return '
        .log-viewer-controls {
            margin-bottom: 20px;
            padding: 10px;
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .log-viewer-controls .button {
            margin-right: 10px;
        }
        
        .log-info {
            margin-top: 10px;
        }
        
        .log-info span {
            margin-right: 20px;
        }
        
        #log-container {
            position: relative;
        }
        
        #log-content {
            width: 100%;
            height: 500px;
            font-family: "Courier New", Courier, monospace;
            font-size: 12px;
            background: #2b2b2b;
            color: #ffffff;
            border: 1px solid #ccc;
            padding: 10px;
            resize: vertical;
        }
        
        #log-loading {
            text-align: center;
            padding: 20px;
        }
        ';
    }
    
    /**
     * JavaScript for log viewer
     */
    private function get_log_viewer_js() {
        $nonce = wp_create_nonce('log_viewer_nonce');
        $ajax_url = admin_url('admin-ajax.php');
        
        return "
        jQuery(document).ready(function($) {
            // Refresh log
            $('#refresh-log').click(function() {
                $('#log-loading').show();
                $('#log-container').hide();
                
                $.post('{$ajax_url}', {
                    action: 'refresh_log',
                    nonce: '{$nonce}'
                }, function(response) {
                    if (response.success) {
                        $('#log-content').val(response.data.content);
                        $('#log-size').text(response.data.size);
                        $('#last-modified').text(response.data.last_modified);
                    }
                    $('#log-loading').hide();
                    $('#log-container').show();
                });
            });
            
            // Clear log
            $('#clear-log').click(function() {
                if (!confirm('Are you sure you want to clear the log file?')) {
                    return;
                }
                
                $.post('{$ajax_url}', {
                    action: 'clear_log',
                    nonce: '{$nonce}'
                }, function(response) {
                    if (response.success) {
                        $('#log-content').val('');
                        $('#log-size').text(response.data.size);
                        $('#last-modified').text(response.data.last_modified);
                        alert('Log file cleared successfully');
                    }
                });
            });
            
            // Download log
            $('#download-log').click(function() {
                window.location.href = '{$ajax_url}?action=download_log&nonce={$nonce}';
            });
            
            // Auto-scroll to bottom of log
            var textarea = document.getElementById('log-content');
            textarea.scrollTop = textarea.scrollHeight;
        });
        ";
    }
    
    /**
     * Helper method to write to log file (use this in your plugin)
     */
    public function write_log($message, $level = 'INFO') {
        $log_dir = dirname($this->log_file_path);
        
        // Create log directory if it doesn't exist
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;
        
        file_put_contents($this->log_file_path, $log_entry, FILE_APPEND | LOCK_EX);
    }
}

// Example usage for logging (call this from anywhere in your plugin):
// $wp_plugin_log_viewer->write_log('Plugin initialized successfully');
// $wp_plugin_log_viewer->write_log('Error occurred while processing data', 'ERROR');
?>