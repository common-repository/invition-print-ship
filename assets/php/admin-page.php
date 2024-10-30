<?php
/**
 * @author Jacco Drabbe <jacco@qurl.nl>
 * @copyright 2017 Invition
 * @version $Id$
 **/

namespace PrintAndShip;

defined('ABSPATH') or die('No script kiddies please!');

/**
 * Create order status selection
 *
 * @return string
 */
function createStatusUnits()
{
    $value = get_option('print_and_ship_order_status');
    $out = '';
    $prepend_status = array('' => 'Please select');

    if (function_exists("wc_get_order_statuses")) {
        $statuses = array_merge($prepend_status, wc_get_order_statuses());
        foreach ($statuses as $status_key => $status_value) {
            $out .= '<option value="' . $status_key . '"';
            $out .= ( $status_key == $value ) ? ' selected="selected"' : '';
            $out .= '>' . $status_value . '</option>';
        }
    }

    return $out;
}

/**
 * Create dropdown options for the 'Allow Backorders' setting
 *
 * @return string
 */
function createBackordersOptions()
{
    $dropdown = "";
        
    // Get saved option
    $selected = get_option("print_and_ship_allow_backorders");

    // If no setting was found, set default
    if (empty($selected)) {
        $selected = "notify";
    }

    $options = array(
        "notify" => "Allow, but notify customer",
        "yes" => "Allow",
        "no" => "Do not allow",
    );

    foreach ($options as $option => $explain) {
        if ($option == $selected) {
            $dropdown .= '<option value="' . $option . '" selected>' . $explain . '</option>';
        } else {
            $dropdown .= '<option value="' . $option . '">' . $explain . '</option>';
        }
    }
        
    return $dropdown;
}

/**
 * Create dropdown options for the 'Price action' setting
 *
 * @param string $productType Type of the product the options are generated for
 * @return string
 */
function createPricesActionOptions($productType)
{
    $dropdown = "";

    switch ($productType) {
        case 'simple':
            $selected = get_option("print_and_ship_simple_prices_action");
            break;
        
        case 'variable':
            $selected = get_option("print_and_ship_variable_prices_action");
            break;
        
        default:
            return $dropdown;
            break;
    }

    // If no setting was found, set default
    if (empty($selected)) {
        $selected = "";
    }

    $options = array(
        "" => "",
        "add" => "Add",
        "subtract" => "Subtract",
    );

    foreach ($options as $option => $explain) {
        if ($option == $selected) {
            $dropdown .= '<option value="' . $option . '" selected>' . $explain . '</option>';
        } else {
            $dropdown .= '<option value="' . $option . '">' . $explain . '</option>';
        }
    }
        
    return $dropdown;
}

/**
 * Create dropdown options for the 'Price type' setting
 *
 * @param string $productType Type of the product the options are generated for
 * @return string
 */
function createPricesTypeOptions($productType)
{
    $dropdown = "";

    switch ($productType) {
        case 'simple':
            $selected = get_option("print_and_ship_simple_prices_type");
            break;
        
        case 'variable':
            $selected = get_option("print_and_ship_variable_prices_type");
            break;
        
        default:
            return $dropdown;
            break;
    }
        
    $currency = get_option("woocommerce_currency");
    if ($currency == "") {
        $currency = "EUR";
    }

    // If no setting was found, set default
    if (empty($selected)) {
        $selected = "";
    }

    $options = array(
        "" => "",
        "currency" => $currency,
        "percent" => "Percent",
    );

    foreach ($options as $option => $explain) {
        if ($option == $selected) {
            $dropdown .= '<option value="' . $option . '" selected>' . $explain . '</option>';
        } else {
            $dropdown .= '<option value="' . $option . '">' . $explain . '</option>';
        }
    }
        
    return $dropdown;
}

/**
 * Create dropdown options for the 'Round prices' setting
 *
 * @param string $productType Type of the product the options are generated for
 * @return string
 */
function roundPricesOptions($productType)
{
    $dropdown = "";
        
    switch ($productType) {
        case 'simple':
            $selected = get_option("print_and_ship_simple_prices_round");
            break;
        
        case 'variable':
            $selected = get_option("print_and_ship_variable_prices_round");
            break;
        
        default:
            return $dropdown;
            break;
    }

    // If no setting was found, set default
    if (empty($selected)) {
        $selected = "dontround";
    }

    $options = array(
        "dontround" => "Don't round prices",
        "dot95" => ".95",
        "dot99" => ".99",
        "round" => ".00",
    );

    foreach ($options as $option => $explain) {
        if ($option == $selected) {
            $dropdown .= '<option value="' . $option . '" selected>' . $explain . '</option>';
        } else {
            $dropdown .= '<option value="' . $option . '">' . $explain . '</option>';
        }
    }
        
    return $dropdown;
}

/**
 * Create dropdown options for the 'Round prices' setting
 *
 * @return string
 */
