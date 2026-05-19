<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gls_shipping_method_parcel_locker_init()
{
	if (!class_exists('GLS_Shipping_Method_Parcel_Locker')) {
		class GLS_Shipping_Method_Parcel_Locker extends WC_Shipping_Method
		{

			/**
			 * Constructor for shipping class
			 *
			 * @access public
			 * @return void
			 */
			public function __construct()
			{
				$this->id                 = GLS_SHIPPING_METHOD_PARCEL_LOCKER_ID;
				$this->method_title       = __('GLS Parcel Locker', 'gls-shipping-for-woocommerce');
				$this->method_description = __('Parcel Shop Delivery (PSD) service that ships parcels to the GLS Locker. GLS Parcel Locker can be selected from the interactive GLS Parcel Shop and GLS Locker finder map.', 'gls-shipping-for-woocommerce');

				$this->init();

				$this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
				$this->title = isset($this->settings['title']) ? $this->settings['title'] : __('Delivery to GLC Parcel Locker', 'gls-shipping-for-woocommerce');
			}

			/**
			 * Init settings
			 *
			 * @access public
			 * @return void
			 */
			function init()
			{
				// Load the settings API
				$this->init_form_fields();
				$this->init_settings();

				add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
			}

			public function init_form_fields()
			{
				$weight_unit = get_option('woocommerce_weight_unit');

				$this->form_fields = array(
					'enabled' => array(
						'title' => __('Enable', 'gls-shipping-for-woocommerce'),
						'type' => 'checkbox',
						'description' => __('Enable this shipping globally.', 'gls-shipping-for-woocommerce'),
						'default' => 'yes'
					),
					'title' => array(
						'title' => __('Title', 'gls-shipping-for-woocommerce'),
						'type' => 'text',
						'description' => __('Title to be display on site', 'gls-shipping-for-woocommerce'),
						'default' => __('Delivery to GLS Parcel Locker', 'gls-shipping-for-woocommerce')
					),
					'shipping_price' => array(
						'title'       => __('Shipping Price', 'gls-shipping-for-woocommerce'),
						'type'        => 'text',
						'description' => __('Enter the shipping price for this method.', 'gls-shipping-for-woocommerce'),
						'default'     => 0,
						'desc_tip'    => true,
					),
					'weight_based_rates' => array(
						'title'       => __('Weight Based Rates: max_weight|cost', 'gls-shipping-for-woocommerce'),
						'type'        => 'textarea',
						/* translators: %s: weight unit (e.g. kg, lbs) */
						'description' => sprintf(__('Optional: Enter weight based rates (one per line). Format: max_weight|cost. Example: 1|100 means up to 1 %s costs 100. Leave empty to use default price.', 'gls-shipping-for-woocommerce'), $weight_unit),
						'default'     => '',
						'placeholder' => 'max_weight|cost
max_weight|cost',
						'css'         => 'width:300px; height: 150px;',
					),
					'free_shipping_threshold' => array(
						'title'       => __('Free Shipping Threshold', 'gls-shipping-for-woocommerce'),
						'type'        => 'number',
						'description' => __('Cart total amount above which shipping will be free. Set to 0 to disable.', 'gls-shipping-for-woocommerce'),
						'default'     => '0',
						'desc_tip'    => true,
						'custom_attributes' => array(
							'min'  => '0',
							'step' => '0.01'
						)
					),
					'supported_countries' => array(
						'title'   => __('Supported Countries', 'gls-shipping-for-woocommerce'),
						'type'    => 'multiselect',
						'class'   => 'wc-enhanced-select',
						'css'     => 'width: 400px;',
						'options' => array(
							'AT' => __('Austria', 'gls-shipping-for-woocommerce'),
							'BE' => __('Belgium', 'gls-shipping-for-woocommerce'),
							'BG' => __('Bulgaria', 'gls-shipping-for-woocommerce'),
							'CZ' => __('Czech Republic', 'gls-shipping-for-woocommerce'),
							'DE' => __('Germany', 'gls-shipping-for-woocommerce'),
							'DK' => __('Denmark', 'gls-shipping-for-woocommerce'),
							'ES' => __('Spain', 'gls-shipping-for-woocommerce'),
							'FI' => __('Finland', 'gls-shipping-for-woocommerce'),
							'FR' => __('France', 'gls-shipping-for-woocommerce'),
							'GR' => __('Greece', 'gls-shipping-for-woocommerce'),
							'HR' => __('Croatia', 'gls-shipping-for-woocommerce'),
							'HU' => __('Hungary', 'gls-shipping-for-woocommerce'),
							'IT' => __('Italy', 'gls-shipping-for-woocommerce'),
							'LU' => __('Luxembourg', 'gls-shipping-for-woocommerce'),
							'NL' => __('Netherlands', 'gls-shipping-for-woocommerce'),
							'PL' => __('Poland', 'gls-shipping-for-woocommerce'),
							'PT' => __('Portugal', 'gls-shipping-for-woocommerce'),
							'RO' => __('Romania', 'gls-shipping-for-woocommerce'),
							'RS' => __('Serbia', 'gls-shipping-for-woocommerce'),
							'SI' => __('Slovenia', 'gls-shipping-for-woocommerce'),
							'SK' => __('Slovakia', 'gls-shipping-for-woocommerce'),
						),
						'default' => array('AT', 'BE', 'CZ', 'DE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'NL', 'PL', 'RO', 'SI', 'SK'),
						'desc_tip' => true,
						'description' => __('Select countries to support for this shipping method.', 'gls-shipping-for-woocommerce'),
					),
					'filter_saturation' => array(
						'title'   => __('Hungary Locker Saturation Filter', 'gls-shipping-for-woocommerce'),
						'type'    => 'multiselect',
						'class'   => 'wc-enhanced-select',
						'css'     => 'width: 400px;',
						'options' => array(
							'1' => __('High Volume', 'gls-shipping-for-woocommerce'),
							'2' => __('Low Volume', 'gls-shipping-for-woocommerce'),
							'3' => __('Out Of Order', 'gls-shipping-for-woocommerce'),
						),
						'default' => array('1', '2', '3'),
						'desc_tip' => true,
						'description' => __('Filter locker locations on the map for Hungary. Only applies when country is Hungary.', 'gls-shipping-for-woocommerce'),
					),
				);
			}

			/**
			 * Calculate Shipping Rate
			 *
			 * @access public
			 * @param array $package
			 * @return void
			 */
			public function calculate_shipping($package = array())
			{
				$supported_countries = $this->get_option('supported_countries', []);
				$weight_based_rates_raw = $this->get_option('weight_based_rates', '');
				$default_price = $this->get_option('shipping_price', '0');
				$free_shipping_threshold = $this->get_option('free_shipping_threshold', '0');
				
				if (in_array($package['destination']['country'], $supported_countries)) {
					
					$cart_total = WC()->cart->get_displayed_subtotal();
					$cart_total = $cart_total - WC()->cart->get_discount_total();
					if ( WC()->cart->display_prices_including_tax() ) {
						$cart_total = $cart_total - WC()->cart->get_discount_tax();
					}
					$shipping_price = $default_price;

					if ($free_shipping_threshold > 0 && $cart_total >= $free_shipping_threshold) {
						$shipping_price = 0;
					// Check if weight-based rates are set and valid
					} else if (!empty(trim($weight_based_rates_raw))) {
						$weight_based_rates = array();
						$lines = explode("\n", $weight_based_rates_raw);
						foreach ($lines as $line) {
							$rate = explode('|', trim($line));
							$weight = str_replace(',', '.', $rate[0]);
							$price = str_replace(',', '.', $rate[1]);
							if (count($rate) == 2 && is_numeric($weight) && is_numeric($price)) {
								$weight_based_rates[] = array(
									'weight' => floatval($weight),
									'price' => floatval($price)
								);
							}
						}

						// If we have valid weight-based rates, use them
						if (!empty($weight_based_rates)) {
							$cart_weight = WC()->cart->get_cart_contents_weight();
							// Sort rates by weight in ascending order
							usort($weight_based_rates, function($a, $b) {
								return $a['weight'] <=> $b['weight'];
							});

							// Find the appropriate rate based on cart weight
							foreach ($weight_based_rates as $rate) {
								if ($cart_weight <= $rate['weight']) {
									$shipping_price = $rate['price'];
									break;
								}
							}

							// If no matching rate found, use the highest rate
							if ($shipping_price === $default_price && !empty($weight_based_rates)) {
								$shipping_price = end($weight_based_rates)['price'];
							}
						}
					}

					$rate = array(
						'id'       => $this->id,
						'label'    => $this->title,
						'cost'     => str_replace(',', '.', $shipping_price),
						'calc_tax' => 'per_order'
					);

					// Register the rate
					$this->add_rate($rate);
				}
			}

		}
	}
}

add_action('woocommerce_shipping_init', 'gls_shipping_method_parcel_locker_init');
