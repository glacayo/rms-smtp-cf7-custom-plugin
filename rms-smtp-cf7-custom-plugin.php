<?php
/**
 * Plugin Name: RMS SMTP for Contact Form 7
 * Plugin URI: https://github.com/RMS-Dev/smtp-cf7
 * Description: Secure SMTP integration for Contact Form 7. Configure SMTP settings from the Tools menu.
 * Version: 1.0.0
 * Author: RMS Development
 * Author URI: https://github.com/RMS-Dev
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rms-smtp-cf7
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Network: false
 * 
 * @package RMS_SMTP_CF7
 */

// Security: Prevent direct access
defined('ABSPATH') || exit;

// Security: Define plugin constants
define('RMS_SMTP_CF7_VERSION', '1.0.0');
define('RMS_SMTP_CF7_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RMS_SMTP_CF7_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RMS_SMTP_CF7_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Security: Minimum WordPress version check
if (version_compare($GLOBALS['wp_version'], '5.8', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        esc_html_e('RMS SMTP for Contact Form 7 requires WordPress 5.8 or later.', 'rms-smtp-cf7');
        echo '</p></div>';
    });
    return;
}

// Security: Check if Contact Form 7 is active
add_action('admin_init', function() {
    if (!class_exists('WPCF7')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-warning"><p>';
            esc_html_e('RMS SMTP for Contact Form 7 works best with Contact Form 7 plugin active.', 'rms-smtp-cf7');
            echo '</p></div>';
        });
    }
});

/**
 * Main plugin class
 */
final class RMS_SMTP_CF7_Plugin {
    
    /**
     * Single instance of the plugin
     * @var RMS_SMTP_CF7_Plugin|null
     */
    private static $instance = null;

    /**
     * Flag to bypass SMTP for diagnostic tests
     * @var bool
     */
    private $bypass_smtp = false;

    /**
     * Get single instance
     * @return RMS_SMTP_CF7_Plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Load text domain
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        
        // Initialize admin
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // SMTP hooks
        add_action('phpmailer_init', [$this, 'configure_smtp']);
        
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Plugin action links
        add_filter('plugin_action_links_' . RMS_SMTP_CF7_PLUGIN_BASENAME, [$this, 'add_action_links']);
        
        // AJAX handlers
        add_action('wp_ajax_rms_smtp_cf7_test', [$this, 'handle_test_connection']);
        add_action('wp_ajax_rms_smtp_cf7_diagnostic', [$this, 'handle_diagnostic']);
    }
    
    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain('rms-smtp-cf7', false, dirname(RMS_SMTP_CF7_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Add admin menu under Tools
     */
    public function add_admin_menu() {
        add_management_page(
            esc_html__('RMS SMTP Settings', 'rms-smtp-cf7'),
            esc_html__('RMS SMTP', 'rms-smtp-cf7'),
            'manage_options',
            'rms-smtp-cf7',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Enqueue admin assets
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        if ('tools_page_rms-smtp-cf7' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'rms-smtp-cf7-admin',
            RMS_SMTP_CF7_PLUGIN_URL . 'assets/css/admin.css',
            [],
            RMS_SMTP_CF7_VERSION
        );
        
        wp_enqueue_script(
            'rms-smtp-cf7-admin',
            RMS_SMTP_CF7_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            RMS_SMTP_CF7_VERSION,
            true
        );
        
        wp_localize_script('rms-smtp-cf7-admin', 'rmsSmtpCf7', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rms_smtp_cf7_test'),
            'testing' => esc_html__('Testing connection...', 'rms-smtp-cf7'),
            'success' => esc_html__('Connection successful!', 'rms-smtp-cf7'),
            'error' => esc_html__('Connection failed. Please check your settings.', 'rms-smtp-cf7'),
        ]);

        wp_localize_script('rms-smtp-cf7-admin', 'rmsSmtpCf7Diagnostic', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rms_smtp_cf7_diagnostic'),
            'testing' => esc_html__('Testing...', 'rms-smtp-cf7'),
            'success_wp' => esc_html__('wp_mail() accepted the send request. Check your inbox.', 'rms-smtp-cf7'),
            'success_mail' => esc_html__('mail() accepted the send request. Check your inbox.', 'rms-smtp-cf7'),
            'error' => esc_html__('Test failed. Check diagnostics for details.', 'rms-smtp-cf7'),
            'rate_limited' => esc_html__('Please wait 30 seconds between tests.', 'rms-smtp-cf7'),
        ]);
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Security: Verify user capability
        if (!current_user_can('manage_options')) {
            return;
        }
        
