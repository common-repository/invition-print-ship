<?php
/**
 * Plugin Name: Printeers Print & Ship
 * Plugin URI: https://printeers.com/getting-started/woocommerce/
 * Description: Sell customised phone cases, you make the sale and Printeers makes the case!
 * Author: Printeers
 * Version: 1.17.0
 * Author URI: http://printeers.com/
 * WC requires at least: 4.0.0
 * WC tested up to: 8.6.0
 *
 * @author    Mike Sies <mike@studiogewaagd.nl>
 * @copyright 2017 Printeers
 **/

namespace PrintAndShip;

defined('ABSPATH') or die('No script kiddies please!');

// Constants
define('PRINT_AND_SHIP_BASEDIR', dirname(__FILE__));
define('PRINT_AND_SHIP_UPLOADDIR', getUploadDir());
define('PRINT_AND_SHIP_UPLOADURL', getUploadURL());
define('PRINT_AND_SHIP_CLASSES', PRINT_AND_SHIP_BASEDIR . '/classes/');
define('PRINT_AND_SHIP_TEMPLATES', PRINT_AND_SHIP_BASEDIR . '/assets/html/');
define('PRINT_AND_SHIP_VERSION', '1.17.0');

// Config
define('PRINT_AND_SHIP_PROD_SERVER_URL', 'https://api.prod.invition.nl/');
define('PRINT_AND_SHIP_TEST_SERVER_URL', 'https://api.test.invition.nl/');

// ******** WP FUNCTIONS, ACTIONS AND FILTERS ******** //

/**
 * Activation of plugin
 *
 * @return void
 */
function activatePlugin()
{
    // Set plugin version
    update_option('print_and_ship_version', PRINT_AND_SHIP_VERSION);

    // Make sure all attributes exist
    registerWcAttributes();
    
    // Set defaults
    if (empty(get_option('print_and_ship_order_status'))) {
        update_option('print_and_ship_order_status', 'wc-processing');
    }
}

/**
 * Get the Print & Ship Upload directory
 * 
 * @return string location of directory
 */
function getUploadDir()
{
    $upload = wp_upload_dir();
    $upload_dir = $upload['basedir'] . '/invition-print-and-ship';

    // Create upload dir for custom designs
    if (!file_exists($upload_dir)) {
        wp_mkdir_p($upload_dir);
    }
    
    return $upload_dir;
}

/**
 * Get the Print & Ship Upload directory
 * 
 * @return string location of directory
 */
function getUploadURL()
{
    $upload = wp_upload_dir();
    $upload_url = $upload['baseurl'];
    
    return $upload_url . '/invition-print-and-ship';
}

/**
 * Schedule the cronjobs
 *
 * @return void
 */
function scheduleCron()
{
    if (!wp_next_scheduled('print_and_ship_import_cron')) {
        wp_schedule_event(time(), 'print_and_ship_5_minutes', 'print_and_ship_import_cron');
    }
    if (!wp_next_scheduled('print_and_ship_orders_cron')) {
        wp_schedule_event(time(), 'print_and_ship_1_minute', 'print_and_ship_orders_cron');
    }
    if (!wp_next_scheduled('print_and_ship_completed_orders_cron')) {
        wp_schedule_event(time(), 'print_and_ship_6_hour', 'print_and_ship_completed_orders_cron');
    }
}


/**
 * Unschedule cronjobs when deactivating plugin
 *
 * @return void
 */
function unscheduleCron()
{
    $timestamp = wp_next_scheduled('print_and_ship_import_cron');
    wp_unschedule_event($timestamp, 'print_and_ship_import_cron');
    
    $timestamp = wp_next_scheduled('print_and_ship_orders_cron');
    wp_unschedule_event($timestamp, 'print_and_ship_orders_cron');
    
    $timestamp = wp_next_scheduled('print_and_ship_completed_orders_cron');
    wp_unschedule_event($timestamp, 'print_and_ship_completed_orders_cron');
}


/**
 * Add 5 minutes as custom interval
 * 
 * @param array $schedules The schedules that already exist
 *
 * @return array
 */
function cronCustomInterval($schedules)
{
    $schedules['print_and_ship_5_minutes'] = array(
        'interval' => 300,
        'display' => 'Every 5 minutes'
    );
    $schedules['print_and_ship_1_minute'] = array(
        'interval' => 60,
        'display' => 'Every minute'
    );
    $schedules['print_and_ship_6_hour'] = array(
        'interval' => 21600,
        'display' => 'Every six hours'
    );
    return $schedules;
}

/**
 * Register our custom shipping methods. Each WooCommerce shipping method is a shipping level
 * 
 * @param array $methods an array of already existing methods
 * 
 * @return array New array of shipping methods
 */
function registerShippingMethods($methods)
{
    include_once PRINT_AND_SHIP_CLASSES . 'class-shipping-auto.php';
    include_once PRINT_AND_SHIP_CLASSES . 'class-shipping-normal.php';
    include_once PRINT_AND_SHIP_CLASSES . 'class-shipping-tracked.php';
    include_once PRINT_AND_SHIP_CLASSES . 'class-shipping-premium.php';

    $methods['print_and_ship_auto'] = 'PrintAndShip\WC_Shipping_PrintAndShip_Auto';
    $methods['print_and_ship_normal'] = 'PrintAndShip\WC_Shipping_PrintAndShip_Normal';
    $methods['print_and_ship_tracked'] = 'PrintAndShip\WC_Shipping_PrintAndShip_Tracked';
    $methods['print_and_ship_premium'] = 'PrintAndShip\WC_Shipping_PrintAndShip_Premium';
    
    return $methods;
}

