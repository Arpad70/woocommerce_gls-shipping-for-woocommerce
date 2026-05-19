<?php

/**
 * Handles migration of existing labels to secure folder
 *
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GLS_Shipping_Label_Migration
{
    /**
     * Batch size for migration (small for compatibility with varied hosting)
     */
    const BATCH_SIZE = 5;

    /**
     * Action Scheduler hook name
     */
    const MIGRATION_HOOK = 'gls_migrate_labels_batch';

    /**
     * Option key for migration status
     * Values: not set, 'not_needed', 'in_progress', 'completed'
     */
    const MIGRATION_OPTION = 'gls_label_migration_status';
    
    /**
     * Option key to track if orphan cleanup has been done
     */
    const CLEANUP_DONE_OPTION = 'gls_label_orphan_cleanup_done';

    /**
     * Constructor
     */
    public function __construct()
    {
        // Register Action Scheduler hook
        add_action(self::MIGRATION_HOOK, array($this, 'process_migration_batch'));

        // Check migration status on admin init
        add_action('admin_init', array($this, 'maybe_start_migration'));

        // Handle fallback for old URLs during migration
        add_action('admin_init', array($this, 'handle_old_label_fallback'), 5);

        // Admin notice during migration
        add_action('admin_notices', array($this, 'display_migration_notice'));
    }

    /**
     * Check if migration needs to run (called once per admin page load)
     */
    public function maybe_start_migration()
    {
        $status = get_option(self::MIGRATION_OPTION);

        // Already done - check if orphan cleanup was also done
        if ($status === 'completed' || $status === 'not_needed') {
            $this->maybe_run_orphan_cleanup();
            return;
        }

        // Migration in progress - ensure action is scheduled
        if ($status === 'in_progress') {
            $this->ensure_action_scheduled();
            return;
        }

        // First time check - see if there are any old labels
        $has_old_labels = $this->has_orders_needing_migration();

        if (!$has_old_labels) {
            // Nothing to migrate - mark as not needed, never check again
            update_option(self::MIGRATION_OPTION, 'not_needed');
            return;
        }

        // Has old labels - start migration
        update_option(self::MIGRATION_OPTION, 'in_progress');
        
        // Setup labels directory
        if (class_exists('GLS_Shipping_For_Woo')) {
            GLS_Shipping_For_Woo::get_instance()->setup_labels_directory();
        }

        $this->ensure_action_scheduled();
    }

    /**
     * Ensure migration action is scheduled
     */
    private function ensure_action_scheduled()
    {
        if (function_exists('as_next_scheduled_action')) {
            if (false === as_next_scheduled_action(self::MIGRATION_HOOK)) {
                as_schedule_single_action(time() + 10, self::MIGRATION_HOOK);
            }
        }
    }

    /**
     * Check if there are any orders with old-style label URLs
     *
     * @return bool
     */
    private function has_orders_needing_migration()
    {
        global $wpdb;

        // Check both HPOS and legacy meta tables
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') &&
            Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            // HPOS enabled
            $table = $wpdb->prefix . 'wc_orders_meta';
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name uses $wpdb->prefix + constant
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT 1 FROM {$table}
                WHERE meta_key = '_gls_print_label'
                AND meta_value LIKE %s
                AND meta_value NOT LIKE %s
                LIMIT 1",
                '%/wp-content/uploads/%',
                '%gls_download_label%'
            ));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        } else {
            // Legacy post meta
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Using $wpdb->postmeta property
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT 1 FROM {$wpdb->postmeta}
                WHERE meta_key = '_gls_print_label'
                AND meta_value LIKE %s
                AND meta_value NOT LIKE %s
                LIMIT 1",
                '%/wp-content/uploads/%',
                '%gls_download_label%'
            ));
        }

        return (bool) $exists;
    }

    /**
     * Get orders that need migration
     *
     * @param int $limit
     * @return array Order IDs
     */
    private function get_orders_needing_migration($limit)
    {
        global $wpdb;

        // Check both HPOS and legacy meta tables
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') &&
            Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            // HPOS enabled
            $table = $wpdb->prefix . 'wc_orders_meta';
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name uses $wpdb->prefix + constant
            $order_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT order_id FROM {$table}
                WHERE meta_key = '_gls_print_label'
                AND meta_value LIKE %s
                AND meta_value NOT LIKE %s
                LIMIT %d",
                '%/wp-content/uploads/%',
                '%gls_download_label%',
                $limit
            ));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        } else {
            // Legacy post meta
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Using $wpdb->postmeta property
            $order_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_gls_print_label'
                AND meta_value LIKE %s
                AND meta_value NOT LIKE %s
                LIMIT %d",
                '%/wp-content/uploads/%',
                '%gls_download_label%',
                $limit
            ));
        }

        return array_map('intval', $order_ids);
    }

    /**
     * Process migration batch via Action Scheduler
     */
    public function process_migration_batch()
    {
        // Ensure labels directory exists
        if (class_exists('GLS_Shipping_For_Woo')) {
            GLS_Shipping_For_Woo::get_instance()->setup_labels_directory();
        }

        // Get orders needing migration
        $orders = $this->get_orders_needing_migration(self::BATCH_SIZE);

        if (empty($orders)) {
            // Migration complete - cleanup orphaned files before marking as done
            $this->cleanup_orphaned_labels();
            update_option(self::MIGRATION_OPTION, 'completed');
            return;
        }

        // Process batch
        foreach ($orders as $order_id) {
            $this->migrate_single_label($order_id);
        }

        // Check if more to process
        if ($this->has_orders_needing_migration()) {
            // Schedule next batch
            if (function_exists('as_schedule_single_action')) {
                as_schedule_single_action(time() + 30, self::MIGRATION_HOOK);
            }
        } else {
            // Done - cleanup orphaned files before marking as complete
            $this->cleanup_orphaned_labels();
            update_option(self::MIGRATION_OPTION, 'completed');
        }
    }

    /**
     * Migrate a single label to secure folder
     *
     * @param int $order_id
     * @return bool Success
     */
    private function migrate_single_label($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        $old_url = $order->get_meta('_gls_print_label', true);
        if (empty($old_url)) {
            return false;
        }

        // Skip if already migrated (new format is just filename, not URL)
        if (strpos($old_url, '/wp-content/uploads/') === false) {
            return true;
        }

        // Convert URL to file path
        $upload_dir = wp_upload_dir();
        $old_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $old_url);

        // Generate new filename from old path
        $original_filename = basename($old_path);
        $new_path = GLS_LABELS_DIR . '/' . $original_filename;

        // Check if already migrated (file exists in new location)
        if (file_exists($new_path)) {
            // File already migrated by another order (bulk labels), just update meta
            $order->update_meta_data('_gls_print_label', $original_filename);
            $order->save();
            return true;
        }

        // Check if old file exists
        if (!file_exists($old_path)) {
            // File doesn't exist anywhere - clear the meta
            $order->delete_meta_data('_gls_print_label');
            $order->save();
            return true;
        }

        // Copy file to new location
        if (!copy($old_path, $new_path)) {
            return false;
        }

        // Update order meta with just the filename
        $order->update_meta_data('_gls_print_label', $original_filename);
        $order->save();

        // Delete old file
        wp_delete_file($old_path);

        return true;
    }

    /**
     * Check if orphan cleanup needs to run (for migrations completed before cleanup was added)
     */
    private function maybe_run_orphan_cleanup()
    {
        // Only run once
        if (get_option(self::CLEANUP_DONE_OPTION)) {
            return;
        }
        
        // Run cleanup
        $this->cleanup_orphaned_labels();
        
        // Mark as done so it doesn't run again
        update_option(self::CLEANUP_DONE_OPTION, true);
    }
    
    /**
     * Cleanup orphaned label files from old uploads directories
     * 
     * This runs once during migration to delete any GLS label files left in old
     * year/month folders. Valid files have already been migrated to gls-shipping-labels.
     * 
     * Uses glob() for efficiency - directly finds matching files instead of scanning
     * all files in the uploads folder (important for large sites with many images).
     */
    private function cleanup_orphaned_labels()
    {
        $upload_dir = wp_upload_dir();
        $uploads_base = $upload_dir['basedir'];
        
        $deleted_count = 0;
        
        // Use glob to directly find GLS label files - much more efficient than scandir
        // Pattern: uploads/202X/XX/shipping_label_*.pdf
        $glob_patterns = array(
            $uploads_base . '/2024/*/shipping_label_*.pdf',
            $uploads_base . '/2025/*/shipping_label_*.pdf',
            $uploads_base . '/2026/*/shipping_label_*.pdf',
        );
        
        foreach ($glob_patterns as $pattern) {
            $files = glob($pattern);
            if ($files === false) {
                continue;
            }
            foreach ($files as $file) {
                wp_delete_file($file);
                $deleted_count++;
            }
        }
        
        if ($deleted_count > 0) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional logging for migration process
            error_log("GLS Migration: Cleaned up {$deleted_count} orphaned label files from old uploads folders.");
        }
    }

    /**
     * Display admin notice during migration
     */
    public function display_migration_notice()
    {
        $status = get_option(self::MIGRATION_OPTION);

        if ($status !== 'in_progress') {
            return;
        }

        // Only show on WooCommerce pages
        $screen = get_current_screen();
        if (!$screen || (strpos($screen->id, 'woocommerce') === false && $screen->id !== 'shop_order' && $screen->id !== 'edit-shop_order')) {
            return;
        }

        echo '<div class="notice notice-info"><p>';
        esc_html_e('GLS Shipping: Migrating labels to secure storage. This runs automatically in the background.', 'gls-shipping-for-woocommerce');
        echo '</p></div>';
    }

    /**
     * Handle fallback for old-style URLs during migration
     */
    public function handle_old_label_fallback()
    {
        if (!isset($_GET['gls_old_label']) || !isset($_GET['order_id']) || !isset($_GET['nonce'])) {
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'gls_old_label_access')) {
            wp_die(esc_html__('Invalid security token.', 'gls-shipping-for-woocommerce'));
        }

        // Check user permissions
        if (!current_user_can('edit_shop_orders')) {
            wp_die(esc_html__('You do not have permission to download shipping labels.', 'gls-shipping-for-woocommerce'));
        }

        $order_id = intval($_GET['order_id']);
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_die(esc_html__('Order not found.', 'gls-shipping-for-woocommerce'));
        }

        $label_url = $order->get_meta('_gls_print_label', true);

        if (empty($label_url)) {
            wp_die(esc_html__('Label not found.', 'gls-shipping-for-woocommerce'));
        }

        // Convert URL to path
        $upload_dir = wp_upload_dir();
        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $label_url);

        if (!file_exists($file_path)) {
            wp_die(esc_html__('PDF label file not found.', 'gls-shipping-for-woocommerce'));
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
}

// Initialize
new GLS_Shipping_Label_Migration();
