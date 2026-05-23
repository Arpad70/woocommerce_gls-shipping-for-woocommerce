<?php

/**
 * Plugin Name: AR Design GLS Shipping for WooCommerce
 * Description: AR Design maintained GLS Shipping for WooCommerce plugin with checkout pickup fixes, release workflow, and GitHub updater metadata.
 * Version: 1.4.7
 * Author: Arpád Horák
 * License: GPLv2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Author URI: https://arpad-horak.cz
 * Update URI: https://github.com/Arpad70/woocommerce_gls-shipping-for-woocommerce
 * Text Domain: gls-shipping-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.9
 * Tested up to: 6.9
 * Requires PHP: 7.1
 *
 * WC requires at least: 5.6
 * WC tested up to: 10.6.1
 */

defined('ABSPATH') || exit;

if (!defined('GLS_SHIPPING_URL')) {
    define('GLS_SHIPPING_URL', plugin_dir_url(__FILE__));
}

if (!defined('GLS_SHIPPING_ABSPATH')) {
    define('GLS_SHIPPING_ABSPATH', dirname(__FILE__) . '/');
}

if (!defined('GLS_SHIPPING_VERSION')) {
    define('GLS_SHIPPING_VERSION', '1.4.7');
}

if (!defined('GLS_LABELS_DIR') || !defined('GLS_LABELS_URL')) {
    $gls_wp_upload_dir = wp_upload_dir();

    if (!defined('GLS_LABELS_DIR')) {
        define('GLS_LABELS_DIR', $gls_wp_upload_dir['basedir'] . '/gls-shipping-labels');
    }

    if (!defined('GLS_LABELS_URL')) {
        define('GLS_LABELS_URL', $gls_wp_upload_dir['baseurl'] . '/gls-shipping-labels');
    }
}

if (!defined('GLS_SHIPPING_METHOD_ID')) {
    define('GLS_SHIPPING_METHOD_ID', 'gls_shipping_method');
}

if (!defined('GLS_SHIPPING_METHOD_ZONES_ID')) {
    define('GLS_SHIPPING_METHOD_ZONES_ID', 'gls_shipping_method_zones');
}

if (!defined('GLS_SHIPPING_METHOD_PARCEL_LOCKER_ID')) {
    define('GLS_SHIPPING_METHOD_PARCEL_LOCKER_ID', 'gls_shipping_method_parcel_locker');
}

if (!defined('GLS_SHIPPING_METHOD_PARCEL_SHOP_ID')) {
    define('GLS_SHIPPING_METHOD_PARCEL_SHOP_ID', 'gls_shipping_method_parcel_shop');
}

if (!defined('GLS_SHIPPING_METHOD_PARCEL_LOCKER_ZONES_ID')) {
    define('GLS_SHIPPING_METHOD_PARCEL_LOCKER_ZONES_ID', 'gls_shipping_method_parcel_locker_zones');
}

if (!defined('GLS_SHIPPING_METHOD_PARCEL_SHOP_ZONES_ID')) {
    define('GLS_SHIPPING_METHOD_PARCEL_SHOP_ZONES_ID', 'gls_shipping_method_parcel_shop_zones');
}

final class GLS_Shipping_For_Woo
{
    private static $instance;

    private $version = '1.4.7';
    private $woocommerce_runtime_bootstrapped = false;

    private function __construct()
    {
        $this->define_constants();
        spl_autoload_register(array($this, 'autoloader'));

        $this->init_core_hooks();
    }