function shippingLevelOptions()
{
    $dropdown = "";
        
    // Get saved option
    $selected = get_option("print_and_ship_shipping_minimal_level");

    // If no setting was found, set default
    if (empty($selected)) {
        $selected = "";
    }

    $options = array(
        "normal" => "Normal",
        "tracked" => "Tracked",
        "premium" => "Premium",
    );

    foreach ($options as $option => $explain) {
        if ($option == $selected) {
            $dropdown .= '<option value="' . $option . '" selected>' . $explain . '</option>';
        } else {
            $dropdown .= '<option value="' . $option . '">' . $explain . '</option>';
        }
    }
        
    return $dropdown;
}

/**
 * Create admin notice
 *
 * @return string
 */
function createAdminNotice()
{
    $template = file_get_contents(PRINT_AND_SHIP_TEMPLATES . 'admin-notice.html');
    $fields = array(
        'type'      => 'error',
        'message'   => 'Your API user or key is not correct',
        'style'     => 'display:none'
    );
    return applyTemplate($template, $fields);
}

$fields = array(
    'nonce'                         => wp_create_nonce('print_and_ship_admin_save_config'),

    // Settings page
    'strInvitionSettings'           => 'Invition Print & Ship settings',
    'strSettingsInfo'               => 'You can manage all settings for the Invition Print & Ship plugin below. 
                                        If you have questions regarding any of the settings, 
                                        <a href="https://help.invition.eu">please take a look here</a>',
    
    // General settings
    'strGeneralSettings'            => 'General',
    'strAPIinformation'             => 'If you don\'t have API information yet, ',
    'strCreateAccount'              => 'Create an account here',
    'strApiUser'                    => 'API user',
    'api_user'                      => get_option('print_and_ship_api_user'),
    'strApiKey'                     => 'API key',
    'api_key'                       => get_option('print_and_ship_api_key'),
    'strTestMode'                   => 'Test mode',
    'test_mode'                     => (get_option('print_and_ship_test_mode') == 'yes') ? 'checked="checked"' : '',
    'strDebugMode'                  => 'Enable debug mode',
    'strDebugModeDesc'              => 'Write debug information to wp-content/uploads/print-and-ship.log',
    'debug_mode'                    => (get_option('print_and_ship_debug_mode') == 'yes') ? 'checked="checked"' : '',
    'strOrderStatus'                => 'Send order to Invition',
    'strOrderReached'               => 'when it reaches the order status: ',
    'orderStatusSelection'          => createStatusUnits(),
    'strAllowBackorders'            => 'Accept backorders?',
    'strAllowBackordersSelection'   => createBackordersOptions(),
    'strAllowBackordersDesc'        => 'Use this setting to choose if your customer can order products which are 
                                        not in stock at Invition',
    
    // Product updates settings
    'strProductUpdates'             => 'Product updates',
    'strProductUpdatesInfo'         => 'Settings on this page will not automatically change your data! Each setting
                                        will generate an action on the Product Updates page. If you execute the
                                        action, the data will be changed. For example when you enable "Automatically
                                        update prices when Invition changes the SRP", will not change the price
                                        without your consent. When a price is changed, you will see actions appear
                                        on the Product Updates page which will change the prices for you when
                                        you activate them.',
    'strSimpleProducts'             => 'Simple products',
    'strSimpleProductsInfo'         => 'These settings only apply to Simple products.',
    'strAutoAddProductImage'        => 'Add product image when no product image is present',
    'auto_add_product_image'        => (get_option('print_and_ship_auto_add_product_image') == 'no') ? '' : 'checked="checked"',
    'strAutoAddProductImageDesc'    => 'When the product does not have an image yet, an example image will be set.',
    'strAutoUpdateSrpSimple'        => 'Update prices when Invition changes suggested retail price',
    'strAutoUpdateSrpSimpleDesc'    => 'Default: no',
    'auto_update_srp_simple'        => (get_option('print_and_ship_auto_update_srp_simple') == 'yes') ? 'checked="checked"' : '',
    'strAutomatically'              => 'Automatically',
    'actionOptionsSimple'           => createPricesActionOptions('simple'),
    'simplePricesAmount'            => get_option('print_and_ship_simple_prices_amount'),
    'typeOptionsSimple'             => createPricesTypeOptions('simple'),
    'strToAllPrices'                => 'to / from all suggested retail prices',
    'strRoundPrices'                => 'Round all prices to nearest',
    'roundOptionsSimple'            => roundPricesOptions('simple'),
    
    'strVariableProducts'           => 'Variable products',
    'strVariableProductsInfo'       => 'These settings only apply to Variable products.',
    'strAutoAddBrands'              => 'Add new brands to variable products',
    'strAutoAddBrandsDesc'          => 'Default: yes',
    'auto_add_brands'               => (get_option('print_and_ship_auto_add_brands') == 'no') ? '' : 'checked="checked"',
    'strAutoAddModels'              => 'Add new models to variable products',
    'strAutoAddModelsDesc'          => 'Default: yes',
    'auto_add_models'               => (get_option('print_and_ship_auto_add_models') == 'no') ? '' : 'checked="checked"',
    'strAutoAddCaseTypeColour'      => 'Add new case type and colour to variable products',
    'strAutoAddCaseTypeColourDesc'  => 'Default: no',
    'auto_add_case_type_colour'     => (get_option('print_and_ship_auto_add_case_type_colour') == 'yes' ) ? 'checked="checked"' : '',
    'strAutoUpdateSrp'              => 'Update prices when Invition changes suggested retail price',
    'strAutoUpdateSrpDesc'          => 'Default: yes',
    'auto_update_srp'               => (get_option('print_and_ship_auto_update_srp') == 'no') ? '' : 'checked="checked"',
    'actionOptionsVariable'         => createPricesActionOptions('variable'),
    'variablePricesAmount'          => get_option('print_and_ship_variable_prices_amount'),
    'typeOptionsVariable'           => createPricesTypeOptions('variable'),
    'roundOptionsVariable'          => roundPricesOptions('variable'),
    'strAutoEnableVariations'       => 'Enable new variations',
    'strAutoEnableVariationsDesc'   => 'Default: yes',
    'auto_enable_variations'        => (get_option('print_and_ship_auto_enable_variations') == 'no') ? '' : 'checked="checked"',
    'strSave'                       => 'Save',
    'admin_notice'                  => createAdminNotice(),

    // Email settings
    'strEmail'                      => 'E-mail',
    'strEmailShipped'               => 'Track and trace e-mail',
    'strEmailShippedDesc'           => 'You can change the contents of the track and trace e-mail here. 
                                        The same header and footer will be used as the other WooCommerce e-mails.',
    'strEmailSubject'               => 'E-mail subject',
    'strEmailSubjectDesc'           => 'Enter the subject for the track and trace e-mail (e.g. 
                                        Your order has been shipped)',
    'email_subject'                 => get_option('print_and_ship_email_subject'),
    'strEmailTitle'                 => 'E-mail title',
    'strEmailTitleDesc'             => 'Enter the title for the track and trace e-mail (e.g. 
                                        Your order has been shipped)',
    'email_title'                   => get_option('print_and_ship_email_title'),
    'strTrackTraceText'             => 'Track & Trace text',
    'strTrackTraceTextDesc'         => 'This text will be displayed below the e-mail text, when there is a track and trace link available. ',
    'track_trace_text'              => get_option('print_and_ship_track_trace_text'),

    // Gift item settings
    'strGiftItems'                  => 'Gift items',
    'strGiftItemsSettings'          => 'Gift item settings',
    'strGiftItemsSettingsDesc'      => 'Use these settings to automatically add gift items, custom packaging or 
                                        other free items. The products cannot be seen in WooCommerce admin and 
                                        your customer will not see the product, but they will be added in the 
                                        order at Invition and will be visible in the Invition Dashboard.',
    'strAutoAddProductOrder'        => 'Add this product to each order',
    'strAutoAddProductOrderDesc'    => 'Enter the Invition SKU of a product that has to be added to each order 
                                        automatically. This can be used to add custom packing items or flyers',
    'auto_add_ipp_sku_order'        => get_option('print_and_ship_auto_add_ipp_sku_order'),
    'strAutoAddProductProduct'      => 'Add this product to each ordered product',
    'strAutoAddProductProductDesc'  => 'Enter the Invition SKU of a product that has to be added to each ordered 
                                        product automatically. This can be used to add custom packing items to a 
                                        product',
    'auto_add_ipp_sku_product'      => get_option('print_and_ship_auto_add_ipp_sku_product'),

    // Shipping settings
    'strShipping'                   => 'Shipping',
    'strShippingSettings'           => 'Shipping settings',
    'minimumShippingLevelOptions'   => shippingLevelOptions(),
    'strShippingMinimalLevel'       => 'Select the minimal shipping level you wish to use',
    'strShippingMinimalLevelDesc'   => 'The setting you choose here will be the minimum shipping level 
                                        that will be used for every order. Example: If you select Tracked, an order
                                        might be upgraded to premium when the amount or quantity is too high, but
                                        it will never be shipped as normal.',
);

