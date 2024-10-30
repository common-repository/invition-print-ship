<?php
/**
 * @author    Mike Sies <mike@studiogewaagd.nl>
 * @copyright 2017 Printeers
 * @version   $Id$
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
    // Get saved option
    $selected = get_option("print_and_ship_allow_backorders");

    $options = array(
        "notify" => "Allow, but notify customer",
        "yes"    => "Allow",
        "no"     => "Do not allow",
    );

    return buildDropdown($options, $selected, 'notify');
}

/**
 * Create dropdown options for the 'Price action' setting
 *
 * @param string $productType Type of the product the options are generated for
 * 
 * @return string
 */
function createPricesActionOptions($productType)
{
    switch ($productType) {
    case 'simple':
        $selected = get_option("print_and_ship_simple_prices_action");
        break;
    
    case 'variable':
        $selected = get_option("print_and_ship_variable_prices_action");
        break;
    
    case 'shipping':
        $selected = get_option("print_and_ship_automatic_shipping_action");
        break;
    
    default:
        return '';
        break;
    }

    $options = array(
        ""         => "",
        "add"      => "Add",
        "subtract" => "Subtract",
    );
        
    return buildDropdown($options, $selected);
}

/**
 * Create dropdown options for the 'Price type' setting
 *
 * @param string $productType Type of the product the options are generated for
 * 
 * @return string
 */
function createPricesTypeOptions($productType)
{
    switch ($productType) {
    case 'simple':
        $selected = get_option("print_and_ship_simple_prices_type");
        break;
    
    case 'variable':
        $selected = get_option("print_and_ship_variable_prices_type");
        break;
    
    case 'shipping':
        $selected = get_option("print_and_ship_automatic_shipping_type");
        break;
    
    default:
        return '';
        break;
    }

    $options = array(
        "" => "",
        "currency" => "Amount",
        "percent" => "Percent",
    );
        
    return buildDropdown($options, $selected);
}

/**
 * Create dropdown options for the 'Price base' setting
 *
 * @param string $productType Type of the product the options are generated for
 * 
 * @return string
 */
function createPriceBaseOptions($productType)
{
    switch ($productType) {
    case 'simple':
        $selected = get_option("print_and_ship_simple_prices_base");
        break;
    
    case 'variable':
        $selected = get_option("print_and_ship_variable_prices_base");
        break;
    
    default:
        return '';
        break;
    }

    $options = array(
        'suggested_retail_price' => 'Suggested Retail Price',
        'purchase_price'         => 'Purchase Price',
    );

    return buildDropdown($options, $selected, "suggested_retail_price");
}

/**
 * Create dropdown options for the 'Round prices' setting
 *
 * @param string $productType Type of the product the options are generated for
 * 
 * @return string
 */
function roundPricesOptions($productType)
{
    switch ($productType) {
    case 'simple':
        $selected = get_option("print_and_ship_simple_prices_round");
        break;
    
    case 'variable':
        $selected = get_option("print_and_ship_variable_prices_round");
        break;
    
    case 'shipping':
        $selected = get_option("print_and_ship_automatic_shipping_round");
        break;
    
    default:
        return '';
        break;
    }

    $options = array(
        "dontround" => "Don't round prices",
        "dot95" => ".95",
        "dot99" => ".99",
        "round" => ".00",
    );

    return buildDropdown($options, $selected, "dontround");
}

/**
 * Create dropdown options for the 'Round prices' setting
 *
 * @return string
 */
function shippingLevelOptions()
{
    // Get saved option
    $selected = get_option("print_and_ship_shipping_minimal_level");
    
    $options = array(
        "normal" => "Normal",
        "tracked" => "Tracked",
        "premium" => "Premium",
    );
    
    return buildDropdown($options, $selected);
}

/**
 * Create a dropdown field with the supplied options
 * 
 * @param array  $options  The options to build a dropdown with
 * @param string $selected The selected option value
 * 
 * @return string HTML string of the dropdown
 */
