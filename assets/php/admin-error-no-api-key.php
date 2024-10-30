<?php
/**
 * @version $Id$
 * @copyright 2018 Printeers
 * @author Jacco Drabbe
 **/

namespace PrintAndShip;

defined('ABSPATH') or die('No script kiddies please!');

$api_user = get_option('print_and_ship_api_user');
$api_key = get_option('print_and_ship_api_key');

if (empty($api_key) || empty($api_user)) {
    $admin_settings_page = '<a href="' . admin_url('admin.php?page=print-and-ship-config') . '">Settings</a>';
    $template = file_get_contents(PRINT_AND_SHIP_TEMPLATES . 'admin-notice.html');
    $fields = array(
        'type'    => 'error',
        'message' => 'API information not configured correctly. Please correct this at the ' . $admin_settings_page . ' page.',
        'style'   => ''
    );

    echo applyTemplate($template, $fields);
}
