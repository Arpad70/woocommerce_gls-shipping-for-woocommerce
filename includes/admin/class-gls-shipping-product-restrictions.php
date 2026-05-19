<?php

/**
 * Handles product-level shipping restrictions for GLS
 *
 * @since     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GLS_Shipping_Product_Restrictions
{
    public function __construct()
    {
        // Add product shipping restriction field
        add_action('woocommerce_product_options_shipping', array($this, 'add_product_shipping_restriction_field'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_shipping_restriction_field'));
        
        // Filter shipping methods based on cart contents
        add_filter('woocommerce_package_rates', array($this, 'filter_shipping_methods_by_cart_restrictions'), 10, 2);
        
        // Add WooCommerce notice when shipping methods are restricted
        add_action('woocommerce_check_cart_items', array($this, 'add_shipping_restriction_notice'));
    }

    /**
     * Add shipping restriction checkbox to product edit screen
     */
    public function add_product_shipping_restriction_field()
    {
        global $post;

        echo '<div class="options_group">';
        
        woocommerce_wp_checkbox(array(
            'id' => '_gls_restrict_parcel_shipping',
            'label' => __('GLS Shipping Restriction', 'gls-shipping-for-woocommerce'),
            'description' => __('Check this box if this product cannot be shipped to parcel shops or parcel lockers. This will hide those shipping options when this product is in the cart.', 'gls-shipping-for-woocommerce'),
            'desc_tip' => true,
        ));
        
        echo '</div>';
    }

    /**
     * Save the shipping restriction field
     */
    public function save_product_shipping_restriction_field($post_id)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce handles nonce verification for product meta
        $restrict_parcel_shipping = isset($_POST['_gls_restrict_parcel_shipping']) ? 'yes' : 'no';
        update_post_meta($post_id, '_gls_restrict_parcel_shipping', $restrict_parcel_shipping);
    }

    /**
     * Filter shipping methods based on cart product restrictions
     */
    public function filter_shipping_methods_by_cart_restrictions($rates, $package)
    {
        if (is_admin() || !WC()->cart) {
            return $rates;
        }

        $has_restricted_products = $this->cart_has_restricted_products();
        
        if (!$has_restricted_products) {
            return $rates;
        }

        // Remove parcel shop and parcel locker shipping methods
        $restricted_methods = array(
            'gls_shipping_method_parcel_shop',
            'gls_shipping_method_parcel_locker',
            'gls_shipping_method_parcel_shop_zones',
            'gls_shipping_method_parcel_locker_zones'
        );

        foreach ($rates as $rate_key => $rate) {
            foreach ($restricted_methods as $restricted_method) {
                if (strpos($rate_key, $restricted_method) === 0) {
                    unset($rates[$rate_key]);
                    break;
                }
            }
        }

        return $rates;
    }

    /**
     * Check if cart contains products with parcel shipping restrictions
     */
    private function cart_has_restricted_products()
    {
        if (!WC()->cart) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            $restrict_parcel_shipping = get_post_meta($product_id, '_gls_restrict_parcel_shipping', true);
            
            if ($restrict_parcel_shipping === 'yes') {
                return true;
            }
        }

        return false;
    }

    /**
     * Add WooCommerce notice when shipping methods are restricted due to cart contents
     */
    public function add_shipping_restriction_notice()
    {
        if (is_admin() || !WC()->cart) {
            return;
        }

        $has_restricted_products = $this->cart_has_restricted_products();
        
        if ($has_restricted_products && $this->has_enabled_parcel_methods()) {
            $restricted_products = $this->get_restricted_product_names();
            
            if (!empty($restricted_products)) {
                $message = sprintf(
                    /* translators: %s: comma-separated list of product names that have shipping restrictions */
                    __('Some products in your cart (%s) cannot be shipped to parcel shops or parcel lockers. Only standard delivery options are available.', 'gls-shipping-for-woocommerce'),
                    implode(', ', $restricted_products)
                );
                
                // Use WooCommerce's built-in notice system
                wc_add_notice($message, 'notice');
            }
        }
    }

    /**
     * Check if any parcel methods are enabled
     */
    private function has_enabled_parcel_methods()
    {
        $parcel_shop_enabled = get_option('woocommerce_gls_shipping_method_parcel_shop_settings', array());
        $parcel_locker_enabled = get_option('woocommerce_gls_shipping_method_parcel_locker_settings', array());
        $parcel_shop_zones_enabled = get_option('woocommerce_gls_shipping_method_parcel_shop_zones_settings', array());
        $parcel_locker_zones_enabled = get_option('woocommerce_gls_shipping_method_parcel_locker_zones_settings', array());
        
        return (
            (isset($parcel_shop_enabled['enabled']) && $parcel_shop_enabled['enabled'] === 'yes') ||
            (isset($parcel_locker_enabled['enabled']) && $parcel_locker_enabled['enabled'] === 'yes') ||
            (isset($parcel_shop_zones_enabled['enabled']) && $parcel_shop_zones_enabled['enabled'] === 'yes') ||
            (isset($parcel_locker_zones_enabled['enabled']) && $parcel_locker_zones_enabled['enabled'] === 'yes')
        );
    }

    /**
     * Get names of restricted products in cart
     */
    private function get_restricted_product_names()
    {
        $restricted_products = array();
        
        if (!WC()->cart) {
            return $restricted_products;
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            $restrict_parcel_shipping = get_post_meta($product_id, '_gls_restrict_parcel_shipping', true);
            
            if ($restrict_parcel_shipping === 'yes') {
                $product = wc_get_product($product_id);
                if ($product) {
                    $restricted_products[] = $product->get_name();
                }
            }
        }

        return array_unique($restricted_products);
    }
}

new GLS_Shipping_Product_Restrictions();
