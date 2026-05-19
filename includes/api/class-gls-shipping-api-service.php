<?php

/**
 * Manages GLS API.
 *
 */

class GLS_Shipping_API_Service
{


	/**
	 * @var string API url
	 */
	private $api_url;

	private $service_settings;

	/**
	 * Constructor.
	 *
	 */
	public function __construct()
	{
		$this->service_settings = get_option("woocommerce_gls_shipping_method_settings");
		$this->api_url = $this->get_api_url('ParcelService', 'PrintLabels');
	}

	public function get_option($key)
	{
		return GLS_Shipping_Account_Helper::get_account_setting($key);
	}


	public function get_api_url($serviceName, $methodName, $format = 'json')
	{
		$countryCode = $this->get_option('country');
		$mode = $this->get_option('mode');

		$baseUrl = $mode === 'production' ? 'https://api.mygls.' : 'https://api.test.mygls.';
		return $baseUrl . $countryCode . '/' . $serviceName . '.svc/' . $format . '/' . $methodName;
	}

	public function get_password()
	{
		$password = $this->get_option("password");
		if (!$password) {
			throw new Exception('Password not set for GLS API');
		}

		$passwordData = unpack('C*', hash('sha512', $password, true)) ?: []; // phpcs:ignore
		return array_values($passwordData);
	}

	private function generate_post_request($post_fields)
	{
		$post_fields['Username'] = $this->get_option("username");
		$post_fields['Password'] = $this->get_password();

		$params = array(
			'headers'     => array('Content-Type' => 'application/json'),
			'body'        => wp_json_encode($post_fields),
			'method'      => 'POST',
			'timeout' 	  => 60,
			'data_format' => 'body',
		);
		return $params;
	}

	public function send_order($post_fields, $is_multi = false)
	{
		$params = $this->generate_post_request($post_fields);
		$response = wp_remote_post($this->api_url, $params);

		if (is_wp_error($response)) {
			$error_message = esc_html($response->get_error_message());
			$this->log_error($error_message, $post_fields);
			throw new Exception('Error communicating with GLS API: ' . esc_html($error_message));
		}

		$response_code = wp_remote_retrieve_response_code($response);
		$body = json_decode(wp_remote_retrieve_body($response), true);

		// Check for HTTP errors (like 401 Unauthorized, 403 Forbidden, etc.)
		if ($response_code >= 400) {
			$error_message = 'GLS API HTTP Error ' . $response_code;
			if ($response_code == 401) {
				$error_message = 'Authentication failed - check your GLS API credentials';
			} elseif ($response_code == 403) {
				$error_message = 'Access forbidden - check your GLS API permissions';
			} elseif (isset($body['Message'])) {
				$error_message .= ': ' . esc_html($body['Message']);
			}
			$this->log_error($error_message, $post_fields);
			throw new Exception(esc_html($error_message));
		}

		// Check for JSON decode errors
		if (json_last_error() !== JSON_ERROR_NONE) {
			$error_message = 'Invalid JSON response from GLS API: ' . json_last_error_msg();
			$this->log_error($error_message, $post_fields);
			throw new Exception(esc_html($error_message));
		}

		// Check for general API errors (authentication, authorization, etc.)
		if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
			$error_message = isset($body['ErrorDescription']) ? esc_html($body['ErrorDescription']) : 'Unknown GLS API error';
			$this->log_error($error_message, $post_fields);
			throw new Exception(esc_html($error_message));
		}

		$failed_orders = [];

		if (!empty($body['PrintLabelsErrorList'])) {
			foreach ($body['PrintLabelsErrorList'] as $error) {
				$error_message = esc_html($error['ErrorDescription']) ?? esc_html('GLS API error.');
				$error_code = $error['ErrorCode'] ?? '';
				
				// If ClientReferenceList is empty, this is likely a general error (like authentication)
				if (empty($error['ClientReferenceList'])) {
					$this->log_error($error_message, $post_fields);
					throw new Exception(esc_html($error_message));
				}
				
				// Process order-specific errors
				foreach ($error['ClientReferenceList'] as $clientRef) {
					$order_id = str_replace('Order:', '', $clientRef);
					$failed_orders[] = [
						'order_id' => $order_id,
						'error_message' => $error_message,
						'error_code' => $error_code
					];
				}
				$this->log_error($error_message, $post_fields);
			}
			if (!$is_multi && count($failed_orders) > 0) {
				throw new Exception(esc_html($failed_orders[0]['error_message']));
			}
		}

		if ($this->get_option("logging") === 'yes') {
			$this->log_response($body, $response, $post_fields);
		}

		return [
			'body' => $body,
			'failed_orders' => $failed_orders
		];
		
	}

	public function get_parcel_status($parcel_number)
	{
		$this->api_url = $this->get_api_url('ParcelService', 'GetParcelStatuses');

		$post_fields = array(
			'Username' => $this->get_option("username"),
			'Password' => $this->get_password(),
			'ParcelNumber' => intval($parcel_number),
			'ReturnPOD' => true
		);

		$params = array(
			'headers'     => array('Content-Type' => 'application/json'),
			'body'        => wp_json_encode($post_fields),
			'method'      => 'POST',
			'timeout' 	  => 60,
			'data_format' => 'body',
		);

		$response = wp_remote_post($this->api_url, $params);

		if (is_wp_error($response)) {
			$error_message = esc_html($response->get_error_message());
			$this->log_error($error_message, $post_fields);
			throw new Exception('Error communicating with GLS API: ' . esc_html($error_message));
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);

		// Always log the response if logging is enabled (both success and error cases)
		if ($this->get_option("logging") === 'yes') {
			$this->log_response($body, $response, $post_fields);
		}

		// Check for errors in the tracking response
		if (!empty($body['GetParcelStatusErrors'])) {
			$errors = array();
			foreach ($body['GetParcelStatusErrors'] as $error) {
				if (is_array($error) || is_object($error)) {
					$errors[] = json_encode($error);
				} else {
					$errors[] = (string)$error;
				}
			}
			$error_message = 'Tracking error: ' . implode(', ', $errors);
			// Also log the error with more context
			$this->log_error($error_message . ' (Parcel Number: ' . $parcel_number . ')', $post_fields);
			throw new Exception(esc_html($error_message));
		}

		return $body;
	}

	private function sanitize_params_for_logging($params)
	{
		$sanitized = $params;
		// Remove sensitive data from logs
		if (isset($sanitized['Username'])) {
			$sanitized['Username'] = '***REDACTED***';
		}
		if (isset($sanitized['Password'])) {
			$sanitized['Password'] = '***REDACTED***';
		}
		return $sanitized;
	}

	private function log_error($error_message, $params)
	{
		$sanitized_params = $this->sanitize_params_for_logging($params);
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional API error logging for debugging
		error_log('** API request to: ' . $this->api_url . ' FAILED **
			Request Params: {' . wp_json_encode($sanitized_params) . '}
			Error: ' . $error_message . '
			** END **');
	}

	private function log_response($body, $response, $params)
	{
		$sanitized_params = $this->sanitize_params_for_logging($params);
		
		// Sanitize large binary data from logs
		if (isset($body['Labels']) && $body['Labels']) {
			$body['Labels'] = 'SANITIZED';
		}
		if (isset($body['POD']) && $body['POD']) {
			$body['POD'] = 'SANITIZED';
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional API response logging for debugging
		error_log('** API request to: ' . $this->api_url . ' SUCCESS **
				Request Params: {' . wp_json_encode($sanitized_params) . '}
				Response Body: ' . wp_json_encode($body) . '
				** END **');
	}
}