    private function includes()
    {
        // Load helpers first
        require_once(GLS_SHIPPING_ABSPATH . 'includes/helpers/class-gls-shipping-sender-address-helper.php');
        require_once(GLS_SHIPPING_ABSPATH . 'includes/helpers/class-gls-shipping-account-helper.php');
        
        require_once(GLS_SHIPPING_ABSPATH . 'includes/public/class-gls-shipping-assets.php');
        require_once(GLS_SHIPPING_ABSPATH . 'includes/public/class-gls-shipping-checkout.php');
        require_once(GLS_SHIPPING_ABSPATH . 'includes/public/class-gls-shipping-my-account.php');
        require_once(GLS_SHIPPING_ABSPATH . 'includes/public/class-gls-shipping-logo-display.php');
        require_once(GLS_SHIPPING_ABSPATH . 'includes/admin/class-gls-shipping-product-restrictions.php');
        require_once(GLS_SHIPPING_ABSPATH . 'includes/api/class-gls-shipping-api-service.php');
        require_once(GLS_SHIPPING_ABSPATH . 'includes/admin/class-gls-shipping-workflow.php');

        // Migration class loaded always (Action Scheduler needs it for cron)
        require_once(GLS_SHIPPING_ABSPATH . 'includes/admin/class-gls-shipping-label-migration.php');
        
        if (is_admin()) {
            require_once(GLS_SHIPPING_ABSPATH . 'includes/admin/class-gls-shipping-order.php');
            require_once(GLS_SHIPPING_ABSPATH . 'includes/admin/class-gls-shipping-bulk.php');
            require_once(GLS_SHIPPING_ABSPATH . 'includes/admin/class-gls-shipping-pickup-history.php');
            require_once(GLS_SHIPPING_ABSPATH . 'includes/admin/class-gls-shipping-pickup.php');
            require_once(GLS_SHIPPING_ABSPATH . 'includes/api/class-gls-shipping-api-data.php');
            require_once(GLS_SHIPPING_ABSPATH . 'includes/api/class-gls-shipping-pickup-api-service.php');
        }

        require_once(GLS_SHIPPING_ABSPATH . 'includes/methods/class-gls-shipping-method.php');
        require_once(GLS_SHIPPING_ABSPATH . 'includes/methods/class-gls-shipping-method-zones.php');
        require_once(GLS_SHIPPING_ABSPATH . 'includes/methods/class-gls-shipping-method-parcel-shop.php');
        require_once(GLS_SHIPPING_ABSPATH . 'includes/methods/class-gls-shipping-method-parcel-shop-zones.php');
        require_once(GLS_SHIPPING_ABSPATH . 'includes/methods/class-gls-shipping-method-parcel-locker.php');
        require_once(GLS_SHIPPING_ABSPATH . 'includes/methods/class-gls-shipping-method-parcel-locker-zones.php');
    }
    /**
     * Define RAF Constants.
     * @since 1.0.0
     */
    private function define_constants()
    {
        $this->define('GLS_SHIPPING_URL', plugin_dir_url(__FILE__));
        $this->define('GLS_SHIPPING_ABSPATH', dirname(__FILE__) . '/');
        $this->define('GLS_SHIPPING_VERSION', $this->get_version());

        // Labels directory - secure folder for PDF labels
        $wp_upload_dir = wp_upload_dir();
        $this->define('GLS_LABELS_DIR', $wp_upload_dir['basedir'] . '/gls-shipping-labels');
        $this->define('GLS_LABELS_URL', $wp_upload_dir['baseurl'] . '/gls-shipping-labels');

        $this->define('GLS_SHIPPING_METHOD_ID', 'gls_shipping_method');
        $this->define('GLS_SHIPPING_METHOD_ZONES_ID', 'gls_shipping_method_zones');
        $this->define('GLS_SHIPPING_METHOD_PARCEL_LOCKER_ID', 'gls_shipping_method_parcel_locker');
        $this->define('GLS_SHIPPING_METHOD_PARCEL_SHOP_ID', 'gls_shipping_method_parcel_shop');

        $this->define('GLS_SHIPPING_METHOD_PARCEL_LOCKER_ZONES_ID', 'gls_shipping_method_parcel_locker_zones');
        $this->define('GLS_SHIPPING_METHOD_PARCEL_SHOP_ZONES_ID', 'gls_shipping_method_parcel_shop_zones');
    }

    /**
     * Get absolute path to the protected labels directory.
     *
     * Kept as a method so static analyzers do not depend on runtime-defined constants.
     *
     * @return string
     */
    public static function get_labels_dir()
    {
        if (defined('GLS_LABELS_DIR')) {
            return constant('GLS_LABELS_DIR');
        }

        $wp_upload_dir = wp_upload_dir();

        return trailingslashit($wp_upload_dir['basedir']) . 'gls-shipping-labels';
    }

    /**
     * Returns Plugin version for global
     * @since  1.0.0
     */
    private function get_version()
    {
        return $this->version;
    }

    private function autoloader($class)
    {
        $class = strtolower($class);
        // Remove prefix
        $class = str_replace('gls_croatia\\', '', $class);

        if (false === strpos($class, 'gls_croatia')) {
            return;
        }

        $class = str_replace('_', '-', $class);
        $class = str_replace('\\', '/', $class);

        $class_parts = explode('/', $class);
        $class_name = end($class_parts);

        $file = GLS_SHIPPING_ABSPATH . str_replace($class_name, 'class-' . $class_name, $class) . '.php';

        // Check if the file exists and require it if found.
        if (file_exists($file)) {
            require $file;
        }
    }

    private function init_core_hooks()
    {
        add_action('init', array($this, 'load_textdomain'));
        // Add download endpoint for secure PDF serving
        add_action('admin_init', array($this, 'handle_label_download'));
        add_action('woocommerce_loaded', array($this, 'bootstrap_woocommerce_runtime'));
    }

    public function bootstrap_woocommerce_runtime()
    {
        if ($this->woocommerce_runtime_bootstrapped) {
            return;
        }

        $this->woocommerce_runtime_bootstrapped = true;
        $this->includes();

        add_filter('woocommerce_shipping_methods', array($this, 'add_gls_shipping_methods'));
    }

    /**
     * Setup secure labels directory with .htaccess protection
     * 
     * @since 1.4.0
     */
    public function setup_labels_directory()
    {
        $labels_dir = self::get_labels_dir();

        if (!file_exists($labels_dir)) {
            wp_mkdir_p($labels_dir);
        }

        // Create .htaccess to deny all direct access
        $htaccess_file = $labels_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "Order deny,allow\nDeny from all";
            @file_put_contents($htaccess_file, $htaccess_content);
        }