/**
 * Add settings link to plugin overview
 * 
 * @param array $links The links that already exist
 *
 * @return void
 */
function addSettingsLink($links)
{
    $settings_link = '<a href="admin.php?page=print-and-ship-config">Settings</a>';
    array_push($links, $settings_link);

    return $links;
}

/**
 * Add email class to WooCommerce
 *
 * @param array $email_classes Email classes
 * 
 * @return array
 */
function addEmail($email_classes)
{
    include_once PRINT_AND_SHIP_CLASSES . 'class-email-tracktrace.php';

    $email_classes['WC_Email_TrackTrace'] = new \PrintAndShip\EmailTrackTrace();
    return $email_classes;
}

/**
 * Add order status to WooCommerce
 *
 * @param array $statuses Order statuses
 * 
 * @return array
 */
function addOrderStatusToList($statuses)
{
    $new_order_statuses = array();

    foreach ($statuses as $key => $status) {
        $new_order_statuses[ $key ] = $status;

        if ($key == 'wc-on-hold') {
            $new_order_statuses['wc-partially-shipped'] = 'Partially shipped';
            $new_order_statuses['wc-ipp-ready'] = 'Ready for Production';
        }
    }

    return $new_order_statuses;
}

/**
 * AJAX Admin save settings
 *
 * @return void
 */
function adminAjaxSaveSettings()
{
    $options = array();
    $wanted = array(
        'print_and_ship_api_user',
        'print_and_ship_api_key',
        'print_and_ship_test_mode',
        'print_and_ship_debug_mode',
        'print_and_ship_order_status',
        'print_and_ship_auto_add_product_image',
        'print_and_ship_allow_backorders',
        'print_and_ship_auto_add_brands',
        'print_and_ship_auto_add_models',
        'print_and_ship_auto_add_case_type_colour',
        'print_and_ship_render_image_for_variable',
        'print_and_ship_render_image_for_variation',
        'print_and_ship_render_image_base_sku',
        'print_and_ship_delete_images_when_delete_variable',
        'print_and_ship_auto_update_srp',
        'print_and_ship_variable_prices_action',
        'print_and_ship_variable_prices_amount',
        'print_and_ship_variable_prices_type',
        'print_and_ship_variable_prices_base',
        'print_and_ship_variable_prices_round',
        'print_and_ship_auto_enable_variations',
        'print_and_ship_email_subject',
        'print_and_ship_email_title',
        'print_and_ship_track_trace_text',
        'print_and_ship_auto_add_ipp_sku_order',
        'print_and_ship_auto_add_ipp_sku_product',
        'print_and_ship_shipping_minimal_level',
        'print_and_ship_auto_update_srp_simple',
        'print_and_ship_simple_prices_amount',
        'print_and_ship_simple_prices_action',
        'print_and_ship_simple_prices_type',
        'print_and_ship_simple_prices_base',
        'print_and_ship_simple_prices_round',
        'print_and_ship_automatic_shipping_action',
        'print_and_ship_automatic_shipping_amount',
        'print_and_ship_automatic_shipping_type',
        'print_and_ship_automatic_shipping_multiplier',
        'print_and_ship_automatic_shipping_round',
    );
        
    // Check the WP nonce for security. Is the user allowed to post?
    if (!wp_verify_nonce($_POST['_nonce'], 'print_and_ship_admin_save_config')) {
        handleAjaxResponse(array('result' => 'ERROR', 'message' => 'There has been a security issue'));
        exit;
    }
        
    // Clean input
    foreach ($wanted as $field) {
        if (isset($_POST[$field])) {
            $options[$field] = sanitize_text_field($_POST[$field]);

            if ($field == 'print_and_ship_variable_prices_amount') {
                $options[$field] = str_replace(',', '.', $options[$field]);
            }
        }
    }
        
    // Update all settings
    foreach ($options as $opt_key => $opt_value) {
        update_option($opt_key, $opt_value);
    }
        
    // Check if the API details are correct
    $ipp = new \PrintAndShip\IPP();
    $result = $ipp->getStockList();
    if ($result) {
        $auth = true;
    } else {
        $auth = false;
    }
        
    handleAjaxResponse(array('result' => 'OK', 'auth' => $auth, 'message' => 'The settings are saved succesfully'));
}

/**
 * Rest API for fetching a list of actions
 *
 * @return void
 */
function restProductupdateDiscoverActions()
{

    // Make sure no PHP errors or notices are displayed (breaks the JSON output)
    error_reporting(0);
    ini_set('display_errors', false);
    ini_set('display_startup_errors', false);

    include_once PRINT_AND_SHIP_CLASSES . 'class-product-update.php';
    $pu = new \PrintAndShip\ProductUpdate();
    $response = $pu->discoverActions();

    // An error was returned instead of the actions
    if (array_key_exists('error', $response)) {
        return array($response);
    }

    return array('actions' => $response);
}

/**
 * RestAPI for executing an action
 *
 * @return void
 */
function restProductUpdateExecuteAction()
{
    // Make sure no PHP errors or notices are displayed (breaks the JSON output)
    error_reporting(0);
    ini_set('display_errors', false);
    ini_set('display_startup_errors', false);

    include_once PRINT_AND_SHIP_CLASSES . 'class-product-update.php';
    $productUpdate = new \PrintAndShip\ProductUpdate();

    $action = json_decode(file_get_contents('php://input'), true);
    if (isset($action["arguments"])) {
        $action["arguments"] = json_decode($action["arguments"], true);
    }
        
    return $productUpdate->executeAction($action);
}

