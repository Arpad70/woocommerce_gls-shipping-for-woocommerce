<?php

/**
 * Class GLS_Shipping_API_Data
 *
 * Handles data formatting for API calls to GLS Shipping service.
 */
class GLS_Shipping_API_Data
{
    /**
     * @var array $orders Array of WooCommerce order instances and their data.
     */
    private $orders = [];

    /**
     * @var array $shipping_method_settings Stores GLS shipping method settings.
     */
    private $shipping_method_settings;

    /**
     * Constructor for GLS_Shipping_API_Data.
     *
     * @param int|array $order_ids Single order ID or array of order IDs.
     */
    public function __construct($order_ids)
    {
        $this->shipping_method_settings = get_option("woocommerce_gls_shipping_method_settings");

        if (is_array($order_ids)) {
            foreach ($order_ids as $order_id) {
                $this->add_order($order_id);
            }
        } else {
            $this->add_order($order_ids);
        }
    }

    /**
     * Adds an order to the orders array.
     *
     * @param int $order_id The WooCommerce order ID.
     */
    private function add_order($order_id)
    {
        $order = wc_get_order($order_id);
        if ($order) {
            $this->orders[] = [
                'order' => $order,
                'is_parcel_delivery_service' => $this->check_parcel_delivery_service($order),
                'pickup_info' => $this->get_pickup_info($order)
            ];
        }
    }

    /**
     * Checks if the order is for parcel delivery service.
     *
     * @param \WC_Order $order The WooCommerce order instance.
     * @return bool
     */
    private function check_parcel_delivery_service($order)
    {
        $shipping_methods = $order->get_shipping_methods();
        $gls_shipping_methods = [
            GLS_SHIPPING_METHOD_PARCEL_LOCKER_ID,
            GLS_SHIPPING_METHOD_PARCEL_SHOP_ID,
            GLS_SHIPPING_METHOD_PARCEL_LOCKER_ZONES_ID,
            GLS_SHIPPING_METHOD_PARCEL_SHOP_ZONES_ID
        ];

        foreach ($shipping_methods as $shipping_method) {
            if (in_array($shipping_method->get_method_id(), $gls_shipping_methods)) {
                return true;
            }
        }

        return false;
    }


    /**
     * Gets pickup information for an order.
     *
     * @param \WC_Order $order The WooCommerce order instance.
     * @return array|null
     */
    private function get_pickup_info($order)
    {
        $pickup_info = $order->get_meta('_gls_pickup_info', true);
        return $pickup_info ? json_decode($pickup_info, true) : null;
    }

    /**
     * Gets phone number with shipping priority over billing.
     *
     * @param \WC_Order $order The WooCommerce order instance.
     * @return string The phone number, preferring shipping over billing.
     */
    private function get_order_phone($order)
    {
        $shipping_phone = $order->get_shipping_phone();
        return !empty($shipping_phone) ? $shipping_phone : $order->get_billing_phone();
    }

    /**
     * Retrieves a specific setting option.
     *
     * @param string $key The key of the option to retrieve.
     * @return mixed|null The value of the specified setting option.
     */
    public function get_option($key)
    {
        return GLS_Shipping_Account_Helper::get_account_setting($key);
    }

