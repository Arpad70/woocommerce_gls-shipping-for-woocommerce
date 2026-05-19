<?php

/**
 * Handles showing of order Information
 *
 * @since     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GLS_Shipping_My_Account
{

    public function __construct()
    {
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_gls_pickup_info_on_account_page'), 30, 1);
        add_action('woocommerce_email_order_details', array($this, 'add_gls_info_to_order_email'), 20, 4);
    }

    private function display_gls_pickup_info($order_id)
    {
        $order = wc_get_order($order_id);

        // Return early if order is false (e.g., during email previews)
        if (!$order) {
            return '';
        }

        $gls_pickup_info = $order->get_meta('_gls_pickup_info', true);
        $tracking_code   = $order->get_meta('_gls_tracking_code', true);

        if (!empty($gls_pickup_info)) {
            $pickup_info = json_decode($gls_pickup_info);

            echo '<strong>' . esc_html__('GLS Pickup Location:', 'gls-shipping-for-woocommerce') . '</strong><br>';
            echo '<strong>' . esc_html__('ID:', 'gls-shipping-for-woocommerce') . '</strong> ' . esc_html($pickup_info->id) . '<br>';
            echo '<strong>' . esc_html__('Name:', 'gls-shipping-for-woocommerce') . '</strong> ' . esc_html($pickup_info->name) . '<br>';
            echo '<strong>' . esc_html__('Address:', 'gls-shipping-for-woocommerce') . '</strong> ' . esc_html($pickup_info->contact->address) . ', ' . esc_html($pickup_info->contact->city) . ', ' . esc_html($pickup_info->contact->postalCode) . '<br>';
            echo '<strong>' . esc_html__('Country:', 'gls-shipping-for-woocommerce') . '</strong> ' . esc_html($pickup_info->contact->countryCode) . '<br/><br/>';
        }

        if ($tracking_code) {
            $gls_shipping_method_settings = get_option("woocommerce_gls_shipping_method_settings");
            $tracking_url = "https://gls-group.eu/" . $gls_shipping_method_settings['country'] . "/en/parcel-tracking/?match=" . $tracking_code;

            echo '<strong>' . esc_html__('GLS Tracking Number: ', 'gls-shipping-for-woocommerce') . '<a href="' . esc_url($tracking_url) . '" target="_blank">' . esc_html($tracking_code) . '</a></strong><br>';
        }
    }

    public function display_gls_pickup_info_on_account_page($order)
    {
        echo '<div style="border: 1px solid #ddd;padding: 20px;margin-bottom: 24px;">';
        $this->display_gls_pickup_info($order->get_id());
        echo "</div>";
    }

    public function add_gls_info_to_order_email($order, $sent_to_admin, $plain_text, $email)
    {
        // Only add info to customer emails, not admin emails
        if ($sent_to_admin) {
            // return;
        }

        // Only add info to processing and completed order
        if ($email->id != 'customer_completed_order' && $email->id != 'customer_processing_order') {
            // return;
        }

        $this->display_gls_pickup_info($order->get_id());
    }
}

new GLS_Shipping_My_Account();
