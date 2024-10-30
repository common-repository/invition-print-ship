<?php
/**
 * @author Mike Sies <support@printeers.com>
 * @copyright 2017 Printeers
 * @version $Id$
 **/

namespace PrintAndShip;

defined('ABSPATH') or die('No script kiddies please!');

/**
 * Get the current running WooCommerce function
 *
 * @return string
 */
function getWooCommerceVersion()
{
    $plugin_folder = get_plugins('/' . 'woocommerce');
    $plugin_file = 'woocommerce.php';
        
    if (isset($plugin_folder[$plugin_file]['Version'])) {
        return $plugin_folder[$plugin_file]['Version'];
    } else {
        return 'WooCommerce version not detected';
    }
}

$fields = array(
    'strSupportText'              => 'If you are experiencing issues using or configuring this plugin, please contact our IT support at support@printeers.com.',
    'strSupport'                  => 'Support',
    'PluginVersion'               => PRINT_AND_SHIP_VERSION,
    'PHPVersion'                  => phpversion(),
    'WPVersion'                   => get_bloginfo('version'),
    'WCVersion'                   => getWooCommerceVersion()
);

$template = file_get_contents(PRINT_AND_SHIP_TEMPLATES . 'support-page.html');
echo applyTemplate($template, $fields);