    /**
     * Generates the service list for a specific order.
     *
     * @param \WC_Order $order The WooCommerce order instance.
     * @param bool $is_parcel_delivery_service Flag to check if the order is for parcel delivery service.
     * @param array|null $pickup_info Pickup information for the order.
     * @return array List of services included in the shipping.
     */
    public function get_service_list($order, $is_parcel_delivery_service, $pickup_info, $custom_services = null)
    {
        $express_service_is_valid = false;
        $service_list = [];
        
        // If no custom services provided, try to get saved order services first
        if ($custom_services === null) {
            $saved_order_services = $order->get_meta('_gls_services', true);
            if (!empty($saved_order_services) && is_array($saved_order_services)) {
                $custom_services = $saved_order_services;
            }
        }

        // Parcel Shop Delivery Service
        if ($is_parcel_delivery_service) {
            if (!$pickup_info) {
                throw new Exception("Pickup information not found!");
            }

            $service_list[] = [
                'Code' => 'PSD',
                'PSDParameter' => [
                    'StringValue' => $pickup_info['id'] ?? ''
                ]
            ];
        }

        // Guaranteed 24h Service
        $service_24h = $custom_services['service_24h'] ?? $this->get_option('service_24h');
        if ($service_24h === 'yes' && $order->get_shipping_country() !== 'RS') {
            $service_list[] = ['Code' => '24H'];
        }

        // Express Delivery Service
        $expressDeliveryTime = $custom_services['express_delivery_service'] ?? $this->get_option('express_delivery_service');
        if (!$is_parcel_delivery_service && $expressDeliveryTime && $this->isExpressDeliverySupported($expressDeliveryTime, $order)) {
            $express_service_is_valid = true;
            $service_list[] = ['Code' => $expressDeliveryTime];
        }

        // Contact Service
        $contact_service = $custom_services['contact_service'] ?? $this->get_option('contact_service');
        if (!$is_parcel_delivery_service && $contact_service === 'yes') {
            $recipientPhoneNumber = $this->get_order_phone($order);
            $service_list[] = [
                'Code' => 'CS1',
                'CS1Parameter' => [
                    'Value' => $recipientPhoneNumber
                ]
            ];
        }

        // Flexible Delivery Service
        $flexible_delivery_service = $custom_services['flexible_delivery_service'] ?? $this->get_option('flexible_delivery_service');
        if (!$is_parcel_delivery_service && $flexible_delivery_service === 'yes' && !$express_service_is_valid) {
            $recipientEmail = $order->get_billing_email();
            $service_list[] = [
                'Code' => 'FDS',
                'FDSParameter' => [
                    'Value' => $recipientEmail
                ]
            ];
        }

        // Flexible Delivery SMS Service
        $flexible_delivery_sms_service = $custom_services['flexible_delivery_sms_service'] ?? $this->get_option('flexible_delivery_sms_service');
        if (!$is_parcel_delivery_service && $flexible_delivery_sms_service === 'yes' && $flexible_delivery_service === 'yes' && !$express_service_is_valid) {
            $recipientPhoneNumber = $this->get_order_phone($order);
            $service_list[] = [
                'Code' => 'FSS',
                'FSSParameter' => [
                    'Value' => $recipientPhoneNumber
                ]
            ];
        }

        // SMS Service
        $sms_service = $custom_services['sms_service'] ?? $this->get_option('sms_service');
        if ($sms_service === 'yes') {
            $sm1Text = $custom_services['sms_service_text'] ?? $this->get_option('sms_service_text');
            $recipientPhoneNumber = $this->get_order_phone($order);
            $service_list[] = [
                'Code' => 'SM1',
                'SM1Parameter' => [
                    'Value' => "{$recipientPhoneNumber}|$sm1Text"
                ]
            ];
        }

        // SMS Pre-advice Service
        $sms_pre_advice_service = $custom_services['sms_pre_advice_service'] ?? $this->get_option('sms_pre_advice_service');
        if ($sms_pre_advice_service === 'yes') {
            $recipientPhoneNumber = $this->get_order_phone($order);
            $service_list[] = [
                'Code' => 'SM2',
                'SM2Parameter' => [
                    'Value' => $recipientPhoneNumber
                ]
            ];
        }

        // Addressee Only Service
        $addressee_only_service = $custom_services['addressee_only_service'] ?? $this->get_option('addressee_only_service');
        if (!$is_parcel_delivery_service && $addressee_only_service === 'yes') {
            $recipientName = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
            $service_list[] = [
                'Code' => 'AOS',
                'AOSParameter' => [
                    'Value' => $recipientName
                ]
            ];
        }

        // Insurance Service
        $insurance_service = $custom_services['insurance_service'] ?? $this->get_option('insurance_service');
        if ($insurance_service === 'yes' && $this->isInsuranceAllowed($order)) {
            $service_list[] = [
                'Code' => 'INS',
                'INSParameter' => [
                    'Value' => $order->get_total()
                ]
            ];
        }

        return $service_list;
    }

