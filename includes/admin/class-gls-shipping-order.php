<?php

/**
 * Handles showing of order Information
 *
 * @since     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__, 2) . '/gls-shipping-for-woocommerce.php';

class GLS_Shipping_Order
{

    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'add_gls_shipping_info_meta_box'));
        add_action('wp_ajax_gls_generate_label', array($this, 'generate_label_and_tracking_number'));
        add_action('wp_ajax_gls_get_parcel_status', array($this, 'get_parcel_status'));
        add_action('wp_ajax_gls_update_pickup_location', array($this, 'update_pickup_location'));
        
        // Save GLS settings when order is updated
        add_action('woocommerce_process_shop_order_meta', array($this, 'save_gls_order_settings'), 10, 2);
        add_action('save_post_shop_order', array($this, 'save_gls_order_settings'), 10, 2);
    }

    public function add_gls_shipping_info_meta_box()
    {
        $screen = 'shop_order';

        if (class_exists('Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')) {
            $screen = wc_get_container()->get(Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id('shop-order') : 'shop_order';
        }

        add_meta_box(
            'gls_shipping_info_meta_box',
            esc_html__('GLS Shipping Info', 'gls-shipping-for-woocommerce'),
            array($this, 'gls_shipping_info_meta_box_content'),
            $screen,
            'side',
            'default'
        );
    }

    private function display_gls_pickup_info($order_id)
    {
        $order = wc_get_order($order_id);
    
        $gls_pickup_info = $order->get_meta('_gls_pickup_info', true);
        $tracking_codes  = $order->get_meta('_gls_tracking_codes', true);
        
        // Legacy support, should be removed later on.
        $tracking_code  = $order->get_meta('_gls_tracking_code', true);
    
        if (!empty($gls_pickup_info)) {
            $pickup_info = json_decode($gls_pickup_info);
            
            // Check if this order uses pickup services (parcel locker or parcel shop)
            $shipping_methods = $order->get_shipping_methods();
            $uses_pickup_service = false;
            $pickup_type = '';
            
            $map_selection_methods = array(
                'gls_shipping_method_parcel_locker',
                'gls_shipping_method_parcel_shop',
                'gls_shipping_method_parcel_locker_zones',
                'gls_shipping_method_parcel_shop_zones'
            );
            
            foreach ($shipping_methods as $shipping_method) {
                if (in_array($shipping_method->get_method_id(), $map_selection_methods)) {
                    $uses_pickup_service = true;
                    if (strpos($shipping_method->get_method_id(), 'locker') !== false) {
                        $pickup_type = 'locker';
                    } else {
                        $pickup_type = 'shop';
                    }
                    break;
                }
            }
    
            echo '<div id="gls-pickup-display">';
            echo '<strong>' . esc_html__('GLS Pickup Location:', 'gls-shipping-for-woocommerce') . '</strong><br/>';
            echo '<strong>' . esc_html__('ID:', 'gls-shipping-for-woocommerce') . '</strong> ' . esc_html($pickup_info->id) . '<br>';
            echo '<strong>' . esc_html__('Name:', 'gls-shipping-for-woocommerce') . '</strong> ' . esc_html($pickup_info->name) . '<br>';
            echo '<strong>' . esc_html__('Address:', 'gls-shipping-for-woocommerce') . '</strong> ' . esc_html($pickup_info->contact->address) . ', ' . esc_html($pickup_info->contact->city) . ', ' . esc_html($pickup_info->contact->postalCode) . '<br>';
            echo '<strong>' . esc_html__('Country:', 'gls-shipping-for-woocommerce') . '</strong> ' . esc_html($pickup_info->contact->countryCode) . '<br>';
            
            // Add change pickup location button if this order uses pickup services
            if ($uses_pickup_service) {
                echo '<br/>';
                if ($pickup_type === 'locker') {
                    echo '<button type="button" class="button button-secondary gls-change-pickup-location" data-order-id="' . esc_attr($order_id) . '" data-pickup-type="locker">' . esc_html__('Change Pickup Location', 'gls-shipping-for-woocommerce') . '</button>';
                } else {
                    echo '<button type="button" class="button button-secondary gls-change-pickup-location" data-order-id="' . esc_attr($order_id) . '" data-pickup-type="shop">' . esc_html__('Change Pickup Location', 'gls-shipping-for-woocommerce') . '</button>';
                }
            }
            echo '</div>';
        }
    
        if (!empty($tracking_codes) && is_array($tracking_codes)) {
            $gls_shipping_method_settings = get_option("woocommerce_gls_shipping_method_settings");
            echo '<br/><strong>' . esc_html__('GLS Tracking Numbers:', 'gls-shipping-for-woocommerce') . '</strong><br>';
            foreach ($tracking_codes as $tracking_code) {
                $tracking_url = "https://gls-group.eu/" . $gls_shipping_method_settings['country'] . "/en/parcel-tracking/?match=" . $tracking_code;
                echo '<a href="' . esc_url($tracking_url) . '" target="_blank">' . esc_html($tracking_code) . '</a><br>';
            }
        } else if (!empty($tracking_code)) {
            // Legacy support, should be removed later on.
            $gls_shipping_method_settings = get_option("woocommerce_gls_shipping_method_settings");
            $tracking_url = "https://gls-group.eu/" . $gls_shipping_method_settings['country'] . "/en/parcel-tracking/?match=" . $tracking_code;
            echo '<br/><strong>' . esc_html__('GLS Tracking Number: ', 'gls-shipping-for-woocommerce') . '<a href="' . esc_url($tracking_url) . '" target="_blank">' . esc_html($tracking_code) . '</a></strong><br>';
        }
    }
    

    public function gls_shipping_info_meta_box_content($order_or_post_id)
    {

        $order = ($order_or_post_id instanceof \WP_Post)
            ? wc_get_order($order_or_post_id->ID)
            : $order_or_post_id;

        // Use secure URL getter to handle both old and new format labels
        $gls_print_label = \GLS_Shipping_For_Woo::get_secure_label_url($order->get_id());
        
        // Get tracking numbers for status buttons
        $tracking_codes = $order->get_meta('_gls_tracking_codes', true);
        $gls_tracking_numbers = array();
        if (!empty($tracking_codes) && is_array($tracking_codes)) {
            $gls_tracking_numbers = $tracking_codes;
        } else {
            // Legacy support - check for single tracking code
            $legacy_tracking_code = $order->get_meta('_gls_tracking_code', true);
            if (!empty($legacy_tracking_code)) {
                $gls_tracking_numbers = array($legacy_tracking_code);
            }
        }

        $this->display_gls_pickup_info($order->get_id(), false);
?>
        <h4 style="margin-bottom:0px;">
            <div style="margin-top:10px;">
                <?php if ($gls_print_label) { ?>
                    <a class="button primary" href="<?php echo esc_url($gls_print_label); ?>" target="_blank" style="width: 100%; text-align: center; display: block; box-sizing: border-box;"><?php esc_html_e("Print Label", "gls-shipping-for-woocommerce"); ?></a>
                    <div style="margin-top:10px;display: flex; flex-direction: column;">
                        <div style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <span><?php esc_html_e("Number of Packages:", "gls-shipping-for-woocommerce"); ?></span>
                            <input type="number" id="gls_label_count" name="gls_label_count" min="1" value="<?php echo esc_attr($order->get_meta('_gls_label_count', true) ?: 1); ?>" style="width: 60px;">
                        </div>
                        <div style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <?php 
                            $gls_shipping_method_settings = get_option("woocommerce_gls_shipping_method_settings");
                            $default_print_position = isset($gls_shipping_method_settings['print_position']) ? $gls_shipping_method_settings['print_position'] : '1';
                            $saved_print_position = $order->get_meta('_gls_print_position', true) ?: $default_print_position;
                            ?>
                            <span><?php esc_html_e("Print Position:", "gls-shipping-for-woocommerce"); ?></span>
                            <select id="gls_print_position" name="gls_print_position" style="width: 60px;">
                                <option value="1" <?php selected($saved_print_position, '1'); ?>>1</option>
                                <option value="2" <?php selected($saved_print_position, '2'); ?>>2</option>
                                <option value="3" <?php selected($saved_print_position, '3'); ?>>3</option>
                                <option value="4" <?php selected($saved_print_position, '4'); ?>>4</option>
                            </select>
                        </div>
                        <?php if ($order->get_payment_method() === 'cod') { ?>
                        <div style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <?php 
                            $saved_cod_reference = $order->get_meta('_gls_cod_reference', true) ?: $order->get_id();
                            ?>
                            <span><?php esc_html_e("COD Reference:", "gls-shipping-for-woocommerce"); ?></span>
                            <input type="text" id="gls_cod_reference" name="gls_cod_reference" value="<?php echo esc_attr($saved_cod_reference); ?>" style="width: 120px;">
                        </div>
                        <?php } ?>
                        
                        <!-- Service Options Toggle -->
                        <div style="margin-bottom: 10px;">
                            <a href="#" id="gls-services-toggle" style="text-decoration: none; color: #0073aa;">
                                <?php esc_html_e("⚙️ Advanced Services", "gls-shipping-for-woocommerce"); ?> <span id="gls-services-arrow">▼</span>
                            </a>
                        </div>
                        
                        <!-- Service Options (Hidden by default) -->
                        <div id="gls-services-options" style="display: none; margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background-color: #f9f9f9;">
                            <?php
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted internal method outputting form fields
                            echo $this->render_service_options($order);
                            ?>
                        </div>
                        
                        <button type="button" class="button gls-print-label" order-id="<?php echo esc_attr($order->get_id()); ?>">
                            <?php esc_html_e("Regenerate Shipping Label", "gls-shipping-for-woocommerce"); ?>
                        </button>
                        <?php if (!empty($gls_tracking_numbers)) { ?>
                            <?php foreach ($gls_tracking_numbers as $index => $tracking_number) { ?>
                            <button type="button" class="button gls-get-status" order-id="<?php echo esc_attr($order->get_id()); ?>" parcel-number="<?php echo esc_attr($tracking_number); ?>" style="margin-top: 10px;">
                                <?php
                                if (count($gls_tracking_numbers) > 1) {
                                    /* translators: %1$d: parcel index number, %2$s: tracking number */
                                    echo esc_html( sprintf(__("Get Parcel Status #%1\$d (%2\$s)", "gls-shipping-for-woocommerce"), intval($index) + 1, $tracking_number) );
                                } else {
                                    /* translators: %s: tracking number */
                                    echo esc_html( sprintf(__("Get Parcel Status (%s)", "gls-shipping-for-woocommerce"), $tracking_number) );
                                }
                                ?>
                            </button>
                            <?php } ?>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div style="margin-top:10px;display: flex; flex-direction: column;">
                        <div style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <span><?php esc_html_e("Number of Packages:", "gls-shipping-for-woocommerce"); ?></span>
                            <input type="number" id="gls_label_count" name="gls_label_count" min="1" value="<?php echo esc_attr($order->get_meta('_gls_label_count', true) ?: 1); ?>" style="width: 60px;">
                        </div>
                        <div style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <?php 
                            $gls_shipping_method_settings = get_option("woocommerce_gls_shipping_method_settings");
                            $default_print_position = isset($gls_shipping_method_settings['print_position']) ? $gls_shipping_method_settings['print_position'] : '1';
                            $saved_print_position = $order->get_meta('_gls_print_position', true) ?: $default_print_position;
                            ?>
                            <span><?php esc_html_e("Print Position:", "gls-shipping-for-woocommerce"); ?></span>
                            <select id="gls_print_position_new" name="gls_print_position_new" style="width: 60px;">
                                <option value="1" <?php selected($saved_print_position, '1'); ?>>1</option>
                                <option value="2" <?php selected($saved_print_position, '2'); ?>>2</option>
                                <option value="3" <?php selected($saved_print_position, '3'); ?>>3</option>
                                <option value="4" <?php selected($saved_print_position, '4'); ?>>4</option>
                            </select>
                        </div>
                        <?php if ($order->get_payment_method() === 'cod') { ?>
                        <div style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <?php 
                            $saved_cod_reference = $order->get_meta('_gls_cod_reference', true) ?: $order->get_id();
                            ?>
                            <span><?php esc_html_e("COD Reference:", "gls-shipping-for-woocommerce"); ?></span>
                            <input type="text" id="gls_cod_reference_new" name="gls_cod_reference_new" value="<?php echo esc_attr($saved_cod_reference); ?>" style="width: 120px;">
                        </div>
                        <?php } ?>
                        
                        <!-- Service Options Toggle -->
                        <div style="margin-bottom: 10px;">
                            <a href="#" id="gls-services-toggle-new" style="text-decoration: none; color: #0073aa;">
                                <?php esc_html_e("⚙️ Advanced Services", "gls-shipping-for-woocommerce"); ?> <span id="gls-services-arrow-new">▼</span>
                            </a>
                        </div>
                        
                        <!-- Service Options (Hidden by default) -->
                        <div id="gls-services-options-new" style="display: none; margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background-color: #f9f9f9;">
                            <?php
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted internal method outputting form fields
                            echo $this->render_service_options($order);
                            ?>
                        </div>
                        
                        <button type="button" class="button gls-print-label" order-id="<?php echo esc_attr($order->get_id()); ?>">
                            <?php esc_html_e("Generate Shipping Label", "gls-shipping-for-woocommerce"); ?>
                        </button>
                    </div>
                <?php } ?>
            </div>
            <div id="gls-info"></div>
            <div id="gls-tracking-status" style="margin-top: 15px;"></div>
        </h4>
