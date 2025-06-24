<?php
/**
 * Plugin Name: Easy PHP Settings
 * Plugin URI:  https://github.com/easy-php-settings
 * Description: An easy way to manage common PHP INI settings from the WordPress admin panel.
 * Version:     1.0.0
 * Author:      H M Shahadul Islam
 * Author URI:  https://github.com/shahadul878
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: easy-php-settings
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/EasyPHPInfo.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/EasyIniFile.php';

class Easy_PHP_Settings {

    private $settings_keys = [
        'memory_limit',
        'upload_max_filesize',
        'post_max_size',
        'max_execution_time',
        'max_input_vars',
        'display_errors',
        'error_reporting'
    ];

    private $recommended_values = [
        'memory_limit' => '256M',
        'upload_max_filesize' => '128M',
        'post_max_size' => '256M',
        'max_execution_time' => '300',
        'max_input_vars' => '10000',
    ];

    private $version = '1.0.0';

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
        $hook = is_multisite() ? 'network_admin_menu' : 'admin_menu';
        add_action( $hook, [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'settings_init' ] );
        add_action( 'admin_init', [ $this, 'handle_ini_file_actions' ] );
        add_action( 'admin_init', [ $this, 'debugging_settings_init' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
        add_action( 'admin_init', [ $this, 'handle_log_actions' ] );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'easy-php-settings', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public function enqueue_styles($hook) {
        // Only load on our admin page
        if ( 'tools_page_easy-php-settings' !== $hook ) {
            return;
        }

        // Enqueue code editor and settings for php.ini files.
        $settings = wp_enqueue_code_editor( array( 'type' => 'text/x-ini' ) );
        if ( false !== $settings ) {
            wp_add_inline_script(
                'code-editor',
                sprintf( 'jQuery( function() { wp.codeEditor.initialize( "eps_settings_custom_php_ini", %s ); } );', wp_json_encode( $settings ) )
            );
        }

        wp_enqueue_style(
            'easy-php-settings-styles',
            plugin_dir_url( __FILE__ ) . 'css/admin-styles.css',
            [],
            '1.0.1'
        );

        // Enqueue admin.js and pass settings keys and strings
        wp_enqueue_script(
            'easy-php-settings-admin',
            plugin_dir_url( __FILE__ ) . 'js/admin.js',
            array('jquery'),
            '1.0.0',
            true
        );
        wp_localize_script(
            'easy-php-settings-admin',
            'epsSettingsKeys',
            $this->settings_keys
        );
        wp_localize_script(
            'easy-php-settings-admin',
            'epsAdminVars',
            array(
                'copiedText' => esc_html__('Copied to clipboard!', 'easy-php-settings'),
                'testCompleted' => esc_html__('Settings test completed. Check the Status tab for detailed information.', 'easy-php-settings'),
            )
        );
    }

    public function get_capability() {
        return is_multisite() ? 'manage_network_options' : 'manage_options';
    }

    public function get_option( $key, $default = false ) {
        return is_multisite() ? get_site_option( $key, $default ) : get_option( $key, $default );
    }

    public function update_option( $key, $value ) {
        return is_multisite() ? update_site_option( $key, $value ) : update_option( $key, $value );
    }

    public function delete_option( $key ) {
        return is_multisite() ? delete_site_option( $key ) : delete_option( $key );
    }

    public function add_admin_menu() {
        add_management_page(
            __( 'PHP Settings Manager', 'easy-php-settings' ),
            __( 'Easy PHP Settings', 'easy-php-settings' ),
            $this->get_capability(),
            'easy-php-settings',
            [ $this, 'options_page_html' ]
        );
    }

    public function settings_init() {
        register_setting( 'easy_php_settings', 'eps_settings', [ $this, 'sanitize_callback' ] );

        // Apply settings
        $this->apply_settings();
    }

    public function sanitize_callback( $input ) {
        $new_input = [];
        foreach ( $this->settings_keys as $key ) {
            if ( isset( $input[ $key ] ) ) {
                $new_input[ $key ] = sanitize_text_field( $input[ $key ] );
            }
        }
        // Save custom php.ini textarea
        if ( isset( $input['custom_php_ini'] ) ) {
            $new_input['custom_php_ini'] = trim( $input['custom_php_ini'] );
        }
        // Auto-generate configuration files when settings are saved
        if ( !empty( $new_input ) ) {
            $this->generate_config_files( $new_input );
        }
        return $new_input;
    }

    public function generate_config_files( $settings ) {
        // Generate .user.ini content from both regular and custom settings
	    $user_ini_content = "; PHP Settings generated by Easy PHP Settings plugin\n";
	    $user_ini_content .= "; Created By H M Shahadul Islam\n";
	    $user_ini_content .= "; Generated on: " . current_time( 'mysql' ) . "\n\n";

        foreach ( $settings as $key => $value ) {
            if ( in_array( $key, $this->settings_keys ) && !empty( $value ) ) {
                $user_ini_content .= "$key = $value\n";
            }
        }

        // Append custom php.ini config
        if ( !empty( $settings['custom_php_ini'] ) ) {
            $user_ini_content .= "\n; Custom php.ini directives\n" . $settings['custom_php_ini'] . "\n";
        }

        // Write to files using the new INIFile class
        $files_written = EasyIniFile::write( $user_ini_content );

        // Show success/error messages
        if ( !empty($files_written) ) {

            $message = sprintf(
            /* translators: %s: List of written INI file names. */
                    __('Settings saved and written to: %s. Please restart your web server for changes to take effect.', 'easy-php-settings'),
                implode(', ', $files_written)
            );
            add_settings_error( 'eps_settings', 'config_files_created', $message, 'updated' );
        } else {
            add_settings_error( 'eps_settings', 'config_files_error', __( 'Settings saved, but could not write to INI files. Please check file permissions.', 'easy-php-settings' ), 'warning' );
        }
    }

    public function handle_ini_file_actions() {
        if ( isset( $_POST['eps_delete_ini_files'] ) && check_admin_referer( 'eps_delete_ini_nonce' ) ) {
             if ( ! current_user_can( $this->get_capability() ) ) {
                return;
            }

            $files_deleted = EasyIniFile::remove_files();

            if ( !empty( $files_deleted ) ) {

                $message = sprintf(
                /* translators: %s: List of deleted INI file names. */
                    __('Successfully deleted: %s.', 'easy-php-settings'),
                    implode(', ', $files_deleted)
                );
                add_settings_error( 'eps_settings', 'files_deleted_success', $message, 'success' );
            } else {
                add_settings_error( 'eps_settings', 'files_deleted_error', __( 'Could not delete INI files. They may not exist or have permission issues.', 'easy-php-settings' ), 'warning' );
            }
        }
    }

    public function render_setting_field( $args ) {
        $options = $this->get_option( 'eps_settings' );
        $key = $args['key'];
        $value = isset( $options[ $key ] ) ? $options[ $key ] : '';
        $current_value = ini_get( $key );
        $all_settings = ini_get_all();

        // Settings are only changeable at runtime if their access level is INI_USER or INI_ALL.
        $access = $all_settings[$key]['access'] ?? 0;
        $is_changeable = ( $access === INI_USER || $access === INI_ALL );

        echo "<input type='text' name='eps_settings[" . esc_attr($key) . "]' value='" . esc_attr( $value ) . "' class='regular-text' placeholder='" . esc_attr( $this->recommended_values[$key] ?? '' ) . "'>";
        
        $this->render_status_indicator($key, $current_value);

        if ( !$is_changeable ) {
            echo '<p class="description" style="color: #d63638;">' . esc_html__( '⚠ This setting cannot be changed at runtime. Use the configuration generator below.', 'easy-php-settings' ) . '</p>';
            $this->show_alternative_instructions($key);
        }
    }

    private function render_status_indicator($key, $current_value) {
        /* translators: %s: Current PHP value */
        $description = sprintf( esc_html__( 'Current value: %s', 'easy-php-settings' ), esc_html( $current_value ) );
        if(isset($this->recommended_values[$key])) {
            $recommended_value_str = $this->recommended_values[$key];
            /* translators: %s: Recommended PHP value */
            $description .= sprintf( esc_html__( ' | Recommended: %s', 'easy-php-settings' ), esc_html( $recommended_value_str ) );
            
            // Convert values to bytes for comparison
            $current_val_bytes = $this->convert_to_bytes($current_value);
            $recommended_val_bytes = $this->convert_to_bytes($recommended_value_str);

            if($current_val_bytes < $recommended_val_bytes) {
                $description .= ' <span style="color: red;">' . esc_html__( '(Low)', 'easy-php-settings' ) . '</span>';
            } else {
                $description .= ' <span style="color: green;">' . esc_html__( '(OK)', 'easy-php-settings' ) . '</span>';
            }
        }
        echo '<p class="description">' . wp_kses_post( $description ) . '</p>';
    }
    
    private function convert_to_bytes($value) {
        $value = trim($value);
        $last = strtolower($value[strlen($value)-1]);
        $value = (int)$value;
        switch($last) {
            case 'g': $value *= 1024;
            case 'm': $value *= 1024;
            case 'k': $value *= 1024;
        }
        return $value;
    }
    
    private function show_alternative_instructions($key) {
        $server_api = php_sapi_name();
        $recommended_value = $this->recommended_values[$key] ?? '128M';
        
        $output = '<div style="background: #f9f9f9; padding: 10px; border-left: 4px solid #0073aa; margin: 10px 0;">';
        $output .= '<p style="margin: 0 0 10px 0; font-weight: bold; color: #0073aa;">' . esc_html__( 'Manual Configuration Required', 'easy-php-settings' ) . '</p>';
        $output .= '<p style="margin: 0 0 10px 0;">' . esc_html__( 'This setting requires server-level configuration. Choose one of the following methods:', 'easy-php-settings' ) . '</p>';
        
        // Method 1: .htaccess (Apache)
        if (strpos($server_api, 'apache') !== false || strpos($server_api, 'cgi') !== false) {
            $output .= '<div style="margin: 10px 0;">';
            $output .= '<strong>' . esc_html__('Method 1: .htaccess file', 'easy-php-settings') . '</strong><br/>';
            $output .= '<code style="background: #fff; padding: 5px; display: block; margin: 5px 0;">php_value ' . esc_html($key) . ' ' . esc_html($recommended_value) . '</code>';
            $output .= '</div>';
        }
        
        // Method 2: .user.ini
        $output .= '<div style="margin: 10px 0;">';
        $output .= '<strong>' . esc_html__('Method 2: .user.ini file', 'easy-php-settings') . '</strong><br/>';
        $output .= '<code style="background: #fff; padding: 5px; display: block; margin: 5px 0;">' . esc_html($key) . ' = ' . esc_html($recommended_value) . '</code>';
        $output .= '</div>';
        
        // Method 3: php.ini
        $output .= '<div style="margin: 10px 0;">';
        $output .= '<strong>' . esc_html__('Method 3: php.ini file', 'easy-php-settings') . '</strong><br/>';
        $output .= '<code style="background: #fff; padding: 5px; display: block; margin: 5px 0;">' . esc_html($key) . ' = ' . esc_html($recommended_value) . '</code>';
        $output .= '</div>';
        
        $output .= '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">' . esc_html__( 'Note: After making changes, restart your web server or contact your hosting provider.', 'easy-php-settings' ) . '</p>';
        $output .= '</div>';

        echo wp_kses_post( $output );
    }

    public function apply_settings() {
        $options = $this->get_option( 'eps_settings' );
        if ( ! empty( $options ) && is_array( $options ) ) {
            $all_settings = ini_get_all();
            foreach ( $options as $key => $value ) {
                if ( in_array( $key, $this->settings_keys ) && ! empty( $value ) ) {

                    // Settings are only changeable at runtime if their access level is INI_USER or INI_ALL.
                    $access = $all_settings[$key]['access'] ?? 0;
                    $is_changeable = ( $access === INI_USER || $access === INI_ALL );

                    if ( $is_changeable ) {
                        $old_value = ini_get($key);
                        // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
                        if ( @ini_set( $key, $value ) === false ) {
                            add_action( 'admin_notices', function() use ($key) {
                                ?>
                                <div class="notice notice-warning is-dismissible">
                                    <p><?php 
                                        /* translators: %s: Name of the PHP setting */
                                        echo wp_kses_post( sprintf( esc_html__( 'Could not set %s. The setting might be disabled by your hosting provider.', 'easy-php-settings' ), "<strong>" . esc_html( $key ) . "</strong>" ) ); 
                                    ?></p>
                                </div>
                                <?php
                            });
                        } else {
                            add_action( 'admin_notices', function() use ($key, $value, $old_value) {
                                ?>
                                <div class="notice notice-success is-dismissible">
                                    <p><?php 
                                        /* translators: 1: Name of the PHP setting, 2: Old value, 3: New value */
                                        echo wp_kses_post( sprintf( esc_html__( 'Successfully changed %1$s from %2$s to %3$s.', 'easy-php-settings' ), '<strong>' . esc_html( $key ) . '</strong>', esc_html( $old_value ), esc_html( $value ) ) ); 
                                    ?></p>
                                </div>
                                <?php
                            });
                        }
                    }
                }
            }
        }
    }

    public function options_page_html() {
        if ( ! current_user_can( $this->get_capability() ) ) {
            return;
        }

		$active_tab = 'general_settings';
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : null;
		if ( isset( $_GET['tab'] ) && wp_verify_nonce( $nonce, 'eps_tab_nonce' ) ) {
			$active_tab = sanitize_key( wp_unslash( $_GET['tab'] ) );
		}
		
		$tab_nonce_url = wp_create_nonce( 'eps_tab_nonce' );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <?php settings_errors(); ?>

            <h2 class="nav-tab-wrapper">
                <a href="?page=easy-php-settings&tab=general_settings&_wpnonce=<?php echo esc_attr( $tab_nonce_url ); ?>" class="nav-tab <?php echo $active_tab == 'general_settings' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('General Settings', 'easy-php-settings'); ?></a>
                <a href="?page=easy-php-settings&tab=debugging&_wpnonce=<?php echo esc_attr( $tab_nonce_url ); ?>" class="nav-tab <?php echo $active_tab == 'debugging' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Debugging', 'easy-php-settings'); ?></a>
                <a href="?page=easy-php-settings&tab=php_settings&_wpnonce=<?php echo esc_attr( $tab_nonce_url ); ?>" class="nav-tab <?php echo $active_tab == 'php_settings' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('PHP Settings', 'easy-php-settings'); ?></a>
                <a href="?page=easy-php-settings&tab=status&_wpnonce=<?php echo esc_attr( $tab_nonce_url ); ?>" class="nav-tab <?php echo $active_tab == 'status' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Status', 'easy-php-settings'); ?></a>
                <a href="?page=easy-php-settings&tab=log_viewer&_wpnonce=<?php echo esc_attr( $tab_nonce_url ); ?>" class="nav-tab <?php echo $active_tab == 'log_viewer' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Log Viewer', 'easy-php-settings'); ?></a>
            </h2>

            <?php if ( $active_tab == 'general_settings' ) : ?>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'easy_php_settings' );
                $options = $this->get_option( 'eps_settings' );
                ?>
                <div style="margin-bottom: 20px;">
                    <label for="eps_settings_custom_php_ini"><strong><?php esc_html_e('Custom php.ini Configuration', 'easy-php-settings'); ?></strong></label>
                    <textarea name="eps_settings[custom_php_ini]" id="eps_settings_custom_php_ini" rows="8" style="width:100%;font-family:monospace;" placeholder="; Example: \nmax_file_uploads = 50\nshort_open_tag = Off\n"><?php echo isset($options['custom_php_ini']) ? esc_textarea($options['custom_php_ini']) : ''; ?></textarea>
                    <p class="description"><?php esc_html_e('Add any custom php.ini directives here. These will be appended to the generated .user.ini and php.ini files.', 'easy-php-settings'); ?></p>
                </div>
                <?php
                do_settings_sections( 'easy_php_settings' );
                submit_button( 'Save Settings' );
                ?>
            </form>

            <form action="" method="post" style="margin-top: 20px;">
                <?php wp_nonce_field( 'eps_delete_ini_nonce' ); ?>
                <button type="submit" name="eps_delete_ini_files" class="button button-danger" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete the .user.ini and php.ini files created by this plugin?', 'easy-php-settings' ) ); ?>');">
                    <?php esc_html_e( 'Delete .ini Files', 'easy-php-settings' ); ?>
                </button>
                 <p class="description"><?php esc_html_e('This will remove the .user.ini and php.ini files from your WordPress root directory.', 'easy-php-settings'); ?></p>
            </form>
            
            <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-left: 4px solid #0073aa;">
                <h3><?php esc_html_e('Configuration Generator', 'easy-php-settings'); ?></h3>
                <p><?php esc_html_e('Generate server configuration files with your custom values:', 'easy-php-settings'); ?></p>
                <button type="button" id="generate-config" class="button button-primary"><?php esc_html_e('Generate Configuration Files', 'easy-php-settings'); ?></button>
                
                <div id="config-output" style="margin-top: 20px; display: none;">
                    <h4><?php esc_html_e('Generated Configuration Files:', 'easy-php-settings'); ?></h4>
                    
                    <div style="margin: 15px 0;">
                        <h5><?php esc_html_e('.user.ini file:', 'easy-php-settings'); ?></h5>
                        <textarea id="user-ini-content" style="width: 100%; height: 200px; font-family: monospace; background: #fff; padding: 10px;" readonly></textarea>
                        <button type="button" class="button button-secondary" onclick="copyToClipboard('user-ini-content')"><?php esc_html_e('Copy to Clipboard', 'easy-php-settings'); ?></button>
                    </div>
                    
                    <div style="margin: 15px 0;">
                        <h5><?php esc_html_e('Instructions:', 'easy-php-settings'); ?></h5>
                        <ol>
                            <li><?php esc_html_e('Copy the .user.ini content and save it as ".user.ini" in your WordPress root directory', 'easy-php-settings'); ?></li>
                            <li><?php esc_html_e('Or copy the .htaccess content and add it to your existing .htaccess file', 'easy-php-settings'); ?></li>
                            <li><?php esc_html_e('Restart your web server or contact your hosting provider', 'easy-php-settings'); ?></li>
                        </ol>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
                <h3><?php esc_html_e('Test Settings', 'easy-php-settings'); ?></h3>
                <p><?php esc_html_e('Click the button below to test if your current settings can be modified at runtime:', 'easy-php-settings'); ?></p>
                <button type="button" id="test-settings" class="button button-secondary"><?php esc_html_e('Test Settings', 'easy-php-settings'); ?></button>
                <div id="test-results" style="margin-top: 10px;"></div>
            </div>
            <?php elseif ( $active_tab == 'debugging' ) : ?>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'easy_php_settings_debugging' );
                do_settings_sections( 'easy_php_settings_debugging' );
                submit_button( __( 'Save Debugging Settings', 'easy-php-settings' ) );
                ?>
            </form>
            <?php elseif ( $active_tab == 'log_viewer' ) : ?>
                <?php $this->render_log_viewer_tab(); ?>
            <?php elseif ( $active_tab == 'status' ) : ?>
                <?php $this->render_status_tab(); ?>
            <?php elseif ( $active_tab == 'php_settings' ) : ?>
                <div id="php-settings-tab">
                    <h3><?php esc_html_e('PHP Settings Table', 'easy-php-settings'); ?></h3>
                    <div style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center;">
                        <input type="text" id="php-settings-search" placeholder="<?php esc_attr_e('Search for directives...', 'easy-php-settings'); ?>" style="min-width: 250px; padding: 5px;" />
                        <button type="button" class="button button-secondary" id="php-settings-copy-selected"><?php esc_html_e('Copy Selected', 'easy-php-settings'); ?></button>
                    </div>
                    <div id="php-settings-table-wrapper">
                        <?php
                        if ( class_exists('EasyPHPInfo') ) {
                            echo EasyPHPInfo::render('Core'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        } else {
                            echo '<p>' . esc_html__('PHPInfo class not found.', 'easy-php-settings') . '</p>';
                        }
                        ?>
                    </div>
                    <p style="margin-top: 10px; color: #666;"><em><?php esc_html_e('Tip: Use the search box to filter settings. Select rows and click "Copy Selected" to copy them to your clipboard.', 'easy-php-settings'); ?></em></p>
                </div>
                <script>
                (function(){
                    // Search filter
                    var searchInput = document.getElementById('php-settings-search');
                    var table = document.getElementById('phpinfo-table');
                    if (searchInput && table) {
                        searchInput.addEventListener('input', function() {
                            var filter = this.value.toLowerCase();
                            var rows = table.getElementsByTagName('tr');
                            for (var i = 1; i < rows.length; i++) { // skip header
                                var cells = rows[i].getElementsByTagName('td');
                                var match = false;
                                for (var j = 0; j < cells.length; j++) {
                                    if (cells[j].textContent.toLowerCase().indexOf(filter) > -1) {
                                        match = true;
                                        break;
                                    }
                                }
                                rows[i].style.display = match ? '' : 'none';
                            }
                        });
                    }
                    // Copy selected
                    var copyBtn = document.getElementById('php-settings-copy-selected');
                    if (copyBtn && table) {
                        copyBtn.addEventListener('click', function() {
                            var rows = table.querySelectorAll('tbody tr');
                            var output = '';
                            rows.forEach(function(row) {
                                var checkbox = row.querySelector('input[type="checkbox"]');
                                if (checkbox && checkbox.checked) {
                                    var key = row.querySelector('.value')?.closest('td')?.previousElementSibling?.textContent?.trim() || '';
                                    var value = row.querySelector('.value')?.textContent?.trim() || '';
                                    if (key && value) {
                                        output += key + ' = ' + value + '\n';
                                    }
                                }
                            });
                            if (output) {
                                var temp = document.createElement('textarea');
                                temp.value = output;
                                document.body.appendChild(temp);
                                temp.select();
                                document.execCommand('copy');
                                document.body.removeChild(temp);
                                alert('<?php esc_html_e('Copied to clipboard!', 'easy-php-settings'); ?>');
                            } else {
                                alert('<?php esc_html_e('No rows selected.', 'easy-php-settings'); ?>');
                            }
                        });
                    }
                })();
                </script>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_log_viewer_tab() {
        global $wp_filesystem;
        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        ?>
        <div id="log-viewer-tab">
            <h3><?php esc_html_e('Debug Log Viewer', 'easy-php-settings'); ?></h3>
            <?php
            if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
                printf(
                    '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
                    esc_html__( 'WP_DEBUG_LOG is not enabled. To use the log viewer, please enable it from the Debugging tab.', 'easy-php-settings' )
                );
                return;
            }

            $log_file = WP_CONTENT_DIR . '/debug.log';
            $log_content = '';

            if ( $wp_filesystem->exists( $log_file ) && $wp_filesystem->is_readable( $log_file ) ) {
                $log_content = $wp_filesystem->get_contents( $log_file );
                if ( empty( $log_content ) ) {
                    echo '<div class="notice notice-info"><p>' . esc_html__( 'The debug log file is empty.', 'easy-php-settings' ) . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-info"><p>' . esc_html__( 'The debug log file does not exist. It will be created when errors are logged.', 'easy-php-settings' ) . '</p></div>';
            }
            ?>
            
            <form method="post" style="margin-bottom: 15px;">
                <?php wp_nonce_field( 'eps_clear_log_nonce' ); ?>
                <input type="submit" name="eps_clear_log" class="button button-danger" value="<?php esc_attr_e( 'Clear Log File', 'easy-php-settings' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to permanently delete the debug log?', 'easy-php-settings' ) ); ?>');">
            </form>

            <textarea id="eps-log-viewer" style="width: 100%; height: 500px; font-family: monospace;"><?php echo esc_textarea( $log_content ); ?></textarea>
        </div>
        <?php
    }

    public function render_status_tab() {
        $all_settings = ini_get_all();
        global $wpdb;

        $db_version = $wpdb->db_version();
        $db_software = 'MySQL';
        if ( strpos( strtolower( $db_version ), 'mariadb' ) !== false ) {
            $db_software = 'MariaDB';
        }

        $server_info = [
            'Server Software' => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'N/A',
            'Server IP' => isset($_SERVER['SERVER_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR'])) : 'N/A',
            'PHP Version' => phpversion(),
            'WordPress Version' => get_bloginfo('version'),
            'Database Software' => $db_software,
            'Database Version' => $db_version,
            'Server API' => php_sapi_name(),
        ];
        ?>
        <div id="status-tab">
            <h3><?php esc_html_e('PHP Configuration Status', 'easy-php-settings'); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Setting', 'easy-php-settings'); ?></th>
                        <th scope="col"><?php esc_html_e('Current Value', 'easy-php-settings'); ?></th>
                        <th scope="col"><?php esc_html_e('Recommended', 'easy-php-settings'); ?></th>
                        <th scope="col"><?php esc_html_e('Changeable', 'easy-php-settings'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->settings_keys as $key) : 
                        $current_value = ini_get($key);
                        $recommended_value = $this->recommended_values[$key] ?? 'N/A';
                        $access = $all_settings[$key]['access'] ?? 0;
                        $is_changeable = ( $access === INI_USER || $access === INI_ALL );
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></strong></td>
                        <td><?php echo esc_html($current_value); ?></td>
                        <td><?php echo esc_html($recommended_value); ?></td>
                        <td>
                            <?php if ($is_changeable) : ?>
                                <span style="color: green;"><?php esc_html_e('Yes', 'easy-php-settings'); ?></span>
                            <?php else : ?>
                                <span style="color: red;"><?php esc_html_e('No', 'easy-php-settings'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3 style="margin-top: 30px;"><?php esc_html_e('Server Status', 'easy-php-settings'); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Metric', 'easy-php-settings'); ?></th>
                        <th scope="col"><?php esc_html_e('Value', 'easy-php-settings'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($server_info as $metric => $value) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($metric); ?></strong></td>
                        <td><?php echo esc_html($value); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function debugging_settings_init() {
        register_setting( 'easy_php_settings_debugging', 'eps_debugging_settings', [ $this, 'update_wp_config_constants' ] );

        add_settings_section(
            'eps_debugging_section',
            __( 'Debugging Constants', 'easy-php-settings' ),
            function() {
                echo '<p>' . esc_html__( 'Control WordPress debugging constants defined in wp-config.php.', 'easy-php-settings' ) . '</p>';
            },
            'easy_php_settings_debugging'
        );

        add_settings_field(
            'wp_debug',
            'WP_DEBUG',
            [ $this, 'render_debugging_field' ],
            'easy_php_settings_debugging',
            'eps_debugging_section',
            [ 'name' => 'wp_debug' ]
        );
        add_settings_field(
            'wp_debug_log',
            'WP_DEBUG_LOG',
            [ $this, 'render_debugging_field' ],
            'easy_php_settings_debugging',
            'eps_debugging_section',
            [ 'name' => 'wp_debug_log' ]
        );
        add_settings_field(
            'wp_debug_display',
            'WP_DEBUG_DISPLAY',
            [ $this, 'render_debugging_field' ],
            'easy_php_settings_debugging',
            'eps_debugging_section',
            [ 'name' => 'wp_debug_display' ]
        );
        add_settings_field(
            'script_debug',
            'SCRIPT_DEBUG',
            [ $this, 'render_debugging_field' ],
            'easy_php_settings_debugging',
            'eps_debugging_section',
            [ 'name' => 'script_debug' ]
        );
    }
    
    public function is_constant_defined($constant) {
        return defined($constant) && constant($constant);
    }
    
    public function render_debugging_field($args) {
        $name = $args['name'];
        $is_defined = $this->is_constant_defined(strtoupper($name));
        $is_disabled = ($name !== 'wp_debug' && !$this->is_constant_defined('WP_DEBUG'));
        
        $html = '<label class="switch">';
        $html .= '<input type="checkbox" name="eps_debugging_settings[' . esc_attr($name) . ']" value="1" ' . checked(1, $is_defined, false) . ' ' . disabled($is_disabled, true, false) . '>';
        $html .= '<span class="slider round"></span>';
        $html .= '</label>';
        
        echo wp_kses( $html, [
            'label' => [ 'class' => [] ],
            'input' => [ 
                'type' => [], 'name' => [], 'value' => [], 
                'checked' => [], 'disabled' => [] 
            ],
            'span' => [ 'class' => [] ],
        ] );
    }

    public function update_wp_config_constants($input) {
        global $wp_filesystem;
        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $config_path = ABSPATH . 'wp-config.php';
        if ( !$wp_filesystem->is_writable($config_path) ) {
            add_settings_error('eps_debugging_settings', 'config_not_writable', __('wp-config.php is not writable.', 'easy-php-settings'), 'error');
            return get_option('eps_debugging_settings');
        }

        $config_content = $wp_filesystem->get_contents($config_path);
        $constants = ['WP_DEBUG', 'WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY', 'SCRIPT_DEBUG'];
        
        $new_options = get_option('eps_debugging_settings', []);
        
        foreach($constants as $const) {
            $key = strtolower($const);
            $value = isset($input[$key]) ? 'true' : 'false';

            if (preg_match('/define\(\s*\''.$const.'\'\s*,\s*(true|false)\s*\);/i', $config_content)) {
                $config_content = preg_replace('/define\(\s*\''.$const.'\'\s*,\s*(true|false)\s*\);/i', "define( '$const', $value );", $config_content);
            } else {
                $config_content = str_replace("/* That's all, stop editing!", "define( '$const', $value );\n\n/* That's all, stop editing!", $config_content);
            }

            $new_options[$key] = $value === 'true';
        }
        
        $wp_filesystem->put_contents($config_path, $config_content);

        add_settings_error('eps_debugging_settings', 'settings_updated', __('Debugging settings updated successfully.', 'easy-php-settings'), 'updated');
        
        return $new_options;
    }

    public function handle_log_actions() {
        if ( isset( $_POST['eps_clear_log'] ) ) {
            check_admin_referer( 'eps_clear_log_nonce' );

            if ( ! current_user_can( $this->get_capability() ) ) {
                return;
            }

            global $wp_filesystem;
            if ( ! $wp_filesystem ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }

            $log_file = WP_CONTENT_DIR . '/debug.log';

            if ( $wp_filesystem->exists( $log_file ) && $wp_filesystem->is_writable( $log_file ) ) {
                $wp_filesystem->put_contents( $log_file, '' );
                add_settings_error( 'eps_settings', 'log_cleared', __( 'Debug log file cleared successfully.', 'easy-php-settings' ), 'updated' );
            } else {
                add_settings_error( 'eps_settings', 'log_clear_error', __( 'Could not clear debug log file. Check file permissions.', 'easy-php-settings' ), 'error' );
            }
        }
    }
}

new Easy_PHP_Settings(); 