<?php
/**
 * Manage Settings in a separate PHP-file for access without WP-methods
 */


class Settings_Manager {
    
    private $plugin_slug;
    private $settings_file_path;
    private $menu_slug;
    
    public function __construct($plugin_slug = 'ipn-adapter') {
        $this->plugin_slug = $plugin_slug;
        $this->menu_slug = $plugin_slug;
        
        $settings_dir = "";
        if(function_exists("wp_upload_dir")) {
            $upload_dir = wp_upload_dir();
            $settings_dir = $upload_dir['basedir'] . '/' . $plugin_slug . '/';
        }
        else if (is_dir("../../uploads/". $plugin_slug))
        {
            $settings_dir = "../../uploads/". $plugin_slug . "/";
        }

        $this->settings_file_path = $settings_dir . 'ipn-config.php';
    }

    public function init_wp_hooks() {
        // WordPress Hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_form_submission'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX Handlers
        add_action('wp_ajax_add_product_mapping', array($this, 'ajax_add_product_mapping'));
        add_action('wp_ajax_remove_product_mapping', array($this, 'ajax_remove_product_mapping'));
        
        $this->ensure_settings_directory();
    }
    
    /**
     * Add to Admin-Menu
     */
    public function add_admin_menu() {
        add_options_page(
            'IPN Adapter Settings',              // Page title
            'IPN Adapter',                       // Menu title
            'manage_options',                    // Capability
            $this->menu_slug,                    // Menu slug
            array($this, 'settings_page')        // Callback
        );
    }
    
    /**
     * Add Admin Scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, $this->menu_slug) === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
    }
    
    /**
     * Ensure that the Settings-folder exists
     */
    private function ensure_settings_directory() {
        $dir = dirname($this->settings_file_path);
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        
        // create template file if not exists
        if (!file_exists($this->settings_file_path)) {
            $this->save_settings_to_file($this->get_default_settings());
        }
    }
    
    /**
     * Read Settings
     */
    public function load_settings_from_file() {
        if (!file_exists($this->settings_file_path)) {
            return $this->get_default_settings();
        }
        
        include $this->settings_file_path;
        
        return isset($settings) ? $settings : $this->get_default_settings();
    }
    
    /**
     * Save Settings
     */
    private function save_settings_to_file($settings) {
        $php_content = "<?php\n";
        $php_content .= "\t// IPN adapter settings\n";
        $php_content .= "\t// Generated on: " . date('Y-m-d H:i:s') . "\n";
        $php_content .= "\t// DO NOT EDIT MANUALLY - Use WordPress Admin Interface\n";
        $php_content .= "\t\$settings = [\n";
        
        foreach ($settings as $key => $value) {
            if (is_numeric($value)) {
                $php_content .= "\t\t\"$key\" => $value,\n";
            } else {
                $escaped_value = addslashes($value);
                $php_content .= "\t\t\"$key\" => \"$escaped_value\",\n";
            }
        }
        
        $php_content .= "\t];\n";
        $php_content .= "?>";
        
        return file_put_contents($this->settings_file_path, $php_content, LOCK_EX) !== false;
    }
    
    /**
     * Default-Settings
     */
    private function get_default_settings() {
        return array(
            'NEWSLETTER_LIST_ID' => 1,
            'DIGISTORE_SECRET' => '',
            'BREVO_SECRET' => '',
            'BREVO_FIRSTNAME' => 'VORNAME',
            'BREVO_LASTNAME' => 'NACHNAME'
        );
    }
    
    /**
     * Extract Product-Mappings
     */
    private function get_product_mappings($settings) {
        $mappings = array();
        
        foreach ($settings as $key => $value) {
            if (preg_match('/^PRODUCT_LIST_ID_(\d+)$/', $key, $matches)) {
                $product_id = $matches[1];
                $mappings[$product_id] = $value;
            }
        }
        
        return $mappings;
    }
    
    /**
     * Add Product-Mappings to Settings
     */
    private function integrate_product_mappings($settings, $mappings) {
        // remove old product-mappings
        foreach (array_keys($settings) as $key) {
            if (preg_match('/^PRODUCT_LIST_ID_\d+$/', $key)) {
                unset($settings[$key]);
            }
        }
        
        // add new product-mappings
        foreach ($mappings as $product_id => $list_id) {
            $settings["PRODUCT_LIST_ID_$product_id"] = (int)$list_id;
        }
        
        return $settings;
    }
    