/**
 * Load all JS and CSS for specific admin pages
 *
 * @param string $hook Page hook
 * 
 * @return void
 */
function adminLoadScripts($hook)
{
    // On all Print and Ship pages
    if (strpos($hook, 'print-and-ship') !== false) {
        wp_register_style('print_and_ship', plugins_url('assets/css/invition-styles.css', __FILE__), array(), '1', 'screen');
        wp_enqueue_style('print_and_ship');
    }

    // Settings page
    if (strpos($hook, 'print-and-ship-config') !== false) {
        wp_register_script('print_and_ship_admin', plugins_url('assets/js/print_and_ship_admin.js', __FILE__), [ 'jquery', 'jquery-ui-tabs'], '1.0', true);
        wp_enqueue_script('print_and_ship_admin');
        wp_register_style('jquery-invition-style', plugins_url('assets/css/jquery-ui.css', __FILE__), array(), '1', 'screen');
        wp_enqueue_style('jquery-invition-style');
    }
        
    // Edit products / posts / orders pages
    if (strpos($hook, 'post') !== false) {
        wp_enqueue_script('print_and_ship_admin_post', plugins_url('assets/js/print_and_ship_admin_post.js', __FILE__));
        wp_enqueue_script('print_and_ship_admin_order', plugins_url('assets/js/print_and_ship_admin_order.js', __FILE__));
            
        wp_enqueue_script('print_and_ship_featherlight_admin_order', plugins_url('assets/js/featherlight.min.js', __FILE__));
        wp_register_style('print_and_ship_featherlight', plugins_url('assets/css/featherlight.min.css', __FILE__), array(), '1', 'screen');
        wp_enqueue_style('print_and_ship_featherlight');
    }

    // Product updater page
    if ($hook == "printeers_page_print-and-ship-update-products") {
        $updateProducts = array(
            'jsWorker'      => plugins_url('assets/js/print_and_ship_admin_update_products_worker.js', PRINT_AND_SHIP_BASEDIR . '/invition-print-and-ship.php'),
            'discoverURL'   => get_rest_url(null, 'invition-print-and-ship/v1/discover-actions'),
            'executeURL'    => get_rest_url(null, 'invition-print-and-ship/v1/execute-action'),
            'imgURL'        => plugins_url('assets/images/', PRINT_AND_SHIP_BASEDIR . '/invition-print-and-ship.php'),
        );
        wp_register_script('print_and_ship_admin_update_products', plugins_url('assets/js/print_and_ship_admin_update_products.js', __FILE__), [ 'jquery', 'wp-api', 'jquery-ui-accordion', 'jquery-ui-progressbar'], '1.0', true);
        wp_localize_script('print_and_ship_admin_update_products', 'print_and_ship', $updateProducts);
        wp_enqueue_script('print_and_ship_admin_update_products');

        wp_register_style('jquery-invition-style', plugins_url('assets/css/jquery-ui.css', __FILE__), array(), '1', 'screen');
        wp_enqueue_style('jquery-invition-style');
    }

    // Create a product wizard
    if (strpos($hook, 'print-and-ship-create-a-product') !== false) {
        wp_register_style('print_and_ship_admin_create_a_product', plugins_url('assets/css/multi-select.css', __FILE__), array(), '1', 'screen');
        wp_enqueue_style('print_and_ship_admin_create_a_product');
        wp_enqueue_script('print_and_ship_admin_create_a_product_multi_select', plugins_url('assets/js/jquery.multi-select.js', __FILE__));
        wp_enqueue_script('print_and_ship_admin_create_a_product_validation', plugins_url('assets/js/print_and_ship_create_a_product_validation.js', __FILE__));
    }
}

/**
 * Add WP Admin menu
 *
 * @return void
 */
function adminMenu()
{
    add_menu_page('Printeers Print & Ship '.'Settings', 'Printeers', 'edit_posts', 'print-and-ship-config', 'PrintAndShip\settingsPage', 'data:image/svg+xml;base64,PHN2ZyB2ZXJzaW9uPSIxLjEiIGlkPSJMYXllcl8xIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB4PSIwcHgiIHk9IjBweCIKCSB2aWV3Qm94PSIwIDAgNTAwIDU4OC45NSIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAwIDAgNTAwIDU4OC45NTsiIHhtbDpzcGFjZT0icHJlc2VydmUiPgo8Zz4KCTxwYXRoIGQ9Ik01MDAsMjQ1LjI1bC02MC4zMS0zNC44MmwtMTUyLjItODcuODd2MGwtOTIuODEtNTMuNThsMCwwbC0wLjAyLTAuMDFMNzUuMjIsMGwwLDAuMTVMNzQuOTcsMGwwLjIsMzUuNGwtMC4xLDY3LjQ1CgkJbC0wLjEtMC4wNkwwLDU5LjY2djBsMCwwdjM1Mi45OGwwLDBsMCwwLjAzdjEzNy40OGwwLjI1LTAuMTVsMCwwLjE1bDc0LjcyLTQzbDAuMTItMC4wN2wwLjEyLDgxLjg2TDUwMCwzNDMuN2wtODQuOTctNDkuMjMKCQlsNDcuNTYtMjcuNTVMNTAwLDI0NS4yNXogTTE0OS45MywyMTYuMjZsNDUuOTgsMjYuNTVsMCwwbDAsMGw4OS40OCw1MS42NmwtMTM1LjQ3LDc4LjIxdi00Ni42MWgwVjIxNi4yNnoiLz4KPC9nPgo8L3N2Zz4=', '56');
    add_submenu_page('print-and-ship-config', 'Printeers Print & Ship '.'Settings', 'Settings', 'edit_posts', 'print-and-ship-config', 'PrintAndShip\settingsPage');
    add_submenu_page('print-and-ship-config', 'Create a product', 'Create a product', 'edit_posts', 'print-and-ship-create-a-product', 'PrintAndShip\adminCreateProduct');
    add_submenu_page(null, 'Add Simple products', 'Add Simple products', 'edit_posts', 'print-and-ship-create-a-simple-product', 'PrintAndShip\adminCreateSimpleProduct');
    add_submenu_page('print-and-ship-config', 'Product updates', 'Product updates', 'edit_posts', 'print-and-ship-update-products', 'PrintAndShip\adminUpdateProductsPage');
    add_submenu_page(null, 'Product updates', 'Product updates', 'edit_posts', 'print-and-ship-update-json', 'PrintAndShip\adminUpdateProductsPage');
    add_submenu_page('print-and-ship-config', 'Printeers Print & Ship '.'Support', 'Support', 'edit_posts', 'print-and-ship-support', 'PrintAndShip\supportPage');
    add_submenu_page('print-and-ship-config', 'Printeers Print & Ship '.'Tools', 'Tools', 'edit_posts', 'print-and-ship-tools', 'PrintAndShip\toolsPage');
}