        // Create index.php to prevent directory listing
        $index_file = $labels_dir . '/index.php';
        if (!file_exists($index_file)) {
            @file_put_contents($index_file, '<?php // Silence is golden');
        }
    }

    /**
     * Handle secure PDF label download
     * Serves PDF files through PHP with authentication
     * 
     * @since 1.4.0
     */
    public function handle_label_download()
    {
        if (!isset($_GET['gls_download_label'])) {
            return;
        }

        // Verify nonce
        if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'gls_download_label')) {
            wp_die(esc_html__('Invalid security token. Please refresh the page and try again.', 'gls-shipping-for-woocommerce'));
        }

        // Check user permissions
        if (!current_user_can('edit_shop_orders')) {
            wp_die(esc_html__('You do not have permission to download shipping labels.', 'gls-shipping-for-woocommerce'));
        }

        $labels_dir = self::get_labels_dir();
        $file_id = sanitize_file_name(wp_unslash($_GET['gls_download_label']));
        $file_path = $labels_dir . '/' . $file_id;

        // Security check - ensure file is within labels directory
        $real_path = realpath($file_path);
        $real_labels_dir = realpath($labels_dir);
        
        if ($real_path === false || strpos($real_path, $real_labels_dir) !== 0) {
            wp_die(esc_html__('Invalid file path.', 'gls-shipping-for-woocommerce'));
        }

        if (!file_exists($file_path)) {
            wp_die(esc_html__('PDF label not found.', 'gls-shipping-for-woocommerce'));
        }

        // Serve the file using WP_Filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $file_contents = $wp_filesystem->get_contents($file_path);
        if (false === $file_contents) {
            wp_die(esc_html__('Could not read PDF file.', 'gls-shipping-for-woocommerce'));
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . strlen($file_contents));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $file_contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary PDF data
        exit;
    }

    /**
     * Generate secure download URL for a label file
     * 
     * @param string $filename The filename of the label
     * @return string The secure download URL
     * @since 1.4.0
     */
    public static function get_label_download_url($filename)
    {
        return add_query_arg(array(
            'gls_download_label' => $filename,
            'nonce' => wp_create_nonce('gls_download_label'),
        ), admin_url('admin.php'));
    }

    /**
     * Get secure URL for a label (handles both old and new format)
     * Always generates fresh nonce for security
     *
     * @param int $order_id
     * @return string|false
     * @since 1.4.0
     */
    public static function get_secure_label_url($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        $label_data = $order->get_meta('_gls_print_label', true);
        if (empty($label_data)) {
            return false;
        }

        // Old format (pre-1.4.0) - direct URL to uploads folder
        if (strpos($label_data, '/wp-content/uploads/') !== false) {
            return add_query_arg(array(
                'gls_old_label' => 1,
                'order_id' => $order_id,
                'nonce' => wp_create_nonce('gls_old_label_access'),
            ), admin_url('admin.php'));
        }

        // New format - stored filename, generate fresh URL with nonce
        return self::get_label_download_url($label_data);
    }

    public function load_textdomain()
    {
        // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Manual loading needed for non-wp.org distribution
        load_plugin_textdomain('gls-shipping-for-woocommerce', false, basename(dirname(__FILE__)) . '/languages/');
    }

    public function add_gls_shipping_methods($methods)
    {
        $methods[GLS_SHIPPING_METHOD_ID] = 'GLS_Shipping_Method';
        $methods[GLS_SHIPPING_METHOD_ZONES_ID] = 'GLS_Shipping_Method_Zones';
        $methods[GLS_SHIPPING_METHOD_PARCEL_LOCKER_ID] = 'GLS_Shipping_Method_Parcel_Shop';
        $methods[GLS_SHIPPING_METHOD_PARCEL_SHOP_ID] = 'GLS_Shipping_Method_Parcel_Locker';
        $methods[GLS_SHIPPING_METHOD_PARCEL_LOCKER_ZONES_ID] = 'GLS_Shipping_Method_Parcel_Locker_Zones';
        $methods[GLS_SHIPPING_METHOD_PARCEL_SHOP_ZONES_ID] = 'GLS_Shipping_Method_Parcel_Shop_Zones';

        return $methods;
    }

    /**
     * Define constant if not already set.
     *
     * @since  1.0.0
     * @param  string $name
     * @param  string|bool $value
     */
    private function define($name, $value)
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Plugin activation callback - setup labels directory
     * 
     * @since 1.4.0
     */
    public static function on_plugin_activation()
    {
        $instance = self::get_instance();
        $instance->setup_labels_directory();
    }
}

// Declare HPOS Compatibility
add_action(
    'before_woocommerce_init',
    function () {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }
);

// Register activation hook to setup labels directory
register_activation_hook(__FILE__, array('GLS_Shipping_For_Woo', 'on_plugin_activation'));

GLS_Shipping_For_Woo::get_instance();