    /**
     * Process Formular-Submission
     */
    public function handle_form_submission() {
        if (!isset($_POST['proxy_settings_nonce']) || 
            !wp_verify_nonce($_POST['proxy_settings_nonce'], 'save_proxy_settings')) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $settings = $this->get_default_settings();
        
        // Newsletter List ID
        if (isset($_POST['newsletter_list_id'])) {
            $settings['NEWSLETTER_LIST_ID'] = max(1, (int)$_POST['newsletter_list_id']);
        }

        // Firstname field name
        if (isset($_POST['brevo_firstname'])) {
            $settings['BREVO_FIRSTNAME'] = $_POST['brevo_firstname'];
        }
        
        // Lastname field name
        if (isset($_POST['brevo_lastname'])) {
            $settings['BREVO_LASTNAME'] = $_POST['brevo_lastname'];
        }
        
        // Secret Keys
        $secret_fields = array('digistore_secret', 'brevo_secret');
        foreach ($secret_fields as $field) {
            if (isset($_POST[$field])) {
                $key = strtoupper($field);
                $settings[$key] = sanitize_text_field($_POST[$field]);
            }
        }
        
        // process product-mappings
        $product_mappings = array();
        for ($idx = 1; $idx < 999; $idx++){
            if (isset($_POST['product_mappings_product_id'.$idx]) && isset($_POST['product_mappings_list_id'.$idx])) {
                $product_id = (int)$_POST['product_mappings_product_id'.$idx];
                $list_id = (int)$_POST['product_mappings_list_id'.$idx];
                
                if ($product_id > 0 && $list_id > 0) {
                    $product_mappings[$product_id] = $list_id;
                }
            }
        }
        
        // add product-mappings
        $settings = $this->integrate_product_mappings($settings, $product_mappings);
        
        // Save to file
        if ($this->save_settings_to_file($settings)) {
            wp_redirect(add_query_arg('settings-updated', 'true', wp_get_referer()));
            exit;
        } else {
            wp_redirect(add_query_arg('settings-error', 'true', wp_get_referer()));
            exit;
        }
    }
    
    /**
     * Show Settings-page
     */
    public function settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $settings = $this->load_settings_from_file();
        $product_mappings = $this->get_product_mappings($settings);
        
        ?>
        <div class="wrap">
            <h1>IPN Adapter Settings</h1>
            
            <?php
            // Success/Error Messages
            if (isset($_GET['settings-updated'])) {
                echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
            } elseif (isset($_GET['settings-error'])) {
                echo '<div class="notice notice-error is-dismissible"><p>Error while saving the settings!</p></div>';
            }
            ?>
            
            <div class="proxy-settings-container">
                <div class="settings-main">
                    <form method="post" action="">
                        <?php wp_nonce_field('save_proxy_settings', 'proxy_settings_nonce'); ?>
                        
                        <h2>Digistore24 Settings</h2>
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="">Adapter endpoint for Digistore</label>
                                    </th>
                                    <td>
                                        <code>POST</code>&nbsp;<code><?php echo get_site_url()."/wp-content/plugins/ipn-adapter/endpoint.php";?></code>
                                        <a style='text-decoration: none;' href='javascript:navigator.clipboard.writeText("<?php echo get_site_url()."/wp-content/plugins/ipn-adapter/endpoint.php";?>")'><span class="dashicons dashicons-clipboard"></span></a>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="digistore_secret">Digistore Secret</label>
                                    </th>
                                    <td>
                                        <input type="password" 
                                               id="digistore_secret" 
                                               name="digistore_secret" 
                                               value="<?php echo esc_attr($settings['DIGISTORE_SECRET']); ?>" 
                                               class="regular-text" />
                                        <p class="description">Password for Digistore-Integration</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <h2>Brevo Settings</h2>
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="brevo_secret">Brevo API Key</label>
                                    </th>
                                    <td>
                                        <input type="password" 
                                               id="brevo_secret" 
                                               name="brevo_secret" 
                                               value="<?php echo esc_attr($settings['BREVO_SECRET']); ?>" 
                                               class="regular-text" />
                                        <p class="description">API-Key from Brevo</p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="brevo_firstname">Brevo Firstname Field</label>
                                    </th>
                                    <td>
                                        <input type="text" 
                                               id="brevo_firstname" 
                                               name="brevo_firstname" 
                                               value="<?php echo esc_attr($settings['BREVO_FIRSTNAME']); ?>" 
                                               class="regular-text" />
                                        <p class="description">Brevo fieldname for Firstname</p>
                                    </td>
                                </tr>                                