/**
 * Load the Create a Simple product page
 *
 * @return void
 */
function adminCreateSimpleProduct()
{
    include_once PRINT_AND_SHIP_BASEDIR . '/assets/php/new-product-page.php';
}

/**
 * Load the Create a product page
 *
 * @return void
 */
function adminCreateProduct()
{
    // TODO: Make this an Ajax process. Quite old fashioned, but works for now

    $step = "";
        
    if (isset($_GET['step'])) {
        $step = $_GET['step'];
    }

    switch ($step) {
    case '2':
        include_once PRINT_AND_SHIP_BASEDIR . '/assets/php/create-a-product/variable-step2.php';
        break;
        
    case '3':
        include_once PRINT_AND_SHIP_BASEDIR . '/assets/php/create-a-product/variable-step3.php';
        break;
        
    case 'finish':
        include_once PRINT_AND_SHIP_BASEDIR . '/assets/php/create-a-product/variable-finish.php';
        break;
        
    default:
        include_once PRINT_AND_SHIP_BASEDIR . '/assets/php/create-a-product/product-step1.php';
        break;
    }
}

/**
 * New product page
 *
 * @return void
 */
function adminUpdateProductsPage()
{
    include_once PRINT_AND_SHIP_BASEDIR . '/assets/php/update-products-page.php';
}

/**
 * Admin page for PRINT_AND_SHIP
 *
 * @return void
 */
function settingsPage()
{
    include_once PRINT_AND_SHIP_BASEDIR . '/assets/php/settings-page.php';
}

/**
 * Support page for PRINT_AND_SHIP
 *
 * @return void
 */
function supportPage()
{
    include_once PRINT_AND_SHIP_BASEDIR . '/assets/php/support-page.php';
}

/**
 * Support page for PRINT_AND_SHIP
 *
 * @return void
 */
function toolsPage()
{
    include_once PRINT_AND_SHIP_BASEDIR . '/assets/php/tools-page.php';
}

/**
 * Show admin notices
 *
 * @return void
 */
function adminNotices()
{
    // WooCommerce not installed
    if (! class_exists('WooCommerce')) {
        include_once PRINT_AND_SHIP_BASEDIR . '/assets/php/admin-error-no-woocommerce.php';
    }
        
    // API details incorrect
    if (isset($_GET['page']) && $_GET['page'] == 'print-and-ship-new-products') {
        include_once PRINT_AND_SHIP_BASEDIR . '/assets/php/admin-error-no-api-key.php';
    }
}


/**
 * Displays tools to manage the print image for a WC Order Item
 *
 * @param int           $item_id  ID of the order item
 * @param WC_Order_Item $item     WooCommerce order item object
 * @param WC_Product    $_product WooCommerce Product object
 * 
 * @return void
 */
function orderPrintImageManager($item_id, $item, $_product)
{
    include PRINT_AND_SHIP_BASEDIR . '/assets/php/admin-order-print-image.php';
}

/**
 * Uploads new print image to order item
 * 
 * @return void
 */
function adminUpdateOrderPrintImage()
{

    $woo = new \PrintAndShip\Woo();
    $result = $woo->updateOrderPrintImage($_POST['order_id'], $_POST['item_id'], $_POST['image']);
        
    handleAjaxResponse(array('result' => $result['success'], 'message' => $result['message']));
}

/**
 * Checks if a product is a Printeers Print item
 *
 * @param int $product_id The ID of the product
 * 
 * @return bool
 */
function isPrintItem($product_id)
{
    $printSize = get_post_meta($product_id, 'print_and_ship_print_size', true);

    // get_post_meta returns false on invalid product_id
    if (!$printSize) {
        return false;
    }

    // No print size set or empty value found
    if ($printSize == "") {
        return false;
    }

    // Is it properly formed? 69x164
    if (!preg_match("/[0-9]{1,3}x[0-9]{1,3}/", $printSize)) {
        return false;
    }
    
    return true;
}

/**
 * Get the URL of a print image
 *
 * @param WC_Order_Item $item item object containing the WooCommerce order item
 * 
 * @return mixed bool | string returns URL when successful or returns false on error
 */
