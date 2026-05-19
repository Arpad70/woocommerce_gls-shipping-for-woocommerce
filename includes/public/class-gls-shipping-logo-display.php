<?php

/**
 * Handles GLS logo display on shipping methods
 *
 * @since     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GLS_Shipping_Logo_Display
{

    public function __construct()
    {
        // Add logo to shipping method labels
        add_filter('woocommerce_cart_shipping_method_full_label', array($this, 'add_gls_logo_to_shipping_method'), 10, 2);
        
        // Also add to shipping method labels during checkout
        add_filter('woocommerce_package_rates', array($this, 'add_gls_logo_to_shipping_rates'), 10, 2);
        
        // Enqueue logo styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_logo_styles'));
    }

    /**
     * Add GLS logo to shipping method labels
     */
    public function add_gls_logo_to_shipping_method($label, $method)
    {
        // Check if logo display is enabled
        if (!$this->is_logo_display_enabled()) {
            return $label;
        }

        // Check if this is a GLS shipping method
        if ($this->is_gls_shipping_method($method)) {
            $logo_url = $this->get_gls_logo_url();
            $logo_html = '<img src="' . esc_url($logo_url) . '" alt="GLS" class="gls-shipping-logo" style="max-height: 16px; height: auto; width: auto; margin-right: 8px; vertical-align: middle; display: inline;">';
            
            // Add logo before the label
            $label = $logo_html . $label;
        }

        return $label;
    }

    /**
     * Add GLS logo to shipping rates
     */
    public function add_gls_logo_to_shipping_rates($rates, $package)
    {
        // Check if logo display is enabled
        if (!$this->is_logo_display_enabled()) {
            return $rates;
        }

        foreach ($rates as $rate_key => $rate) {
            if ($this->is_gls_shipping_method($rate)) {
                $logo_url = $this->get_gls_logo_url();
                $logo_html = '<img src="' . esc_url($logo_url) . '" alt="GLS" class="gls-shipping-logo" style="max-height: 16px; height: auto; width: auto; margin-right: 8px; vertical-align: middle; display: inline;">';
                
                // Modify the rate label to include the logo
                $current_label = $rate->get_label();
                
                // Only add logo if it's not already there
                if (strpos($current_label, 'gls-shipping-logo') === false) {
                    $rate->set_label($logo_html . $current_label);
                }
            }
        }

        return $rates;
    }

    /**
     * Check if logo display is enabled in settings
     */
    private function is_logo_display_enabled()
    {
        $gls_shipping_settings = get_option('woocommerce_gls_shipping_method_settings', array());
        return isset($gls_shipping_settings['show_gls_logo']) && $gls_shipping_settings['show_gls_logo'] === 'yes';
    }

    /**
     * Check if the shipping method is a GLS method
     */
    private function is_gls_shipping_method($method)
    {
        $gls_method_prefixes = array(
            'gls_shipping_method',
            'gls_shipping_method_zones',
            'gls_shipping_method_parcel_shop',
            'gls_shipping_method_parcel_locker',
            'gls_shipping_method_parcel_shop_zones',
            'gls_shipping_method_parcel_locker_zones'
        );

        $method_id = $method->get_method_id();
        
        foreach ($gls_method_prefixes as $prefix) {
            if (strpos($method_id, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the GLS logo URL
     */
    private function get_gls_logo_url()
    {
        return GLS_SHIPPING_URL . 'assets/img/gls_logo.svg';
    }

    /**
     * Enqueue logo display styles
     */
    public function enqueue_logo_styles()
    {
        if (!$this->is_logo_display_enabled()) {
            return;
        }

        // Only load on cart and checkout pages
        if (is_cart() || is_checkout()) {
            wp_add_inline_style('woocommerce-general', '
                .gls-shipping-logo {
                    display: inline !important;
                    max-height: 32px !important;
                    height: auto !important;
                    width: auto !important;
                    margin-right: 8px !important;
                    margin-left: 0 !important;
                    vertical-align: middle !important;
                    max-width: 80px !important;
                    border: none !important;
                    box-shadow: none !important;
                    background: none !important;
                }
                
                /* Ensure proper alignment in shipping methods list */
                .woocommerce-shipping-methods .gls-shipping-logo,
                .wc_payment_methods .gls-shipping-logo,
                .shipping_method .gls-shipping-logo,
                label[for*="shipping_method"] .gls-shipping-logo {
                    margin-right: 8px !important;
                    vertical-align: middle !important;
                }
                
                /* Ensure logo appears inline with radio buttons */
                .woocommerce-shipping-methods li label,
                .shipping_method label {
                    display: inline-flex !important;
                    align-items: center !important;
                    flex-wrap: wrap !important;
                }
                
                /* Responsive adjustments */
                @media (max-width: 768px) {
                    .gls-shipping-logo {
                        max-height: 28px !important;
                        margin-right: 6px !important;
                        max-width: 70px !important;
                    }
                }
                
                /* Dark mode compatibility */
                @media (prefers-color-scheme: dark) {
                    .gls-shipping-logo {
                        filter: brightness(1.1);
                    }
                }
            ');
        }
    }
}

new GLS_Shipping_Logo_Display();