                                <tr>
                                    <th scope="row">
                                        <label for="brevo_lastname">Brevo Lastname Field</label>
                                    </th>
                                    <td>
                                        <input type="text" 
                                               id="brevo_lastname" 
                                               name="brevo_lastname" 
                                               value="<?php echo esc_attr($settings['BREVO_LASTNAME']); ?>" 
                                               class="regular-text" />
                                        <p class="description">Brevo fieldname for Lastname</p>
                                    </td>
                                </tr> 

                                <tr>
                                    <th scope="row">
                                        <label for="newsletter_list_id">Newsletter List ID</label>
                                    </th>
                                    <td>
                                        <input type="number" 
                                               id="newsletter_list_id" 
                                               name="newsletter_list_id" 
                                               value="<?php echo esc_attr($settings['NEWSLETTER_LIST_ID']); ?>" 
                                               min="1" 
                                               required />
                                        <p class="description">Positiv Integer value for Newsletter list</p>
                                    </td>
                                </tr>
                                
                            </tbody>
                        </table>
                        
                        <h4>Product ID Mapping</h4>
                        <p>Link your products with specific Brevo lists:</p>
                        
                        <div id="product-mappings">
                            <?php $idx = 1; foreach ($product_mappings as $product_id => $list_id): ?>
                            <div class="product-mapping-row">
                                <label>Product ID:</label>
                                <input type="number" 
                                       name="product_mappings_product_id<?php echo strval($idx);?>"
                                       value="<?php echo esc_attr($product_id); ?>" 
                                       min="1" 
                                       required />
                                <label>→ List ID:</label>
                                <input type="number" 
                                       name="product_mappings_list_id<?php echo strval($idx);$idx++;?>"
                                       value="<?php echo esc_attr($list_id); ?>" 
                                       min="1" 
                                       required />
                                <button type="button" class="button remove-mapping">Remove</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <p>
                            <button type="button" id="add-product-mapping" class="button">Add new Mapping</button>
                        </p>
                        
                        <?php submit_button('Save Settings'); ?>
                    </form>
                </div>
            </div>
        </div>
        
        <style>
        .proxy-settings-container {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
        
        .settings-main {
            flex: 1;
        }
        
        .settings-sidebar {
            width: 300px;
        }
        
        .postbox {
            background: #fff;
            border: 1px solid #c3c4c7;
            box-shadow: 0 1px 1px rgba(0,0,0,0.04);
            margin-bottom: 20px;
        }
        
        .postbox h3 {
            margin: 0;
            padding: 8px 12px;
            background: #f6f7f7;
            border-bottom: 1px solid #c3c4c7;
        }
        
        .postbox .inside {
            padding: 12px;
        }
        
        .product-mapping-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        
        .product-mapping-row input[type="number"] {
            width: 100px;
        }
        
        .product-mapping-row label {
            font-weight: bold;
        }
        
        .remove-mapping {
            background: #dc3232;
            border-color: #dc3232;
            color: white;
        }
        
        .remove-mapping:hover {
            background: #a02020;
            border-color: #a02020;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // add product mapping
            $('#add-product-mapping').click(function() {
                var rowCount = $('.product-mapping-row input[name*="product_mappings_product_id"]').length;
                rowCount++;
                var html = '<div class="product-mapping-row">' +
                    '<label>Product ID:</label>' +
                    '<input type="number" name="product_mappings_product_id'+rowCount+'" min="1" required />' +
                    '<label>→ List ID:</label>' +
                    '<input type="number" name="product_mappings_list_id'+rowCount+'" min="1" required />' +
                    '<button type="button" class="button remove-mapping">Remove</button>' +
                    '</div>';
                $('#product-mappings').append(html);
            });
            
            // remove product mapping
            $(document).on('click', '.remove-mapping', function() {
                $(this).closest('.product-mapping-row').remove();
            });
        });
        </script>
        <?php
    }
}

?>