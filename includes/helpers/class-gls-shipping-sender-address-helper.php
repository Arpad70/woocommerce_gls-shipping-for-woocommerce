<?php

/**
 * Class GLS_Shipping_Sender_Address_Helper
 *
 * Handles sender address management for GLS Shipping.
 * This class provides centralized access to sender addresses without dependencies on shipping method instances.
 */
class GLS_Shipping_Sender_Address_Helper
{
    /**
     * Get the default sender address
     * 
     * @return array Default sender address or fallback to store address
     */
    public static function get_default_sender_address()
    {
        $sender_addresses = self::get_all_sender_addresses();
        
        // Look for the default address
        foreach ($sender_addresses as $address) {
            if (!empty($address['is_default'])) {
                return $address;
            }
        }
        
        // No default found = "none" was selected = use WooCommerce store address
        return self::get_store_fallback_address();
    }

    /**
     * Get all sender addresses
     * 
     * @return array All sender addresses
     */
    public static function get_all_sender_addresses()
    {
        $settings = get_option("woocommerce_gls_shipping_method_settings", array());
        return $settings['sender_addresses_grid'] ?? array();
    }

    /**
     * Check if sender addresses are configured
     * 
     * @return bool True if addresses exist
     */
    public static function has_sender_addresses()
    {
        $addresses = self::get_all_sender_addresses();
        return !empty($addresses);
    }

    /**
     * Get store fallback address when no sender addresses are configured
     * 
     * @return array Store address formatted for GLS API
     */
    public static function get_store_fallback_address()
    {
        // Get phone number from plugin settings
        $settings = get_option("woocommerce_gls_shipping_method_settings", array());
        $store_phone = isset($settings['phone_number']) ? $settings['phone_number'] : '';
        
        return array(
            'name' => get_bloginfo('name'),
            'contact_name' => get_bloginfo('name'),
            'street' => get_option('woocommerce_store_address', ''),
            'house_number' => '',
            'city' => get_option('woocommerce_store_city', ''),
            'postcode' => get_option('woocommerce_store_postcode', ''),
            'country' => self::get_store_country(),
            'phone' => $store_phone,
            'email' => get_option('admin_email', ''),
            'is_default' => false
        );
    }

    /**
     * Get all addresses including store fallback as first option
     * 
     * @return array All addresses with store address as first item
     */
    public static function get_all_addresses_with_store_fallback()
    {
        $addresses = array();
        
        // Add configured sender addresses
        $sender_addresses = self::get_all_sender_addresses();
        foreach ($sender_addresses as $address) {
            $addresses[] = $address;
        }
        
        // Always add store address as option (for pickup interface)
        $store_address = self::get_store_fallback_address();
        $addresses[] = $store_address;
        
        return $addresses;
    }


    /**
     * Save sender addresses to database
     * 
     * @param array $addresses Array of sender addresses
     * @return bool Success status
     */
    public static function save_sender_addresses($addresses)
    {
        $settings = get_option("woocommerce_gls_shipping_method_settings", array());
        $settings['sender_addresses_grid'] = $addresses;
        return update_option("woocommerce_gls_shipping_method_settings", $settings);
    }

    /**
     * Add new sender address
     * 
     * @param array $address New address data
     * @param bool $set_as_default Whether to set as default
     * @return bool Success status
     */
    public static function add_sender_address($address, $set_as_default = false)
    {
        $addresses = self::get_all_sender_addresses();
        
        // If setting as default, clear other defaults
        if ($set_as_default) {
            foreach ($addresses as &$existing_address) {
                $existing_address['is_default'] = false;
            }
            $address['is_default'] = true;
        }
        
        $addresses[] = $address;
        return self::save_sender_addresses($addresses);
    }

    /**
     * Update sender address by index
     * 
     * @param int $index Address index
     * @param array $address Updated address data
     * @return bool Success status
     */
    public static function update_sender_address($index, $address)
    {
        $addresses = self::get_all_sender_addresses();
        
        if (!isset($addresses[$index])) {
            return false;
        }
        
        // If setting as default, clear other defaults
        if (!empty($address['is_default'])) {
            foreach ($addresses as $key => &$existing_address) {
                if ($key !== $index) {
                    $existing_address['is_default'] = false;
                }
            }
        }
        
        $addresses[$index] = $address;
        return self::save_sender_addresses($addresses);
    }

    /**
     * Delete sender address by index
     * 
     * @param int $index Address index
     * @return bool Success status
     */
    public static function delete_sender_address($index)
    {
        $addresses = self::get_all_sender_addresses();
        
        if (!isset($addresses[$index])) {
            return false;
        }
        
        unset($addresses[$index]);
        // Reindex array
        $addresses = array_values($addresses);
        
        return self::save_sender_addresses($addresses);
    }

    /**
     * Set default sender address by index
     * 
     * @param int $index Address index
     * @return bool Success status
     */
    public static function set_default_sender_address($index)
    {
        $addresses = self::get_all_sender_addresses();
        
        if (!isset($addresses[$index])) {
            return false;
        }
        
        // Clear all defaults
        foreach ($addresses as &$address) {
            $address['is_default'] = false;
        }
        
        // Set new default
        $addresses[$index]['is_default'] = true;
        
        return self::save_sender_addresses($addresses);
    }

    /**
     * Format sender address for GLS API pickup address
     * 
     * @param array $sender_address Sender address data
     * @param string $fallback_phone Fallback phone number
     * @return array Formatted pickup address for API
     */
    public static function format_for_api_pickup($sender_address, $fallback_phone = '')
    {
        $street = '';
        if (!empty($sender_address['street']) || !empty($sender_address['house_number'])) {
            $street = trim($sender_address['street'] . ' ' . $sender_address['house_number']);
        }

        return array(
            'Name' => !empty($sender_address['name']) ? $sender_address['name'] : get_bloginfo('name'),
            'Street' => !empty($street) ? $street : (get_option('woocommerce_store_address') . ' ' . get_option('woocommerce_store_address_2')),
            'City' => !empty($sender_address['city']) ? $sender_address['city'] : get_option('woocommerce_store_city'),
            'ZipCode' => !empty($sender_address['postcode']) ? $sender_address['postcode'] : get_option('woocommerce_store_postcode'),
            'CountryIsoCode' => !empty($sender_address['country']) ? $sender_address['country'] : self::get_store_country(),
            'ContactName' => !empty($sender_address['contact_name']) ? $sender_address['contact_name'] : (!empty($sender_address['name']) ? $sender_address['name'] : get_bloginfo('name')),
            'ContactPhone' => !empty($sender_address['phone']) ? $sender_address['phone'] : $fallback_phone,
            'ContactEmail' => !empty($sender_address['email']) ? $sender_address['email'] : get_option('admin_email')
        );
    }

    /**
     * Get store country code
     * 
     * @return string Country code
     */
    private static function get_store_country()
    {
        $store_raw_country = get_option('woocommerce_default_country');
        $split_country = explode(":", $store_raw_country);
        return isset($split_country[0]) ? $split_country[0] : 'HR';
    }
}
