=== GLS Shipping for WooCommerce ===
Contributors: goran87
Tags: gls, shipping, woocommerce shipping
Requires at least: 5.9
Tested up to: 6.9
Stable tag: 1.4.3
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

GLS Shipping plugin for WooCommerce

== Description ==

This plugin seamlessly integrates GLS Shipping into your website, supporting both global shipping methods and custom Shipping Zones. It includes advanced features for multiple account management, bulk label operations, pickup point management, package tracking, pickup announcements, and comprehensive order management with enhanced security and performance.

## Introduction ##

This WooCommerce shipping plugin integrates with GLS Group to provide direct shipping capabilities within your WooCommerce store. This plugin uses external services to handle shipping processes and tracking effectively.

## Key Features ##

* **Multiple Accounts Management**: Support for multiple GLS accounts (Client ID, Username, Password, Country) with grid interface for easy management and switching
* **Pickup Announcement System**: Announce package pickup to GLS directly from admin panel
* **Package Tracking**: Real-time package status checking using GetParcelStatuses API
* **Bulk Operations**: Generate and print shipping labels in bulk with tracking number extraction
* **Print Position Selection**: Customize print position per order through BulkPrint options
* **Service Selection**: Change shipping service on orders before label generation
* **COD Reference Management**: Custom COD reference values with order-specific configuration
* **Content Field Placeholders**: Dynamic placeholders for order content (order_id, customer_comment, etc.)
* **GLS Parcel ID Tracking**: Dedicated column for GLS parcel IDs on order grid with export support
* **Product Restrictions**: Exclude products from parcel locker/shop shipping methods based on size
* **GLS Logo Display**: Configurable GLS logo display on checkout shipping methods
* **Multi-Language Support**: Full translation support for 7 languages (Croatian, Czech, Hungarian, Romanian, Slovenian, Slovak, Serbian)
* **Shipping Zones**: Flexible shipping zone configuration for different regions
* **Enhanced Security**: Improved sanitization and data validation throughout the plugin
* **HPOS Compatible**: Full support for WooCommerce High-Performance Order Storage

## Supported Countries ##
- Croatia
- Czech Republic
- Hungary
- Romania
- Slovenia
- Slovakia
- Serbia

## External Services ##
This plugin makes use of the following third-party services:

