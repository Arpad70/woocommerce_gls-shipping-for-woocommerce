<?php

/**
 * Handles Bulk Orders
 *
 * @since     1.2.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__, 2) . '/gls-shipping-for-woocommerce.php';

class GLS_Shipping_Bulk
{
    /**
        * @var \GLS_Shipping_Order
     */
    private $order_handler;

    public function __construct()
    {
        // Initialize order handler
        $this->order_handler = new \GLS_Shipping_Order();

        // Add bulk actions for GLS label generation
        add_filter('bulk_actions-edit-shop_order', array($this, 'register_gls_bulk_actions'));
        add_filter('bulk_actions-woocommerce_page_wc-orders', array($this, 'register_gls_bulk_actions'));

        // Handle bulk action for GLS label generation
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'process_bulk_gls_label_generation'), 10, 3);
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', array($this, 'process_bulk_gls_label_generation'), 10, 3);

        // Display admin notice after bulk action
        add_action('admin_notices', array($this, 'gls_bulk_action_admin_notice'));

        // Add GLS order actions
        add_filter('woocommerce_admin_order_actions', array($this, 'add_gls_order_actions'), 10, 2);

        // Enqueue admin styles
        add_action('admin_print_styles', array($this, 'admin_enqueue_styles'));

        // Add GLS Tracking Number column to orders list (both standard and HPOS)
        add_filter('manage_edit-shop_order_columns', array($this, 'add_gls_parcel_id_column'));
        add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'add_gls_parcel_id_column'));
        
        // Column content for standard WooCommerce
        add_action('manage_shop_order_posts_custom_column', array($this, 'populate_gls_parcel_id_column'), 10, 2);
        
        // Column content for HPOS - unified approach
        add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'populate_gls_parcel_id_column'), 10, 2);
    }

    // Add GLS-specific order actions
    /**
     * @param array<string, mixed> $actions
     * @param \WC_Order $order
     * @return array<string, mixed>
     */
    public function add_gls_order_actions($actions, $order) {
        $order_id = $order->get_id();
        
        // Use secure URL getter to handle both old and new format labels
        $gls_print_label = \GLS_Shipping_For_Woo::get_secure_label_url($order_id);
    
        if ($gls_print_label) {
            // Action to download existing GLS label
            $actions['gls_download_label'] = array(
                'url'    => $gls_print_label,
                'target' => '_blank',
                'name'   => __('Download GLS Label', 'gls-shipping-for-woocommerce'),
                'action' => 'gls-download-label',
            );
        } else {
            // Action to generate new GLS label
            $actions['gls_generate_label'] = array(
                'url'    => '#',
                'name'   => __('Generate GLS Label', 'gls-shipping-for-woocommerce'),
                'action' => 'gls-generate-label',
            );
        }
    
        return $actions;
    }

    // Register GLS bulk action
    /**
     * @param array<string, string> $bulk_actions
     * @return array<string, string>
     */
    public function register_gls_bulk_actions($bulk_actions) {
        $bulk_actions['generate_gls_labels'] = __('Bulk Generate GLS Labels', 'gls-shipping-for-woocommerce');
        $bulk_actions['print_gls_labels'] = __('Bulk Print GLS Labels', 'gls-shipping-for-woocommerce');
        return $bulk_actions;
    }
    
    // Process bulk GLS label generation
    /**
     * @param string $redirect
     * @param string $doaction
     * @param array<int, int|string> $order_ids
     * @return string
     */
    public function process_bulk_gls_label_generation($redirect, $doaction, $order_ids)
    {
        if ('generate_gls_labels' === $doaction) {
            $processed = 0;
            $failed_orders = array();
            foreach ($order_ids as $order_id) {
                // Use centralized label generation method
                $result = $this->order_handler->generate_single_order_label($order_id);
                
                if ($result['success']) {
                    $processed++;
                } else {
                    $failed_orders[] = $order_id;
                }
            }
    
            // Add query args to URL for displaying notices
            $redirect = add_query_arg(
                array(
                    'bulk_action' => 'generate_gls_labels',
                    'gls_labels_generated' => $processed,
                    'gls_labels_failed' => count($failed_orders),
                    'failed_orders' => implode(',', $failed_orders),
                    'changed' => count($order_ids),
                ),
                $redirect
            );
        }
        // Bulk print labels, dont generate for each but just print in single PDF
        if ('print_gls_labels' === $doaction) {
            try {
                $prepare_data = new \GLS_Shipping_API_Data($order_ids);
                $data = $prepare_data->generate_post_fields_multi();
        
                // Send order to GLS API
                $is_multi = true;
                $api = new \GLS_Shipping_API_Service();
                $result = $api->send_order($data, $is_multi);

                $body = $result['body'];
                $failed_orders = $result['failed_orders'];
                
                // Check if all orders failed - don't attempt PDF creation if no successful labels
                if (count($failed_orders) >= count($order_ids)) {
                    $redirect = add_query_arg(
                        array(
                            'bulk_action' => 'print_gls_labels',
                            'gls_labels_printed' => 0,
                            'gls_labels_failed' => count($failed_orders),
                            'failed_orders' => implode(',', array_column($failed_orders, 'order_id')),
                        ),
                        $redirect
                    );
                    return $redirect;
                }
        
                $pdf_filename = $this->bulk_create_print_labels($body);
        
                if ($pdf_filename) {
                    // Save tracking numbers to order meta
                    if (!empty($body['PrintLabelsInfoList'])) {
                        // Group tracking codes by order ID to handle multiple parcels per order
                        $orders_data = array();
                        
                        foreach ($body['PrintLabelsInfoList'] as $labelInfo) {
                            if (isset($labelInfo['ClientReference'])) {
                                $order_id = str_replace('Order:', '', $labelInfo['ClientReference']);
                                
                                if (!isset($orders_data[$order_id])) {
                                    $orders_data[$order_id] = array(
                                        'tracking_codes' => array(),
                                        'parcel_ids' => array()
                                    );
                                }
                                
                                if (isset($labelInfo['ParcelNumber'])) {
                                    $orders_data[$order_id]['tracking_codes'][] = $labelInfo['ParcelNumber'];
                                }
                                if (isset($labelInfo['ParcelId'])) {
                                    $orders_data[$order_id]['parcel_ids'][] = $labelInfo['ParcelId'];
                                }
                            }
                        }
                        
                        // Now save all tracking codes for each order
                        $successful_orders = array();
                        foreach ($orders_data as $order_id => $data) {
                            $order = wc_get_order($order_id);
                            if ($order) {
                                if (!empty($data['tracking_codes'])) {
                                    $order->update_meta_data('_gls_tracking_codes', $data['tracking_codes']);
                                }
                                if (!empty($data['parcel_ids'])) {
                                    $order->update_meta_data('_gls_parcel_ids', $data['parcel_ids']);
                                }
                                
                                // Save just the filename, URL with nonce is generated on display
                                $order->update_meta_data('_gls_print_label', $pdf_filename);
                                $order->save();
                                
                                $successful_orders[] = $order_id;
                            }
                        }
                        
                        // Fire hook after successful bulk label generation
                        do_action('gls_bulk_labels_generated', $order_ids, $successful_orders, $failed_orders);
                    }

                    // Add query args to URL for displaying notices and providing PDF link
                    $pdf_url = \GLS_Shipping_For_Woo::get_label_download_url($pdf_filename);
                    $redirect = add_query_arg(
                        array(
                            'bulk_action' => 'print_gls_labels',
                            'gls_labels_printed' => count($order_ids) - count($failed_orders),
                            'gls_labels_failed' => count($failed_orders),
                            'gls_pdf_url' => urlencode($pdf_url),
                            'failed_orders' => implode(',', array_column($failed_orders, 'order_id')),
                        ),
                        $redirect
                    );
                } else {
                    // Handle error case
                    $redirect = add_query_arg(
                        array(
                            'bulk_action' => 'print_gls_labels',
                            'gls_labels_printed_error' => 'true',
                        ),
                        $redirect
                    );
                }
            } catch (Exception $e) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional error logging for debugging
                error_log('GLS Bulk Print Labels Error: ' . $e->getMessage());
                
                // Handle the exception gracefully - redirect with error message
                $redirect = add_query_arg(
                    array(
                        'bulk_action' => 'print_gls_labels',
                        'gls_labels_printed_error' => 'true',
                        'gls_error_message' => urlencode($e->getMessage()),
                    ),
                    $redirect
                );
            }
        }
    
        return $redirect;
    }

    /**
     * @param array<string, mixed> $body
     * @return string|false
     */
    public function bulk_create_print_labels($body)
    {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    
        WP_Filesystem();
        global $wp_filesystem;
    
        // Check if Labels exist and is an array
        if (empty($body['Labels']) || !is_array($body['Labels'])) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional error logging for debugging
            error_log('GLS Bulk Print: No labels found in API response. This may happen if all orders failed validation.');
            return false;
        }
    
        $label_print = implode(array_map('chr', $body['Labels']));
        
        // Ensure labels directory exists
        \GLS_Shipping_For_Woo::get_instance()->setup_labels_directory();
        
        // Use secure labels directory
        $timestamp = current_time('YmdHis');
        $file_name = 'shipping_label_bulk_' . $timestamp . '.pdf';
        $file_path = \GLS_Shipping_For_Woo::get_labels_dir() . '/' . $file_name;
        
        if ($wp_filesystem->put_contents($file_path, $label_print)) {
            // Return just the filename, URL is generated where needed
            return $file_name;
        }
        return false;
    }

    // Display admin notice after bulk action
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display-only notice after redirect, nonce verified in original action
    public function gls_bulk_action_admin_notice() {
        if (isset($_REQUEST['bulk_action'])) {
            // Sanitize the bulk action parameter
            $bulk_action = sanitize_text_field(wp_unslash($_REQUEST['bulk_action']));
            
            if ('generate_gls_labels' === $bulk_action) {
                $generated = isset($_REQUEST['gls_labels_generated']) ? intval($_REQUEST['gls_labels_generated']) : 0;
                $failed = isset($_REQUEST['gls_labels_failed']) ? intval($_REQUEST['gls_labels_failed']) : 0;
                
                // Sanitize failed_orders - only allow integers (order IDs)
                $failed_orders = array();
                if (isset($_REQUEST['failed_orders']) && !empty($_REQUEST['failed_orders'])) {
                    $raw_failed_orders = sanitize_text_field(wp_unslash($_REQUEST['failed_orders']));
                    $failed_orders_array = explode(',', $raw_failed_orders);
                    foreach ($failed_orders_array as $order_id) {
                        $sanitized_id = absint(trim($order_id));
                        if ($sanitized_id > 0) {
                            $failed_orders[] = $sanitized_id;
                        }
                    }
                }

                // Prepare success message
                $message = sprintf(
                    /* translators: %s: number of generated labels */
                    _n(
                        '%s GLS label was successfully generated.',
                        '%s GLS labels were successfully generated.',
                        $generated,
                        'gls-shipping-for-woocommerce'
                    ),
                    number_format_i18n($generated)
                );

                // Add failure message if any labels failed to generate
                if ($failed > 0) {
                    $message .= ' ' . sprintf(
                        /* translators: %s: number of failed labels */
                        _n(
                            '%s label failed to generate.',
                            '%s labels failed to generate.',
                            $failed,
                            'gls-shipping-for-woocommerce'
                        ),
                        number_format_i18n($failed)
                    );
                    if (!empty($failed_orders)) {
                        $message .= ' ' . sprintf(
                            /* translators: %s: comma-separated list of order IDs that failed */
                            __('Failed order IDs: %s', 'gls-shipping-for-woocommerce'),
                            esc_html(implode(', ', $failed_orders))
                        );
                    }
                }

                // Display the notice with proper escaping
                printf(
                    '<div id="message" class="updated notice is-dismissible"><p>%s</p></div>',
                    wp_kses_post($message)
                );
            } elseif ('print_gls_labels' === $bulk_action) {
                if (isset($_REQUEST['gls_labels_printed']) && isset($_REQUEST['gls_pdf_url'])) {
                    $printed = intval($_REQUEST['gls_labels_printed']);
                    $failed = isset($_REQUEST['gls_labels_failed']) ? intval($_REQUEST['gls_labels_failed']) : 0;
                    $pdf_url = esc_url_raw(urldecode(sanitize_text_field(wp_unslash($_REQUEST['gls_pdf_url']))));
                    
                    // Sanitize failed_orders - only allow integers (order IDs)
                    $failed_orders = array();
                    if (isset($_REQUEST['failed_orders']) && !empty($_REQUEST['failed_orders'])) {
                        $raw_failed_orders = sanitize_text_field(wp_unslash($_REQUEST['failed_orders']));
                        $failed_orders_array = explode(',', $raw_failed_orders);
                        foreach ($failed_orders_array as $order_id) {
                            $sanitized_id = absint(trim($order_id));
                            if ($sanitized_id > 0) {
                                $failed_orders[] = $sanitized_id;
                            }
                        }
                    }
                    
                    // Prepare success message
                    $message = sprintf(
                        /* translators: %s: number of orders processed */
                        _n(
                            'GLS label for %s order has been generated. ',
                            'GLS labels for %s orders have been generated. ',
                            $printed,
                            'gls-shipping-for-woocommerce'
                        ),
                        number_format_i18n($printed)
                    );

                    // Add failure message if any labels failed to generate
                    if ($failed > 0) {
                        $message .= sprintf(
                            /* translators: %s: number of failed labels */
                            _n(
                                '%s label failed to generate. ',
                                '%s labels failed to generate. ',
                                $failed,
                                'gls-shipping-for-woocommerce'
                            ),
                            number_format_i18n($failed)
                        );
                        if (!empty($failed_orders)) {
                            $message .= sprintf(
                                /* translators: %s: comma-separated list of order IDs that failed */
                                __('Failed order IDs: %s', 'gls-shipping-for-woocommerce'),
                                esc_html(implode(', ', $failed_orders))
                            );
                        }
                    }

                    $message .= sprintf(
                        /* translators: %s: URL to download the PDF file */
                        __('<br><a href="%s" target="_blank">Click here to download the PDF</a>', 'gls-shipping-for-woocommerce'),
                        esc_url($pdf_url)
                    );

                    // Display the notice with proper escaping
                    printf(
                        '<div id="message" class="updated notice is-dismissible"><p>%s</p></div>',
                        wp_kses_post($message)
                    );
                } elseif (isset($_REQUEST['gls_labels_printed_error'])) {
                    $message = __('An error occurred while generating the GLS labels PDF.', 'gls-shipping-for-woocommerce');
                    
                    // Display specific error message if available
                    if (isset($_REQUEST['gls_error_message']) && !empty($_REQUEST['gls_error_message'])) {
                        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via sanitize_text_field after urldecode
                        $error_detail = sanitize_text_field(urldecode(wp_unslash($_REQUEST['gls_error_message'])));
                        $message .= ' ' . sprintf(
                            /* translators: %s: error message from GLS API */
                            __('Error: %s', 'gls-shipping-for-woocommerce'),
                            $error_detail
                        );
                    }
                    
                    printf(
                        '<div id="message" class="error notice is-dismissible"><p>%s</p></div>',
                        esc_html($message)
                    );
                }
            }
        }
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // Enqueue bulk styles
    public function admin_enqueue_styles()
    {
        $currentScreen = get_current_screen();
        $screenID = $currentScreen->id;
        if ($screenID === "shop_order" || $screenID === "woocommerce_page_wc-orders" || $screenID === "edit-shop_order") {
             // Add inline CSS for GLS buttons
             $custom_css = "
                a.button.gls-download-label::after {
                    content: '\\f316';
                }
                a.button.gls-generate-label::after {
                    content: '\\f502';
                }
				.wc-action-button-gls-download-label {
					background: #c0e2ad !important;
					color: #2c4700 !important;
					border-color: #2c4700 !important;
				}

				.wc-action-button-gls-generate-label {
					background: #c8e7f2 !important;
					color: #2c4700 !important;
					border-color: #2c4700 !important;
				}
            ";
            wp_add_inline_style('woocommerce_admin_styles', $custom_css);
        }
    }

    /**
     * Add GLS Tracking Number column to orders list
     */
    /**
     * @param array<string, mixed> $columns
     * @return array<string, mixed>
     */
    public function add_gls_parcel_id_column($columns)
    {
        // Insert the GLS Tracking Number column after the order status column
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'order_status') {
                $new_columns['gls_parcel_id'] = __('GLS Tracking Number', 'gls-shipping-for-woocommerce');
            }
        }
        return $new_columns;
    }

    /**
     * Populate GLS Tracking Number column content (works for both standard and HPOS)
     */
    /**
     * @param string $column
     * @param \WC_Order|int $order_data
     * @return void
     */
    public function populate_gls_parcel_id_column($column, $order_data)
    {
        if ($column === 'gls_parcel_id') {
            // Handle different parameter types for standard vs HPOS
            if (is_object($order_data)) {
                // HPOS passes order object
                $order_id = $order_data->get_id();
            } else {
                // Standard WooCommerce passes post ID
                $order_id = $order_data;
            }
            $this->display_parcel_ids($order_id);
        }
    }

    /**
     * Display tracking numbers for an order
     */
    /**
     * @param int $order_id
     * @return void
     */
    private function display_parcel_ids($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            echo '-';
            return;
        }

        $tracking_numbers = array();

        // Get tracking numbers from _gls_tracking_codes meta (preferred)
        $stored_tracking_codes = $order->get_meta('_gls_tracking_codes', true);
        if (!empty($stored_tracking_codes)) {
            if (is_array($stored_tracking_codes)) {
                foreach ($stored_tracking_codes as $tracking_code) {
                    if (!in_array($tracking_code, $tracking_numbers)) {
                        $tracking_numbers[] = esc_html($tracking_code);
                    }
                }
            } else {
                if (!in_array($stored_tracking_codes, $tracking_numbers)) {
                    $tracking_numbers[] = esc_html($stored_tracking_codes);
                }
            }
        } else {
            // Legacy support - check for single tracking code
            $legacy_tracking_code = $order->get_meta('_gls_tracking_code', true);
            if (!empty($legacy_tracking_code)) {
                $tracking_numbers[] = esc_html($legacy_tracking_code);
            }
        }

        // Display the tracking numbers (each element is already escaped with esc_html())
        if (!empty($tracking_numbers)) {
            echo wp_kses_post( implode(' ', $tracking_numbers) );
        } else {
            echo '-';
        }
    }
}

new GLS_Shipping_Bulk();
