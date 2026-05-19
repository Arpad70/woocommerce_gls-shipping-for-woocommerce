<?php

/**
 * Class GLS_Shipping_Account_Helper
 *
 * Centralized account management for GLS Shipping.
 * Handles both single and multiple account modes.
 */
class GLS_Shipping_Account_Helper
{
    /**
     * Get the active account from settings
     * 
     * @return array|false Active account data or false if none found
     */
    public static function get_active_account()
    {
        $settings = get_option("woocommerce_gls_shipping_method_settings", array());
        $account_mode = isset($settings['account_mode']) ? $settings['account_mode'] : 'single';
        
        if ($account_mode === 'single') {
            // Return single account data
            return array(
                'client_id' => $settings['client_id'] ?? '',
                'username' => $settings['username'] ?? '',
                'password' => $settings['password'] ?? '',
                'country' => $settings['country'] ?? 'HR',
                'mode' => $settings['mode'] ?? 'production'
            );
        }
        
        // Multiple accounts mode
        $accounts = $settings['gls_accounts_grid'] ?? array();
        
        if (empty($accounts)) {
            return false;
        }
        
        // Find the active account
        foreach ($accounts as $account) {
            if (!empty($account['active']) && $account['active']) {
                // Verify the account has required credentials
                if (!empty($account['client_id']) && !empty($account['username']) && !empty($account['password'])) {
                    return $account;
                }
            }
        }
        
        // Fallback: return first account with valid credentials
        foreach ($accounts as $account) {
            if (!empty($account['client_id']) && !empty($account['username']) && !empty($account['password'])) {
                return $account;
            }
        }
        
        // No valid accounts found
        return false;
    }
    
    /**
     * Get all available GLS accounts
     * 
     * @return array All accounts (single or multiple mode)
     */
    public static function get_all_accounts()
    {
        $settings = get_option("woocommerce_gls_shipping_method_settings", array());
        $account_mode = isset($settings['account_mode']) ? $settings['account_mode'] : 'single';
        
        if ($account_mode === 'single') {
            return array(
                array(
                    'name' => __('Default Account', 'gls-shipping-for-woocommerce'),
                    'client_id' => $settings['client_id'] ?? '',
                    'username' => $settings['username'] ?? '',
                    'password' => $settings['password'] ?? '',
                    'country' => $settings['country'] ?? 'HR',
                    'mode' => $settings['mode'] ?? 'production'
                )
            );
        }
        
        return $settings['gls_accounts_grid'] ?? array();
    }
    
    /**
     * Check if we're in multiple accounts mode
     * 
     * @return bool
     */
    public static function is_multiple_accounts_mode()
    {
        $settings = get_option("woocommerce_gls_shipping_method_settings", array());
        return isset($settings['account_mode']) && $settings['account_mode'] === 'multiple';
    }
    
    /**
     * Get account-specific setting value
     * Checks active account first (for account-specific keys), then falls back to global setting
     * 
     * @param string $key Setting key
     * @return mixed Setting value
     */
    public static function get_account_setting($key)
    {
        $settings = get_option("woocommerce_gls_shipping_method_settings", array());
        
        // Define which settings are account-specific (stored per account)
        $account_specific_keys = ['client_id', 'username', 'password', 'country', 'mode'];
        
        // Only check account level for account-specific settings
        if (in_array($key, $account_specific_keys) && self::is_multiple_accounts_mode()) {
            $active_account = self::get_active_account();
            if ($active_account && isset($active_account[$key])) {
                return $active_account[$key];
            }
        }
        
        // For all other settings, get from global settings
        return $settings[$key] ?? null;
    }
    
    /**
     * Get allowed account countries
     * 
     * @return array Allowed country codes
     */
    public static function get_allowed_account_countries()
    {
        return array('CZ', 'HR', 'HU', 'RO', 'SI', 'SK', 'RS');
    }

    /**
     * Validate accounts grid data
     * 
     * @param array $value Raw accounts data from form
     * @return array Validated accounts data
     */
    public static function validate_accounts_grid($value)
    {
        if (!is_array($value)) {
            return array();
        }

        $allowed_countries = self::get_allowed_account_countries();
        $validated_accounts = array();
        $has_active = false;

        foreach ($value as $index => $account) {
            if (is_array($account)) {
                // Validate required fields
                $required_fields = array('client_id', 'username', 'password');
                $is_valid = true;

                foreach ($required_fields as $field) {
                    if (empty($account[$field])) {
                        $is_valid = false;
                        break;
                    }
                }

                if ($is_valid) {
                    // Use client_id + username combination as unique key
                    $unique_key = sanitize_text_field($account['client_id']) . '_' . sanitize_text_field($account['username']);
                    
                    // Skip if this combination already exists
                    if (isset($validated_accounts[$unique_key])) {
                        continue;
                    }
                    
                    // Validate country
                    $country = sanitize_text_field($account['country'] ?? 'HR');
                    if (!in_array($country, $allowed_countries)) {
                        $country = 'HR'; // Default to HR if invalid country
                    }
                    
                    $validated_account = array(
                        'name' => sanitize_text_field($account['client_id']),
                        'client_id' => sanitize_text_field($account['client_id']),
                        'username' => sanitize_text_field($account['username']),
                        'password' => sanitize_text_field($account['password']),
                        'country' => $country,
                        'mode' => sanitize_text_field($account['mode'] ?? 'production'),
                        'active' => !empty($account['active']) && $account['active'] === '1'
                    );

                    // Ensure only one active account
                    if ($validated_account['active']) {
                        if ($has_active) {
                            $validated_account['active'] = false;
                        } else {
                            $has_active = true;
                        }
                    }

                    $validated_accounts[$unique_key] = $validated_account;
                }
            }
        }

        // If no active account is selected but we have valid accounts, make the first one active
        if (!$has_active && !empty($validated_accounts)) {
            $first_key = array_key_first($validated_accounts);
            $validated_accounts[$first_key]['active'] = true;
        }

        return $validated_accounts;
    }
}
