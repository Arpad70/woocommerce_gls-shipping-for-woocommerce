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
		var localizedConfig =
			typeof gls_croatia !== "undefined" && gls_croatia
				? gls_croatia
				: {};

		var pickupInfoStorageKey =
			localizedConfig.pickup_info_storage_key ||
			"gls_pickup_info";
		var pickupMethodStorageKey =
			localizedConfig.pickup_method_storage_key ||
			"gls_pickup_info_shipping_method";
		var extensionNamespace =
			localizedConfig.extension_namespace ||
			"gls-shipping-for-woocommerce";

		function getStorage() {
			try {
				return window.sessionStorage;
			} catch (error) {
				return null;
			}
		}

		function readStoredPickupInfo() {
			var storage = getStorage();

			if (!storage) {
				return "";
			}

			return storage.getItem(pickupInfoStorageKey) || "";
		}

		function readStoredShippingMethodBaseId() {
			var storage = getStorage();

			if (!storage) {
				return "";
			}

			return storage.getItem(pickupMethodStorageKey) || "";
		}

		function persistPickupInfo(pickupInfo, shippingMethodBaseId) {
			var storage = getStorage();

			if (!storage) {
				return;
			}

			storage.setItem(pickupInfoStorageKey, pickupInfo || "");
			storage.setItem(pickupMethodStorageKey, shippingMethodBaseId || "");
		}

		function removeStoredPickupInfo() {
			var storage = getStorage();

			if (!storage) {
				return;
			}

			storage.removeItem(pickupInfoStorageKey);
			storage.removeItem(pickupMethodStorageKey);
		}

		function parsePickupInfo(pickupInfo) {
			if (!pickupInfo || typeof pickupInfo !== "string") {
				return null;
			}

			try {
				return JSON.parse(pickupInfo);
			} catch (error) {
				return null;
			}
		}

		function hasMeaningfulPickupInfo(pickupInfo) {
			if (!pickupInfo || !String(pickupInfo).trim()) {
				return false;
			}

			var parsedPickupInfo = parsePickupInfo(String(pickupInfo));

			if (!parsedPickupInfo || typeof parsedPickupInfo !== "object") {
				return true;
			}

			if (parsedPickupInfo.name) {
				return true;
			}

			var contact = parsedPickupInfo.contact || {};

			return Boolean(
				contact.address ||
					contact.city ||
					contact.postalCode ||
					contact.countryCode
			);
		}

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

		function hydrateHiddenInputFromStorage() {
			var hiddenInput = getHiddenInput();
			var storedPickupInfo = readStoredPickupInfo();

			if (!hiddenInput || hiddenInput.value || !hasMeaningfulPickupInfo(storedPickupInfo)) {
				return hiddenInput;
			}

			hiddenInput.value = storedPickupInfo;

			var storedMethodBaseId = readStoredShippingMethodBaseId();
			if (storedMethodBaseId) {
				hiddenInput.dataset.shippingMethod = storedMethodBaseId;
			}

			return hiddenInput;
		}

		function renderPickupInfo(pickupInfo) {
			if (typeof pickupInfo === "string") {
				pickupInfo = parsePickupInfo(pickupInfo);
			}

			var pickupInfoDiv = document.getElementById("gls-pickup-info");

			if (!pickupInfoDiv || !pickupInfo || !pickupInfo.contact) {
				return;
			}

			pickupInfoDiv.innerHTML =
				"<strong>" +
				localizedConfig.pickup_location +
				":</strong><br>" +
				localizedConfig.name +
				": " +
				(pickupInfo.name || "") +
				"<br>" +
				localizedConfig.address +
				": " +
				(pickupInfo.contact.address || "") +
				", " +
				(pickupInfo.contact.city || "") +
				", " +
				(pickupInfo.contact.postalCode || "") +
				"<br>" +
				localizedConfig.country +
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

					var serializedPickupInfo = JSON.stringify(pickupInfo);
					var shippingMethodBaseId = getShippingMethodBaseId(
						getCurrentShippingMethodValue()
					);

					hiddenInput.value = serializedPickupInfo;
					hiddenInput.dataset.shippingMethod = shippingMethodBaseId;
					persistPickupInfo(serializedPickupInfo, shippingMethodBaseId);

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
				localizedConfig.filter_saturation
			) {
				mapElement.setAttribute(
					"filter-saturation",
					localizedConfig.filter_saturation
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

			removeStoredPickupInfo();
		}

		function syncPickupInfoFromStorage() {
			var hiddenInput = hydrateHiddenInputFromStorage();
			var pickupInfo = hiddenInput && hiddenInput.value ? hiddenInput.value : readStoredPickupInfo();

			if (!hasMeaningfulPickupInfo(pickupInfo)) {
				return;
			}

			renderPickupInfo(pickupInfo);
		}

		function isStoreApiCheckoutUrl(url) {
			return /\/wc\/store\/(?:v\d+\/)?checkout(?:\/|$|\?)/.test(url);
		}

		function injectPickupInfoIntoRequestBody(bodyText) {
			if (typeof bodyText !== "string" || !bodyText.trim()) {
				return bodyText;
			}

			var pickupInfo = readStoredPickupInfo();
			if (!hasMeaningfulPickupInfo(pickupInfo)) {
				return bodyText;
			}

			try {
				var payload = JSON.parse(bodyText);
				if (!payload || typeof payload !== "object" || Array.isArray(payload)) {
					return bodyText;
				}

				payload.gls_pickup_info = pickupInfo;
				payload[pickupInfoStorageKey] = pickupInfo;
				payload.extensions = payload.extensions || {};
				payload.extensions[pickupInfoStorageKey] = pickupInfo;
				payload.extensions[extensionNamespace] =
					payload.extensions[extensionNamespace] || {};
				payload.extensions[extensionNamespace].pickup_info = pickupInfo;
				payload.extensions[extensionNamespace][pickupInfoStorageKey] = pickupInfo;

				return JSON.stringify(payload);
			} catch (error) {
				return bodyText;
			}
		}

		function installStoreApiCheckoutBridge() {
			if (
				typeof window.fetch !== "function" ||
				window.fetch.__glsPickupBridgeInstalled
			) {
				return;
			}

			var originalFetch = window.fetch.bind(window);

			window.fetch = async function (resource, options) {
				var requestUrl =
					typeof resource === "string"
						? resource
						: resource && resource.url
						? resource.url
						: "";

				if (!isStoreApiCheckoutUrl(requestUrl)) {
					return originalFetch(resource, options);
				}

				if (
					resource instanceof Request &&
					(!options || typeof options.body === "undefined")
				) {
					var requestBody = await resource.clone().text();
					var patchedRequestBody = injectPickupInfoIntoRequestBody(requestBody);

					if (patchedRequestBody !== requestBody) {
						var requestHeaders = new Headers(resource.headers || {});
						if (!requestHeaders.has("Content-Type")) {
							requestHeaders.set("Content-Type", "application/json");
						}

						resource = new Request(resource, {
							body: patchedRequestBody,
							headers: requestHeaders,
						});
					}

					return originalFetch(resource, options);
				}

				if (options && typeof options.body === "string") {
					var patchedBody = injectPickupInfoIntoRequestBody(options.body);
					if (patchedBody !== options.body) {
						options = Object.assign({}, options, {
							body: patchedBody,
						});
					}
				}

				return originalFetch(resource, options);
			};

			window.fetch.__glsPickupBridgeInstalled = true;
		}

		function updateCheckout() {
			hydrateHiddenInputFromStorage();
			syncPickupInfoFromStorage();

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
				if (!selectedShippingMethodBaseId) {
					return;
				}

				clearGLSPickupInfo();
				return;
			}

			if (
				selectedShippingMethodBaseId &&
				hiddenInput &&
				hasMeaningfulPickupInfo(hiddenInput.value) &&
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

		hydrateHiddenInputFromStorage();
		syncPickupInfoFromStorage();
		bindMapChangeHandlers();
		installStoreApiCheckoutBridge();
		document.body.addEventListener("updated_checkout", function () {
			bindMapChangeHandlers();
			updateCheckout();
		});
	});
})();