<?php
    }

    /**
     * Centralized method to generate label for a single order
     * Used by both single order generation and bulk operations
     * 
     * @param int $order_id Order ID
     * @param int|null $count Number of packages (if null, uses saved or default)
     * @param int|null $print_position Print position (if null, uses saved or default)
     * @param string|null $cod_reference COD reference (if null, uses saved or default)
     * @param array|null $services Services array (if null, uses saved or default)
     * @return array Result with success status and data/error
     */
    public function generate_single_order_label($order_id, $count = null, $print_position = null, $cod_reference = null, $services = null)
    {
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new Exception("Order not found: $order_id");
            }
            
            // Get final count - use provided value, saved value, or default to 1
            if ($count !== null) {
                $final_count = $count;
            } else {
                $saved_count = $order->get_meta('_gls_label_count', true);
                $final_count = !empty($saved_count) ? intval($saved_count) : 1;
            }
            
            // For other settings, use provided values or saved values (no complex fallback needed)
            $final_print_position = $print_position ? $print_position : intval($order->get_meta('_gls_print_position', true));
            if (!$final_print_position) {
                $final_print_position = null;
            }
            
            $final_cod_reference = $cod_reference ? $cod_reference : $order->get_meta('_gls_cod_reference', true);
            if (empty($final_cod_reference)) {
                $final_cod_reference = null;
            }
            
            $final_services = $services ? $services : $order->get_meta('_gls_services', true);
            if (empty($final_services)) {
                $final_services = null;
            }
            
            // Prepare data for API request
            $prepare_data = new \GLS_Shipping_API_Data($order_id);
            $data = $prepare_data->generate_post_fields($final_count, $final_print_position, $final_cod_reference, $final_services);

            // Send to GLS API
            $api = new \GLS_Shipping_API_Service();
            $result = $api->send_order($data);
            
            // Save label and tracking information
            $this->save_label_and_tracking_info($result['body'], $order_id);
            
            return array('success' => true, 'data' => $result);
            
        } catch (Exception $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional error logging for debugging
            error_log("Failed to generate GLS label for order $order_id: " . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    public function generate_label_and_tracking_number()
    {
        if (!isset($_POST['postNonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['postNonce'])), 'import-nonce')) {
            die('Busted!');
        }

        $order_id = isset($_POST['orderId']) ? sanitize_text_field(wp_unslash($_POST['orderId'])) : '';
        $count = isset($_POST['count']) ? intval($_POST['count']) : null;
        $print_position = isset($_POST['printPosition']) ? intval($_POST['printPosition']) : null;
        $cod_reference = isset($_POST['codReference']) ? sanitize_text_field(wp_unslash($_POST['codReference'])) : null;
        $services = isset($_POST['services']) ? json_decode(sanitize_text_field(wp_unslash($_POST['services'])), true) : null;
        
        // Use centralized method
        $result = $this->generate_single_order_label($order_id, $count, $print_position, $cod_reference, $services);
        
        if ($result['success']) {
            wp_send_json_success(array('success' => true));
        } else {
            wp_send_json_error(array('success' => false, 'error' => $result['error']));
        }
    }

    public function save_label_and_tracking_info($body, $order_id)
    {
        $order = wc_get_order($order_id);
        if (!empty($body['Labels'])) {
            $this->save_print_labels($body['Labels'], $order_id, $order);
        }

        if (!empty($body['PrintLabelsInfoList'])) {
            $this->save_tracking_info($body['PrintLabelsInfoList'], $order_id, $order);
        }

        // Fire hook after successful label generation
        do_action('gls_label_generated', $order_id, $order, $body);
    }

    public function save_print_labels($labels, $order_id, $order)
    {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    
        WP_Filesystem();
        global $wp_filesystem;
    
        $label_print = implode(array_map('chr', $labels));
        
        // Ensure labels directory exists
        \GLS_Shipping_For_Woo::get_instance()->setup_labels_directory();
        
        // Use secure labels directory
        $timestamp = current_time('YmdHis');
        $file_name = 'shipping_label_' . $order_id . '_' . $timestamp . '.pdf';
        $file_path = \GLS_Shipping_For_Woo::get_labels_dir() . '/' . $file_name;
        
        if ($wp_filesystem->put_contents($file_path, $label_print)) {
            // Store just the filename, URL with nonce is generated on display
            $order->update_meta_data('_gls_print_label', $file_name);
            $order->save();
        }
    }
    

    
    public function save_tracking_info($printLabelsInfoList, $order_id, $order)
    {
        $tracking_codes = array();
        $parcel_ids = array();
    
        foreach ($printLabelsInfoList as $labelInfo) {
            if (isset($labelInfo['ParcelNumber'])) {
                $tracking_codes[] = $labelInfo['ParcelNumber'];
            }
            if (isset($labelInfo['ParcelId'])) {
                $parcel_ids[] = $labelInfo['ParcelId'];
            }
        }
    
        if (!empty($tracking_codes)) {
            $order->update_meta_data('_gls_tracking_codes', $tracking_codes);
        }
    
        if (!empty($parcel_ids)) {
            $order->update_meta_data('_gls_parcel_ids', $parcel_ids);
        }
    
        $order->save();
    }

    public function get_parcel_status()
    {
        if (!isset($_POST['postNonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['postNonce'])), 'import-nonce')) {
            wp_send_json_error(array('error' => 'Invalid security token'));
            wp_die();
        }

        $order_id = isset($_POST['orderId']) ? intval($_POST['orderId']) : 0;
        $parcel_number = isset($_POST['parcelNumber']) ? sanitize_text_field(wp_unslash($_POST['parcelNumber'])) : '';

        if (empty($order_id) || empty($parcel_number)) {
            wp_send_json_error(array('error' => 'Missing order ID or parcel number'));
            wp_die();
        }

        try {
            $api_service = new \GLS_Shipping_API_Service();
            $tracking_data = $api_service->get_parcel_status($parcel_number);
            
            wp_send_json_success(array('tracking_data' => $tracking_data));
        } catch (Exception $e) {
            wp_send_json_error(array('error' => $e->getMessage()));
        }

        wp_die();
    }

    /**
     * Render service options for order screen
     */
    private function render_service_options($order)
    {
        $gls_shipping_method_settings = get_option("woocommerce_gls_shipping_method_settings");
        $shipping_country = $order->get_shipping_country();
        
        // Get saved order-specific services
        $saved_order_services = $order->get_meta('_gls_services', true);
        $saved_order_services = is_array($saved_order_services) ? $saved_order_services : array();
        
        // Helper function to get checked status - prioritize order-specific settings
        $get_checked_status = function($service_key, $global_key = null) use ($saved_order_services, $gls_shipping_method_settings) {
            // Check if order has specific service setting
            if (isset($saved_order_services[$service_key])) {
                return $saved_order_services[$service_key] === 'on' || $saved_order_services[$service_key] === 'yes';
            }
            // Fall back to global setting
            $global_key = $global_key ?: $service_key;
            return ($gls_shipping_method_settings[$global_key] ?? 'no') === 'yes';
        };
        
        // Helper function to get field value - prioritize order-specific settings
        $get_field_value = function($service_key, $global_key = null) use ($saved_order_services, $gls_shipping_method_settings) {
            // Check if order has specific service setting
            if (isset($saved_order_services[$service_key])) {
                return $saved_order_services[$service_key];
            }
            // Fall back to global setting
            $global_key = $global_key ?: $service_key;
            return $gls_shipping_method_settings[$global_key] ?? '';
        };
        
        ob_start();
        ?>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-size: 13px;">
            
            <!-- 24H Service -->
            <?php if ($shipping_country !== 'RS') { ?>
            <div>
                <label>
                    <input type="checkbox" id="gls_service_24h" name="gls_service_24h" 
                           <?php checked($get_checked_status('service_24h')); ?>>
                    <?php esc_html_e('24H Service', 'gls-shipping-for-woocommerce'); ?>
                </label>
            </div>
            <?php } ?>
            
            <!-- Express Delivery Service -->
            <div>
                <label><?php esc_html_e('Express Service:', 'gls-shipping-for-woocommerce'); ?></label>
                <select id="gls_express_delivery_service" name="gls_express_delivery_service" style="width: 100%; margin-top: 2px;">
                    <?php $express_value = $get_field_value('express_delivery_service'); ?>
                    <option value="" <?php selected($express_value, ''); ?>><?php esc_html_e('Disabled', 'gls-shipping-for-woocommerce'); ?></option>
                    <option value="T09" <?php selected($express_value, 'T09'); ?>>T09 (09:00)</option>
                    <option value="T10" <?php selected($express_value, 'T10'); ?>>T10 (10:00)</option>
                    <option value="T12" <?php selected($express_value, 'T12'); ?>>T12 (12:00)</option>
                </select>
            </div>
            
            <!-- Contact Service -->
            <div>
                <label>
                    <input type="checkbox" id="gls_contact_service" name="gls_contact_service"
                           <?php checked($get_checked_status('contact_service')); ?>>
                    <?php esc_html_e('Contact Service (CS1)', 'gls-shipping-for-woocommerce'); ?>
                </label>
            </div>
            
            <!-- Flexible Delivery Service -->
            <div>
                <label>
                    <input type="checkbox" id="gls_flexible_delivery_service" name="gls_flexible_delivery_service"
                           <?php checked($get_checked_status('flexible_delivery_service')); ?>>
                    <?php esc_html_e('Flexible Delivery (FDS)', 'gls-shipping-for-woocommerce'); ?>
                </label>
            </div>
            
            <!-- Flexible Delivery SMS Service -->
            <div>
                <label>
                    <input type="checkbox" id="gls_flexible_delivery_sms_service" name="gls_flexible_delivery_sms_service"
                           <?php checked($get_checked_status('flexible_delivery_sms_service')); ?>>
                    <?php esc_html_e('Flexible Delivery SMS (FSS)', 'gls-shipping-for-woocommerce'); ?>
                </label>
            </div>
            
            <!-- SMS Service -->
            <div>
                <label>
                    <input type="checkbox" id="gls_sms_service" name="gls_sms_service"
                           <?php checked($get_checked_status('sms_service')); ?>>
                    <?php esc_html_e('SMS Service (SM1)', 'gls-shipping-for-woocommerce'); ?>
                </label>
            </div>
            
            <!-- SMS Pre-advice Service -->
            <div>
                <label>
                    <input type="checkbox" id="gls_sms_pre_advice_service" name="gls_sms_pre_advice_service"
                           <?php checked($get_checked_status('sms_pre_advice_service')); ?>>
                    <?php esc_html_e('SMS Pre-advice (SM2)', 'gls-shipping-for-woocommerce'); ?>
                </label>
            </div>
            
            <!-- Addressee Only Service -->
            <div>
                <label>
                    <input type="checkbox" id="gls_addressee_only_service" name="gls_addressee_only_service"
                           <?php checked($get_checked_status('addressee_only_service')); ?>>
                    <?php esc_html_e('Addressee Only (AOS)', 'gls-shipping-for-woocommerce'); ?>
                </label>
            </div>
            
            <!-- Insurance Service -->
            <div>
                <label>
                    <input type="checkbox" id="gls_insurance_service" name="gls_insurance_service"
                           <?php checked($get_checked_status('insurance_service')); ?>>
                    <?php esc_html_e('Insurance (INS)', 'gls-shipping-for-woocommerce'); ?>
                </label>
            </div>
            
        </div>
        
        <!-- SMS Service Text -->
        <div id="gls_sms_text_container" style="margin-top: 10px; display: <?php echo $get_checked_status('sms_service') ? 'block' : 'none'; ?>;">
            <label><?php esc_html_e('SMS Text:', 'gls-shipping-for-woocommerce'); ?></label>
            <input type="text" id="gls_sms_service_text" name="gls_sms_service_text" 
                   value="<?php echo esc_attr($get_field_value('sms_service_text')); ?>" 
                   style="width: 100%; margin-top: 2px;" 
                   placeholder="Max 130 characters">
            <small style="color: #666;"><?php esc_html_e('Variables: #ParcelNr#, #COD#, #PickupDate#, #From_Name#, #ClientRef#', 'gls-shipping-for-woocommerce'); ?></small>
        </div>
        
        <?php
        return ob_get_clean();
    }

    /**
     * Handle AJAX request to update pickup location
     */
    public function update_pickup_location()
    {
        if (!isset($_POST['postNonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['postNonce'])), 'import-nonce')) {
            wp_send_json_error(array('error' => 'Invalid security token'));
            wp_die();
        }

        $order_id = isset($_POST['orderId']) ? intval($_POST['orderId']) : 0;
        $pickup_info = isset($_POST['pickupInfo']) ? sanitize_text_field(wp_unslash($_POST['pickupInfo'])) : '';

        if (empty($order_id) || empty($pickup_info)) {
            wp_send_json_error(array('error' => 'Missing order ID or pickup information'));
            wp_die();
        }

        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                wp_send_json_error(array('error' => 'Order not found'));
                wp_die();
            }

            // Update the pickup location
            $order->update_meta_data('_gls_pickup_info', $pickup_info);
            $order->save();

            // Add order note about the change
            $pickup_data = json_decode($pickup_info);
            if ($pickup_data) {
                $note = sprintf(
                    /* translators: %1$s: pickup location name, %2$s: pickup location ID */
                    __('GLS pickup location changed to: %1$s (%2$s)', 'gls-shipping-for-woocommerce'),
                    $pickup_data->name,
                    $pickup_data->id
                );
                $order->add_order_note($note);
            }

            wp_send_json_success(array('message' => __('Pickup location updated successfully', 'gls-shipping-for-woocommerce')));
        } catch (Exception $e) {
            wp_send_json_error(array('error' => $e->getMessage()));
        }

        wp_die();
    }

    /**
     * Save GLS settings from POST data
     *
     * @param int $order_id The order ID
     */
    public function save_gls_order_settings($order_id)
    {
        // Verify this is an admin request
        if (!is_admin()) {
            return;
        }

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (did_action('save_post_shop_order') > 1) {
            return;
        }

        // Check if this is an order edit page
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, array('shop_order', 'woocommerce_page_wc-orders'), true)) {
            return;
        }

        // Verify WooCommerce meta box nonce
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verification only
        $nonce = isset($_POST['woocommerce_meta_nonce']) ? wp_unslash($_POST['woocommerce_meta_nonce']) : '';
        if (!wp_verify_nonce($nonce, 'woocommerce_save_data')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Save number of packages if provided
        if (isset($_POST['gls_label_count']) && !empty($_POST['gls_label_count'])) {
            $label_count = intval($_POST['gls_label_count']);
            if ($label_count > 0) {
                $order->update_meta_data('_gls_label_count', $label_count);
            }
        }

        // Save print position if provided (check both possible fields)
        $print_position = null;
        if (isset($_POST['gls_print_position']) && !empty($_POST['gls_print_position'])) {
            $print_position = intval($_POST['gls_print_position']);
        } elseif (isset($_POST['gls_print_position_new']) && !empty($_POST['gls_print_position_new'])) {
            $print_position = intval($_POST['gls_print_position_new']);
        }

        if ($print_position !== null) {
            $order->update_meta_data('_gls_print_position', $print_position);
        }

        // Save COD reference if provided (check both possible fields)
        $cod_reference = null;
        if (isset($_POST['gls_cod_reference']) && !empty($_POST['gls_cod_reference'])) {
            $cod_reference = sanitize_text_field(wp_unslash($_POST['gls_cod_reference']));
        } elseif (isset($_POST['gls_cod_reference_new']) && !empty($_POST['gls_cod_reference_new'])) {
            $cod_reference = sanitize_text_field(wp_unslash($_POST['gls_cod_reference_new']));
        }

        if ($cod_reference !== null) {
            $order->update_meta_data('_gls_cod_reference', $cod_reference);
        }

        // Save services in API format (without gls_ prefix, with yes/no values)
        $services = array();
        $service_field_mappings = array(
            'gls_service_24h' => 'service_24h',
            'gls_contact_service' => 'contact_service',
            'gls_flexible_delivery_service' => 'flexible_delivery_service',
            'gls_flexible_delivery_sms_service' => 'flexible_delivery_sms_service',
            'gls_sms_service' => 'sms_service',
            'gls_sms_pre_advice_service' => 'sms_pre_advice_service',
            'gls_addressee_only_service' => 'addressee_only_service',
            'gls_insurance_service' => 'insurance_service'
        );

        // Process checkbox services
        foreach ($service_field_mappings as $form_field => $api_field) {
            $services[$api_field] = isset($_POST[$form_field]) ? 'yes' : 'no';
        }

        // Special handling for select and text fields
        if (isset($_POST['gls_express_delivery_service'])) {
            $services['express_delivery_service'] = sanitize_text_field(wp_unslash($_POST['gls_express_delivery_service']));
        }
        if (isset($_POST['gls_sms_service_text'])) {
            $services['sms_service_text'] = sanitize_text_field(wp_unslash($_POST['gls_sms_service_text']));
        }

        if (!empty($services)) {
            $order->update_meta_data('_gls_services', $services);
        }

        $order->save();
    }
}

new GLS_Shipping_Order();
