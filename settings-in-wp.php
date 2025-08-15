<?php
/**
 * WordPress Plugin Settings Integration
 * Ersetzt separate PHP-Datei für Settings durch WordPress-native Lösung
 */

class Ipn_Settings {
    
    private $plugin_slug;
    private $option_group;
    private $option_name;
    private $menu_slug;
    
    public function __construct($plugin_slug = 'ipn-adapter') {
        $this->plugin_slug = $plugin_slug;
        $this->option_group = $plugin_slug . '_settings';
        $this->option_name = $plugin_slug . '_options';
        $this->menu_slug = $plugin_slug . '-settings';
        
        // WordPress Hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        
        // AJAX-Handler für erweiterte Funktionen
        add_action('wp_ajax_reset_plugin_settings', array($this, 'ajax_reset_settings'));
        add_action('wp_ajax_export_plugin_settings', array($this, 'ajax_export_settings'));
        add_action('wp_ajax_import_plugin_settings', array($this, 'ajax_import_settings'));
    }
    
    /**
     * Admin-Menü hinzufügen
     */
    public function add_admin_menu() {
        add_options_page(
            'IPN-Adapter Settings',         // Page title
            'IPN-Adapter',                  // Menu title
            'manage_options',               // Capability
            $this->menu_slug,               // Menu slug
            array($this, 'options_page')    // Callback
        );
    }
    