function adminGetPrintImageURL($item)
{
        
    // Missing or invalid item object
    if (!is_object($item) || !method_exists($item, "get_meta")) {
        return false;
    }
        
    // Its not an existing product
    if (!method_exists($item, "get_product_id")) {
        return false;
    }

    $product_id = $item->get_product_id();
        
    // Get the print image
    if ($item->get_meta('print_and_ship_print_image')) {
        // The order item has its own print image
        $printImage = $item->get_meta('print_and_ship_print_image');
    
    } else {
        // Get the print image of the product itself
        $meta = get_post_meta($product_id, 'print_and_ship_print_image');
        
        // The product does not have a print image
        if (!array_key_exists(0, $meta)) {
            return false;
        }

        $printImage = $meta[0];
    }

    // No print image found
    if ($printImage == 0) {
        return false;
    }

    // The print image is stored in the print and ship upload URL
    if (!is_numeric($printImage)) {
        return PRINT_AND_SHIP_UPLOADURL . '/' . $printImage;   
    }

    // The print image is stored in the media library
    if (!$printImageURL = wp_get_attachment_url($printImage)) {
        return false;
    }

    return $printImageURL;
}

/**
 * Run the cronjob
 * 
 * @param bool $force Force overwrite all stocks?
 *
 * @return void
 */
function runImportCron($force = false)
{
    $woo = new \PrintAndShip\Woo();
    $woo->importProductData($force);
    $woo->importAttributesData();
}

/**
 * Check for orders to submit to Printeers
 *
 * @return void
 */
function runOrdersCron()
{
    $orders = wc_get_orders(
        array(
            'limit'        => -1,
            'status'       => preg_replace('/^wc\-/', '', get_option('print_and_ship_order_status')),
            'meta_key'     => 'print_and_ship_order_reference',
            'meta_compare' => 'NOT EXISTS'
        )
    );
    
    foreach ($orders as $order) {
        sendOrder($order->get_id(), $order);
    }
}

/**
 * Check for completed orders on Printeers side
 * This is necessary when the callback does not function properly, to make sure orders are finished
 *
 * @return void
 */
function runCompletedOrdersCron()
{
    $orders = wc_get_orders(array('status' => preg_replace('/^wc\-/', '', get_option('print_and_ship_order_status'))));
    $ipp = new \PrintAndShip\IPP();
    foreach ($orders as $order) {
        debuglog("Checking for updates for order " . $order->get_id());
        $ipp->updateStatus($order->get_id());
    }
}

/**
 * Check print value
 *
 * @param string $value   Value
 * @param int    $post_id Post ID
 * @param array  $field   Field
 * 
 * @return string
 */
function filterAcfPrintValue($value, $post_id, $field)
{
    $ipp = new \PrintAndShip\IPP();

    // Check the print value
    $print_and_ship_sku = ( isset($_POST['print_and_ship_sku']) ) ? sanitize_text_field($_POST['print_and_ship_sku']) : '';
    if (! empty($print_and_ship_sku)) {
        $product = $ipp->getStockItem($print_and_ship_sku);

        if (isset($product->dimension_height_mm) && isset($product->dimension_width_mm)) {
            $value = '1';
        } else {
            $value = '0';
        }
    }

    return $value;
}

/**
 * ACF Filter settings dir
 *
 * @return string
 */
function filterAcfSettingsDir()
{
    $dir = plugins_url('includes/acf/', __FILE__);
    return $dir;
}

/**
 * ACF Filter for settings path
 *
 * @return string
 */
function filterAcfSettingsPath()
{
    $path = PRINT_AND_SHIP_BASEDIR . '/includes/acf/';
    return $path;
}

/**
 * Add action to dropdown box
 *
 * @param array $actions Actions
 * 
 * @return array
 */
function filterAddOrderAction($actions)
{
    /* @var WC_Order $theorder */
    global $theorder;

    $order_id = $theorder->get_id();
    $failures = get_post_meta($order_id, 'print_and_ship_failures', true);

    if (! empty($failures)) {
        $actions['send_order_to_invition'] = 'Resend order to Printeers';
    }

    return $actions;
}


/**
 * Add an Order sent to Printeers column to product list
 *
 * @return $columns
 */
function addOrdersColumnHeader($columns)
{
    $columns["sent_to_invition"] = "Sent to Printeers";

    return $columns;
}

/**
 * Populate the Order sent to Printeers column
 *
 * @return $output
 */
function populateOrdersColumn($column, $id)
{
    if ($column == 'sent_to_invition') {
        // Does it have an order reference? 
        $orderReference = get_post_meta($id, 'print_and_ship_order_reference', true);
        if ($orderReference != "") {
            echo '<span style="color: green; font-size: 20px;">&check;</span> ' . $orderReference;
            return;
        }
    }
}

/**
 * Filter the image upload validator
 *
 * @param bool   $valid   Image is valid
 * @param int    $value   Attachment ID
 * @param string $field   Field
 * @param string $input   Input
 * @param int    $post_id Post ID override
 * 
 * @return bool|string
 */
function filterValidatePrintImage($valid, $value, $field, $input, $post_id = 0)
{
    if (! $valid) {
        return $valid;
    }

    if (empty($post_id)) {
        $post_id = ( isset($_POST['post_ID']) && is_numeric($_POST['post_ID']) ) ? intval($_POST['post_ID']) : 0;
    }

    $image_check = get_post_meta($post_id, '_image_check', true);

    if ($value == $image_check) {
        return true;
    }

    if (! empty($post_id)) {
        $data = wp_get_attachment_image_src($value, 'full');
        $pixels_width = $data[1];
        $pixels_height = $data[2];

        $valid = checkImageDimensions($post_id, $pixels_width, $pixels_height);

        if ($valid !== true) {
            update_post_meta($post_id, '_image_check', $value);
        }
    }

    return $valid;
}


