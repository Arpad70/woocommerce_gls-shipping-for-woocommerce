/* eslint-disable */
(function () {
	"use strict";

	function domReady(callback) {
		if (document.readyState === "loading") {
			document.addEventListener("DOMContentLoaded", callback);
		} else {
			callback();
		}
	}

	domReady(function () {
		function getCheckoutForm() {
			return document.forms["checkout"] || document.querySelector("form.checkout");
		}

		function getCurrentShippingMethodValue() {
			var selectedShippingMethod = document.querySelector(
				'input[name="shipping_method[0]"]:checked'
			);

			return selectedShippingMethod ? selectedShippingMethod.value : "";
		}

		function getShippingMethodBaseId(methodValue) {
			if (!methodValue) {
				return "";
			}

			return String(methodValue).split(":")[0];
		}

		function getMapElement(mapClass) {
			return document.querySelector("gls-dpm-dialog." + mapClass);
		}

		function getHiddenInput() {
			var hiddenInput = document.getElementById("gls-pickup-info-data");
			var checkoutForm = getCheckoutForm();

			if (!hiddenInput && checkoutForm) {
				hiddenInput = document.createElement("input");
				hiddenInput.type = "hidden";
				hiddenInput.id = "gls-pickup-info-data";
				hiddenInput.name = "gls_pickup_info";
				checkoutForm.appendChild(hiddenInput);
			}

			return hiddenInput;
		}

		function renderPickupInfo(pickupInfo) {
			var pickupInfoDiv = document.getElementById("gls-pickup-info");

			if (!pickupInfoDiv || !pickupInfo || !pickupInfo.contact) {
				return;
			}

			pickupInfoDiv.innerHTML =
				"<strong>" +
				gls_croatia.pickup_location +
				":</strong><br>" +
				gls_croatia.name +
				": " +
				(pickupInfo.name || "") +
				"<br>" +
				gls_croatia.address +
				": " +
				(pickupInfo.contact.address || "") +
				", " +
				(pickupInfo.contact.city || "") +
				", " +
				(pickupInfo.contact.postalCode || "") +
				"<br>" +
				gls_croatia.country +
				": " +
				(pickupInfo.contact.countryCode || "");
			pickupInfoDiv.style.display = "block";
		}

		function bindMapChangeHandlers() {
			var mapElements = document.getElementsByClassName("inchoo-gls-map");

			if (mapElements.length === 0) {
				return;
			}

			for (var i = 0; i < mapElements.length; i++) {
				if (mapElements[i].dataset.glsBound === "1") {
					continue;
				}

				mapElements[i].dataset.glsBound = "1";
				mapElements[i].addEventListener("change", function (e) {
					var pickupInfo = e.detail || {};
					var hiddenInput = getHiddenInput();

					renderPickupInfo(pickupInfo);

					if (!hiddenInput) {
						return;
					}

					hiddenInput.value = JSON.stringify(pickupInfo);
					hiddenInput.dataset.shippingMethod = getShippingMethodBaseId(
						getCurrentShippingMethodValue()
					);

					if (window.jQuery) {
						window.jQuery(document.body).trigger("update_checkout");
					}
				});
			}
		}

		function showMapModal(mapClass) {
			// Use shipping country when "Ship to a different address" is checked,
			// so the map matches the country used by PHP calculate_shipping() (destination).
			var shipToDifferent = document.getElementById(
				"ship-to-different-address-checkbox"
			);
			var countryFieldId =
				shipToDifferent && shipToDifferent.checked
					? "shipping_country"
					: "billing_country";
			var countryField = document.getElementById(countryFieldId);
			var selectedCountry = countryField ? countryField.value : "";

			var mapElement = getMapElement(mapClass);
			if (!mapElement) {
				return;
			}

			var countryLower = selectedCountry.toLowerCase();
			mapElement.setAttribute("country", countryLower);

			// Apply filter-saturation for Hungary parcel locker only
			if (
				countryLower === "hu" &&
				mapClass === "gls-map-locker" &&
				gls_croatia.filter_saturation
			) {
				mapElement.setAttribute(
					"filter-saturation",
					gls_croatia.filter_saturation
				);
			} else {
				mapElement.removeAttribute("filter-saturation");
			}

			mapElement.showModal();
		}

		document.body.addEventListener("click", function (event) {
			var lockerButton = event.target.closest(
				".dugme-gls_shipping_method_parcel_locker"
			);
			var shopButton = event.target.closest(
				".dugme-gls_shipping_method_parcel_shop"
			);

			if (lockerButton) {
				showMapModal("gls-map-locker");
			} else if (shopButton) {
				showMapModal("gls-map-shop");
			}
		});

		function clearGLSPickupInfo() {
			var glsPickupInfo = document.getElementById("gls-pickup-info");
			var glsPickupInfoData = document.getElementById(
				"gls-pickup-info-data"
			);

			if (glsPickupInfo) {
				glsPickupInfo.innerHTML = "";
				glsPickupInfo.style.display = "none";
			}
			if (glsPickupInfoData) {
				glsPickupInfoData.value = "";
				delete glsPickupInfoData.dataset.shippingMethod;
			}
		}

		function updateCheckout() {
			var selectedShippingMethodValue = getCurrentShippingMethodValue();
			var selectedShippingMethodBaseId = getShippingMethodBaseId(
				selectedShippingMethodValue
			);
			var hiddenInput = getHiddenInput();
			var lockerMap = getMapElement("gls-map-locker");
			var shopMap = getMapElement("gls-map-shop");
			var isLockerMethod =
				selectedShippingMethodBaseId ===
				"gls_shipping_method_parcel_locker" ||
				selectedShippingMethodBaseId ===
					"gls_shipping_method_parcel_locker_zones";
			var isShopMethod =
				selectedShippingMethodBaseId ===
				"gls_shipping_method_parcel_shop" ||
				selectedShippingMethodBaseId ===
					"gls_shipping_method_parcel_shop_zones";

			if (lockerMap) {
				lockerMap.setAttribute("filter-type", "parcel-locker");
			}

			if (shopMap) {
				shopMap.setAttribute("filter-type", "parcel-shop");
			}

			if (!isLockerMethod && !isShopMethod) {
				clearGLSPickupInfo();
				return;
			}

			if (
				hiddenInput &&
				hiddenInput.value &&
				hiddenInput.dataset.shippingMethod &&
				hiddenInput.dataset.shippingMethod !== selectedShippingMethodBaseId
			) {
				clearGLSPickupInfo();
			}
		}

		// Event listener for shipping method change
		document.body.addEventListener("change", function (event) {
			if (event.target.name === "shipping_method[0]") {
				updateCheckout();
			}
		});

		bindMapChangeHandlers();
		document.body.addEventListener("updated_checkout", function () {
			bindMapChangeHandlers();
			updateCheckout();
		});
	});
})();
