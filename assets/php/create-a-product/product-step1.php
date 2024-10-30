<?php
/**
 * @author Mike Sies <support@printeers.com>
 * @copyright 2019 Printeers
 * @version $Id$
 **/

namespace PrintAndShip;

defined('ABSPATH') or die('No script kiddies please!');

$fields = array(
    'simpleProductImage' => plugins_url('assets/images/simple-product.png', PRINT_AND_SHIP_BASEDIR . '/invition-print-and-ship.php'),
    'variableProductImage' => plugins_url('assets/images/variable-product.png', PRINT_AND_SHIP_BASEDIR . '/invition-print-and-ship.php'),
);

$template = file_get_contents(PRINT_AND_SHIP_TEMPLATES . '/create-a-product/product-step1.html');
echo applyTemplate($template, $fields);