    /**
     * Checks if express delivery is supported for the order.
     *
     * @param string $expressDeliveryTime The express delivery time option.
     * @param \WC_Order $order The WooCommerce order instance.
     * @return bool
     */
    public function isExpressDeliverySupported($expressDeliveryTime, $order)
    {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        WP_Filesystem();
        global $wp_filesystem;

        $countryToCheck = $this->get_option('country');
        $zipcodeToCheck = $order->get_shipping_postcode();

        $file_path = GLS_SHIPPING_ABSPATH . "includes/api/express-service.csv";
        $csv_data = $wp_filesystem->get_contents($file_path);

        if ($csv_data) {
            $lines = explode("\n", $csv_data);
            array_shift($lines);

            foreach ($lines as $line) {
                $data = str_getcsv($line);

                if (!empty($data)) {
                    $country = $data[0];
                    $zipcode = $data[1];

                    if ($country === $countryToCheck && $zipcode === $zipcodeToCheck) {
                        if ($expressDeliveryTime === "T12") {
                            return $data[2] === "x";
                        }
                        if ($expressDeliveryTime === "T09") {
                            return $data[3] === "x";
                        }
                        if ($expressDeliveryTime === "T10") {
                            return $data[4] === "x";
                        }
                        return false;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Checks if insurance is allowed for the order.
     *
     * @param \WC_Order $order The WooCommerce order instance.
     * @return bool Returns true if insurance is allowed, false otherwise.
     */
    public function isInsuranceAllowed($order)
    {
        $packageValue = $order->get_total();
        $originCountry = $this->get_option('country');
        $destinationCountry = $order->get_shipping_country();

        return $this->checkInsuranceCriteria($packageValue, $originCountry, $destinationCountry);
    }

    /**
     * Checks if the package meets the criteria for insurance based on value and origin/destination countries.
     *
     * @param float $packageValue Value of the package.
     * @param string $originCountry Country of origin.
     * @param string $destinationCountry Destination country.
     * @return bool True if criteria are met, otherwise false.
     */
    private function checkInsuranceCriteria($packageValue, $originCountry, $destinationCountry)
    {
        $type = $originCountry === $destinationCountry ? 'country_domestic_insurance' : 'country_export_insurance';

        $minMax = $this->getCode($type, $originCountry);

        if (!$minMax) {
            return false;
        }

        if ($packageValue >= $minMax['min'] && $packageValue <= $minMax['max']) {
            return true;
        }
        return false;
    }

    /**
     * Retrieves GLS carrier configuration data.
     *
     * @param string $type Type of configuration data to retrieve.
     * @param int|string|null $code Specific code to retrieve data for.
     * @return mixed Configuration data.
     */
    public function getCode($type, $code = null)
    {
        $data = [
            'country_calling_code' => [
                'CZ' => '+420',
                'HR' => '+385',
                'HU' => '+36',
                'RO' => '+40',
                'SI' => '+386',
                'SK' => '+421',
                'RS' => '+381',
            ],
            'country_domestic_insurance' => [
                'CZ' => ['min' => 20000, 'max' => 100000], // CZK
                'HR' => ['min' => 165.9, 'max' => 1659.04], // EUR
                'HU' => ['min' => 50000, 'max' => 500000], // HUF
                'RO' => ['min' => 2000, 'max' => 7000], // RON
                'SI' => ['min' => 200, 'max' => 2000], // EUR
                'SK' => ['min' => 332, 'max' => 2655], // EUR
                'RS' => ['min' => 40000, 'max' => 200000] // RSD
            ],
            'country_export_insurance' => [
                'CZ' => ['min' => 20000, 'max' => 100000], // CZK
                'HR' => ['min' => 165.91, 'max' => 663.61], // EUR
                'HU' => ['min' => 50000, 'max' => 200000], // HUF
                'RO' => ['min' => 2000, 'max' => 7000], // RON
                'SI' => ['min' => 200, 'max' => 2000], // EUR
                'SK' => ['min' => 332, 'max' => 1000] // EUR
            ]
        ];

        if ($code === null) {
            return $data[$type] ?? [];
        }

        return $data[$type][$code] ?? null;
    }

    /**
     * Gets the pickup address for the shipment.
     * 
     * Updated to use the new dedicated sender address helper.
     *
     * @param \WC_Order $order The WooCommerce order instance.
     * @return array The pickup address information.
     */
    public function get_pickup_address($order)
    {
        // Get default sender address (includes automatic store fallback)
        $sender_address = GLS_Shipping_Sender_Address_Helper::get_default_sender_address();
        
        // Format for API using helper (includes field-level fallbacks)
        $pickup_address = GLS_Shipping_Sender_Address_Helper::format_for_api_pickup(
            $sender_address, 
            $this->get_option('phone_number')
        );

        return apply_filters('gls_shipping_for_woocommerce_api_get_pickup_address', $pickup_address, $order);
    }
    
    /**
     * Gets the delivery address for a specific order.
     *
     * @param \WC_Order $order The WooCommerce order instance.
     * @return array The delivery address information.
     */
    public function get_delivery_address($order)
    {
        $delivery_address = [
            'Name' => $order->get_shipping_company() ?: $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
            'Street' => $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2(),
            'City' => $order->get_shipping_city(),
            'ZipCode' => $order->get_shipping_postcode(),
            'CountryIsoCode' => $order->get_shipping_country(),
            'ContactName' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
            'ContactPhone' => $this->get_order_phone($order),
            'ContactEmail' => $order->get_billing_email()
        ];

        return $delivery_address;
    }

    /**
     * Generates post fields for the API request for multiple orders.
     *
     * @return array The generated post fields for the API request.
     */
    public function generate_post_fields_multi()
    {
        $parcel_list = [];
        $has_custom_print_position = false;
        $first_print_position = null;

        foreach ($this->orders as $order_data) {
            $order = $order_data['order'];
            $is_parcel_delivery_service = $order_data['is_parcel_delivery_service'];
            $pickup_info = $order_data['pickup_info'];

            $clientReferenceFormat = $this->get_option('client_reference_format');
            $senderIdentityCardNumber = $this->get_option('sender_identity_card_number');
            $content = $this->get_option('content');
            $orderId = $order->get_id();
            $clientReference = str_replace('{{order_id}}', $orderId, $clientReferenceFormat);

            // Get saved order settings
            $saved_services = $order->get_meta('_gls_services', true);
            $saved_cod_reference = $order->get_meta('_gls_cod_reference', true);
            $saved_print_position = $order->get_meta('_gls_print_position', true);
            $saved_label_count = $order->get_meta('_gls_label_count', true);

            // Use saved services if available
            $services = !empty($saved_services) ? $saved_services : null;
            
            // Use saved label count or default to 1
            $label_count = !empty($saved_label_count) ? intval($saved_label_count) : 1;

            // Track print positions for multi-order processing
            if (!empty($saved_print_position)) {
                $print_position = intval($saved_print_position);
                if ($first_print_position === null) {
                    $first_print_position = $print_position;
                } elseif ($first_print_position !== $print_position) {
                    $has_custom_print_position = true;
                }
            }

            $parcel = [
                'ClientNumber' => (int)$this->get_option("client_id"),
                'ClientReference' => $clientReference,
                'Count' => $label_count
            ];
            $parcel['PickupAddress'] = $this->get_pickup_address($order);
            $parcel['DeliveryAddress'] = $this->get_delivery_address($order);
            $parcel['ServiceList'] = $this->get_service_list($order, $is_parcel_delivery_service, $pickup_info, $services);

            // Add SenderIdentityCardNumber for Serbia
            if ($order->get_shipping_country() === 'RS') {
                $parcel['SenderIdentityCardNumber'] = $senderIdentityCardNumber;
            }
            
            // Add Content with placeholder processing (for all countries)
            if (!empty($content)) {
                $parcel['Content'] = $this->process_content_placeholders($content, $order);
            }

            if ($order->get_payment_method() === 'cod') {
                $parcel['CODAmount'] = $order->get_total();
                // Use saved COD reference if available, otherwise use order ID
                $parcel['CODReference'] = !empty($saved_cod_reference) ? $saved_cod_reference : $orderId;
            }

            $parcel_list[] = $parcel;
        }

        // Determine print position for the whole batch
        $final_print_position = 1; // Default
        if ($first_print_position !== null && !$has_custom_print_position) {
            // All orders have the same custom print position
            $final_print_position = $first_print_position;
        } else {
            // Use global setting if orders have different print positions or none set
            $final_print_position = (int)$this->get_option("print_position") ?: 1;
        }

        $params = [
            'WebshopEngine' => 'woocommercehr',
            'ParcelList' => $parcel_list,
            'PrintPosition' => $final_print_position,
            'TypeOfPrinter' => $this->get_option("type_of_printer") ?: 'A4_2x2',
            'ShowPrintDialog' => false
        ];

        return $params;
    }

    /**
     * Generates post fields for the API request for a single order.
     *
     * @param int $count Number of packages.
     * @param int|null $print_position Custom print position for this order.
     * @param string|null $cod_reference Custom COD reference for this order.
     * @param array|null $services Custom services for this order.
     * @return array The generated post fields for the API request.
     */
    public function generate_post_fields($count = 1, $print_position = null, $cod_reference = null, $services = null)
    {
        if (empty($this->orders)) {
            throw new Exception("No orders available.");
        }

        $order_data = $this->orders[0];
        $order = $order_data['order'];
        $is_parcel_delivery_service = $order_data['is_parcel_delivery_service'];
        $pickup_info = $order_data['pickup_info'];

        $clientReferenceFormat = $this->get_option('client_reference_format');
        $senderIdentityCardNumber = $this->get_option('sender_identity_card_number');
        $content = $this->get_option('content');
        $orderId = $order->get_id();
        $clientReference = str_replace('{{order_id}}', $orderId, $clientReferenceFormat);

        $parcel = [
            'ClientNumber' => (int)$this->get_option("client_id"),
            'ClientReference' => $clientReference,
            'Count' => $count
        ];
        $parcel['PickupAddress'] = $this->get_pickup_address($order);
        $parcel['DeliveryAddress'] = $this->get_delivery_address($order);
        $parcel['ServiceList'] = $this->get_service_list($order, $is_parcel_delivery_service, $pickup_info, $services);

        // Add SenderIdentityCardNumber for Serbia
        if ($order->get_shipping_country() === 'RS') {
            $parcel['SenderIdentityCardNumber'] = $senderIdentityCardNumber;
        }
        
        // Add Content with placeholder processing (for all countries)
        if (!empty($content)) {
            $parcel['Content'] = $this->process_content_placeholders($content, $order);
        }

        if ($order->get_payment_method() === 'cod') {
            $parcel['CODAmount'] = $order->get_total();
            // Use custom COD reference if provided, otherwise fall back to order ID
            $final_cod_reference = $cod_reference !== null ? $cod_reference : $orderId;
            $parcel['CODReference'] = $final_cod_reference;
        }

        // Use custom print position if provided, otherwise use default from settings
        $final_print_position = $print_position !== null ? $print_position : ((int)$this->get_option("print_position") ?: 1);

        $params = [
            'WebshopEngine' => 'woocommercehr',
            'ParcelList' => [$parcel],
            'PrintPosition' => $final_print_position,
            'TypeOfPrinter' => $this->get_option("type_of_printer") ?: 'A4_2x2',
            'ShowPrintDialog' => false
        ];

        return $params;
    }

    /**
     * Process content placeholders with order data.
     *
     * @param string $content The content string with placeholders.
     * @param \WC_Order $order The WooCommerce order instance.
     * @return string The processed content with placeholders replaced.
     */
    private function process_content_placeholders($content, $order)
    {
        $placeholders = array(
            '{{order_id}}' => $order->get_id(),
            '{{customer_name}}' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            '{{customer_email}}' => $order->get_billing_email(),
            '{{customer_phone}}' => $this->get_order_phone($order),
            '{{customer_comment}}' => $order->get_customer_note(),
            '{{order_total}}' => $order->get_total(),
            '{{shipping_method}}' => $order->get_shipping_method(),
        );

        // Replace placeholders with actual values
        foreach ($placeholders as $placeholder => $value) {
            $content = str_replace($placeholder, $value, $content);
        }

        return $content;
    }
}