        register_setting(
            'rms_smtp_cf7_settings',
            'rms_smtp_cf7_options',
            [
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => $this->get_default_settings(),
            ]
        );
    }
    
    /**
     * Get default settings
     * @return array
     */
    private function get_default_settings() {
        return [
            'enabled' => 0,
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'authentication' => 1,
            'username' => '',
            'password' => '',
            'from_email' => '',
            'from_name' => '',
            'smtp_debug' => 0,
        ];
    }
    
    /**
     * Sanitize settings before save
     * @param array $input Raw input settings
     * @return array Sanitized settings
     */
    public function sanitize_settings($input) {
        $sanitized = [];
        
        // Security: Whitelist approach - only accept known fields
        $defaults = $this->get_default_settings();
        
        foreach ($defaults as $key => $default) {
            if (!isset($input[$key])) {
                $sanitized[$key] = $default;
                continue;
            }
            
            switch ($key) {
                case 'enabled':
                case 'authentication':
                case 'smtp_debug':
                    $sanitized[$key] = absint($input[$key]) ? 1 : 0;
                    break;
                    
                case 'host':
                    // Security: Sanitize host - only allow valid hostnames
                    $sanitized[$key] = sanitize_text_field($input[$key]);
                    // Security: Validate host format
                    if (!empty($sanitized[$key]) && !$this->is_valid_host($sanitized[$key])) {
                        add_settings_error(
                            'rms_smtp_cf7',
                            'invalid_host',
                            esc_html__('Invalid SMTP host format.', 'rms-smtp-cf7')
                        );
                        $sanitized[$key] = $default;
                    }
                    break;
                    
                case 'port':
                    // Security: Validate port range
                    $port = absint($input[$key]);
                    $sanitized[$key] = ($port >= 1 && $port <= 65535) ? $port : $default;
                    break;
                    
                case 'encryption':
                    // Security: Whitelist encryption values
                    $allowed = ['none', 'ssl', 'tls'];
                    $sanitized[$key] = in_array($input[$key], $allowed, true) 
                        ? $input[$key] 
                        : $default;
                    break;
                    
                case 'username':
                    // Security: Sanitize username - no HTML
                    $sanitized[$key] = sanitize_email($input[$key]);
                    if (empty($sanitized[$key]) && !empty($input[$key])) {
                        // Fallback to text field if not a valid email
                        $sanitized[$key] = sanitize_text_field($input[$key]);
                    }
                    break;
                    
                case 'password':
                    // Security: Store encrypted password
                    if (!empty($input[$key]) && $input[$key] !== '********') {
                        $sanitized[$key] = $this->encrypt_password($input[$key]);
                    } else {
                        // Keep existing password if masked
                        $existing = get_option('rms_smtp_cf7_options', []);
                        $sanitized[$key] = $existing['password'] ?? '';
                    }
                    break;
                    
                case 'from_email':
                    $sanitized[$key] = sanitize_email($input[$key]);
                    break;
                    
                case 'from_name':
                    $sanitized[$key] = sanitize_text_field($input[$key]);
                    break;
                    
                default:
                    $sanitized[$key] = sanitize_text_field($input[$key]);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate host format
     * @param string $host Hostname to validate
     * @return bool
     */
    private function is_valid_host($host) {
        // Security: Prevent injection attacks in host field
        if (preg_match('/[<>"\']/', $host)) {
            return false;
        }

        // Allow localhost explicitly
        if (strtolower($host) === 'localhost') {
            return true;
        }

        // Allow IP addresses (no private/reserved) and domain names
        return (bool) preg_match('/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/', $host)
            || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
    
    /**
     * Encrypt password for storage
     * @param string $password Plain text password
     * @return string Encrypted password
     */
    private function encrypt_password($password) {
        // Security: Derive fixed 32-byte key from WordPress salt
        $key = hash_hmac('sha256', wp_salt('auth'), 'rms_smtp_cf7_v1', true);
        $iv = random_bytes(openssl_cipher_iv_length('aes-256-gcm'));
        $tag = '';
        $encrypted = openssl_encrypt($password, 'aes-256-gcm', $key, 0, $iv, $tag);

        // Store IV, auth tag, and encrypted data
        return base64_encode($iv . '::' . $tag . '::' . $encrypted);
    }
    
    /**
     * Decrypt stored password
     * @param string $encrypted Encrypted password
     * @return string Decrypted password
     */
    private function decrypt_password($encrypted) {
        $key = hash_hmac('sha256', wp_salt('auth'), 'rms_smtp_cf7_v1', true);
        $data = base64_decode($encrypted);

        // Security: Validate format before decrypting
        $parts = explode('::', $data, 3);
        if (count($parts) !== 3) {
            return '';
        }

        list($iv, $tag, $ciphertext) = $parts;

        $decrypted = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, 0, $iv, $tag);

        return $decrypted !== false ? $decrypted : '';
    }
    
    /**
     * Configure PHPMailer for SMTP
     * @param PHPMailer $phpmailer PHPMailer instance
     */
    public function configure_smtp($phpmailer) {
        // Security: Skip SMTP config for diagnostic bypass
        if ($this->bypass_smtp) {
            return;
        }

        $options = get_option('rms_smtp_cf7_options', $this->get_default_settings());

        // Security: Only configure if SMTP is enabled
        if (empty($options['enabled'])) {
            return;
        }
        
        // Security: Verify required settings exist
        if (empty($options['host'])) {
            return;
        }
        
        // Configure SMTP
        $phpmailer->isSMTP();
        $phpmailer->Host = sanitize_text_field($options['host']);
        $phpmailer->Port = absint($options['port']);
        
        // Security: Set SMTPSecure based on encryption setting
        if ($options['encryption'] !== 'none') {
            $phpmailer->SMTPSecure = $options['encryption'];
        }
        
        // Authentication
        if (!empty($options['authentication'])) {
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = sanitize_text_field($options['username']);
            $decrypted = $this->decrypt_password($options['password']);
            if ($decrypted === false || $decrypted === '') {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('RMS SMTP: Failed to decrypt SMTP password.');
                }
                $phpmailer->Password = '';
            } else {
                $phpmailer->Password = $decrypted;
            }
        }
        
        // From address
        if (!empty($options['from_email'])) {
            $phpmailer->From = sanitize_email($options['from_email']);
        }
        
        if (!empty($options['from_name'])) {
            $phpmailer->FromName = sanitize_text_field($options['from_name']);
        }
        
        // Debug mode (only for admins, gated on WP_DEBUG)
        if (!empty($options['smtp_debug'])
            && current_user_can('manage_options')
            && defined('WP_DEBUG') && WP_DEBUG
            && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG
        ) {
            $phpmailer->SMTPDebug = 2;
            $phpmailer->Debugoutput = function($str, $level) {
                error_log("RMS SMTP Debug [$level]: " . trim($str));
            };
        }
    }
    
    /**
     * Get passive diagnostics data
     * @return array
     */
    private function get_diagnostics_data() {
        return [
            'php_mail_available' => function_exists('mail') && is_callable('mail'),
            'wp_mail_ready' => function_exists('wp_mail'),
            'php_version' => phpversion(),
            'php_sapi' => php_sapi_name(),
            'disable_functions' => ini_get('disable_functions') ?: 'None',
            'sendmail_path' => ini_get('sendmail_path') ?: 'Not set',
            'smtp_host' => ini_get('SMTP') ?: 'Not set',
            'smtp_port' => ini_get('smtp_port') ?: 'Not set',
        ];
    }

    /**
     * Render diagnostics section
     */
    private function render_diagnostics_section() {
        $diagnostics = $this->get_diagnostics_data();
        ?>
        <div class="rms-smtp-cf7-diagnostics">
            <h2><?php esc_html_e('Mail Diagnostics', 'rms-smtp-cf7'); ?></h2>
            <p><?php esc_html_e('Check if your server allows WordPress and PHP mail functions. Some hosting providers disable these to prevent spam.', 'rms-smtp-cf7'); ?></p>

            <!-- Status Cards -->
            <div class="rms-diagnostic-status">
                <div class="rms-diagnostic-card <?php echo $diagnostics['php_mail_available'] ? 'success' : 'error'; ?>">
                    <strong><?php esc_html_e('PHP mail()', 'rms-smtp-cf7'); ?></strong><br>
                    <?php echo $diagnostics['php_mail_available']
                        ? esc_html__('Available', 'rms-smtp-cf7')
                        : esc_html__('Disabled or Unavailable', 'rms-smtp-cf7'); ?>
                </div>
                <div class="rms-diagnostic-card <?php echo $diagnostics['wp_mail_ready'] ? 'success' : 'warning'; ?>">
                    <strong><?php esc_html_e('WordPress wp_mail()', 'rms-smtp-cf7'); ?></strong><br>
                    <?php echo $diagnostics['wp_mail_ready']
                        ? esc_html__('Ready', 'rms-smtp-cf7')
                        : esc_html__('Not Available', 'rms-smtp-cf7'); ?>
                </div>
            </div>

            <!-- Environment Config -->
            <div class="rms-diagnostic-config">
                <h3><?php esc_html_e('Server Mail Configuration', 'rms-smtp-cf7'); ?></h3>
                <table>
                    <tr>
                        <th><?php esc_html_e('PHP Version', 'rms-smtp-cf7'); ?></th>
                        <td><?php echo esc_html($diagnostics['php_version']); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('PHP SAPI', 'rms-smtp-cf7'); ?></th>
                        <td><?php echo esc_html($diagnostics['php_sapi']); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('disable_functions', 'rms-smtp-cf7'); ?></th>
                        <td><?php echo esc_html($diagnostics['disable_functions']); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('sendmail_path', 'rms-smtp-cf7'); ?></th>
                        <td><?php echo esc_html($diagnostics['sendmail_path']); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('SMTP Host (php.ini)', 'rms-smtp-cf7'); ?></th>
                        <td><?php echo esc_html($diagnostics['smtp_host']); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('SMTP Port (php.ini)', 'rms-smtp-cf7'); ?></th>
                        <td><?php echo esc_html($diagnostics['smtp_port']); ?></td>
                    </tr>
                </table>
            </div>

            <!-- Test Controls -->
            <h3><?php esc_html_e('Send Tests', 'rms-smtp-cf7'); ?></h3>
            <p><?php esc_html_e('Test if your server can actually send emails using native WordPress or PHP functions.', 'rms-smtp-cf7'); ?></p>

            <p>
                <label for="rms_diagnostic_email">
                    <?php esc_html_e('Test Email Address:', 'rms-smtp-cf7'); ?>
                </label>
                <input type="email"
                       id="rms_diagnostic_email"
                       class="regular-text"
                       value="<?php echo esc_attr(get_option('admin_email')); ?>">
            </p>

            <p>
                <button type="button" id="rms_diagnostic_wp_btn" class="button button-secondary">
                    <?php esc_html_e('Test wp_mail() (Native)', 'rms-smtp-cf7'); ?>
                </button>
                <button type="button" id="rms_diagnostic_mail_btn" class="button button-secondary">
                    <?php esc_html_e('Test mail() (PHP)', 'rms-smtp-cf7'); ?>
                </button>
            </p>

            <div id="rms_diagnostic_result" class="rms-smtp-cf7-result"></div>
        </div>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Security: Double-check capability
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'rms-smtp-cf7'));
        }
        
        $options = get_option('rms_smtp_cf7_options', $this->get_default_settings());
        ?>
        <div class="wrap rms-smtp-cf7-wrap">
            <h1><?php esc_html_e('RMS SMTP Settings', 'rms-smtp-cf7'); ?></h1>
            
            <?php settings_errors('rms_smtp_cf7'); ?>
            
            <form method="post" action="options.php">
                <?php settings_fields('rms_smtp_cf7_settings'); ?>
                
                <table class="form-table">
                    <!-- Enable SMTP -->
                    <tr>
                        <th scope="row">
                            <label for="rms_smtp_enabled">
                                <?php esc_html_e('Enable SMTP', 'rms-smtp-cf7'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="rms_smtp_enabled" 
                                   name="rms_smtp_cf7_options[enabled]" 
                                   value="1"
                                   <?php checked(1, $options['enabled']); ?>>
                            <p class="description">
                                <?php esc_html_e('Enable SMTP for all WordPress emails (including Contact Form 7).', 'rms-smtp-cf7'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- SMTP Host -->
                    <tr>
                        <th scope="row">
                            <label for="rms_smtp_host">
                                <?php esc_html_e('SMTP Host', 'rms-smtp-cf7'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="rms_smtp_host" 
                                   name="rms_smtp_cf7_options[host]" 
                                   value="<?php echo esc_attr($options['host']); ?>"
                                   class="regular-text"
                                   placeholder="smtp.gmail.com"
                                   required>
                        </td>
                    </tr>
                    
                    <!-- SMTP Port -->
                    <tr>
                        <th scope="row">
                            <label for="rms_smtp_port">
                                <?php esc_html_e('SMTP Port', 'rms-smtp-cf7'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="rms_smtp_port" 
                                   name="rms_smtp_cf7_options[port]" 
                                   value="<?php echo esc_attr($options['port']); ?>"
                                   class="small-text"
                                   min="1" 
                                   max="65535"
                                   required>
                            <p class="description">
                                <?php esc_html_e('Common ports: 25 (no encryption), 465 (SSL), 587 (TLS).', 'rms-smtp-cf7'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Encryption -->
                    <tr>
                        <th scope="row">
                            <label for="rms_smtp_encryption">
                                <?php esc_html_e('Encryption', 'rms-smtp-cf7'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="rms_smtp_encryption" name="rms_smtp_cf7_options[encryption]">
                                <option value="none" <?php selected($options['encryption'], 'none'); ?>>
                                    <?php esc_html_e('None', 'rms-smtp-cf7'); ?>
                                </option>
                                <option value="ssl" <?php selected($options['encryption'], 'ssl'); ?>>
                                    <?php esc_html_e('SSL', 'rms-smtp-cf7'); ?>
                                </option>
                                <option value="tls" <?php selected($options['encryption'], 'tls'); ?>>
                                    <?php esc_html_e('TLS', 'rms-smtp-cf7'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    
                    <!-- Authentication -->
                    <tr>
                        <th scope="row">
                            <label for="rms_smtp_auth">
                                <?php esc_html_e('Authentication', 'rms-smtp-cf7'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="rms_smtp_auth" 
                                   name="rms_smtp_cf7_options[authentication]" 
                                   value="1"
                                   <?php checked(1, $options['authentication']); ?>>
                            <p class="description">
                                <?php esc_html_e('Enable SMTP authentication (required for most servers).', 'rms-smtp-cf7'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Username -->
                    <tr>
                        <th scope="row">
                            <label for="rms_smtp_username">
                                <?php esc_html_e('Username', 'rms-smtp-cf7'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="rms_smtp_username" 
                                   name="rms_smtp_cf7_options[username]" 
                                   value="<?php echo esc_attr($options['username']); ?>"
                                   class="regular-text"
                                   autocomplete="off">
                        </td>
                    </tr>
                    
                    <!-- Password -->
                    <tr>
                        <th scope="row">
                            <label for="rms_smtp_password">
                                <?php esc_html_e('Password', 'rms-smtp-cf7'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="rms_smtp_password" 
                                   name="rms_smtp_cf7_options[password]" 
                                   value="<?php echo !empty($options['password']) ? '********' : ''; ?>"
                                   class="regular-text"
                                   autocomplete="new-password">
                            <p class="description">
                                <?php esc_html_e('Leave as ******** to keep existing password.', 'rms-smtp-cf7'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- From Email -->
                    <tr>
                        <th scope="row">
                            <label for="rms_smtp_from_email">
                                <?php esc_html_e('From Email', 'rms-smtp-cf7'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="email" 
                                   id="rms_smtp_from_email" 
                                   name="rms_smtp_cf7_options[from_email]" 
                                   value="<?php echo esc_attr($options['from_email']); ?>"
                                   class="regular-text"
                                   placeholder="noreply@example.com">
                            <p class="description">
                                <?php esc_html_e('Email address that emails will be sent from.', 'rms-smtp-cf7'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- From Name -->
                    <tr>
                        <th scope="row">
                            <label for="rms_smtp_from_name">
                                <?php esc_html_e('From Name', 'rms-smtp-cf7'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="rms_smtp_from_name" 
                                   name="rms_smtp_cf7_options[from_name]" 
                                   value="<?php echo esc_attr($options['from_name']); ?>"
                                   class="regular-text"
                                   placeholder="My Website">
                        </td>
                    </tr>
                    
                    <!-- Debug Mode -->
                    <tr>
                        <th scope="row">
                            <label for="rms_smtp_debug">
                                <?php esc_html_e('Debug Mode', 'rms-smtp-cf7'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="rms_smtp_debug" 
                                   name="rms_smtp_cf7_options[smtp_debug]" 
                                   value="1"
                                   <?php checked(1, $options['smtp_debug']); ?>>
                            <p class="description">
                                <?php esc_html_e('Enable SMTP debugging (logs to WordPress debug log).', 'rms-smtp-cf7'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(esc_html__('Save Settings', 'rms-smtp-cf7')); ?>
            </form>
            
            <!-- Test Connection Section -->
            <div class="rms-smtp-cf7-test-section">
                <h2><?php esc_html_e('Test Connection', 'rms-smtp-cf7'); ?></h2>
                <p><?php esc_html_e('Send a test email to verify your SMTP settings.', 'rms-smtp-cf7'); ?></p>
                
                <p>
                    <label for="rms_smtp_test_email">
                        <?php esc_html_e('Test Email Address:', 'rms-smtp-cf7'); ?>
                    </label>
                    <input type="email" 
                           id="rms_smtp_test_email" 
                           class="regular-text"
                           value="<?php echo esc_attr(get_option('admin_email')); ?>">
                </p>
                
                <button type="button" id="rms_smtp_test_btn" class="button button-secondary">
                    <?php esc_html_e('Send Test Email', 'rms-smtp-cf7'); ?>
                </button>
                
                <div id="rms_smtp_test_result" class="rms-smtp-cf7-result"></div>
            </div>

            <!-- Mail Diagnostics Section -->
            <?php $this->render_diagnostics_section(); ?>
        </div>
        <?php
    }
    
    /**
     * Add action links to plugin page
     * @param array $links Existing links
     * @return array Modified links
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . esc_url(admin_url('tools.php?page=rms-smtp-cf7')) . '">';
        $settings_link .= esc_html__('Settings', 'rms-smtp-cf7');
        $settings_link .= '</a>';
        
        array_unshift($links, $settings_link);
        
        return $links;
    }
    
    /**
     * Handle AJAX test connection request
     */
    public function handle_test_connection() {
        // Security: Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rms_smtp_cf7_test')) {
            wp_send_json_error(esc_html__('Security check failed.', 'rms-smtp-cf7'));
        }

        // Security: Verify user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Permission denied.', 'rms-smtp-cf7'));
        }

        // Rate limiting: 1 request per 30 seconds per user
        $rate_limit_key = 'rms_smtp_cf7_test_' . get_current_user_id();
        if (get_transient($rate_limit_key)) {
            wp_send_json_error(esc_html__('Please wait before sending another test email.', 'rms-smtp-cf7'));
        }
        set_transient($rate_limit_key, 1, 30);
        
        // Security: Verify SMTP is enabled
        $options = get_option('rms_smtp_cf7_options', $this->get_default_settings());
        if (empty($options['enabled'])) {
            wp_send_json_error(esc_html__('SMTP is not enabled.', 'rms-smtp-cf7'));
        }
        
        // Security: Validate test email
        $test_email = sanitize_email($_POST['test_email'] ?? '');
        if (empty($test_email) || !is_email($test_email)) {
            wp_send_json_error(esc_html__('Invalid test email address.', 'rms-smtp-cf7'));
        }
        
        // Send test email
        $subject = esc_html__('RMS SMTP Test Email', 'rms-smtp-cf7');
        $message = esc_html__('This is a test email from RMS SMTP for Contact Form 7 plugin. If you received this email, your SMTP configuration is working correctly.', 'rms-smtp-cf7');
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        
        $sent = wp_mail($test_email, $subject, $message, $headers);
        
        if ($sent) {
            wp_send_json_success(esc_html__('Test email sent successfully!', 'rms-smtp-cf7'));
        } else {
            wp_send_json_error(esc_html__('Failed to send test email. Please check your SMTP settings.', 'rms-smtp-cf7'));
        }
    }

    /**
     * Handle AJAX diagnostic request
     */
    public function handle_diagnostic() {
        // Security: Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rms_smtp_cf7_diagnostic')) {
            wp_send_json_error(esc_html__('Security check failed.', 'rms-smtp-cf7'));
        }

        // Security: Verify user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Permission denied.', 'rms-smtp-cf7'));
        }

        // Rate limiting: 30 seconds per user
        $user_id = get_current_user_id();
        $transient_key = 'rms_smtp_cf7_diag_' . $user_id;
        if (get_transient($transient_key)) {
            wp_send_json_error(esc_html__('Please wait 30 seconds between tests.', 'rms-smtp-cf7'));
        }

        // Security: Validate test type
        $test_type = sanitize_text_field($_POST['test_type'] ?? '');
        if (!in_array($test_type, ['native_wp_mail', 'direct_mail'], true)) {
            wp_send_json_error(esc_html__('Invalid test type.', 'rms-smtp-cf7'));
        }

        // Security: Validate test email
        $test_email = sanitize_email($_POST['test_email'] ?? '');
        if (empty($test_email) || !is_email($test_email)) {
            wp_send_json_error(esc_html__('Invalid test email address.', 'rms-smtp-cf7'));
        }

        // Set rate limit
        set_transient($transient_key, true, 30);

        $subject = esc_html__('RMS SMTP Diagnostic Test', 'rms-smtp-cf7');
        $message = esc_html__('This is a diagnostic test email. If you received this, the mail function is working.', 'rms-smtp-cf7');
        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        if ($test_type === 'native_wp_mail') {
            // Bypass SMTP hook for native wp_mail test
            $this->bypass_smtp = true;
            try {
                $sent = wp_mail($test_email, $subject, $message, $headers);
                if ($sent) {
                    wp_send_json_success(esc_html__('wp_mail() accepted the send request. Check your inbox.', 'rms-smtp-cf7'));
                } else {
                    wp_send_json_error(esc_html__('wp_mail() failed. The server may be blocking email sending.', 'rms-smtp-cf7'));
                }
            } catch (\Exception $e) {
                wp_send_json_error(esc_html__('wp_mail() threw an exception: ', 'rms-smtp-cf7') . $e->getMessage());
            } finally {
                $this->bypass_smtp = false;
            }
        } else {
            // Direct PHP mail() test
            $sent = mail($test_email, $subject, $message, implode("\r\n", $headers));
            if ($sent) {
                wp_send_json_success(esc_html__('mail() accepted the send request. Check your inbox.', 'rms-smtp-cf7'));
            } else {
                wp_send_json_error(esc_html__('mail() returned false. The server may be blocking PHP mail().', 'rms-smtp-cf7'));
            }
        }
    }
}

// Initialize plugin
add_action('plugins_loaded', ['RMS_SMTP_CF7_Plugin', 'get_instance']);

// Activation hook
register_activation_hook(__FILE__, function() {
    // Security: Check capability on activation
    if (!current_user_can('activate_plugins')) {
        return;
    }
    
    // Set default options
    $defaults = [
        'enabled' => 0,
        'host' => '',
        'port' => 587,
        'encryption' => 'tls',
        'authentication' => 1,
        'username' => '',
        'password' => '',
        'from_email' => '',
        'from_name' => '',
        'smtp_debug' => 0,
    ];
    
    add_option('rms_smtp_cf7_options', $defaults);
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Security: Check capability on deactivation
    if (!current_user_can('deactivate_plugins')) {
        return;
    }
    
    // Optional: Clean up options on deactivation
    // delete_option('rms_smtp_cf7_options');
});