### GLS Group APIs ###
- **Service:** GLS Shipping Tracking
- **Purpose:** Allows users to track their shipments directly through WooCommerce.
- **URL:** [GLS Group](https://gls-group.com/HR/en/)
- **Privacy Policy:** [GLS Privacy Policy](https://gls-group.com/HR/en/privacy-policy)

### OpenStreetMap ###
- **Service:** OpenStreetMap API
- **Purpose:** Used to provide map functionalities in the shipping plugin.
- **URL:** [OpenStreetMap](https://openstreetmap.org)
- **Privacy Policy:** [OpenStreetMap Privacy](https://wiki.osmfoundation.org/wiki/Privacy_Policy)

## Data Handling and Privacy ##
When using our plugin, certain data such as tracking numbers and geographical locations may be transmitted to third-party services mentioned above. We do not store this data on our servers. Please review the privacy policies of the respective services (linked above) to understand how they manage your data.

= Links and Additional Information =
For more details about GLS Shipping plugin for WooCommerce and how it integrates with your WordPress site, please visit our website: [GLS Group](https://gls-group.com/HR/en/)
To understand how we handle and protect your data, please review our Terms of Use and Privacy Policies available at the following links:
* [Terms of Service](https://gls-group.com/HR/en/terms-conditions/) 
* [Privacy Policy](https://gls-group.com/HR/en/privacy-policy/)

== Installation ==

To install and configure this plugin:
1. Download and activate the plugin in your WooCommerce store.
2. Navigate to WooCommerce Settings > Shipping and select GLS Shipping.
3. Enter your GLS API credentials and configure the necessary settings to enable the shipping and tracking functionalities.

== Frequently Asked Questions ==

== Screenshots ==

== Changelog ==

= 1.4.3 =
* AR Design release skeleton: added VERSION file, changelog, build script, release workflow, and version consistency checks.
* Checkout fix: cash on delivery is now hidden when the selected GLS pickup point does not support cash payments.
* Checkout UX: selecting GLS parcel locker / parcel shop immediately refreshes checkout payment methods.

= 1.4.2 =
* Fix: Fixed a bug where "Ship with different address" would stil take Billing address for map pin options.

= 1.4.1 =
* Fix: Fixed Reflected Cross-Site Scripting (XSS) vulnerability in bulk action admin notices via the 'failed_orders' parameter. Added proper input sanitization and output escaping.
* Fix: Added proper exception handling for GLS API errors in bulk label operations to prevent fatal errors. API errors are now displayed as admin notices instead of causing crashes.

= 1.4.0 =
* Secure PDF Label Storage: Labels are now stored in a protected directory with authentication-based download.
* Added Hungary Locker Saturation Filter for parcel locker map (filter-saturation attribute).

= 1.3.3 =
* Tested up to WordPress 6.9.

= 1.3.2 =
* Fix: Fixed PHP error issue.

= 1.3.1 =
* Fix: Fixed Free Shipping with Tax Inclusive prices.

= 1.3.0 =
* **Multiple Accounts Management**: Added support for multiple GLS accounts (Client ID, Username, Password, Country) with grid interface for managing, modifying, deleting, and adding new accounts. Includes migration for existing merchants.
* **Sender Address Configuration**: Added ability to configure sender address within plugin settings with fallback to platform default. Custom address fields added under account data.
* **Post-Order Pickup Point Changes**: Merchants can now change parcel lockers or parcel shops within existing orders.
* **Enhanced Print/Download Icons**: Updated GLS icon colors on order grid for better visual distinction.
* **Pickup Announcement System**: Added functionality for merchants to announce package pickup to GLS from admin panel with grid interface and required form fields.
* **Package Tracking**: Added button within orders to check package status using GetParcelStatuses API.
* **GLS Parcel ID Column**: Added column for GLS parcel ID on order/shipment grid. Multiple values separated by spaces.
* **Export Support**: Enhanced export functionality for XLSX/CSV with GLS parcel ID data included.
* **Print Position Selection**: Added print position selection through BulkPrint option within orders, with field next to label generation button.
* **Bulk Print Tracking**: Enhanced bulk print to extract and save tracking numbers from response to associated orders.
* **Content Field Placeholders**: Added placeholders (order_id, customer_comment, etc.) for Content field in config, similar to Client Reference field. Removed Serbia-only restriction.
* **COD Reference Field**: Added field next to label generation button for custom CODReference values, defaulting to "#order_id". Field only visible for COD orders.
* **Service Selection Popup**: Added ability to change Service on orders before label generation, with config defaults and per-order customization.
* **GLS Logo Display**: Added configuration option to display GLS logo on checkout shipping methods.
* **Parcel Locker Size Integration**: Added "Exclude For Parcel Locker/Shop" product attribute to disable parcel locker shipping methods for incompatible products.
* **Major Code Cleanup**: Comprehensive refactoring for improved performance and maintainability
* **Enhanced Security**: Improved sanitization and data validation throughout the plugin
* **Updated Translations**: Complete translation updates for all supported languages (Croatian, Czech, Hungarian, Romanian, Slovenian, Slovak, Serbian)
* **Bug Fixes**: Fixed GLS count display, bulk services handling, parcel preselection, contact name handling, and various other improvements

= 1.2.6 =
* Fix: Woo Store theme mobile bug fix.

= 1.2.5 =
* Fix: Fatal error on email previews.

= 1.2.4 =
* Fixed issue when changing shipping method would leave pickup info

= 1.2.3 =
* Added WebshopEngine in request logs.

= 1.2.2 =
* Removed second Street Address field from content.

= 1.2.1 =
* Added support for Street Address second field

= 1.2.0 =
* Added support for Shipping Zones
* Refactored script from jQuery to Vanilla JS
* Added support for Free Shipping
* Bulk Label Generation on the order listing screen
* Bulk Label Printing on the order listing screen
* Introduced weight-based pricing support
* Added the ability to set the number of packages

= 1.1.4 =
* Tax support

= 1.1.3 =
* Support for WordPress 6.6

= 1.1.2 =
* Support for SenderIdentityCardNumber and Content fields.
* Support for Print Position and Printer Type

= 1.1.1 =
* Updated readme file and additional sanitization. 

= 1.1.0 =
* Sanitization and escaping

= 1.0.1 =
* HPOS Support fix.

= 1.0.0 =
* Initial version

== Upgrade Notice ==