/**
 * Fix for issue where too many variations causes the front end to not pre-load all variations
 *
 * @param int    $qty     Quantity
 * @param object $product Product
 * 
 * @return int
 */
function wcCustomAjaxThreshold($qty, $product)
{
    return 1000;
}

/**
 * Add Printeers order reference to WooCommerce order search box
 * 
 * @param array $search_fields The already set fields
 * 
 * @return array $search_fields The updated fields
 */
function updateOrderSearchFields($search_fields)
{
    $search_fields[] = 'print_and_ship_order_reference';
  
    return $search_fields;
}  

/**
 * Action hook: init
 *
 * @return void
 */
function initializePlugin()
{
    // Register all rest API endpoints
    add_action(
        'rest_api_init', 
        function () {

            // Request a JSON with all possible actions
            register_rest_route(
                'invition-print-and-ship/v1', 
                '/discover-actions', 
                array(
                    'methods' => 'GET',
                    'callback' => 'PrintAndShip\restProductupdateDiscoverActions',
                    'permission_callback' => function () {
                        return current_user_can('edit_pages');
                    },
                )
            );

            // Post a JSON with all actions to be executed
            register_rest_route(
                'invition-print-and-ship/v1', 
                '/execute-action', 
                array(
                    'methods' => 'POST',
                    'callback' => 'PrintAndShip\restProductUpdateExecuteAction',
                    'permission_callback' => function () {
                        return current_user_can('edit_pages');
                    },
                )
            );
            
            // Process the callback for a certain order
            register_rest_route(
                'invition-print-and-ship/v1', 
                '/callback', 
                array(
                    'methods' => 'GET',
                    'callback' => 'PrintAndShip\processIPPCallback',
                    'permission_callback' => '__return_true',
                )
            );
        }
    );

    if (is_admin()) {
        // AJAX
        add_action('wp_ajax_print_and_ship_admin_save_settings', 'PrintAndShip\adminAjaxSaveSettings');
        add_action('wp_ajax_print_and_ship_admin_order_print_image', 'PrintAndShip\adminUpdateOrderPrintImage');

        // Actions
        add_action('admin_menu', 'PrintAndShip\adminMenu', 200);
        add_action('admin_enqueue_scripts', 'PrintAndShip\adminLoadScripts');
        add_action('admin_notices', 'PrintAndShip\adminNotices');
            
        // WooCommerce
        add_filter('woocommerce_order_actions', 'PrintAndShip\filterAddOrderAction');
        add_filter('manage_edit-shop_order_columns', 'PrintAndShip\addOrdersColumnHeader', 10, 1);
        add_filter('manage_shop_order_posts_custom_column', 'PrintAndShip\populateOrdersColumn', 10, 3);
        add_filter('woocommerce_shop_order_search_fields', 'PrintAndShip\updateOrderSearchFields', 10, 3);
        add_action('woocommerce_process_product_meta', 'PrintAndShip\saveProduct', 99);
        add_action('woocommerce_product_after_variable_attributes', 'PrintAndShip\addPrintAndShipSkuToVariations', 10, 3);
        add_action('woocommerce_save_product_variation', 'PrintAndShip\savePrintAndShipSkuVariations', 10, 2);
        add_action('woocommerce_after_order_itemmeta', 'PrintAndShip\orderPrintImageManager', 10, 3);
        add_action('before_delete_post', 'PrintAndShip\deleteMediaFromVariations');
    }
    
    // ACF Fields
    if (function_exists('register_field_group')) {
        include_once PRINT_AND_SHIP_BASEDIR . '/acf_fields.php';
    }
    
    if (defined('PRINT_AND_SHIP_ACF_LOADED') && PRINT_AND_SHIP_ACF_LOADED && (strpos($_SERVER['REQUEST_URI'], 'post') !== false)) {
        add_filter('acf/settings/path', 'PrintAndShip\filterAcfSettingsPath');
        add_filter('acf/settings/dir', 'PrintAndShip\filterAcfSettingsDir');
        add_filter('acf/settings/show_admin', '__return_false');
        add_filter('acf/update_value/name=print', 'PrintAndShip\filterAcfPrintValue', 10, 3);
        
        if (is_admin() && $_SERVER['REQUEST_METHOD'] == 'POST') {
            acf_form_head();
        }
    }
    
    // Actions
    add_action('wp_loaded', 'PrintAndShip\scheduleCron');
    add_action('wp_loaded', 'PrintAndShip\registerCustomOrderStatuses');
    add_action('print_and_ship_import_cron', 'PrintAndShip\runImportCron');
    add_action('print_and_ship_orders_cron', 'PrintAndShip\runOrdersCron');
    add_action('print_and_ship_completed_orders_cron', 'PrintAndShip\runCompletedOrdersCron');

    // WooCommerce
    add_action('woocommerce_view_order', 'PrintAndShip\displayShipmentsOnOrderPage', 20);
    
    // Filters
    add_filter('cron_schedules', 'PrintAndShip\cronCustomInterval');
    add_filter("plugin_action_links_".plugin_basename(__FILE__), 'PrintAndShip\addSettingsLink');

    // Shortcodes
    add_shortcode('expected_available_date', 'PrintAndShip\displayExpectedAvailableDate');
    
    // WooCommerce
    add_filter('wc_order_statuses', 'PrintAndShip\addOrderStatusToList');
    add_filter('woocommerce_ajax_variation_threshold', 'PrintAndShip\wcCustomAjaxThreshold', 10, 2);
    add_filter('woocommerce_email_classes', 'PrintAndShip\addEmail');
    add_filter('woocommerce_shipping_methods', 'PrintAndShip\registerShippingMethods');
}