function buildDropdown($options, $selected, $default = '')
{
    $dropdown = "";
        
    // If no setting was found, set default
    if (empty($selected)) {
        $selected = $default;
    }

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
    'nonce'                             => wp_create_nonce('print_and_ship_admin_save_config'),
    
    // Shared strings
    'strAutomatically'                  => 'Automatically',
    'strToAllPrices'                    => 'to / from',
    'strMultiplyBy'                     => __('Multiply by'),
    'strMultiplyByDesc'                 => __(
        'All Printeers prices are supplied in EUR. To change the price to your currency, use this multiplier.
                                        Example: Exchange rate: 1 EUR is 1.1 USD. Enter 1.1 here.'
    ),
    
    // Settings page
    'strPrinteersSettings'               => 'Printeers Print & Ship settings',
    'strSettingsInfo'                   => 'You can manage all settings for the Printeers Print & Ship plugin below. 
                                            If you have questions regarding any of the settings, 
                                            <a href="https://printeers.com/2020/11/10/how-to-install-configure-invition-print-ship-for-woocommerce/">please take a look here</a>',
    
    // General settings
    'strGeneralSettings'                => 'General',
    'strAPIinformation'                 => 'If you don\'t have API information yet, ',
    'strCreateAccount'                  => 'Create an account here',
    'strApiUser'                        => 'API user',
    'api_user'                          => get_option('print_and_ship_api_user'),
    'strApiKey'                         => 'API key',
    'api_key'                           => get_option('print_and_ship_api_key'),
    'strDemoMode'                       => 'Demo mode',
    'test_mode'                         => (get_option('print_and_ship_test_mode') == 'yes') ? 'checked="checked"' : '',
    'strDebugMode'                      => 'Enable debug mode',
    'strDebugModeDesc'                  => 'Write debug information to wp-content/uploads/print-and-ship.log',
    'debug_mode'                        => (get_option('print_and_ship_debug_mode') == 'yes') ? 'checked="checked"' : '',
    'strOrderStatus'                    => 'Send order to Printeers',
    'strOrderReached'                   => 'when it reaches the order status: ',
    'orderStatusSelection'              => createStatusUnits(),
    'strAllowBackorders'                => 'Accept backorders?',
    'strAllowBackordersSelection'       => createBackordersOptions(),
    'strAllowBackordersDesc'            => 'Use this setting to choose if your customer can order products which are 
                                            not in stock at Printeers',
    
    // Product updates settings 
    'strProductUpdates'                 => 'Product updates',
    'strProductUpdatesInfo'             => 'Settings on this page will not automatically change your data! Each setting
                                            will generate an action on the Product Updates page. If you execute the
                                            action, the data will be changed. For example when you enable "Automatically
                                            update prices when Printeers changes the price", will not change the price
                                            without your consent. When a price is changed, you will see actions appear
                                            on the Product Updates page which will change the prices for you when
                                            you activate them.',
    'strSimpleProducts'                 => 'Simple products',
    'strSimpleProductsInfo'             => 'These settings only apply to Simple products.',
    'strAutoAddProductImage'            => 'Add product image when no product image is present',
    'auto_add_product_image'            => (get_option('print_and_ship_auto_add_product_image') == 'no') ? '' : 'checked="checked"',
    'strAutoAddProductImageDesc'        => 'When the product does not have an image yet, an example image will be set',
    'strAutoUpdateSrpSimple'            => 'Update prices when Printeers changes the price',
    'strAutoUpdateSrpSimpleDesc'        => 'Default: no',
    'auto_update_srp_simple'            => (get_option('print_and_ship_auto_update_srp_simple') == 'yes') ? 'checked="checked"' : '',
    'actionOptionsSimple'               => createPricesActionOptions('simple'),
    'simplePricesAmount'                => get_option('print_and_ship_simple_prices_amount'),
    'typeOptionsSimple'                 => createPricesTypeOptions('simple'),
    'simplePricesBase'                  => createPriceBaseOptions('simple'),
    'strRoundPrices'                    => 'Round all prices to nearest',
    'roundOptionsSimple'                => roundPricesOptions('simple'),
    'strVATNotice'                      => 'Please note! Purchase prices are supplied without VAT. Suggested Retail Prices
                                            have profit margins calculated based on 21% VAT. We recommend to change your
                                            settings as follows: <br /> <strong>Purchase price based calculation</strong><br />
                                            WooCommerce setting: Prices are entered excluding VAT. <br /> <strong>SRP based 
                                            calculation</strong><br /> WooCommerce setting: Prices are entered including VAT.',
    
    'strVariableProducts'               => 'Variable products',
    'strVariableProductsInfo'           => 'These settings only apply to Variable products.',
    'strAutoAddBrands'                  => 'Add new brands to variable products',
    'strAutoAddBrandsDesc'              => 'Default: yes',
    'auto_add_brands'                   => (get_option('print_and_ship_auto_add_brands') == 'no') ? '' : 'checked="checked"',
    'strAutoAddModels'                  => 'Add new models to variable products',
    'strAutoAddModelsDesc'              => 'Default: yes',
    'auto_add_models'                   => (get_option('print_and_ship_auto_add_models') == 'no') ? '' : 'checked="checked"',
    'strAutoAddCaseTypeColour'          => 'Add new case type and colour to variable products',
    'strAutoAddCaseTypeColourDesc'      => 'Default: no',
    'auto_add_case_type_colour'         => (get_option('print_and_ship_auto_add_case_type_colour') == 'yes' ) ? 'checked="checked"' : '',
    'strRenderImageFor'                 => 'Render image for',
    'strVariable'                       => 'Variable products',
    'renderImageForVariable'            => (get_option('print_and_ship_render_image_for_variable') == 'yes') ? 'checked="checked"' : '',
    'strVariation'                      => 'Variations',
    'renderImageForVariation'           => (get_option('print_and_ship_render_image_for_variation') == 'yes') ? 'checked="checked"' : '',
    'strRenderImageBaseSku'             => 'Base SKU for rendering',
    'strRenderImageBaseSkuDesc'         => 'When rendering an image for the main variable product, enter a SKU here. 
                                            This SKU will be used to render images for all variable products.',
    'renderImageBaseSku'                => get_option('print_and_ship_render_image_base_sku'),
    'strDeleteImagesWhenDeleteVariable'     => 'Delete variation images when variable product is deleted',
    'deleteImagesWhenDeleteVariable'        => (get_option('print_and_ship_delete_images_when_delete_variable') == 'yes') ? 'checked="checked"' : '',
    'strDeleteImagesWhenDeleteVariableDesc' => 'This function deletes all attached images from the variations of a variable product, when a variable 
                                                product is deleted. This is useful when you want to clean up your media library. <strong>Warning: 
                                                This action cannot be undone!</strong> Make sure you have a backup of your media library and that the
                                                images of the variations are not used on other places on your website.',
    'strAutoUpdateSrp'                  => 'Update prices when Printeers changes the price',
    'strAutoUpdateSrpDesc'              => 'Default: yes',
    'auto_update_srp'                   => (get_option('print_and_ship_auto_update_srp') == 'no') ? '' : 'checked="checked"',
    'actionOptionsVariable'             => createPricesActionOptions('variable'),
    'variablePricesAmount'              => get_option('print_and_ship_variable_prices_amount'),
    'typeOptionsVariable'               => createPricesTypeOptions('variable'),
    'variablePricesBase'                => createPriceBaseOptions('variable'),
    'roundOptionsVariable'              => roundPricesOptions('variable'),
    'strAutoEnableVariations'           => 'Enable new variations',
    'strAutoEnableVariationsDesc'       => 'Default: yes',
    'auto_enable_variations'            => (get_option('print_and_ship_auto_enable_variations') == 'no') ? '' : 'checked="checked"',
    'strSave'                           => 'Save',
    'admin_notice'                      => createAdminNotice(),

    // Email settings
    'strEmail'                          => 'E-mail',
    'strEmailShipped'                   => 'Track and trace e-mail',
    'strEmailShippedDesc'               => 'You can change the contents of the track and trace e-mail here. 
                                            The same header and footer will be used as the other WooCommerce e-mails.',
    'strEmailSubject'                   => 'E-mail subject',
    'strEmailSubjectDesc'               => 'Enter the subject for the track and trace e-mail (e.g. 
                                            Your order has been shipped)',
    'email_subject'                     => get_option('print_and_ship_email_subject'),
    'strEmailTitle'                     => 'E-mail title',
    'strEmailTitleDesc'                 => 'Enter the title for the track and trace e-mail (e.g. 
                                            Your order has been shipped)',
    'email_title'                       => get_option('print_and_ship_email_title'),
    'strTrackTraceText'                 => 'Track & Trace e-mail text',
    'strTrackTraceTextDesc'             => 'This is the text for the Track & Trace e-mail',
    'track_trace_text'                  => get_option('print_and_ship_track_trace_text'),

    // Gift item settings
    'strGiftItems'                      => 'Gift items',
    'strGiftItemsSettings'              => 'Gift item settings',
    'strGiftItemsSettingsDesc'          => 'Use these settings to automatically add gift items, custom packaging or 
                                            other free items. The products cannot be seen in WooCommerce admin and 
                                            your customer will not see the product, but they will be added in the 
                                            order at Printeers and will be visible in the Printeers Dashboard.',
    'strAutoAddProductOrder'            => 'Add this product to each order',
    'strAutoAddProductOrderDesc'        => 'Enter the Printeers SKU of a product that has to be added to each order 
    automatically. This can be used     to add custom packing items or flyers',
    'auto_add_ipp_sku_order'            => get_option('print_and_ship_auto_add_ipp_sku_order'),
    'strAutoAddProductProduct'          => 'Add this product to each ordered product',
    'strAutoAddProductProductDesc'      => 'Enter the Printeers SKU of a product that has to be added to each ordered 
                                            product automatically. This can be used to add custom packing items to a 
                                            product',
    'auto_add_ipp_sku_product'          => get_option('print_and_ship_auto_add_ipp_sku_product'),

    // Shipping settings
    'strShipping'                       => 'Shipping',
    'strShippingSettings'               => 'Shipping settings',
    'minimumShippingLevelOptions'       => shippingLevelOptions(),
    'strShippingMinimalLevel'           => 'Select the minimal shipping level you wish to use',
    'strShippingMinimalLevelDesc'       => 'The setting you choose here will be the minimum shipping level 
                                        that will be used for every order. Example: If you select Tracked, an order
                                        might be upgraded to premium when the amount or quantity is too high, but
                                        it will never be shipped as normal. <br />
                                        <strong>Please note! This also applies to 
                                        automatic shipping calculation!</strong>',
    'strAutomaticShippingCalculator'    => 'Automatic Shipping Calculator',
    'automaticShippingAmount'           => get_option('print_and_ship_automatic_shipping_amount'),
    'automaticShippingActionOptions'    => createPricesActionOptions('shipping'),
    'automaticShippingTypeOptions'      => createPricesTypeOptions('shipping'),
    'automaticShippingMultiplier'       => get_option('print_and_ship_automatic_shipping_multiplier'),
    'automaticShippingRoundOptions'     => roundPricesOptions('shipping'),
);

$template = file_get_contents(PRINT_AND_SHIP_TEMPLATES . 'settings-page.html');
echo applyTemplate($template, $fields);