    /**
     * Settings registrieren und Felder definieren
     */
    public function settings_init() {
        // Settings registrieren
        register_setting($this->option_group, $this->option_name, array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
        
        // Sektion 1: Allgemeine Einstellungen
        add_settings_section(
            'general_section',
            'Allgemeine Einstellungen',
            array($this, 'general_section_callback'),
            $this->menu_slug
        );
        
        add_settings_field(
            'enable_logging',
            'Logging aktivieren',
            array($this, 'checkbox_field_callback'),
            $this->menu_slug,
            'general_section',
            array(
                'field' => 'enable_logging',
                'description' => 'Aktiviert die Protokollierung von Plugin-Aktivitäten'
            )
        );
        
        add_settings_field(
            'log_level',
            'Log Level',
            array($this, 'select_field_callback'),
            $this->menu_slug,
            'general_section',
            array(
                'field' => 'log_level',
                'options' => array(
                    'error' => 'Error',
                    'warning' => 'Warning', 
                    'info' => 'Info',
                    'debug' => 'Debug'
                ),
                'description' => 'Minimales Level für Log-Einträge'
            )
        );
        
        add_settings_field(
            'max_log_size',
            'Maximale Log-Größe (MB)',
            array($this, 'number_field_callback'),
            $this->menu_slug,
            'general_section',
            array(
                'field' => 'max_log_size',
                'min' => 1,
                'max' => 100,
                'description' => 'Maximale Größe der Log-Datei in Megabyte'
            )
        );
        
        // Sektion 2: API Einstellungen
        add_settings_section(
            'api_section',
            'API Einstellungen',
            array($this, 'api_section_callback'),
            $this->menu_slug
        );
        
        add_settings_field(
            'api_key',
            'API Key',
            array($this, 'password_field_callback'),
            $this->menu_slug,
            'api_section',
            array(
                'field' => 'api_key',
                'description' => 'API-Schlüssel für externe Dienste'
            )
        );
        
        add_settings_field(
            'api_url',
            'API URL',
            array($this, 'url_field_callback'),
            $this->menu_slug,
            'api_section',
            array(
                'field' => 'api_url',
                'description' => 'Basis-URL für API-Anfragen'
            )
        );
        
        add_settings_field(
            'api_timeout',
            'API Timeout (Sekunden)',
            array($this, 'number_field_callback'),
            $this->menu_slug,
            'api_section',
            array(
                'field' => 'api_timeout',
                'min' => 5,
                'max' => 120,
                'description' => 'Timeout für API-Anfragen in Sekunden'
            )
        );
        
        // Sektion 3: Erweiterte Einstellungen
        add_settings_section(
            'advanced_section',
            'Erweiterte Einstellungen',
            array($this, 'advanced_section_callback'),
            $this->menu_slug
        );
        
        add_settings_field(
            'custom_css',
            'Benutzerdefiniertes CSS',
            array($this, 'textarea_field_callback'),
            $this->menu_slug,
            'advanced_section',
            array(
                'field' => 'custom_css',
                'rows' => 10,
                'description' => 'Benutzerdefinierte CSS-Regeln'
            )
        );
        
        add_settings_field(
            'allowed_user_roles',
            'Erlaubte Benutzerrollen',
            array($this, 'multiselect_field_callback'),
            $this->menu_slug,
            'advanced_section',
            array(
                'field' => 'allowed_user_roles',
                'options' => $this->get_user_roles(),
                'description' => 'Benutzerrollen die das Plugin verwenden dürfen'
            )
        );
    }
    
    /**
     * Settings-Seite anzeigen
     */
    public function options_page() {
        // Prüfen ob Benutzer berechtigt ist
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php
            // Success/Error Messages anzeigen
            if (isset($_GET['settings-updated'])) {
                add_settings_error('general', 'settings_updated', 'Einstellungen gespeichert!', 'updated');
            }
            settings_errors();
            ?>
            
            <div class="plugin-settings-container">
                <div class="settings-form">
                    <form action="options.php" method="post">
                        <?php
                        settings_fields($this->option_group);
                        do_settings_sections($this->menu_slug);
                        submit_button('Einstellungen speichern');
                        ?>
                    </form>
                </div>
                
                <div class="settings-sidebar">
                    <div class="postbox">
                        <h3><span>Plugin-Aktionen</span></h3>
                        <div class="inside">
                            <p>
                                <button type="button" class="button" id="reset-settings">
                                    Auf Standardwerte zurücksetzen
                                </button>
                            </p>
                            <p>
                                <button type="button" class="button" id="export-settings">
                                    Einstellungen exportieren
                                </button>
                            </p>
                            <p>
                                <input type="file" id="import-file" accept=".json" style="display: none;">
                                <button type="button" class="button" id="import-settings">
                                    Einstellungen importieren
                                </button>
                            </p>
                        </div>
                    </div>
                    
                    <div class="postbox">
                        <h3><span>Plugin-Informationen</span></h3>
                        <div class="inside">
                            <p><strong>Version:</strong> 1.0.0</p>
                            <p><strong>Aktuelle Einstellungen:</strong> <?php echo count($this->get_settings()); ?> Optionen</p>
                            <p><strong>Letzte Änderung:</strong> <?php echo date('d.m.Y H:i'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .plugin-settings-container {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
        
        .settings-form {
            flex: 1;
        }
        
        .settings-sidebar {
            width: 300px;
        }
        
        .postbox {
            background: #fff;
            border: 1px solid #c3c4c7;
            box-shadow: 0 1px 1px rgba(0,0,0,0.04);
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
        
        .form-table th {
            width: 200px;
        }
        
        .form-table td {
            padding-bottom: 20px;
        }
        
        .description {
            font-style: italic;
            color: #646970;
            margin-top: 5px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Reset Settings
            $('#reset-settings').click(function() {
                if (confirm('Sind Sie sicher, dass Sie alle Einstellungen auf die Standardwerte zurücksetzen möchten?')) {
                    $.post(ajaxurl, {
                        action: 'reset_plugin_settings',
                        nonce: '<?php echo wp_create_nonce('reset_settings_nonce'); ?>'
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Fehler beim Zurücksetzen der Einstellungen');
                        }
                    });
                }
            });
            
            // Export Settings
            $('#export-settings').click(function() {
                $.post(ajaxurl, {
                    action: 'export_plugin_settings',
                    nonce: '<?php echo wp_create_nonce('export_settings_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        var dataStr = JSON.stringify(response.data, null, 2);
                        var dataBlob = new Blob([dataStr], {type: 'application/json'});
                        var url = URL.createObjectURL(dataBlob);
                        var link = document.createElement('a');
                        link.href = url;
                        link.download = 'plugin-settings-' + new Date().toISOString().slice(0,10) + '.json';
                        link.click();
                    }
                });
            });
            
            // Import Settings
            $('#import-settings').click(function() {
                $('#import-file').click();
            });
            
            $('#import-file').change(function(e) {
                var file = e.target.files[0];
                if (file) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        try {
                            var settings = JSON.parse(e.target.result);
                            if (confirm('Möchten Sie diese Einstellungen importieren? Dies überschreibt die aktuellen Einstellungen.')) {
                                $.post(ajaxurl, {
                                    action: 'import_plugin_settings',
                                    settings: JSON.stringify(settings),
                                    nonce: '<?php echo wp_create_nonce('import_settings_nonce'); ?>'
                                }, function(response) {
                                    if (response.success) {
                                        location.reload();
                                    } else {
                                        alert('Fehler beim Importieren der Einstellungen');
                                    }
                                });
                            }
                        } catch (error) {
                            alert('Ungültige Einstellungsdatei');
                        }
                    };
                    reader.readAsText(file);
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Sektion Callbacks
     */
    public function general_section_callback() {
        echo '<p>Grundlegende Einstellungen für das Plugin.</p>';
    }
    
    public function api_section_callback() {
        echo '<p>Konfiguration für externe API-Verbindungen.</p>';
    }
    
    public function advanced_section_callback() {
        echo '<p>Erweiterte Konfigurationsoptionen.</p>';
    }
    
    /**
     * Feld Callbacks
     */
    public function checkbox_field_callback($args) {
        $settings = $this->get_settings();
        $field = $args['field'];
        $value = isset($settings[$field]) ? $settings[$field] : 0;
        
        printf(
            '<input type="checkbox" id="%1$s" name="%2$s[%1$s]" value="1" %3$s />',
            esc_attr($field),
            $this->option_name,
            checked(1, $value, false)
        );
        
        if (!empty($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    public function select_field_callback($args) {
        $settings = $this->get_settings();
        $field = $args['field'];
        $value = isset($settings[$field]) ? $settings[$field] : '';
        
        printf('<select id="%1$s" name="%2$s[%1$s]">', esc_attr($field), $this->option_name);
        
        foreach ($args['options'] as $option_value => $option_label) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr($option_value),
                selected($value, $option_value, false),
                esc_html($option_label)
            );
        }
        
        echo '</select>';
        
        if (!empty($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    public function number_field_callback($args) {
        $settings = $this->get_settings();
        $field = $args['field'];
        $value = isset($settings[$field]) ? $settings[$field] : '';
        
        printf(
            '<input type="number" id="%1$s" name="%2$s[%1$s]" value="%3$s" min="%4$s" max="%5$s" />',
            esc_attr($field),
            $this->option_name,
            esc_attr($value),
            isset($args['min']) ? esc_attr($args['min']) : '',
            isset($args['max']) ? esc_attr($args['max']) : ''
        );
        
        if (!empty($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    public function password_field_callback($args) {
        $settings = $this->get_settings();
        $field = $args['field'];
        $value = isset($settings[$field]) ? $settings[$field] : '';
        
        printf(
            '<input type="password" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" />',
            esc_attr($field),
            $this->option_name,
            esc_attr($value)
        );
        
        if (!empty($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    public function url_field_callback($args) {
        $settings = $this->get_settings();
        $field = $args['field'];
        $value = isset($settings[$field]) ? $settings[$field] : '';
        
        printf(
            '<input type="url" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" />',
            esc_attr($field),
            $this->option_name,
            esc_attr($value)
        );
        
        if (!empty($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    public function textarea_field_callback($args) {
        $settings = $this->get_settings();
        $field = $args['field'];
        $value = isset($settings[$field]) ? $settings[$field] : '';
        $rows = isset($args['rows']) ? $args['rows'] : 5;
        
        printf(
            '<textarea id="%1$s" name="%2$s[%1$s]" rows="%4$s" class="large-text">%3$s</textarea>',
            esc_attr($field),
            $this->option_name,
            esc_textarea($value),
            esc_attr($rows)
        );
        
        if (!empty($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    public function multiselect_field_callback($args) {
        $settings = $this->get_settings();
        $field = $args['field'];
        $values = isset($settings[$field]) ? (array) $settings[$field] : array();
        
        printf('<select id="%1$s" name="%2$s[%1$s][]" multiple size="5">', esc_attr($field), $this->option_name);
        
        foreach ($args['options'] as $option_value => $option_label) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr($option_value),
                in_array($option_value, $values) ? 'selected' : '',
                esc_html($option_label)
            );
        }
        
        echo '</select>';
        
        if (!empty($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    /**
     * Settings sanitization
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (!is_array($input)) {
            return $sanitized;
        }
        
        // Checkbox fields
        $checkbox_fields = array('enable_logging');
        foreach ($checkbox_fields as $field) {
            $sanitized[$field] = isset($input[$field]) ? 1 : 0;
        }
        
        // Text fields
        $text_fields = array('log_level', 'api_key');
        foreach ($text_fields as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = sanitize_text_field($input[$field]);
            }
        }
        
        // URL fields
        if (isset($input['api_url'])) {
            $sanitized['api_url'] = esc_url_raw($input['api_url']);
        }
        
        // Number fields
        $number_fields = array('max_log_size', 'api_timeout');
        foreach ($number_fields as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = absint($input[$field]);
            }
        }
        
        // Textarea fields
        if (isset($input['custom_css'])) {
            $sanitized['custom_css'] = wp_strip_all_tags($input['custom_css']);
        }
        
        // Array fields
        if (isset($input['allowed_user_roles']) && is_array($input['allowed_user_roles'])) {
            $sanitized['allowed_user_roles'] = array_map('sanitize_text_field', $input['allowed_user_roles']);
        }
        
        return $sanitized;
    }
    
    /**
     * Settings abrufen
     */
    public function get_settings() {
        return get_option($this->option_name, $this->get_default_settings());
    }
    
    /**
     * Einzelne Setting abrufen
     */
    public function get_setting($key, $default = null) {
        $settings = $this->get_settings();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
    
    /**
     * Default Settings
     */
    private function get_default_settings() {
        return array(
            'enable_logging' => 1,
            'log_level' => 'info',
            'max_log_size' => 10,
            'api_key' => '',
            'api_url' => '',
            'api_timeout' => 30,
            'custom_css' => '',
            'allowed_user_roles' => array('administrator')
        );
    }
    
    /**
     * User Roles abrufen
     */
    private function get_user_roles() {
        global $wp_roles;
        $roles = array();
        
        foreach ($wp_roles->roles as $role => $details) {
            $roles[$role] = $details['name'];
        }
        
        return $roles;
    }
    
    /**
     * AJAX: Settings zurücksetzen
     */
    public function ajax_reset_settings() {
        check_ajax_referer('reset_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        delete_option($this->option_name);
        wp_send_json_success();
    }
    
    /**
     * AJAX: Settings exportieren
     */
    public function ajax_export_settings() {
        check_ajax_referer('export_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $settings = $this->get_settings();
        wp_send_json_success($settings);
    }
    
    /**
     * AJAX: Settings importieren
     */
    public function ajax_import_settings() {
        check_ajax_referer('import_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $settings_json = sanitize_textarea_field($_POST['settings']);
        $settings = json_decode($settings_json, true);
        
        if (!is_array($settings)) {
            wp_send_json_error('Invalid settings format');
        }
        
        // Settings durch Sanitization-Funktion laufen lassen
        $sanitized_settings = $this->sanitize_settings($settings);
        update_option($this->option_name, $sanitized_settings);
        
        wp_send_json_success();
    }
}

// Plugin Settings initialisieren
add_action('plugins_loaded', function() {
    global $wp_plugin_settings;
    $wp_plugin_settings = new WP_Plugin_Settings('ipm-adapter');
});

// Beispiel für Verwendung der Settings in anderen Teilen des Plugins:
/*
global $wp_plugin_settings;

// Einzelne Setting abrufen
$logging_enabled = $wp_plugin_settings->get_setting('enable_logging', false);

// Alle Settings abrufen
$all_settings = $wp_plugin_settings->get_settings();

if ($logging_enabled) {
    // Logging Code ausführen
}
*/
?>