// Print & Ship Add-on Settings support
$addonsTabs = '';
$addonsForms = '';

if (isAddonActive("zakeke")) {
    $addonsTabs .= '<li><a href="#tabs-6">Zakeke</a></li>';
    $zakekeFields = array(
        'strZakeke'         => 'Print & Ship Zakeke Add-on',
        'strZakekeDesc'     => 'With the Zakeke add-on, Print & Ship can automatically download all print images 
                                    generated by Zakeke and required by Invition to print an order.',
        'strClientID'       => 'Your Zakeke Client ID',
        'strSecretKey'      => 'Your Zakeke Secret Key',
        'clientID'          => get_option('print_and_ship_zakeke_cliend_id'),
        'secretKey'         => get_option('print_and_ship_zakeke_secret_key'),
    );

    $zakekeTemplate = file_get_contents(PRINT_AND_SHIP_TEMPLATES . 'addons/zakeke.html');
    $addonsForms .= applyTemplate($zakekeTemplate, $zakekeFields);
}

// Add the addons templates
$fields = array_merge(
    $fields,
    array(
        'addonsTabs'     => $addonsTabs,
        'addonsForms'    => $addonsForms,
    )
);

$template = file_get_contents(PRINT_AND_SHIP_TEMPLATES . 'admin-page.html');
echo applyTemplate($template, $fields);