/**
 * Register post status for WooCommerce
 *
 * @return void
 */
function registerCustomOrderStatuses()
{
    register_post_status(
        'wc-partially-shipped', 
        array(
            'label'                     => 'Partially shipped',
            'public'                    => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list'    => true,
            'exclude_from_search'       => false,
            'label_count'               => _n_noop('Partially shipped <span class="count">(%s)</span>', 'Partially shipped <span class="count">(%s)</span>')
        )
    );
    register_post_status(
        'wc-ipp-ready', 
        array(
            'label'                     => 'Ready for Production',
            'public'                    => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list'    => true,
            'exclude_from_search'       => false,
            'label_count'               => _n_noop('Ready for Production <span class="count">(%s)</span>', 'Ready for Production <span class="count">(%s)</span>')
        )
    );
}

/**
 * Register all attributes (taxonomies) needed to create variations
 *
 * @return void
 */
function registerWcAttributes()
{
        
    // We need WooCommerce for this function to work
    if (! class_exists('WooCommerce')) {
        return false;
    }

    // Create an array of the attributes we want to add
    $attributes = array(
        "device_model" => array(
            "slug"  => "device_model",
            "label" => "Model",
        ),
        "device_brand" => array(
            "slug"  => "device_brand",
            "label" => "Brand",
        ),
        "case_type_colour" => array(
            "slug"  => "case_type_colour",
            "label" => "Case type & colour",
        ),
    );
        
    // Add the taxonomies to WooCommerce
    foreach ($attributes as $attribute) {
        if (!get_taxonomy("pa_" . $attribute["slug"])) {
            $result = wc_create_attribute(
                array(
                    "name" => $attribute["label"],
                    "slug" => $attribute["slug"],
                    "type" => "select",
                )
            );
        }
    }
}

/**
 * Process received IPP callback
 *
 * @return void
 */
function processIPPCallback()
{
    $ipp = new \PrintAndShip\IPP();
    $ipp->processCallback();

    return array("Callback URL" => "Success");
}

/**
 * WooCommerce product save
 *
 * @param int $product_id Product ID
 * 
 * @return void
 */
function saveProduct($product_id)
{
    // Are Printeers services enabled?
    if (!isset($_POST['acf']['field_73v7h2bgew8'])) {
        return;
    }
    
    $print_and_ship_sku = (isset($_POST['acf']['field_ssy453bh'])) ? sanitize_text_field($_POST['acf']['field_ssy453bh']) : '';
    
    // Was an Printeers SKU entered?
    if ($print_and_ship_sku == '') {
        return;
    }

    $ipp = new \PrintAndShip\IPP();
    $woo = new \PrintAndShip\Woo();
    
    // Get the item from the stock list
    $item = $ipp->getStockItem($print_and_ship_sku);
    
    // Did we find an item?
    if (is_null($item)) {
        // TODO: Display error when the product isnt found
        return;
    }

    $woo->importStock($product_id, $item);
    $woo->importAttributes($product_id, $item);

    // Is it a print item?
    if ($item->kind == "print") {
        $woo->importPrintSize($product_id, $item);
    }

}

/**
 * Add the Printeers SKU field to variations
 *
 * @param int    $loop           Loop
 * @param array  $variation_data Variation data
 * @param object $variation      Variation to connect to
 * 
 * @return void
 */
function addPrintAndShipSkuToVariations($loop, $variation_data, $variation)
{
    woocommerce_wp_text_input(
        array(
            'id' => 'print_and_ship_sku[' . $loop . ']',
            'class' => 'short',
            'label' => 'Printeers SKU',
            'value' => get_post_meta($variation->ID, 'print_and_ship_sku', true)
        )
    );
}

/**
 * Save the entered variation data
 *
 * @param int $variation_id The variation ID
 * @param int $i            Not sure what this does
 * 
 * @return void
 */
function savePrintAndShipSkuVariations($variation_id, $i)
{
    $print_and_ship_sku = $_POST['print_and_ship_sku'][$i];
    if (! empty($print_and_ship_sku)) {
        update_post_meta($variation_id, 'print_and_ship_sku', sanitize_text_field($print_and_ship_sku));
    } else {
        delete_post_meta($variation_id, 'print_and_ship_sku');
    }
}

/**
 * Send order to Printeers
 *
 * @param int      $id    Order ID
 * @param WC_Order $order Order
 * 
 * @return void
 */
function sendOrder($id, $order)
{
    $ipp_reference = get_post_meta($id, 'print_and_ship_order_reference', true);

    // Only when the order has not been sent before
    if (empty($ipp_reference)) {
        // Prepare the order
        $data = \PrintAndShip\Woo::prepareOrder($order);
        if ($data === null) {  
            // If the return is null, the order contains no Printeers products
            return;
        }

        if ($data === false) {
            // If the return is false, the print image is not uploaded for all products
            $order->set_status('on-hold');
            $order->save();     
            return;   
        }
        
        // Send the order
        $ipp = new \PrintAndShip\IPP();
        if (!$ipp->sendOrder($data)) {
            $order->set_status('on-hold');
            $order->save();     
            return;   
        }
    }
}

