=== Printeers Print & Ship ===
Contributors: invition
Author: Printeers
Author URI: https://printeers.com/
Tags: dropship, supplier, dropshipping, phone case, print on demand
Requires at least: 4.9
Tested up to: 6.4
Requires PHP: 7.1
Stable Tag: trunk
License: Modified BSD License
License URI: https://opensource.org/licenses/BSD-3-Clause

Sell phone cases with your own design using the Printeers Print & Ship dropship service.

== Description ==
Connect your website to the Printeers Platform with this plugin and start selling customized phone cases right away. This plugin is an extension for WooCommerce which lets you sell printed smartphone cases with your own designs. Orders will be posted to Printeers automatically and shipments, product stocks and attributes are updated automatically.

= Generating product variations =
The most interesting feature is the Automatic Product Variations. There are so many different smartphone models and brands available that it is almost impossible to quickly setup your store and sell a case for them al. We have solved that problem. Printeers Print & Ship makes it easy to generate product variations for every brand, phone model and case type. You can start selling your design on over 150 devices without much effort. Even the product images are rendered automatically. To make this a bit more visible, you can take a look at our showcase-website [All in Flavour](http://allinflavour.com).

= Automatic order processing through the order API using dropshipping =
The plugin uses our order API. When your customer places an order and completes the payment, the order is automatically transferred to our system for printing and shipping. We print on demand phone cases. When we ship your order, the order status is changed automatically. Your customer receives an e-mail with a tracking code (if applicable) and shipping confirmation.

= Price and stock imports =
To make it even easier, the dropshipping-plugin also synchronizes pricing and product stocks. When your customer selects a product it will see the right price and the right stock levels. This means you will never by accident sell anything which is out of stock or sell something for the wrong price. We manage that for you.

== Installation ==
This plugin requires WooCommerce version 3.4 or higher to function properly. Installation and can be done in 4 simple steps:

1. Install WooCommerce
2. Install Printeers Print & Ship
3. Request API details
4. Set API details in your plugin

A longer installation manual [can be found here](https://printeers.com/guides/woocommerce/how-to-install-invition-print-ship-on-your-wordpress/).

== Screenshots ==
1. Automatic product variations
2. Product updates page

== Changelog ==
= 1.17 =
* Send shipping email and phone number instead of billing, when available
* It is now possible to enable/disable rendering an image for both the main variable product as for variations
* Fixed a bug where attributes are added more than once.
* Added possibility to automatically cleanup images from variations when the variable product is deleted

= 1.16 =
* Renamed Invition to Printeers
* Added expected available date as a shortcode to display on frontend

= 1.15.2 =
* Added print_and_ship_order_reference to order search field

= 1.15.1 =
* Bugfix: Ignore orders without Printeers products

= 1.15 =
* Replaced links to help centre
* Change order status to on hold when sendOrder fails
* Improve error reporting when sendOrder fails
* Remove limit from wc_get_orders query
* Added custom query vars to only query orders without order reference for runOrdersCron

= 1.14 =
* Product updates is now using WC_Product_Variation to add variations to the database

= 1.13.2 =
* Bugfix: Automatic shipping calculator used the parent ID to calculate shipping instead of the variation ID

= 1.13.1 =
* Hotfix: Check if the product exists in the received stocklist before importing
* Hotfix: Check if the stocklist is received before starting import

= 1.13 =
* Removed print_and_ship_is_print_item meta. We now check if it's a print item by checking the print dimensions in the database
* Rewritten import logic to make it readable and non-spaghetti
* Extensive code cleanup
* Added support for variable products in admin order print image manager

= 1.12.1 =
* Hotfix: Change permissions from manage_options to edit_posts so shop managers can manage the Print & Ship plugin

= 1.12 =
* Added displaying shipments on the view order page
* Bugfix: Plugin now prevents non-print items from being added to the Zakeke queue
* Bugfix: Since 5.5 all rest routes require a permission_callback

= 1.11 =
* Simplified product adding
* Moved the Zakeke part to the Zakeke extension
* Renamed import function
* Import print sizes to keep track of size changes

= 1.10.1 =
* Import print sizes
* Add hooks to create product and size changed
* Extract Zakeke logic more to Zakeke extension
* Reversed some logic for readability

= 1.10 =
* Added automatic shipping calculation through the Printeers Quote API
* Added automatic calculations based on the automated shipping rates

= 1.9.7 =
* Moved creating products to separate function for future accessibility

= 1.9.6 =
* Define a simple product as simple at product creation
* Bugfix: Simple product query in Product Updates also queried variable

= 1.9.5 =
* Rewritten sendOrder function for more readability
* Added more debug information in sendOrder function

= 1.9.4 =
* Option for filtering existing products from Create a simple product list

= 1.9.3 =
* Added Printeers shipping levels as WooCommerce shipping methods
* Shipping method on order level can now overrule general setting

= 1.9.2 =
* Fixed bug: Product updates suggests discounted products to fix price as well
* Added base price for price calculations (srp / purchase price)
* Updated settings styles

= 1.9.1 =
* Add phone number and email to Printeers order

= 1.9.0 =
* Removed importPrint() function as a print item will always be a print item. 
* Removed print image validation. Validation happens in the International Printeers Platform
* Removed validation notice
* Removed check if product already exists on rendering 'Create a product' table
* Check once every six hours for open orders if they are finished at Printeers (for when the callback was not set)
* Product updates will only suggest adding a variation when the product image can be rendered
* Created a new upload dir to separate some images for easier cleanup and less WP polution
* Renamed adminGetPrintImage to adminGetPrintImageURL
* Removed private $product from Woo as it is not used anymore
* Updated ACF to 5.8.9 because of incompatibility issues since WordPress 5.4

= 1.8.4 =
* Bugfix: Removed the Emogrifier class for now. Will include an improved e-mail handling in the next update

= 1.8.3 =
* Bugfix: Gift items per product did not work because of a typo in get_option name

= 1.8.2 =
* Removed Zakeke settings because the plugin requests it directly from Zakeke

= 1.8.1 =
* Bugfix: track and trace class was still using old (non existing) debuglog function

= 1.8.0 =
* Moved CHANGELOGE file to readme.txt
* Renamed admin-page to admin-settings

= 1.7.3 =
* Select all checkboxes on the Product Updates page

= 1.7.2 =
* Bugfix: Called default PHP function within PrintAndShip namespace

= 1.7.1 =
* WooCommerce version check
* Debuglog can now be enabled/disabled in the settings
* Bugfix: Check if we have a print image before trying to use it in adminGetPrintImage()
* Bugfix: runImportOrdersCron fires on WordPress load, should only fire on cron run

= 1.7.0 =
* Price management for simple products
* Product updates page shows difference between simple and variable products
* Bugfix: Undefined and unused variable in Create a product finish3

= 1.6.5 =
* Bugfix: Callback was not working because of improper namespace implementation

= 1.6.4 =
* Deleted all frontend functionality as this is never used by customers
* Rewritten some logic about print images

= 1.6 =
* Removed all prefixes from function names and added a namespace to the plugin

= 1.5.3 =
* Create simple products page checks if Zakeke addon is active, if yes: show extra option for adding products.
* WooCommerce order status 'Ready for production'
* Default status setting first checks if Zakeke is active
* Orders are now placed with a 1 minute cron instead of with a WooCommerce status change hook

= 1.5.2 =
* Minimum shipping level can now be set as master value
* Part of code formatted to PSR-2 standards (rest will follow in later updates)
* Added a function check to admin-page.php to prevent errors when WooCommerce is not enabled
* Rewritten stock import query
* Removed function findProductID from class-woo.php and stock importer to prevent unneccessary queries

= 1.5.1 =
* Packing and gift items can be automatically added to an order now
* Removed unused code from class-woo.php

= 1.5 =
* Settings for shipment e-mail
* Added tabs on settings page to create a better overview
* Bugfix: Undefined property: stdClass::$amount_left in classes/class-new-products-table.php on line 337
* Removed tablenav bottom actions at Simple products page to prevent confusion
* Bugfix: Search field in Create simple product was not displayed
* Bugfix: Call to a member function is_type() on boolean in /wp-content/plugins/invition-print-ship/classes/class-woo.php:219
* Rewritte prepareOrder function to make it more readable and cleaner
* Raised timeout for wp_remote_post to make sure big images and slow servers still work

= 1.4.2 =
* Settings for add/subtract a certain amount or percentage to or from generated variable products
* Setting for rounding prices
* Function in class.product_update to calculate prices based on settings
* Bugfix: non existing products conflict with print image uploader
* Bugfix: SRP change only updated _regular_price, now also updates _price

= 1.4.1 =
* Moved logic for selecting print image to separate function print_and_ship_adminGetPrintImage
* Bugfix: get_meta on null in assets/php/admin-order-print-image.php line 15
* Tested for WordPress 5.2

= 1.4 =
* When an order is not committed, the print image can be changed
* Print image is displayed on the order page
* Featherlight lightbox included because default Thickbox works bad
* Improved error reporting on some functions
* woo->uploadImage now first looks for a print image on orderline level

= 1.3.3 =
* Bugfix: Issue with discontinued products resolved

= 1.3.2 =
* Added action to remove variations when they are discontinued to keep the site clean
* Bug fixed which caused discontinued products to be added

= 1.3.1 =
* Links to help pages for more information
* Added error reporting in Product Updates page
* Force disable notices in WP API JSON results
* Solved some PHP notices
* Form labels in settings page are now correct

= 1.3 =
* Created a wizard to add products to WooCommerce easier
* Added Invition fonts and colours to the plugin
* Link to Add products was removed from the menu and moved to the wizard

= 1.2.2 =
* Allow backorders setting (User now decides if backorders are allowed (if allowed by Printeers))

= 1.2.1 =
* Security fix, replace esc_attr function for sanitize_text_field

= 1.2 =
* Display plugin version on support page
* Product updates page now displays notice when no actions are found
* Product updates now checks if an attribute was added to the database before suggesting to connect it to a product
* Renamed wp_options and wp_product_meta entries, new prefix is print_and_ship (BREAKING CHANGE)
* API callback url changed to /wp-json/invition-and-print-ship/v1/callback for consistency (BREAKING CHANGE)
* Replaced prefix itw_ in all function names and file names
* Function names after the prefix are now in camelCase to keep it readable
* Plugin main file now called invition-print-and-ship.php (used to be ipp-to-woo.php)
* Removed function itw_admin_footer() because it's not neccessary and it wasn't working anyway
* Function which checks Imagick status is now removed from support and settings page, Imagick support remains for now

= 1.1.4 =
* Changelog file added
* Moved get_option requests in class-product-update.php to function top to prevent unneccessary db roundtrips
* Rebuilt discover actions queries to make the code faster and more efficient
