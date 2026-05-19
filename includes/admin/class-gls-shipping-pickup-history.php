<?php

/**
 * Handles GLS pickup history database operations
 *
 * @since     2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GLS_Shipping_Pickup_History
{
    private $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'gls_pickup_history';
        
        // Create table on activation
        add_action('admin_init', array($this, 'maybe_create_table'));
    }

    /**
     * Create pickup history table if it doesn't exist
     */
    public function maybe_create_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            pickup_id varchar(255) DEFAULT NULL,
            request_data longtext NOT NULL,
            response_data longtext DEFAULT NULL,
            status varchar(50) DEFAULT 'pending',
            error_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Save pickup request to history
     */
    public function save_pickup_request($pickup_data, $response = null, $status = 'pending', $error_message = null)
    {
        global $wpdb;

        $pickup_id = null;
        if ($response && isset($response['PickupRequestId'])) {
            $pickup_id = $response['PickupRequestId'];
        }

        $data = array(
            'pickup_id' => $pickup_id,
            'request_data' => wp_json_encode($pickup_data),
            'response_data' => $response ? wp_json_encode($response) : null,
            'status' => $status,
            'error_message' => $error_message,
            'created_at' => current_time('mysql')
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin table
        $result = $wpdb->insert($this->table_name, $data);

        if ($result === false) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional error logging for debugging
            error_log('Failed to save pickup history: ' . $wpdb->last_error);
            return false;
        }

        return $wpdb->insert_id;
    }


    /**
     * Get pickup history with pagination
     */
    public function get_pickup_history($page = 1, $per_page = 20, $search = '', $status_filter = '')
    {
        global $wpdb;

        $offset = ($page - 1) * $per_page;
        
        // Build WHERE clause
        $where_clauses = array();
        $where_values = array();

        if (!empty($search)) {
            $where_clauses[] = "(request_data LIKE %s OR pickup_id LIKE %s OR error_message LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        if (!empty($status_filter)) {
            $where_clauses[] = "status = %s";
            $where_values[] = $status_filter;
        }

        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }

        // Get total count
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table name uses $wpdb->prefix + constant, WHERE uses only hardcoded placeholders
        $count_sql = "SELECT COUNT(*) FROM {$this->table_name} {$where_sql}";
        if (!empty($where_values)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL has placeholders, values are sanitized
            $count_sql = $wpdb->prepare($count_sql, $where_values);
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table with dynamic filtering
        $total_items = $wpdb->get_var($count_sql);

        // Get records - order by newest first (created_at DESC), then by ID DESC as secondary sort
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses $wpdb->prefix + constant, WHERE uses only hardcoded placeholders
        $sql = "SELECT * FROM {$this->table_name} {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, array($per_page, $offset));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table with prepared placeholders
        $records = $wpdb->get_results($wpdb->prepare($sql, $query_values));

        // Parse JSON data
        foreach ($records as &$record) {
            $record->request_data = json_decode($record->request_data, true);
            if ($record->response_data) {
                $record->response_data = json_decode($record->response_data, true);
            }
        }

        return array(
            'items' => $records,
            'total' => $total_items,
            'pages' => ceil($total_items / $per_page),
            'current_page' => $page,
            'per_page' => $per_page
        );
    }

    /**
     * Get pickup by ID
     */
    public function get_pickup_by_id($id)
    {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses $wpdb->prefix + constant
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if ($record) {
            $record->request_data = json_decode($record->request_data, true);
            if ($record->response_data) {
                $record->response_data = json_decode($record->response_data, true);
            }
        }

        return $record;
    }

}
