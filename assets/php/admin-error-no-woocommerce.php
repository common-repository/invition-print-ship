<?php
/**
 * @version $Id$
 * @copyright 2018 Printeers
 * @author Jacco Drabbe
 **/

    namespace PrintAndShip;

defined('ABSPATH') or die('No script kiddies please!');

$template = file_get_contents(PRINT_AND_SHIP_TEMPLATES . 'admin-notice.html');
$fields = array(
    'type'    => 'error',
    'message' => 'WooCommerce was not found. Please install WooCommerce before proceeding with Printeers Print &amp; Ship.',
    'style'   => ''
);

echo applyTemplate($template, $fields);
