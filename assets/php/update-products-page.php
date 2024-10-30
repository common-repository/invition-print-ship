<?php
/**
 * @version $Id$
 * @copyright 2018 Printeers
 * @author Mike Sies
 **/

namespace PrintAndShip;

defined('ABSPATH') or die('No script kiddies please!');

require_once PRINT_AND_SHIP_CLASSES . '/class-product-update.php';

$fields = array(
    'title'         => 'Update product data from Printeers',
    'selected'      => 'selected',
    'select_all'    => 'Select all',
    'imgurl'        => plugins_url('assets/images/loading.apng', PRINT_AND_SHIP_BASEDIR . '/invition-print-and-ship.php'),
);
    
$template = file_get_contents(PRINT_AND_SHIP_TEMPLATES . 'update-products-page.html');

echo applyTemplate($template, $fields);
