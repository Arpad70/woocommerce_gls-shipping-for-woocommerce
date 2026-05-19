<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gls_shipping_method_init()
{
	if (!class_exists('GLS_Shipping_Method')) {
		class GLS_Shipping_Method extends WC_Shipping_Method
		{

			/**
			 * Constructor for the GLS Shipping Method.
			 *
			 * Sets up the GLS shipping method by initializing the method ID, title, and description.
			 * Also sets the default values for 'enabled' and 'title' settings and calls the init method.
			 * @access public
			 * @return void
			 */
			public function __construct()
			{
				$this->id                 = GLS_SHIPPING_METHOD_ID;
				$this->method_title       = __('GLS Delivery to Address', 'gls-shipping-for-woocommerce');
				$this->method_description = __('Parcels are shipped to the customer’s address.', 'gls-shipping-for-woocommerce');

				$this->init();

				$this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
				$this->title = isset($this->settings['title']) ? $this->settings['title'] : __('Delivery to Address', 'gls-shipping-for-woocommerce');
			}

			/**
			 * Init settings
			 *
			 * Loads the WooCommerce settings API, sets up form fields for the shipping method,
			 * and registers an action hook for updating shipping options.
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

			/**
			 * Initializes form fields for the GLS Shipping Method settings.
			 *
			 * Defines the structure and default values for the settings form fields
			 * used in the WooCommerce admin panel.
			 * @access public
			 * @return void
			 */
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
						'default' => __('Delivery to Address', 'gls-shipping-for-woocommerce')
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
						'options' => WC()->countries->get_countries(),
						'default' => array('AT', 'BE', 'BG', 'CZ', 'DE', 'DK', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IT', 'LU', 'NL', 'PL', 'PT', 'RO', 'RS', 'SI', 'SK'),
						'desc_tip' => true,
						'description' => __('Select countries to support for this shipping method.', 'gls-shipping-for-woocommerce'),
					),
					'main_section' => array(
						'title'       => __('GLS API Settings', 'gls-shipping-for-woocommerce'),
						'type'        => 'title',
						'description' => __('API Settings for all of the GLS Shipping Options.', 'gls-shipping-for-woocommerce'),
					),
					'account_mode' => array(
						'title'       => __('Account Mode', 'gls-shipping-for-woocommerce'),
						'type'        => 'select',
						'description' => __('Choose between single or multiple GLS accounts.', 'gls-shipping-for-woocommerce'),
						'desc_tip'    => true,
						'default'     => 'single',
						'options'     => array(
							'single'   => __('Single GLS Account', 'gls-shipping-for-woocommerce'),
							'multiple' => __('Multiple GLS Accounts', 'gls-shipping-for-woocommerce'),
						),
					),
					'gls_accounts_grid' => array(
						'title'       => __('GLS Accounts', 'gls-shipping-for-woocommerce'),
						'type'        => 'gls_accounts_grid',
						'description' => __('Manage multiple GLS accounts. Each account can have different credentials and country settings.', 'gls-shipping-for-woocommerce'),
						'desc_tip'    => true,
					),
					'client_id' => array(
						'title'       => __('Client ID', 'gls-shipping-for-woocommerce'),
						'type'        => 'text',
						'description' => __('Enter your GLS Client ID.', 'gls-shipping-for-woocommerce'),
						'desc_tip'    => true,
					),
					'username' => array(
						'title'       => __('Username', 'gls-shipping-for-woocommerce'),
						'type'        => 'text',
						'description' => __('Enter your GLS Username.', 'gls-shipping-for-woocommerce'),
						'desc_tip'    => true,
					),
					'password' => array(
						'title'       => __('Password', 'gls-shipping-for-woocommerce'),
						'type'        => 'password',
						'description' => __('Enter your GLS Password.', 'gls-shipping-for-woocommerce'),
						'desc_tip'    => true,
					),
					'country' => array(
						'title'       => __('Country', 'gls-shipping-for-woocommerce'),
						'type'        => 'select',
						'description' => __('Select the country for the GLS service.', 'gls-shipping-for-woocommerce'),
						'desc_tip'    => true,
						'options'     => array(
							'CZ' => __('Czech Republic', 'gls-shipping-for-woocommerce'),
							'HR' => __('Croatia', 'gls-shipping-for-woocommerce'),
							'HU' => __('Hungary', 'gls-shipping-for-woocommerce'),
							'RO' => __('Romania', 'gls-shipping-for-woocommerce'),
							'SI' => __('Slovenia', 'gls-shipping-for-woocommerce'),
							'SK' => __('Slovakia', 'gls-shipping-for-woocommerce'),
							'RS' => __('Serbia', 'gls-shipping-for-woocommerce'),
						),
					),
					'mode' => array(
						'title'       => __('Mode', 'gls-shipping-for-woocommerce'),
						'type'        => 'select',
						'description' => __('Select the mode for the GLS API.', 'gls-shipping-for-woocommerce'),
						'desc_tip'    => true,
						'options'     => array(
							'production' => __('Production', 'gls-shipping-for-woocommerce'),
							'sandbox'    => __('Sandbox', 'gls-shipping-for-woocommerce'),
						),
					),
					'sender_addresses_grid' => array(
						'title'       => __('Sender Addresses', 'gls-shipping-for-woocommerce'),
						'type'        => 'sender_addresses_grid',
						'description' => __('Manage multiple sender addresses. Each address can be set as default for shipments.', 'gls-shipping-for-woocommerce'),
						'desc_tip'    => true,
					),
					'logging' => array(
						'title'       => __('Enable Logging', 'gls-shipping-for-woocommerce'),
						'type'        => 'checkbox',
						'label'       => __('Enable logging of GLS API requests and responses', 'gls-shipping-for-woocommerce'),
						'default'     => 'no',
					),
					'sender_identity_card_number' => array(
						'title' => __('Sender Identity Card Number. Required for Serbia.', 'gls-shipping-for-woocommerce'),
						'type' => 'text',
						'description' => __('Only in Serbia! REQUIRED', 'gls-shipping-for-woocommerce'),
						'default' => '',
						'desc_tip' => true,
					),
					'content' => array(
						'title' => __('Content', 'gls-shipping-for-woocommerce'),
						'type' => 'text',
						'description' => __('Parcel info printed on label. Use placeholders: {{order_id}}, {{customer_comment}}, {{customer_name}}, {{customer_email}}, {{customer_phone}}, {{order_total}}, {{shipping_method}}.', 'gls-shipping-for-woocommerce'),
						'default' => 'Order: {{order_id}}',
						'desc_tip' => true,
					),
					'client_reference_format' => array(
						'title' => __('Order Reference Format', 'gls-shipping-for-woocommerce'),
						'type' => 'text',
						'description' => __('Enter the format for order reference. Use {{order_id}} where you want the order ID to be inserted.', 'gls-shipping-for-woocommerce'),
						'default' => 'Order:{{order_id}}',
						'desc_tip' => true,
					),
					'print_position' => array(
						'title'       => __('Print Position', 'gls-shipping-for-woocommerce'),
						'type'        => 'select',
						'description' => __('Select the Print Position.', 'gls-shipping-for-woocommerce'),
						'desc_tip'    => true,
						'options'     => array(
							1 => '1',
							2 => '2',
							3 => '3',
							4 => '4',
						),
					),
					'type_of_printer' => array(
						'title'       => __('Type of Printer', 'gls-shipping-for-woocommerce'),
						'type'        => 'select',
						'description' => __('Select the Printer Type.', 'gls-shipping-for-woocommerce'),
						'desc_tip'    => true,
						'options'     => array(
							'A4_2x2'  => 'A4_2x2', 
							'A4_4x1'  => 'A4_4x1', 
							'Connect' => 'Connect', 
							'Thermo'  => 'Thermo',
						),
					),
					'sub_section' => array(
						'title'       => __('GLS Services', 'gls-shipping-for-woocommerce'),
						'type'        => 'title',
						'description' => __('Enable/Disable each of GLS Services for your store.', 'gls-shipping-for-woocommerce'),
					),
					'service_24h' => array(
						'title'   => __('Guaranteed 24h Service (24H)', 'gls-shipping-for-woocommerce'),
						'type'    => 'checkbox',
						'label'   => __('Enable 24H', 'gls-shipping-for-woocommerce'),
						'description' => __('Not available in Serbia.', 'gls-shipping-for-woocommerce'),
						'desc_tip' => true,
						'default' => 'no',
					),
					'express_delivery_service' => array(
						'title'   => __('Express Delivery Service (T09, T10, T12)', 'gls-shipping-for-woocommerce'),
						'type'    => 'select',
						'label'   => __('Express Delivery Service Time', 'gls-shipping-for-woocommerce'),
						'description' => __('Availability depends on the country. Can’t be used with FDS and FSS services.', 'gls-shipping-for-woocommerce'),
						'options'     => array(
							'' => 'Disabled',
							'T09' => '09:00',
							'T10' => '10:00',
							'T12' => '12:00',
						),
						'desc_tip' => true,
					),
					'contact_service' => array(
						'title'   => __('Contact Service (CS1)', 'gls-shipping-for-woocommerce'),
						'type'    => 'checkbox',
						'label'   => __('Enable CS1', 'gls-shipping-for-woocommerce'),
						'default' => 'no',
					),
					'flexible_delivery_service' => array(
						'title'   => __('Flexible Delivery Service (FDS)', 'gls-shipping-for-woocommerce'),
						'type'    => 'checkbox',
						'label'   => __('Enable FDS', 'gls-shipping-for-woocommerce'),
						'description' => __('Can’t be used with T09, T10, and T12 services.', 'gls-shipping-for-woocommerce'),
						'desc_tip' => true,
						'default' => 'no',
					),
					'flexible_delivery_sms_service' => array(
						'title'   => __('Flexible Delivery SMS Service (FSS)', 'gls-shipping-for-woocommerce'),
						'type'    => 'checkbox',
						'label'   => __('Enable FSS', 'gls-shipping-for-woocommerce'),
						'description' => __('Not available without FDS service.', 'gls-shipping-for-woocommerce'),
						'desc_tip' => true,
						'default' => 'no',
					),
					'sms_service' => array(
						'title'   => __('SMS Service (SM1)', 'gls-shipping-for-woocommerce'),
						'type'    => 'checkbox',
						'label'   => __('Enable SM1', 'gls-shipping-for-woocommerce'),
						'description' => __('SMS service with a maximum text length of 130.', 'gls-shipping-for-woocommerce'),
						'desc_tip' => true,
						'default' => 'no',
					),
					'sms_service_text' => array(
						'title'   => __('SMS Service Text', 'gls-shipping-for-woocommerce'),
						'type'    => 'text',
						'label'   => __('SM1 Service Text', 'gls-shipping-for-woocommerce'),
						'description' => __('SMS Service Text. Variables that can be used in the text of the SMS: #ParcelNr#, #COD#, #PickupDate#, #From_Name#, #ClientRef#.', 'gls-shipping-for-woocommerce'),
						'desc_tip' => true,
					),
					'sms_pre_advice_service' => array(
						'title'   => __('SMS Pre-advice Service (SM2)', 'gls-shipping-for-woocommerce'),
						'type'    => 'checkbox',
						'label'   => __('Enable SM2', 'gls-shipping-for-woocommerce'),
						'default' => 'no',
					),
					'addressee_only_service' => array(
						'title'   => __('Addressee Only Service (AOS)', 'gls-shipping-for-woocommerce'),
						'type'    => 'checkbox',
						'label'   => __('Enable AOS', 'gls-shipping-for-woocommerce'),
						'default' => 'no',
					),
					'insurance_service' => array(
						'title'   => __('Insurance Service (INS)', 'gls-shipping-for-woocommerce'),
						'type'    => 'checkbox',
						'label'   => __('Enable INS', 'gls-shipping-for-woocommerce'),
						'description' => __('Available within specific limits based on the country.', 'gls-shipping-for-woocommerce'),
						'desc_tip' => true,
						'default' => 'no',
					),
					'phone_number' => array(
						'title'   => __('Store Phone Number', 'gls-shipping-for-woocommerce'),
						'type'    => 'text',
						'label'   => __('Store Phone Number', 'gls-shipping-for-woocommerce'),
						'description' => __('Store Phone number that will be sent to GLS as a contact information.', 'gls-shipping-for-woocommerce'),
						'desc_tip' => true,
					),
					'sub_section2' => array(
						'title'       => __('Display Options', 'gls-shipping-for-woocommerce'),
						'type'        => 'title',
						'description' => __('Customize how GLS shipping methods are displayed to customers.', 'gls-shipping-for-woocommerce'),
					),
					'show_gls_logo' => array(
						'title'       => __('Show GLS Logo', 'gls-shipping-for-woocommerce'),
						'type'        => 'checkbox',
						'label'       => __('Display GLS logo next to shipping methods', 'gls-shipping-for-woocommerce'),
						'description' => __('Show the GLS logo icon next to all GLS shipping methods in cart and checkout to highlight your GLS delivery options.', 'gls-shipping-for-woocommerce'),
						'desc_tip'    => true,
						'default'     => 'no',
					),
					'sub_section3' => array(
						'title'       => '',
						'type'        => 'title',
					),
				);
			}

			/**
			 * Generate custom field for sender addresses grid
			 */
			public function generate_sender_addresses_grid_html($key, $data)
			{
				$field_key = $this->get_field_key($key);
				$defaults = array(
					'title'       => '',
					'disabled'    => false,
					'class'       => '',
					'css'         => '',
					'placeholder' => '',
					'type'        => 'sender_addresses_grid',
					'desc_tip'    => false,
					'description' => '',
				);

				$data = wp_parse_args($data, $defaults);
				$addresses = $this->get_option('sender_addresses_grid', array());
				
				ob_start();
				?>
				<tr valign="top" id="sender_addresses_row">
					<th scope="row" class="titledesc">
						<label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo wp_kses_post( $this->get_tooltip_html($data) ); ?></label>
					</th>
					<td class="forminp">
						<fieldset>
							<legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
							<div id="sender-addresses-container">
								<table class="sender-addresses-table wp-list-table widefat fixed striped" style="margin-bottom: 10px;">
									<thead>
										<tr>
											<th><?php esc_html_e('Default', 'gls-shipping-for-woocommerce'); ?></th>
											<th><?php esc_html_e('Name', 'gls-shipping-for-woocommerce'); ?></th>
											<th><?php esc_html_e('Actions', 'gls-shipping-for-woocommerce'); ?></th>
										</tr>
									</thead>
									<tbody id="sender-addresses-tbody">
										<!-- Default "No custom address" option -->
										<tr class="sender-address-row" data-index="none">
											<td>
												<input type="radio" name="<?php echo esc_attr( $this->get_field_key('sender_addresses_grid') ); ?>_default" value="none" <?php checked(!$this->has_default_address($addresses), true); ?> class="address-default-radio" />
											</td>
											<td>
												<span class="address-name-display" style="font-style: italic; color: #666;"><?php esc_html_e('No custom sender address (use default from store settings)', 'gls-shipping-for-woocommerce'); ?></span>
											</td>
											<td>
												<!-- No actions for default option -->
											</td>
										</tr>
										<?php
										if (!empty($addresses)) {
											foreach ($addresses as $index => $address) {
												$this->render_address_row($index, $address);
											}
										}
										?>
									</tbody>
								</table>
								<button type="button" id="add-sender-address" class="button button-secondary"><?php esc_html_e('Add New Address', 'gls-shipping-for-woocommerce'); ?></button>
							</div>
							<?php echo wp_kses_post( $this->get_description_html($data) ); ?>
						</fieldset>
					</td>
				</tr>
				<?php
				return ob_get_clean();
			}

			/**
			 * Generate custom field for GLS accounts grid
			 */
			public function generate_gls_accounts_grid_html($key, $data)
			{
				$field_key = $this->get_field_key($key);
				$defaults = array(
					'title'       => '',
					'disabled'    => false,
					'class'       => '',
					'css'         => '',
					'placeholder' => '',
					'type'        => 'gls_accounts_grid',
					'desc_tip'    => false,
					'description' => '',
				);

				$data = wp_parse_args($data, $defaults);
				$accounts = $this->get_option('gls_accounts_grid', array());
				
				ob_start();
				?>
				<tr valign="top" id="gls_accounts_row" style="<?php echo $this->get_option('account_mode', 'single') === 'single' ? 'display: none;' : ''; ?>">
					<th scope="row" class="titledesc">
						<label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo wp_kses_post( $this->get_tooltip_html($data) ); ?></label>
					</th>
					<td class="forminp">
						<fieldset>
							<legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
							<div id="gls-accounts-container">
								<table class="gls-accounts-table wp-list-table widefat fixed striped" style="margin-bottom: 10px;">
									<thead>
										<tr>
											<th><?php esc_html_e('Active', 'gls-shipping-for-woocommerce'); ?></th>
											<th><?php esc_html_e('Client ID', 'gls-shipping-for-woocommerce'); ?></th>
											<th><?php esc_html_e('Actions', 'gls-shipping-for-woocommerce'); ?></th>
										</tr>
									</thead>
									<tbody id="gls-accounts-tbody">
										<?php
										if (!empty($accounts)) {
											foreach ($accounts as $index => $account) {
												$this->render_account_row($index, $account);
											}
										}
										?>
									</tbody>
								</table>
								<button type="button" id="add-gls-account" class="button button-secondary"><?php esc_html_e('Add New Account', 'gls-shipping-for-woocommerce'); ?></button>
							</div>
							<?php echo wp_kses_post( $this->get_description_html($data) ); ?>
						</fieldset>
					</td>
				</tr>
				<?php
				return ob_get_clean();
			}

			/**
			 * Check if any address is set as default
			 */
			private function has_default_address($addresses)
			{
				if (empty($addresses)) {
					return false;
				}
				
				foreach ($addresses as $address) {
					if (!empty($address['is_default']) && $address['is_default']) {
						return true;
					}
				}
				
				return false;
			}

			/**
			 * Render a single address row
			 */
			private function render_address_row($index, $address = array())
			{
				$address = wp_parse_args($address, array(
					'name' => '',
					'contact_name' => '',
					'street' => '',
					'house_number' => '',
					'city' => '',
					'postcode' => '',
					'country' => 'HR',
					'phone' => '',
					'email' => '',
					'is_default' => false
				));
				?>
				<tr class="sender-address-row" data-index="<?php echo esc_attr( $index ); ?>">
					<td>
						<input type="radio" name="<?php echo esc_attr( $this->get_field_key('sender_addresses_grid') ); ?>_default" value="<?php echo esc_attr( $index ); ?>" <?php checked($address['is_default'], true); ?> class="address-default-radio" />
					</td>
					<td>
						<span class="address-name-display"><?php echo esc_html($address['name'] ?: __('New Address', 'gls-shipping-for-woocommerce')); ?></span>
					</td>
					<td>
						<button type="button" class="button button-small edit-address"><?php esc_html_e('Edit', 'gls-shipping-for-woocommerce'); ?></button>
						<button type="button" class="button button-small delete-address"><?php esc_html_e('Delete', 'gls-shipping-for-woocommerce'); ?></button>
						
						<!-- Hidden fields to store all address data -->
						<input type="hidden" name="<?php echo esc_attr( $this->get_field_key('sender_addresses_grid') ); ?>[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr($address['name']); ?>" class="address-name" />
						<input type="hidden" name="<?php echo esc_attr( $this->get_field_key('sender_addresses_grid') ); ?>[<?php echo esc_attr( $index ); ?>][contact_name]" value="<?php echo esc_attr($address['contact_name']); ?>" class="address-contact-name" />
						<input type="hidden" name="<?php echo esc_attr( $this->get_field_key('sender_addresses_grid') ); ?>[<?php echo esc_attr( $index ); ?>][street]" value="<?php echo esc_attr($address['street']); ?>" class="address-street" />
						<input type="hidden" name="<?php echo esc_attr( $this->get_field_key('sender_addresses_grid') ); ?>[<?php echo esc_attr( $index ); ?>][house_number]" value="<?php echo esc_attr($address['house_number']); ?>" class="address-house-number" />
						<input type="hidden" name="<?php echo esc_attr( $this->get_field_key('sender_addresses_grid') ); ?>[<?php echo esc_attr( $index ); ?>][city]" value="<?php echo esc_attr($address['city']); ?>" class="address-city" />
						<input type="hidden" name="<?php echo esc_attr( $this->get_field_key('sender_addresses_grid') ); ?>[<?php echo esc_attr( $index ); ?>][postcode]" value="<?php echo esc_attr($address['postcode']); ?>" class="address-postcode" />
						<input type="hidden" name="<?php echo esc_attr( $this->get_field_key('sender_addresses_grid') ); ?>[<?php echo esc_attr( $index ); ?>][country]" value="<?php echo esc_attr($address['country']); ?>" class="address-country" />
						<input type="hidden" name="<?php echo esc_attr( $this->get_field_key('sender_addresses_grid') ); ?>[<?php echo esc_attr( $index ); ?>][phone]" value="<?php echo esc_attr($address['phone']); ?>" class="address-phone" />
						<input type="hidden" name="<?php echo esc_attr( $this->get_field_key('sender_addresses_grid') ); ?>[<?php echo esc_attr( $index ); ?>][email]" value="<?php echo esc_attr($address['email']); ?>" class="address-email" />
						<input type="hidden" name="<?php echo esc_attr( $this->get_field_key('sender_addresses_grid') ); ?>[<?php echo esc_attr( $index ); ?>][is_default]" value="<?php echo $address['is_default'] ? '1' : '0'; ?>" class="address-is-default" />
					</td>
				</tr>
				<?php
			}

			/**
			 * Render a single account row
			 */
			private function render_account_row($index, $account = array())
			{
				$account = wp_parse_args($account, array(
					'name' => '',
					'client_id' => '',
					'username' => '',
					'password' => '',
					'country' => 'HR',
					'mode' => 'production',
					'active' => false
				));
				?>
				<tr class="gls-account-row" data-index="<?php echo esc_attr( $index ); ?>">
					<td>
						<input type="radio" name="gls_account_active_selection" value="<?php echo esc_attr( $index ); ?>" <?php checked($account['active'], true); ?> class="account-active-radio" />
					</td>
					<td>
						<span class="account-clientid-display"><?php echo esc_html($account['client_id'] ?: __('New Account', 'gls-shipping-for-woocommerce')); ?></span>
					</td>
					<td>
						<button type="button" class="button button-small edit-account"><?php esc_html_e('Edit', 'gls-shipping-for-woocommerce'); ?></button>
						<button type="button" class="button button-small delete-account"><?php esc_html_e('Delete', 'gls-shipping-for-woocommerce'); ?></button>
						
						<!-- Hidden fields to store all account data -->
						<input type="hidden" name="<?php echo esc_attr( $this->get_field_key('gls_accounts_grid') ); ?>[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr($account['name']); ?>" class="account-name" />
						<input type="hidden" name="<?php echo esc_attr( $this->get_field_key('gls_accounts_grid') ); ?>[<?php echo esc_attr( $index ); ?>][client_id]" value="<?php echo esc_attr($account['client_id']); ?>" class="account-client-id" />
						<input type="hidden" name="<?php echo esc_attr( $this->get_field_key('gls_accounts_grid') ); ?>[<?php echo esc_attr( $index ); ?>][username]" value="<?php echo esc_attr($account['username']); ?>" class="account-username" />
						<input type="hidden" name="<?php echo esc_attr( $this->get_field_key('gls_accounts_grid') ); ?>[<?php echo esc_attr( $index ); ?>][password]" value="<?php echo esc_attr($account['password']); ?>" class="account-password" />
						<input type="hidden" name="<?php echo esc_attr( $this->get_field_key('gls_accounts_grid') ); ?>[<?php echo esc_attr( $index ); ?>][country]" value="<?php echo esc_attr($account['country']); ?>" class="account-country" />
						<input type="hidden" name="<?php echo esc_attr( $this->get_field_key('gls_accounts_grid') ); ?>[<?php echo esc_attr( $index ); ?>][mode]" value="<?php echo esc_attr($account['mode']); ?>" class="account-mode" />
						<input type="hidden" name="<?php echo esc_attr( $this->get_field_key('gls_accounts_grid') ); ?>[<?php echo esc_attr( $index ); ?>][active]" value="<?php echo $account['active'] ? '1' : '0'; ?>" class="account-active-hidden" />
					</td>
				</tr>
				<?php
			}

			/**
			 * Validate account_mode field type
			 */
			public function validate_account_mode_field($key, $value)
			{
				// Sanitize the value
				$sanitized_value = sanitize_text_field($value);

				// If user selected multiple mode, check if they have any valid accounts
				if ($sanitized_value === 'multiple') {
					// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- WooCommerce handles nonce, data sanitized below
					$accounts_data = isset($_POST[$this->get_field_key('gls_accounts_grid')]) ? wp_unslash($_POST[$this->get_field_key('gls_accounts_grid')]) : array();
					
					// Check if there are any accounts with required credentials
					$has_valid_accounts = false;
					if (is_array($accounts_data)) {
						foreach ($accounts_data as $account) {
							if (is_array($account) && 
								!empty($account['client_id']) && 
								!empty($account['username']) && 
								!empty($account['password'])) {
								$has_valid_accounts = true;
								break;
							}
						}
					}
					
					// If no valid accounts found, force back to single mode
					if (!$has_valid_accounts) {
						// Add admin notice to inform user
						add_action('admin_notices', function() {
							echo '<div class="notice notice-warning is-dismissible">';
							echo '<p>' . esc_html__('Multiple accounts mode was not saved because no valid GLS accounts were found. Please add at least one account to use multiple accounts mode.', 'gls-shipping-for-woocommerce') . '</p>';
							echo '</div>';
						});
						
						return 'single';
					}
				}
				
				return $sanitized_value;
			}

			/**
			 * Validate gls_accounts_grid field type
			 */
			public function validate_gls_accounts_grid_field($key, $value)
			{
				return GLS_Shipping_Account_Helper::validate_accounts_grid($value);
			}

			/**
			 * Validate sender_addresses_grid field type
			 */
			public function validate_sender_addresses_grid_field($key, $value)
			{
				if (!is_array($value)) {
					return array();
				}

				$validated_addresses = array();
				$has_default = false;

				foreach ($value as $index => $address) {
					if (is_array($address)) {
						// Validate required fields
						$required_fields = array('name', 'street', 'house_number', 'city', 'postcode', 'country');
						$is_valid = true;

						foreach ($required_fields as $field) {
							if (empty($address[$field])) {
								$is_valid = false;
								break;
							}
						}

						if ($is_valid) {
							$validated_address = array(
								'name' => sanitize_text_field($address['name']),
								'contact_name' => sanitize_text_field($address['contact_name'] ?? ''),
								'street' => sanitize_text_field($address['street']),
								'house_number' => sanitize_text_field($address['house_number']),
								'city' => sanitize_text_field($address['city']),
								'postcode' => sanitize_text_field($address['postcode']),
								'country' => sanitize_text_field($address['country']),
								'phone' => sanitize_text_field($address['phone'] ?? ''),
								'email' => sanitize_email($address['email'] ?? ''),
								'is_default' => !empty($address['is_default']) && $address['is_default'] === '1'
							);

							// Ensure only one default address
							if ($validated_address['is_default']) {
								if ($has_default) {
									$validated_address['is_default'] = false;
								} else {
									$has_default = true;
								}
							}

							$validated_addresses[] = $validated_address;
						}
					}
				}

				return $validated_addresses;
			}


			/**
			 * Calculates the shipping rate based on the package details.
			 *
			 * Determines if the destination country is supported and applies the set shipping rate.
			 *
			 * @param array $package Details of the package being shipped.
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

add_action('woocommerce_shipping_init', 'gls_shipping_method_init');