/**
 * Delete all media attached to a variable product
 * 
 * @param int $post_id The ID of the product
 * 
 * @return void
 */
function deleteMediaFromVariations($post_id) {
    debuglog("Starting delete media from variations belonging to post ID " . $post_id);

    // Check if the delete option is enabled
    $deleteEnabled = get_option('print_and_ship_delete_images_when_delete_variable');
    if ($deleteEnabled != 'yes') {
        debuglog("Delete not enabled");
        return;
    }

    // Check if the product exists and is a variable product
    $product = wc_get_product($post_id);
    if (!$product || $product->get_type() !== 'variable') {
        debuglog("Not a variable product or not a product at all " . $post_id);
        return;
    }

    // Check if the product has Printeers enabled
    $printeersEnabled = get_post_meta($post_id, 'print_and_ship_enable', true);
    if ($printeersEnabled != 'yes' && $printeersEnabled != '1') {
        debuglog("Printeers not enabled for" . $post_id);
        return;
    }

    // Get all variations
    $args = array(
        'post_parent' => $post_id,
        'post_type'   => 'product_variation',
        'numberposts' => -1,
        'post_status' => 'trash', // Specifically get trashed items
    );
    $variations = get_posts($args);
    
    foreach ($variations as $variation) {
        debuglog("Deleting media from variation " . $variation->ID);
        $variation_media = get_attached_media('', $variation->ID);

        foreach ($variation_media as $file) {
            wp_delete_attachment($file->ID, true);
        }
    }
}

// ******** WOO FRONTEND ******** //

/**
 * Display the shipping information on the frontend
 * 
 * @param int $orderID The ID of the currently viewed order
 * 
 * @return void
 */
function displayShipmentsOnOrderPage($orderID)
{
    $wooShipments = get_post_meta($orderID, 'print_and_ship_shipments', true);

    // Are there shipments to display?
    if (!\is_array($wooShipments) || count($wooShipments) == 0) {
        return;
    }

    echo "<h2>" . __('Shipments') . "</h2>";
    echo "<table>";
    
    foreach ($wooShipments as $shipment) {
        echo "<tr>";

        // Display the shipment date
        echo "<td>";
        echo date('d-m-Y', strtotime($shipment->created));
        echo "</td>";

        // Display the method and, if available, the tracking URL
        echo "<td>";
        echo $shipment->shipping_method;
        if (property_exists($shipment, 'track_and_trace_url')) {
            echo '<br /><a href="' . $shipment->track_and_trace_url . '">Track & Trace</a>';
        }
        echo "</td>";

        echo "</tr>";
    }

    echo "</table>";
}

/**
 * Display the expected available date through Shortcode
 * 
 * @return void
 */
function displayExpectedAvailableDate() 
{
    $expectedAvailableDate = get_post_meta(get_the_id(), 'expected_available_date', true);

    if ($expectedAvailableDate != null && $expectedAvailableDate != "") {
        echo "<span class=\"expectedAvailableText\">" . __('This product is expected back in stock on') . "</span>&nbsp;";
        echo "<span class=\"expectedAvailableDate\">" . $expectedAvailableDate . "</span>";
    }
}

// ******** OTHER FUNCTIONS ******** //

/**
 * Apply template
 *
 * @param string $template Template content
 * @param array  $fields   Replace template codes
 * 
 * @return string
 */
function applyTemplate($template, $fields)
{
    foreach ($fields as $key => $value) {
        $template = str_replace('%%' . $key . '%%', $value, $template);
    }

    return $template;
}

/**
 * Checks if a Print and Ship addon exists. Currently only accepts Zakeke
 *
 * @param string $addon The addon to check on
 * 
 * @return bool Is the addon active?
 */
function isAddonActive($addon)
{
    switch ($addon) {
    case 'zakeke':
        if (is_plugin_active("invition-print-ship-" . $addon . "/invition-print-and-ship-" . $addon . ".php")) {
            return true;
        } else {
            return false;
        }
        break;
    
    default:
        debuglog("Unknown addon requested " . $addon);
        break;
    }
}

/**
 * Print & Ship Debug logger
 *
 * @param mixed $message Message to be logged
 * 
 * @return void
 */
function debuglog($message)
{
    if (get_option('print_and_ship_debug_mode') == 'yes') {
        $line  = date('Y-m-d H:i:s') . ' - ' . $_SERVER['REMOTE_ADDR'] . ' - ';
        $line .= ( is_string($message) ) ? $message : print_r($message, true);
        $line .= "\n";

        $dir = wp_upload_dir();
        $file = $dir['basedir'] . '/print-and-ship.log';
        file_put_contents($file, $line, FILE_APPEND);
    }
}

/**
 * Handle AJAX response
 *
 * @param array $response Response
 * 
 * @return void
 */
function handleAjaxResponse($response)
{
    header('Content-Type: application/json');
    echo json_encode($response);

    wp_die();
}

// ******** HOOKS ******** //

add_action('init', 'PrintAndShip\initializePlugin');
register_activation_hook(__FILE__, 'PrintAndShip\activatePlugin');
register_deactivation_hook(__FILE__, 'PrintAndShip\unscheduleCron');


// ******** CLASSES ******** //

require_once PRINT_AND_SHIP_CLASSES . 'class-ipp.php';
require_once PRINT_AND_SHIP_CLASSES . 'class-woo.php';

// ******** ACF ******** //

if (! function_exists('acf_add_local_field_group')) {
    define('PRINT_AND_SHIP_ACF_LOADED', true);
    include_once PRINT_AND_SHIP_BASEDIR . '/includes/acf/acf.php';
}
