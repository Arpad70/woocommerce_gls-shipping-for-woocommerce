<?php

/**
 * Handles GLS Pickup API requests
 *
 * @since     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GLS_Shipping_Pickup_API_Service
{
    private $service_settings;

    public function __construct()
    {
        $this->service_settings = get_option("woocommerce_gls_shipping_method_settings");
    }

    /**
     * Get option value (supports multiple accounts)
     */
    public function get_option($key)
    {
        return GLS_Shipping_Account_Helper::get_account_setting($key);
    }

    /**
     * Get API URL for pickup requests
     */
    private function get_pickup_api_url()
    {
        $countryCode = $this->get_option('country');
        $mode = $this->get_option('mode');

        $baseUrl = $mode === 'production' ? 'https://api.mygls.' : 'https://api.test.mygls.';
        return $baseUrl . $countryCode . '/ParcelService.svc/json/CreatePickupRequest';
    }

    /**
     * Get password as byte array
     */
    private function get_password()
    {
        $password = $this->get_option("password");
        if (!$password) {
            throw new Exception('Password not set for GLS API');
        }

        $passwordData = unpack('C*', hash('sha512', $password, true)) ?: [];
        return array_values($passwordData);
    }

        /**
     * Convert date to .NET format
     */
private function convert_date_to_dotnet_format($date_string)
    {
        $timestamp = strtotime($date_string);
        
        // Handle invalid date
        if ($timestamp === false) {
            throw new Exception(esc_html('Invalid date format: ' . $date_string));
        }
        
        return '/Date(' . ($timestamp * 1000) . ')/';
    }

    /**
     * Create pickup request
     */
    public function create_pickup_request($pickup_data)
    {
        try {
            // Validate API credentials
            $username = $this->get_option("username");
            $client_number = $this->get_option("client_id");
            
            if (!$username || !$client_number) {
                throw new Exception(__('GLS API credentials not configured.', 'gls-shipping-for-woocommerce'));
            }

            // Prepare request data
            $request_data = array(
                'Username' => $username,
                'Password' => $this->get_password(),
                'ClientNumber' => $client_number,
                'Count' => $pickup_data['package_count'],
                'PickupTimeFrom' => $this->convert_date_to_dotnet_format($pickup_data['pickup_date_from']),
                'PickupTimeTo' => $this->convert_date_to_dotnet_format($pickup_data['pickup_date_to']),
                'Address' => array(
                    'Name' => $pickup_data['address_name'],
                    'ContactName' => $pickup_data['contact_name'],
                    'ContactPhone' => $pickup_data['contact_phone'],
                    'ContactEmail' => $pickup_data['contact_email'],
                    'Street' => $pickup_data['street'],
                    'HouseNumber' => $pickup_data['house_number'],
                    'City' => $pickup_data['city'],
                    'ZipCode' => $pickup_data['zip_code'],
                    'CountryIsoCode' => $pickup_data['country_code']
                )
            );

            // Make API request
            $api_url = $this->get_pickup_api_url();
            $response = $this->send_pickup_request($api_url, $request_data);

            // Log the response if logging is enabled
            if ($this->get_option("logging") === 'yes') {
                $this->log_pickup_response($response, $request_data);
            }

            return $response;

        } catch (Exception $e) {
            // Log error if logging is enabled
            if ($this->get_option("logging") === 'yes') {
                $this->log_pickup_error($e->getMessage(), $pickup_data);
            }
            
            throw $e;
        }
    }

    /**
     * Send pickup request to GLS API
     */
    private function send_pickup_request($api_url, $request_data)
    {
        $params = array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode($request_data),
            'method' => 'POST',
            'timeout' => 60,
            'data_format' => 'body',
        );

        $response = wp_remote_post($api_url, $params);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            throw new Exception(esc_html('Error communicating with GLS API: ' . $error_message));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200) {
            throw new Exception(esc_html('GLS API returned error code: ' . $response_code));
        }

        // Check for API errors
        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            $error_message = isset($body['ErrorDescription']) ? $body['ErrorDescription'] : 'Unknown API error';
            throw new Exception(esc_html('GLS API Error: ' . $error_message));
        }

        // Check for pickup request specific errors
        if (isset($body['PickupRequestErrors']) && !empty($body['PickupRequestErrors'])) {
            $pickup_errors = $body['PickupRequestErrors'];
            $error_messages = array();

            foreach ($pickup_errors as $error) {
                if (isset($error['ErrorCode']) && $error['ErrorCode'] !== 0) {
                    $error_msg = isset($error['ErrorDescription']) ? $error['ErrorDescription'] : 'Unknown pickup error';
                    $error_messages[] = $error_msg;
                }
            }

            if (!empty($error_messages)) {
                throw new Exception(esc_html('GLS Pickup Error: ' . implode('; ', $error_messages)));
            }
        }

        return $body;
    }

    /**
     * Log pickup response
     */
    private function sanitize_request_data_for_logging($request_data)
    {
        $sanitized = $request_data;
        // Remove sensitive data from logs
        if (isset($sanitized['Username'])) {
            $sanitized['Username'] = '***REDACTED***';
        }
        if (isset($sanitized['Password'])) {
            $sanitized['Password'] = '***REDACTED***';
        }
        return $sanitized;
    }

    private function log_pickup_response($response, $request_data)
    {
        $sanitized_request_data = $this->sanitize_request_data_for_logging($request_data);
        
        // Sanitize large binary data from logs
        if (isset($response['Labels']) && $response['Labels']) {
            $response['Labels'] = 'SANITIZED';
        }
        if (isset($response['POD']) && $response['POD']) {
            $response['POD'] = 'SANITIZED';
        }
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'action' => 'pickup_request',
            'request' => $sanitized_request_data,
            'response' => $response
        );

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional API logging for debugging
        error_log('GLS Pickup API Response: ' . wp_json_encode($log_entry));
    }

    /**
     * Log pickup error
     */
    private function log_pickup_error($error_message, $pickup_data)
    {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'action' => 'pickup_request_error',
            'error' => $error_message,
            'request_data' => $pickup_data // pickup_data doesn't contain credentials, safe to log
        );

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional API error logging for debugging
        error_log('GLS Pickup API Error: ' . wp_json_encode($log_entry));
    }
}
