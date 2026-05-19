(function () {
	jQuery(document).ready(function ($) {
		// ============================================================================
		// INITIALIZATION
		// ============================================================================

		// Initialize GLS Accounts Management
		handleAccountModeToggle();
		handleAccountsGrid();

		// Initialize GLS Sender Addresses Management
		handleSenderAddressesGrid();

		// ============================================================================
		// HELPER FUNCTIONS
		// ============================================================================

		// Common modal helper functions
		function addModalStyles(styleId, modalId, contentClass) {
			if ($(`#${styleId}`).length === 0) {
				$('head').append(`
					<style id="${styleId}">
						#${modalId} {
							position: fixed;
							top: 0;
							left: 0;
							width: 100%;
							height: 100%;
							background-color: rgba(0,0,0,0.5);
							z-index: 100000;
						}
						.${contentClass} {
							position: relative;
							background-color: #fff;
							margin: 5% auto;
							padding: 20px;
							width: 90%;
							max-width: 600px;
							max-height: 80vh;
							overflow-y: auto;
							border-radius: 4px;
							box-shadow: 0 4px 6px rgba(0,0,0,0.1);
						}
					</style>
				`);
			}
		}

		function getAccountCountryOptions(selectedCountry = 'HR') {
			const countries = {
				CZ: 'Czech Republic',
				HR: 'Croatia',
				HU: 'Hungary',
				RO: 'Romania',
				SI: 'Slovenia',
				SK: 'Slovakia',
				RS: 'Serbia'
			};

			let options = '';
			for (const [code, name] of Object.entries(countries)) {
				const selected = code === selectedCountry ? 'selected' : '';
				options += `<option value="${code}" ${selected}>${name}</option>`;
			}
			return options;
		}

		function getCountryOptions(selectedCountry = 'HR') {
			const countries = {
				AT: 'Austria',
				BE: 'Belgium',
				BG: 'Bulgaria',
				CZ: 'Czech Republic',
				DE: 'Germany',
				DK: 'Denmark',
				ES: 'Spain',
				FI: 'Finland',
				FR: 'France',
				GR: 'Greece',
				HR: 'Croatia',
				HU: 'Hungary',
				IT: 'Italy',
				LU: 'Luxembourg',
				NL: 'Netherlands',
				PL: 'Poland',
				PT: 'Portugal',
				RO: 'Romania',
				RS: 'Serbia',
				SI: 'Slovenia',
				SK: 'Slovakia'
			};

			let options = '';
			for (const [code, name] of Object.entries(countries)) {
				const selected = code === selectedCountry ? 'selected' : '';
				options += `<option value="${code}" ${selected}>${name}</option>`;
			}
			return options;
		}

		// ============================================================================
		// GLS ACCOUNTS MANAGEMENT
		// ============================================================================

		function handleAccountModeToggle() {
			const $accountMode = $('#woocommerce_gls_shipping_method_account_mode');
			const $accountsRow = $('#gls_accounts_row');
			const $singleFields = $(
				'#woocommerce_gls_shipping_method_client_id, #woocommerce_gls_shipping_method_username, #woocommerce_gls_shipping_method_password, #woocommerce_gls_shipping_method_country, #woocommerce_gls_shipping_method_mode'
			).closest('tr');

			function toggleFields() {
				const mode = $accountMode.val();
				if (mode === 'multiple') {
					$accountsRow.show();
					$singleFields.hide();
				} else {
					$accountsRow.hide();
					$singleFields.show();
				}
			}

			$accountMode.on('change', toggleFields);
			toggleFields(); // Initial state
		}

		function handleAccountsGrid() {
			let accountIndex = $('.gls-account-row').length;

			// Add new account
			$(document).on('click', '#add-gls-account', function () {
				const newRow = createAccountRow(accountIndex);
				$('#gls-accounts-tbody').append(newRow);
				accountIndex++;
			});

			// Delete account
			$(document).on('click', '.delete-account', function () {
				if (confirm('Are you sure you want to delete this account?')) {
					$(this).closest('tr').remove();
					reindexAccounts();
				}
			});

			// Edit account (modal functionality)
			$(document).on('click', '.edit-account', function () {
				const $row = $(this).closest('tr');
				const index = $row.data('index');
				openAccountEditModal($row, index);
			});

			// Handle active account radio button changes
			$(document).on('change', '.account-active-radio', function () {
				// Uncheck all other radios
				$('.account-active-radio').not(this).prop('checked', false);

				// Update hidden fields
				$('.account-active-hidden').val('0');

				// Set the selected account as active
				$(this).closest('tr').find('.account-active-hidden').val('1');
			});
		}

		function createAccountRow(index) {
			const countryOptions = getAccountCountryOptions('HR');

			return `
				<tr class="gls-account-row" data-index="${index}">
					<td>
						<input type="radio" name="gls_account_active_selection" value="${index}" class="account-active-radio" />
					</td>
					<td>
						<span class="account-clientid-display">New Account</span>
					</td>
					<td>
						<button type="button" class="button button-small edit-account">Edit</button>
						<button type="button" class="button button-small delete-account">Delete</button>
						
						<!-- Hidden fields to store all account data -->
						<input type="hidden" name="woocommerce_gls_shipping_method_gls_accounts_grid[${index}][name]" value="" class="account-name" />
						<input type="hidden" name="woocommerce_gls_shipping_method_gls_accounts_grid[${index}][client_id]" value="" class="account-client-id" />
						<input type="hidden" name="woocommerce_gls_shipping_method_gls_accounts_grid[${index}][username]" value="" class="account-username" />
						<input type="hidden" name="woocommerce_gls_shipping_method_gls_accounts_grid[${index}][password]" value="" class="account-password" />
						<input type="hidden" name="woocommerce_gls_shipping_method_gls_accounts_grid[${index}][country]" value="HR" class="account-country" />
						<input type="hidden" name="woocommerce_gls_shipping_method_gls_accounts_grid[${index}][mode]" value="production" class="account-mode" />
						<input type="hidden" name="woocommerce_gls_shipping_method_gls_accounts_grid[${index}][active]" value="0" class="account-active-hidden" />
					</td>
				</tr>
			`;
		}

		function reindexAccounts() {
			$('.gls-account-row').each(function (newIndex) {
				$(this).attr('data-index', newIndex);
				$(this)
					.find('input, select')
					.each(function () {
						const name = $(this).attr('name');
						if (name) {
							const newName = name.replace(/\[\d+\]/, '[' + newIndex + ']');
							$(this).attr('name', newName);
						}
					});
			});
		}

		function openAccountEditModal($row, index) {
			const account = {
				name: $row.find('.account-name').val() || '',
				client_id: $row.find('.account-client-id').val() || '',
				username: $row.find('.account-username').val() || '',
				password: $row.find('.account-password').val() || '',
				country: $row.find('.account-country').val() || 'HR',
				mode: $row.find('.account-mode').val() || 'production'
			};

			addModalStyles('gls-account-modal-styles', 'gls-account-modal', 'gls-account-modal-content');

			const modal = `
				<div id="gls-account-modal" style="display: none;">
					<div class="gls-account-modal-content">
						<h3>Edit GLS Account</h3>
						<table class="form-table">
							<tr>
								<th><label>Client ID *</label></th>
								<td><input type="text" id="modal-client-id" value="${account.client_id}" style="width: 100%;" /></td>
							</tr>
							<tr>
								<th><label>Username *</label></th>
								<td><input type="text" id="modal-username" value="${account.username}" style="width: 100%;" /></td>
							</tr>
							<tr>
								<th><label>Password *</label></th>
								<td><input type="password" id="modal-password" value="${account.password}" style="width: 100%;" /></td>
							</tr>
							<tr>
								<th><label>Country</label></th>
								<td><select id="modal-country" style="width: 100%;">${getAccountCountryOptions(account.country)}</select></td>
							</tr>
							<tr>
								<th><label>Mode</label></th>
								<td>
									<select id="modal-mode" style="width: 100%;">
										<option value="production" ${account.mode === 'production' ? 'selected' : ''}>Production</option>
										<option value="sandbox" ${account.mode === 'sandbox' ? 'selected' : ''}>Sandbox</option>
									</select>
								</td>
							</tr>
						</table>
						<p class="submit">
							<button type="button" class="button-primary" id="save-account">Save Changes</button>
							<button type="button" class="button" id="cancel-account-edit">Cancel</button>
						</p>
					</div>
				</div>
			`;

			// Remove existing modal if it exists
			$('#gls-account-modal').remove();

			$('body').append(modal);
			$('#gls-account-modal').show();

			$('#save-account')
				.off('click')
				.on('click', function () {
					const clientId = $('#modal-client-id').val().trim();
					const username = $('#modal-username').val().trim();
					const password = $('#modal-password').val().trim();

					if (!clientId || !username || !password) {
						alert('Please fill in all required fields (marked with *)');
						return;
					}

					$row.find('.account-name').val(clientId);
					$row.find('.account-client-id').val(clientId);
					$row.find('.account-username').val(username);
					$row.find('.account-password').val(password);
					$row.find('.account-country').val($('#modal-country').val());
					$row.find('.account-mode').val($('#modal-mode').val());
					$row.find('.account-clientid-display').text(clientId);

					$('#gls-account-modal').hide();
				});

			$('#cancel-account-edit')
				.off('click')
				.on('click', function () {
					$('#gls-account-modal').hide();
				});
		}

		// ============================================================================
		// GLS LABEL GENERATION & TRACKING
		// ============================================================================

		// Generating label in order details page
		$('.gls-print-label').on('click', function () {
			const orderId = $(this).attr('order-id');
			const $button = $(this);
			const count = $('#gls_label_count').val() || 1;

			// Get print position - check both possible IDs for regenerate vs new label
			let printPosition = $('#gls_print_position').val();
			if (!printPosition) {
				printPosition = $('#gls_print_position_new').val();
			}

			// Get COD reference - check both possible IDs for regenerate vs new label
			let codReference = $('#gls_cod_reference').val();
			if (!codReference) {
				codReference = $('#gls_cod_reference_new').val();
			}

			// Collect service options
			const services = collectServiceOptions();

			$button.prop('disabled', true);
			generateGLSLabel(orderId, $button, count, printPosition, codReference, services);
		});

		// Get parcel status in order details page
		$('.gls-get-status').on('click', function () {
			const orderId = $(this).attr('order-id');
			const parcelNumber = $(this).attr('parcel-number');
			const $button = $(this);
			$button.prop('disabled', true);
			getParcelStatus(orderId, parcelNumber, $button);
		});

		// Toggle service options visibility
		$('#gls-services-toggle, #gls-services-toggle-new').on('click', function (e) {
			e.preventDefault();
			const isNew = $(this).attr('id').includes('new');
			const optionsId = isNew ? '#gls-services-options-new' : '#gls-services-options';
			const arrowId = isNew ? '#gls-services-arrow-new' : '#gls-services-arrow';

			$(optionsId).slideToggle(200);
			$(arrowId).text($(optionsId).is(':visible') ? '▲' : '▼');
		});

		// Show/hide SMS text field based on SMS service checkbox
		$(document).on('change', '#gls_sms_service', function () {
			if ($(this).is(':checked')) {
				$('#gls_sms_text_container').slideDown(200);
			} else {
				$('#gls_sms_text_container').slideUp(200);
			}
		});

		// Generate label in order listing page
		$('a.gls-generate-label').on('click', function (e) {
			e.preventDefault();
			const orderId = $(this).closest('tr').find('.check-column input').val();
			const $button = $(this);
			$button.addClass('disabled');
			generateGLSLabel(orderId, $button, null, null, null, null); // Use saved order settings for all parameters
		});

		function collectServiceOptions() {
			// Always collect current service options from the form
			// This ensures saved services are properly sent even when panel is closed
			return {
				service_24h: $('#gls_service_24h').is(':checked') ? 'yes' : 'no',
				express_delivery_service: $('#gls_express_delivery_service').val() || '',
				contact_service: $('#gls_contact_service').is(':checked') ? 'yes' : 'no',
				flexible_delivery_service: $('#gls_flexible_delivery_service').is(':checked') ? 'yes' : 'no',
				flexible_delivery_sms_service: $('#gls_flexible_delivery_sms_service').is(':checked') ? 'yes' : 'no',
				sms_service: $('#gls_sms_service').is(':checked') ? 'yes' : 'no',
				sms_service_text: $('#gls_sms_service_text').val() || '',
				sms_pre_advice_service: $('#gls_sms_pre_advice_service').is(':checked') ? 'yes' : 'no',
				addressee_only_service: $('#gls_addressee_only_service').is(':checked') ? 'yes' : 'no',
				insurance_service: $('#gls_insurance_service').is(':checked') ? 'yes' : 'no'
			};
		}

		function generateGLSLabel(orderId, $button, count, printPosition, codReference, services) {
			const data = {
				action: 'gls_generate_label',
				orderId: orderId,
				postNonce: gls_croatia.ajaxNonce
			};

			// Add count if provided
			if (count !== null && count !== undefined) {
				data.count = count;
			}

			// Add print position if provided
			if (printPosition) {
				data.printPosition = printPosition;
			}

			// Add COD reference if provided
			if (codReference) {
				data.codReference = codReference;
			}

			// Add services if provided
			if (services) {
				data.services = JSON.stringify(services);
			}

			$.ajax({
				url: gls_croatia.adminAjaxUrl,
				type: 'POST',
				data: data,
				success: function (response) {
					if (response.success) {
						location.reload();
					} else {
						alert('Error generating GLS Label: ' + response.data.error);
					}
				},
				error: function () {
					alert('An error occurred while generating the GLS Label.');
				},
				complete: function () {
					// Re-enable the button
					if ($button.hasClass('gls-print-label')) {
						$button.prop('disabled', false);
					} else {
						$button.removeClass('disabled');
					}
				}
			});
		}

		function getParcelStatus(orderId, parcelNumber, $button) {
			// Clear previous status
			$('#gls-tracking-status').html('<p>Loading tracking information...</p>');

			$.ajax({
				url: gls_croatia.adminAjaxUrl,
				type: 'POST',
				data: {
					action: 'gls_get_parcel_status',
					orderId: orderId,
					parcelNumber: parcelNumber,
					postNonce: gls_croatia.ajaxNonce
				},
				success: function (response) {
					if (response.success) {
						displayTrackingStatus(response.data.tracking_data);
					} else {
						$('#gls-tracking-status').html('<div class="notice notice-error"><p>Error getting tracking status: ' + response.data.error + '</p></div>');
					}
				},
				error: function () {
					$('#gls-tracking-status').html('<div class="notice notice-error"><p>An error occurred while getting tracking status.</p></div>');
				},
				complete: function () {
					$button.prop('disabled', false);
				}
			});
		}

		function displayTrackingStatus(trackingData) {
			let html = '<div class="notice notice-info"><h4>Tracking Information</h4>';

			// Basic info
			html += '<p><strong>Parcel Number:</strong> ' + trackingData.ParcelNumber + '</p>';
			html += '<p><strong>Client Reference:</strong> ' + trackingData.ClientReference + '</p>';

			// Status list
			if (trackingData.ParcelStatusList && trackingData.ParcelStatusList.length > 0) {
				html += '<h5>Status History:</h5>';
				html += '<div style="margin-top: 10px;">';

				trackingData.ParcelStatusList.forEach(function (status, index) {
					// Parse the .NET date format
					const dateMatch = status.StatusDate.match(/Date\((\d+)([+-]\d{4})?\)/);
					let formattedDate = status.StatusDate;
					if (dateMatch) {
						const timestamp = parseInt(dateMatch[1]);
						const date = new Date(timestamp);
						formattedDate = date.toLocaleString();
					}

					html += '<div style="border: 1px solid #ddd; margin-bottom: 10px; padding: 15px; border-radius: 4px; background-color: #f9f9f9;">';
					html += '<div style="margin-bottom: 8px;"><strong>Date:</strong> ' + formattedDate + '</div>';
					html += '<div style="margin-bottom: 8px;"><strong>Status:</strong> ' + status.StatusDescription + ' (' + status.StatusCode + ')</div>';
					html += '<div style="margin-bottom: 8px;"><strong>Location:</strong> ' + status.DepotCity + '</div>';
					if (status.StatusInfo) {
						html += '<div><strong>Info:</strong> ' + status.StatusInfo + '</div>';
					}
					html += '</div>';
				});

				html += '</div>';
			}

			html += '</div>';
			$('#gls-tracking-status').html(html);
		}

		// ============================================================================
		// GLS SENDER ADDRESSES MANAGEMENT
		// ============================================================================

		function handleSenderAddressesGrid() {
			let addressIndex = $('.sender-address-row[data-index!="none"]').length;

			// Add new address
			$(document).on('click', '#add-sender-address', function () {
				const newRow = createAddressRow(addressIndex);
				$('#sender-addresses-tbody').append(newRow);
				addressIndex++;
			});

			// Delete address
			$(document).on('click', '.delete-address', function () {
				if (confirm('Are you sure you want to delete this address?')) {
					$(this).closest('tr').remove();
					reindexAddresses();
				}
			});

			// Edit address (modal functionality)
			$(document).on('click', '.edit-address', function () {
				const $row = $(this).closest('tr');
				const index = $row.data('index');
				openAddressEditModal($row, index);
			});

			// Handle default selection
			$(document).on('change', '.address-default-radio', function () {
				const selectedValue = $(this).val();

				// Uncheck all other radios
				$('.address-default-radio').not(this).prop('checked', false);

				// Update hidden fields
				$('.address-is-default').val('0');

				// Only set to default if it's not the "none" option
				if (selectedValue !== 'none') {
					$(this).closest('tr').find('.address-is-default').val('1');
				}
			});
		}

		function createAddressRow(index) {
			return `
				<tr class="sender-address-row" data-index="${index}">
					<td>
						<input type="radio" name="woocommerce_gls_shipping_method_sender_addresses_grid_default" value="${index}" class="address-default-radio" />
					</td>
					<td>
						<span class="address-name-display">New Address</span>
					</td>
					<td>
						<button type="button" class="button button-small edit-address">Edit</button>
						<button type="button" class="button button-small delete-address">Delete</button>
						
						<!-- Hidden fields to store all address data -->
						<input type="hidden" name="woocommerce_gls_shipping_method_sender_addresses_grid[${index}][name]" value="" class="address-name" />
						<input type="hidden" name="woocommerce_gls_shipping_method_sender_addresses_grid[${index}][contact_name]" value="" class="address-contact-name" />
						<input type="hidden" name="woocommerce_gls_shipping_method_sender_addresses_grid[${index}][street]" value="" class="address-street" />
						<input type="hidden" name="woocommerce_gls_shipping_method_sender_addresses_grid[${index}][house_number]" value="" class="address-house-number" />
						<input type="hidden" name="woocommerce_gls_shipping_method_sender_addresses_grid[${index}][city]" value="" class="address-city" />
						<input type="hidden" name="woocommerce_gls_shipping_method_sender_addresses_grid[${index}][postcode]" value="" class="address-postcode" />
						<input type="hidden" name="woocommerce_gls_shipping_method_sender_addresses_grid[${index}][country]" value="HR" class="address-country" />
						<input type="hidden" name="woocommerce_gls_shipping_method_sender_addresses_grid[${index}][phone]" value="" class="address-phone" />
						<input type="hidden" name="woocommerce_gls_shipping_method_sender_addresses_grid[${index}][email]" value="" class="address-email" />
						<input type="hidden" name="woocommerce_gls_shipping_method_sender_addresses_grid[${index}][is_default]" value="0" class="address-is-default" />
					</td>
				</tr>
			`;
		}

		function reindexAddresses() {
			$('.sender-address-row[data-index!="none"]').each(function (newIndex) {
				$(this).attr('data-index', newIndex);
				$(this)
					.find('input, select')
					.each(function () {
						const name = $(this).attr('name');
						if (name) {
							const newName = name.replace(/\[\d+\]/, '[' + newIndex + ']');
							$(this).attr('name', newName);
						}
					});
			});
		}

		function openAddressEditModal($row, index) {
			// Add modal styles if not already added
			if ($('#gls-address-modal-styles').length === 0) {
				$('head').append(`
					<style id="gls-address-modal-styles">
						#sender-address-modal {
							position: fixed;
							top: 0;
							left: 0;
							width: 100%;
							height: 100%;
							background-color: rgba(0,0,0,0.5);
							z-index: 100000;
						}
						.gls-address-modal-content {
							position: relative;
							background-color: #fff;
							margin: 5% auto;
							padding: 20px;
							width: 90%;
							max-width: 600px;
							max-height: 80vh;
							overflow-y: auto;
							border-radius: 4px;
							box-shadow: 0 4px 6px rgba(0,0,0,0.1);
						}
						.sender-addresses-table input[type="text"] {
							width: 100%;
							max-width: 150px;
						}
						.sender-addresses-table select {
							width: 100%;
							max-width: 120px;
						}
					</style>
				`);
			}

			const address = {
				name: $row.find('.address-name').val() || '',
				contact_name: $row.find('.address-contact-name').val() || '',
				street: $row.find('.address-street').val() || '',
				house_number: $row.find('.address-house-number').val() || '',
				city: $row.find('.address-city').val() || '',
				postcode: $row.find('.address-postcode').val() || '',
				country: $row.find('.address-country').val() || 'HR',
				phone: $row.find('.address-phone').val() || '',
				email: $row.find('.address-email').val() || '',
				is_default: $row.find('.address-is-default').val() === '1'
			};

			const countryOptions = getCountryOptions(address.country);

			const modal = `
				<div id="sender-address-modal" style="display: none;">
					<div class="gls-address-modal-content">
						<h3>Edit Sender Address</h3>
						
						<table class="form-table">
							<tr>
								<th><label>Name *</label></th>
								<td><input type="text" id="modal-address-name" value="${address.name}" style="width: 100%;" /></td>
							</tr>
							<tr>
								<th><label>Contact Name</label></th>
								<td><input type="text" id="modal-address-contact-name" value="${address.contact_name || ''}" style="width: 100%;" /></td>
							</tr>
							<tr>
								<th><label>Street *</label></th>
								<td><input type="text" id="modal-address-street" value="${address.street}" style="width: 100%;" /></td>
							</tr>
							<tr>
								<th><label>House Number *</label></th>
								<td><input type="text" id="modal-address-house-number" value="${address.house_number}" style="width: 100%;" /></td>
							</tr>
							<tr>
								<th><label>City *</label></th>
								<td><input type="text" id="modal-address-city" value="${address.city}" style="width: 100%;" /></td>
							</tr>
							<tr>
								<th><label>Postcode *</label></th>
								<td><input type="text" id="modal-address-postcode" value="${address.postcode}" style="width: 100%;" /></td>
							</tr>
							<tr>
								<th><label>Country</label></th>
								<td>
									<select id="modal-address-country" style="width: 100%;">
										${countryOptions}
									</select>
								</td>
							</tr>
							<tr>
								<th><label>Phone</label></th>
								<td><input type="text" id="modal-address-phone" value="${address.phone}" style="width: 100%;" /></td>
							</tr>
							<tr>
								<th><label>Email</label></th>
								<td><input type="email" id="modal-address-email" value="${address.email}" style="width: 100%;" /></td>
							</tr>
							<tr>
								<th><label>Set as Default</label></th>
								<td><input type="checkbox" id="modal-address-is-default" ${address.is_default ? 'checked' : ''} /></td>
							</tr>
						</table>
						
						<p class="submit">
							<button type="button" class="button-primary" id="save-address">Save Changes</button>
							<button type="button" class="button" id="cancel-address-edit">Cancel</button>
						</p>
					</div>
				</div>
			`;

			// Remove existing modal if it exists
			$('#sender-address-modal').remove();

			$('body').append(modal);
			$('#sender-address-modal').show();

			$('#save-address')
				.off('click')
				.on('click', function () {
					// Validate required fields
					const name = $('#modal-address-name').val().trim();
					const street = $('#modal-address-street').val().trim();
					const houseNumber = $('#modal-address-house-number').val().trim();
					const city = $('#modal-address-city').val().trim();
					const postcode = $('#modal-address-postcode').val().trim();

					if (!name || !street || !houseNumber || !city || !postcode) {
						alert('Please fill in all required fields (marked with *)');
						return;
					}

					// Update hidden fields
					$row.find('.address-name').val(name);
					$row.find('.address-contact-name').val($('#modal-address-contact-name').val());
					$row.find('.address-street').val(street);
					$row.find('.address-house-number').val(houseNumber);
					$row.find('.address-city').val(city);
					$row.find('.address-postcode').val(postcode);
					$row.find('.address-country').val($('#modal-address-country').val());
					$row.find('.address-phone').val($('#modal-address-phone').val());
					$row.find('.address-email').val($('#modal-address-email').val());

					// Handle default setting
					const isDefault = $('#modal-address-is-default').is(':checked');
					if (isDefault) {
						$('.address-default-radio').prop('checked', false);
						$('.address-is-default').val('0');
						$row.find('.address-default-radio').prop('checked', true);
						$row.find('.address-is-default').val('1');
					} else {
						$row.find('.address-is-default').val('0');
					}

					// Update display values
					$row.find('.address-name-display').text(name);

					$('#sender-address-modal').hide();
				});

			$('#cancel-address-edit')
				.off('click')
				.on('click', function () {
					$('#sender-address-modal').hide();
				});
		}

		// Close modals when clicking outside or on close button
		$(document).on('click', '#gls-account-modal', function (e) {
			if (e.target === this) {
				$(this).hide();
			}
		});

		$(document).on('click', '#sender-address-modal', function (e) {
			if (e.target === this) {
				$(this).hide();
			}
		});

		// ============================================================================
		// GLS PICKUP LOCATION MANAGEMENT
		// ============================================================================

		// Handle GLS pickup location changes in admin
		handleGLSPickupLocationChange();

		function handleGLSPickupLocationChange() {
			var glsScriptLoaded = false;
			var glsDialogsCreated = false;

			// Handle "Change pickup location" button clicks
			$(document).on('click', '.gls-change-pickup-location', function () {
				var $button = $(this);
				var orderId = $button.data('order-id');

				// Disable button during loading
				$button.prop('disabled', true).text('Loading...');

				// Load GLS script and create dialogs if not already done
				loadGLSMapResources(function () {
					// Re-enable button
					$button.prop('disabled', false).text('Change Pickup Location');

					// Show unified map modal that allows selection of any pickup type
					showAdminMapModal('gls-map-unified', orderId);
				});
			});

			function loadGLSMapResources(callback) {
				// If already loaded, just call callback
				if (glsScriptLoaded && glsDialogsCreated) {
					callback();
					return;
				}

				// Load the GLS DPM script first
				if (!glsScriptLoaded) {
					var script = document.createElement('script');
					script.type = 'module';
					script.src = 'https://map.gls-croatia.com/widget/gls-dpm.js';
					script.onload = function () {
						glsScriptLoaded = true;
						createMapDialogs();
						setupMapEventListeners();
						callback();
					};
					script.onerror = function () {
						alert('Failed to load GLS map. Please try again.');
						// Re-enable any disabled buttons
						$('.gls-change-pickup-location').prop('disabled', false).text('Change Pickup Location');
					};
					document.head.appendChild(script);
				} else {
					// Script already loaded, just create dialogs if needed
					if (!glsDialogsCreated) {
						createMapDialogs();
						setupMapEventListeners();
					}
					callback();
				}
			}

			function createMapDialogs() {
				if (glsDialogsCreated) return;

				// Create locker dialog
				var lockerDialog = document.createElement('gls-dpm-dialog');
				lockerDialog.setAttribute('country', 'hr');
				lockerDialog.setAttribute('style', 'position: relative; z-index: 9999;');
				lockerDialog.setAttribute('class', 'inchoo-gls-map gls-map-locker');
				lockerDialog.setAttribute('filter-type', 'parcel-locker');
				document.body.appendChild(lockerDialog);

				// Create shop dialog
				var shopDialog = document.createElement('gls-dpm-dialog');
				shopDialog.setAttribute('country', 'hr');
				shopDialog.setAttribute('style', 'position: relative; z-index: 9999;');
				shopDialog.setAttribute('class', 'inchoo-gls-map gls-map-shop');
				shopDialog.setAttribute('filter-type', 'parcel-shop');
				document.body.appendChild(shopDialog);

				// Create unified dialog for admin edit orders (shows all pickup types)
				var unifiedDialog = document.createElement('gls-dpm-dialog');
				unifiedDialog.setAttribute('country', 'hr');
				unifiedDialog.setAttribute('style', 'position: relative; z-index: 9999;');
				unifiedDialog.setAttribute('class', 'inchoo-gls-map gls-map-unified');
				// Don't set filter-type to show all location types
				document.body.appendChild(unifiedDialog);

				glsDialogsCreated = true;
			}

			function setupMapEventListeners() {
				// Initialize map elements
				var mapElements = document.getElementsByClassName('inchoo-gls-map');

				if (mapElements.length > 0) {
					for (var i = 0; i < mapElements.length; i++) {
						// Remove any existing listeners to avoid duplicates
						mapElements[i].removeEventListener('change', handleMapChange);
						// Add new listener
						mapElements[i].addEventListener('change', handleMapChange);
					}
				}
			}

			function handleMapChange(e) {
				var pickupInfo = e.detail;
				var mapElement = e.target;
				var orderId = mapElement.getAttribute('data-order-id');

				// Update the pickup location via AJAX
				updatePickupLocation(orderId, pickupInfo);
			}

			function showAdminMapModal(mapClass, orderId) {
				var mapElement = document.querySelector('.' + mapClass);
				if (mapElement) {
					// Get country from the order billing/shipping address if available
					var selectedCountry = 'hr'; // Default to Croatia

					// Try to get country from billing fields
					var billingCountryElement = document.getElementById('_billing_country');
					if (billingCountryElement && billingCountryElement.value) {
						selectedCountry = billingCountryElement.value.toLowerCase();
					}

					mapElement.setAttribute('country', selectedCountry);

					// Store order ID for later use in handleMapChange
					mapElement.setAttribute('data-order-id', orderId);

					mapElement.showModal();
				}
			}

			function updatePickupLocation(orderId, pickupInfo) {
				$.ajax({
					url: gls_croatia.adminAjaxUrl,
					type: 'POST',
					data: {
						action: 'gls_update_pickup_location',
						orderId: orderId,
						pickupInfo: JSON.stringify(pickupInfo),
						postNonce: gls_croatia.ajaxNonce
					},
					success: function (response) {
						if (response.success) {
							// Update the pickup display with new information
							updatePickupDisplay(pickupInfo);
						} else {
							alert('Error updating pickup location: ' + response.data.error);
						}
					},
					error: function () {
						alert('An error occurred while updating the pickup location.');
					}
				});
			}

			function updatePickupDisplay(pickupInfo) {
				var $display = $('#gls-pickup-display');
				if ($display.length) {
					var html = '<strong>' + gls_croatia.pickup_location + ':</strong><br/>';
					html += '<strong>ID:</strong> ' + pickupInfo.id + '<br>';
					html += '<strong>' + gls_croatia.name + ':</strong> ' + pickupInfo.name + '<br>';
					html += '<strong>' + gls_croatia.address + ':</strong> ' + pickupInfo.contact.address + ', ' + pickupInfo.contact.city + ', ' + pickupInfo.contact.postalCode + '<br>';
					html += '<strong>' + gls_croatia.country + ':</strong> ' + pickupInfo.contact.countryCode + '<br>';

					// Determine pickup type from the selected location
					var pickupType = 'shop'; // Default to shop
					if (pickupInfo.type && pickupInfo.type.toLowerCase().includes('locker')) {
						pickupType = 'locker';
					} else if (pickupInfo.lockerIds && pickupInfo.lockerIds.length > 0) {
						// Alternative way to detect locker
						pickupType = 'locker';
					}

					// Update/create the change button with the correct pickup type
					var $button = $display.find('.gls-change-pickup-location');
					var orderId = $button.length ? $button.data('order-id') : '';

					if (orderId) {
						html +=
							'<br/><button type="button" class="button button-secondary gls-change-pickup-location" data-order-id="' +
							orderId +
							'" data-pickup-type="' +
							pickupType +
							'">Change Pickup Location</button>';
					}

					$display.html(html);
				}
			}
		}
	});
})(jQuery);
