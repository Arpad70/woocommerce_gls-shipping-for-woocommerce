<?php

/**
 * Handles GLS pickup scheduling functionality
 *
 * @since     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GLS_Shipping_Pickup
{

    public function __construct()
    {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_pickup_admin_menu'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_pickup_scripts'));
        
        // Initialize pickup history
        new GLS_Shipping_Pickup_History();
    }

    /**
     * Add GLS Pickup admin menu
     */
    public function add_pickup_admin_menu()
    {
        add_submenu_page(
            'woocommerce',
            __('GLS Pickup', 'gls-shipping-for-woocommerce'),
            __('GLS Pickup', 'gls-shipping-for-woocommerce'),
            'manage_woocommerce',
            'gls-pickup',
            array($this, 'pickup_admin_page')
        );
    }

    /**
     * Enqueue pickup scripts
     */
    public function enqueue_pickup_scripts($hook)
    {
        if ($hook !== 'woocommerce_page_gls-pickup') {
            return;
        }

        // Get addresses for JavaScript
        $all_addresses = GLS_Shipping_Sender_Address_Helper::get_all_addresses_with_store_fallback();
        
        $translation_array = array(
            'addresses' => $all_addresses,
        );
        
        wp_localize_script('jquery', 'glsPickupData', $translation_array);
        
        wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                // Set minimum date to today for HTML5 date inputs
                const today = new Date().toISOString().split("T")[0];
                $("#pickup_date_from, #pickup_date_to").attr("min", today);
                
                // Address selection handler
                const addresses = glsPickupData.addresses;
                $("#sender_address_select").on("change", function() {
                    const selectedIndex = $(this).val();
                    const selectedAddress = addresses[selectedIndex];
                    
                    if (selectedAddress) {
                        // Show address details
                        const addressText = selectedAddress.name + "<br>" +
                            selectedAddress.street + " " + (selectedAddress.house_number || "") + "<br>" +
                            selectedAddress.postcode + " " + selectedAddress.city + "<br>" +
                            selectedAddress.country + "<br>" +
                            "Phone: " + (selectedAddress.phone || "N/A") + "<br>" +
                            "Email: " + (selectedAddress.email || "N/A");
                        
                        $("#address-details-text").html(addressText);
                        $("#selected-address-details").show();
                    } else {
                        // Hide address details if no valid address selected
                        $("#selected-address-details").hide();
                    }
                });
                
                // Trigger initial population
                $("#sender_address_select").trigger("change");
            });
        ');
    }

    /**
     * Render pickup admin page
     */
    public function pickup_admin_page()
    {
        // Handle form submission
        $message = '';
        $error = '';
        
        if (isset($_POST['schedule_pickup']) && isset($_POST['gls_pickup_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['gls_pickup_nonce'])), 'gls_pickup_action')) {
            $result = $this->process_pickup_form();
            if (is_wp_error($result)) {
                $error = $result->get_error_message();
            } else {
                $message = __('Pickup scheduled successfully!', 'gls-shipping-for-woocommerce');
            }
        }
        
        // Get all addresses (including store fallback as first option)
        $all_addresses = GLS_Shipping_Sender_Address_Helper::get_all_addresses_with_store_fallback();
        
        $current_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'schedule';
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('GLS Pickup Management', 'gls-shipping-for-woocommerce'); ?></h1>
            
            <?php if ($message): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html($error); ?></p>
                </div>
            <?php endif; ?>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=gls-pickup&tab=schedule" class="nav-tab <?php echo $current_tab === 'schedule' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Schedule Pickup', 'gls-shipping-for-woocommerce'); ?>
                </a>
                <a href="?page=gls-pickup&tab=history" class="nav-tab <?php echo $current_tab === 'history' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Pickup History', 'gls-shipping-for-woocommerce'); ?>
                </a>
            </nav>
            
            <?php if ($current_tab === 'schedule'): ?>
                <?php $this->render_schedule_tab($all_addresses); ?>
            <?php elseif ($current_tab === 'history'): ?>
                <?php $this->render_history_tab(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render schedule pickup tab
     */
    private function render_schedule_tab($all_addresses)
    {
        ?>
        <div class="tab-content">
            <p><?php esc_html_e('Schedule a pickup request with GLS to collect packages from your location.', 'gls-shipping-for-woocommerce'); ?></p>
            
            <form id="gls-pickup-form" method="post">
                <?php wp_nonce_field('gls_pickup_action', 'gls_pickup_nonce'); ?>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="package_count"><?php esc_html_e('Number of Packages', 'gls-shipping-for-woocommerce'); ?> *</label>
                            </th>
                            <td>
                                <input type="number" id="package_count" name="package_count" min="1" value="1" required class="regular-text" />
                                <p class="description"><?php esc_html_e('Total number of packages to be collected.', 'gls-shipping-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="pickup_date_from"><?php esc_html_e('Pickup Date From', 'gls-shipping-for-woocommerce'); ?> *</label>
                            </th>
                            <td>
                                <input type="date" id="pickup_date_from" name="pickup_date_from" required class="regular-text" style="width: 160px;" />
                                <input type="time" id="pickup_time_from" name="pickup_time_from" value="08:00" class="regular-text" style="width: 120px; margin-left: 10px;" />
                                <p class="description"><?php esc_html_e('Earliest date and time for pickup.', 'gls-shipping-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="pickup_date_to"><?php esc_html_e('Pickup Date To', 'gls-shipping-for-woocommerce'); ?> *</label>
                            </th>
                            <td>
                                <input type="date" id="pickup_date_to" name="pickup_date_to" required class="regular-text" style="width: 160px;" />
                                <input type="time" id="pickup_time_to" name="pickup_time_to" value="17:00" class="regular-text" style="width: 120px; margin-left: 10px;" />
                                <p class="description"><?php esc_html_e('Latest date and time for pickup.', 'gls-shipping-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <h2><?php esc_html_e('Pickup Address', 'gls-shipping-for-woocommerce'); ?></h2>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="sender_address_select"><?php esc_html_e('Pickup Address', 'gls-shipping-for-woocommerce'); ?> *</label>
                            </th>
                            <td>
                                <select id="sender_address_select" name="sender_address_select" class="regular-text" required>
                                    <?php foreach ($all_addresses as $index => $address): ?>
                                        <option value="<?php echo esc_attr($index); ?>" <?php selected($index, 0); ?>>
                                            <?php echo esc_html($address['name'] . ' - ' . $address['city']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e('Choose the pickup address from configured sender addresses or store default.', 'gls-shipping-for-woocommerce'); ?></p>
                                
                                <!-- Display selected address details -->
                                <div id="selected-address-details" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-left: 4px solid #0073aa; display: none;">
                                    <strong><?php esc_html_e('Selected Address Details:', 'gls-shipping-for-woocommerce'); ?></strong><br>
                                    <span id="address-details-text"></span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <?php submit_button(__('Schedule Pickup', 'gls-shipping-for-woocommerce'), 'primary', 'schedule_pickup'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render pickup history tab
     */
    private function render_history_tab()
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display-only pagination/filtering
        // Check if viewing details
        if (isset($_GET['view_details'])) {
            $this->render_pickup_details(intval($_GET['view_details']));
            return;
        }

        // Get filter parameters
        $search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field(wp_unslash($_GET['status_filter'])) : '';
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $per_page = 20;

        // Get history data
        $history = new GLS_Shipping_Pickup_History();
        $data = $history->get_pickup_history($current_page, $per_page, $search, $status_filter);
        
        ?>
        <div class="tab-content">
            <div class="pickup-history-container" style="margin-top: 24px;">
                <?php $this->render_history_table($data); ?>
                <?php $this->render_history_pagination($data, $search, $status_filter); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render pickup details view
     */
    private function render_pickup_details($pickup_id)
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Permission denied.', 'gls-shipping-for-woocommerce'));
        }

        $history = new GLS_Shipping_Pickup_History();
        $pickup = $history->get_pickup_by_id($pickup_id);

        if (!$pickup) {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e('Pickup not found.', 'gls-shipping-for-woocommerce'); ?></p>
            </div>
            <a href="?page=gls-pickup&tab=history" class="button" style="margin-top: 24px;"><?php esc_html_e('← Back to History', 'gls-shipping-for-woocommerce'); ?></a>
            <?php
            return;
        }

        ?>
        <div class="tab-content">
            <div style="margin-bottom: 20px;">
                <a href="?page=gls-pickup&tab=history" class="button" style="margin-top: 24px;"><?php esc_html_e('← Back to History', 'gls-shipping-for-woocommerce'); ?></a>
            </div>

            <?php /* translators: %d: pickup ID number */ ?>
            <h2><?php echo esc_html(sprintf(__('Pickup Details #%d', 'gls-shipping-for-woocommerce'), $pickup->id)); ?></h2>
            
            <div class="pickup-details-container">
                <div class="pickup-info">
                    <h3><?php esc_html_e('Request Information', 'gls-shipping-for-woocommerce'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('Status', 'gls-shipping-for-woocommerce'); ?></th>
                            <td>
                                <span class="pickup-status status-<?php echo esc_attr($pickup->status); ?>">
                                    <?php 
                                    switch($pickup->status) {
                                        case 'success':
                                            esc_html_e('Success', 'gls-shipping-for-woocommerce');
                                            break;
                                        case 'error':
                                            esc_html_e('Error', 'gls-shipping-for-woocommerce');
                                            break;
                                        default:
                                            esc_html_e('Pending', 'gls-shipping-for-woocommerce');
                                    }
                                    ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Created', 'gls-shipping-for-woocommerce'); ?></th>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($pickup->created_at))); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Updated', 'gls-shipping-for-woocommerce'); ?></th>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($pickup->updated_at))); ?></td>
                        </tr>
                        <?php if ($pickup->pickup_id): ?>
                        <tr>
                            <th><?php esc_html_e('Pickup ID', 'gls-shipping-for-woocommerce'); ?></th>
                            <td><?php echo esc_html($pickup->pickup_id); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                    
                    <?php if ($pickup->request_data): ?>
                    <h3><?php esc_html_e('Request Data', 'gls-shipping-for-woocommerce'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('Package Count', 'gls-shipping-for-woocommerce'); ?></th>
                            <td><?php echo esc_html($pickup->request_data['package_count'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Pickup From', 'gls-shipping-for-woocommerce'); ?></th>
                            <td><?php echo esc_html($pickup->request_data['pickup_date_from'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Pickup To', 'gls-shipping-for-woocommerce'); ?></th>
                            <td><?php echo esc_html($pickup->request_data['pickup_date_to'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Contact Name', 'gls-shipping-for-woocommerce'); ?></th>
                            <td><?php echo esc_html($pickup->request_data['contact_name'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Contact Email', 'gls-shipping-for-woocommerce'); ?></th>
                            <td><?php echo esc_html($pickup->request_data['contact_email'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Contact Phone', 'gls-shipping-for-woocommerce'); ?></th>
                            <td><?php echo esc_html($pickup->request_data['contact_phone'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Address', 'gls-shipping-for-woocommerce'); ?></th>
                            <td>
                                <?php 
                                $address_parts = array();
                                if (!empty($pickup->request_data['street'])) {
                                    $address_parts[] = $pickup->request_data['street'] . ' ' . ($pickup->request_data['house_number'] ?? '');
                                }
                                if (!empty($pickup->request_data['zip_code']) || !empty($pickup->request_data['city'])) {
                                    $address_parts[] = ($pickup->request_data['zip_code'] ?? '') . ' ' . ($pickup->request_data['city'] ?? '');
                                }
                                if (!empty($pickup->request_data['country_code'])) {
                                    $address_parts[] = $pickup->request_data['country_code'];
                                }
                                echo esc_html(implode(', ', array_filter($address_parts)) ?: '-');
                                ?>
                            </td>
                        </tr>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render history table
     */
    private function render_history_table($data)
    {
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 60px;"><?php esc_html_e('ID', 'gls-shipping-for-woocommerce'); ?></th>
                    <th><?php esc_html_e('Account', 'gls-shipping-for-woocommerce'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Count', 'gls-shipping-for-woocommerce'); ?></th>
                    <th><?php esc_html_e('Time From', 'gls-shipping-for-woocommerce'); ?></th>
                    <th><?php esc_html_e('Time To', 'gls-shipping-for-woocommerce'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Actions', 'gls-shipping-for-woocommerce'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($data['items'])): ?>
                    <?php foreach ($data['items'] as $item): ?>
                        <tr>
                            <td><?php echo esc_html($item->id); ?></td>
                            <td>
                                <?php 
                                $account_info = '';
                                if (!empty($item->request_data['contact_name'])) {
                                    $account_info = $item->request_data['contact_name'];
                                } elseif (!empty($item->request_data['address_name'])) {
                                    $account_info = $item->request_data['address_name'];
                                } else {
                                    $account_info = '-';
                                }
                                echo esc_html($account_info);
                                ?>
                            </td>
                            <td><?php echo esc_html($item->request_data['package_count'] ?? '-'); ?></td>
                            <td>
                                <?php 
                                $time_from = $item->request_data['pickup_date_from'] ?? '';
                                if ($time_from) {
                                    echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($time_from)));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                $time_to = $item->request_data['pickup_date_to'] ?? '';
                                if ($time_to) {
                                    echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($time_to)));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <a href="?page=gls-pickup&tab=history&view_details=<?php echo esc_attr($item->id); ?>" 
                                   class="button button-small"><?php esc_html_e('View', 'gls-shipping-for-woocommerce'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center;"><?php esc_html_e('No pickup requests found.', 'gls-shipping-for-woocommerce'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <style>
            .pickup-status { 
                padding: 3px 8px; 
                border-radius: 3px; 
                font-size: 11px; 
                font-weight: bold; 
                text-transform: uppercase;
            }
            .status-success { 
                background: #d4edda; 
                color: #155724; 
            }
            .status-error { 
                background: #f8d7da; 
                color: #721c24; 
            }
            .status-pending { 
                background: #fff3cd; 
                color: #856404; 
            }
        </style>
        <?php
    }

    /**
     * Render history pagination
     */
    private function render_history_pagination($data, $search = '', $status_filter = '')
    {
        if ($data['pages'] <= 1) {
            return;
        }

        $base_url = admin_url('admin.php?page=gls-pickup&tab=history');
        if ($search) {
            $base_url .= '&search=' . urlencode($search);
        }
        if ($status_filter) {
            $base_url .= '&status_filter=' . urlencode($status_filter);
        }

        ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php /* translators: %d: number of items */ ?>
                <span class="displaying-num"><?php echo esc_html(sprintf(__('%d items', 'gls-shipping-for-woocommerce'), $data['total'])); ?></span>
                <span class="pagination-links">
                    <?php if ($data['current_page'] > 1): ?>
                        <a class="first-page button" href="<?php echo esc_url($base_url . '&paged=1'); ?>">
                            <span aria-hidden="true">«</span>
                        </a>
                        <a class="prev-page button" href="<?php echo esc_url($base_url . '&paged=' . ($data['current_page'] - 1)); ?>">
                            <span aria-hidden="true">‹</span>
                        </a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $data['current_page'] - 2);
                    $end = min($data['pages'], $data['current_page'] + 2);
                    
                    for ($i = $start; $i <= $end; $i++): ?>
                        <?php if ($i == $data['current_page']): ?>
                            <span class="paging-input">
                                <span class="tablenav-paging-text"><?php echo esc_html($i); ?> of <?php echo esc_html($data['pages']); ?></span>
                            </span>
                        <?php else: ?>
                            <a class="button" href="<?php echo esc_url($base_url . '&paged=' . $i); ?>"><?php echo esc_html($i); ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($data['current_page'] < $data['pages']): ?>
                        <a class="next-page button" href="<?php echo esc_url($base_url . '&paged=' . ($data['current_page'] + 1)); ?>">
                            <span aria-hidden="true">›</span>
                        </a>
                        <a class="last-page button" href="<?php echo esc_url($base_url . '&paged=' . $data['pages']); ?>">
                            <span aria-hidden="true">»</span>
                        </a>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <?php
    }



    /**
     * Process pickup form submission
     * Nonce verified in calling method (render_pickup_page)
     */
    private function process_pickup_form()
    {
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            return new WP_Error('permission_denied', __('Permission denied.', 'gls-shipping-for-woocommerce'));
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in render_pickup_page() before calling this method
        try {
            // Get all addresses for validation
            $all_addresses = GLS_Shipping_Sender_Address_Helper::get_all_addresses_with_store_fallback();
            $sender_address_index = isset($_POST['sender_address_select']) ? intval($_POST['sender_address_select']) : 0;
            
            if (!isset($all_addresses[$sender_address_index])) {
                return new WP_Error('invalid_address', __('Invalid sender address selected.', 'gls-shipping-for-woocommerce'));
            }
            
            $selected_address = $all_addresses[$sender_address_index];

            // Validate required fields
            $package_count = isset($_POST['package_count']) ? intval($_POST['package_count']) : 0;
            if ($package_count < 1) {
                return new WP_Error('invalid_package_count', __('Package count must be at least 1.', 'gls-shipping-for-woocommerce'));
            }

            $pickup_date_from = isset($_POST['pickup_date_from']) ? sanitize_text_field(wp_unslash($_POST['pickup_date_from'])) : '';
            $pickup_date_to = isset($_POST['pickup_date_to']) ? sanitize_text_field(wp_unslash($_POST['pickup_date_to'])) : '';

            if (empty($pickup_date_from) || empty($pickup_date_to)) {
                return new WP_Error('missing_dates', __('Pickup dates are required.', 'gls-shipping-for-woocommerce'));
            }

            // Combine date and time fields
            $pickup_time_from = !empty($_POST['pickup_time_from']) ? sanitize_text_field(wp_unslash($_POST['pickup_time_from'])) : '08:00';
            $pickup_time_to = !empty($_POST['pickup_time_to']) ? sanitize_text_field(wp_unslash($_POST['pickup_time_to'])) : '17:00';
            
            $pickup_datetime_from = $pickup_date_from . ' ' . $pickup_time_from;
            $pickup_datetime_to = $pickup_date_to . ' ' . $pickup_time_to;

            // Create pickup request data from selected address
            $pickup_data = array(
                'package_count' => $package_count,
                'pickup_date_from' => $pickup_datetime_from,
                'pickup_date_to' => $pickup_datetime_to,
                'contact_name' => $selected_address['contact_name'] ?? $selected_address['name'] ?? '',
                'contact_phone' => $selected_address['phone'] ?? '',
                'contact_email' => $selected_address['email'] ?? '',
                'address_name' => $selected_address['name'] ?? '',
                'street' => $selected_address['street'] ?? '',
                'house_number' => $selected_address['house_number'] ?? '',
                'city' => $selected_address['city'] ?? '',
                'zip_code' => $selected_address['postcode'] ?? '',
                'country_code' => $selected_address['country'] ?? 'HR'
            );

            // Call API service
            $api_service = new GLS_Shipping_Pickup_API_Service();
            $result = $api_service->create_pickup_request($pickup_data);
            
            // Save only successful requests to history
            $history = new GLS_Shipping_Pickup_History();
            $history->save_pickup_request($pickup_data, $result, 'success');
            
            return $result;

        } catch (Exception $e) {
            return new WP_Error('api_error', $e->getMessage());
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

}

new GLS_Shipping_Pickup();
