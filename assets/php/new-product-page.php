<?php
/**
 * @version $Id$
 * @copyright 2018 Printeers
 * @author Jacco Drabbe
 **/

namespace PrintAndShip;

defined('ABSPATH') or die('No script kiddies please!');

require_once PRINT_AND_SHIP_CLASSES . '/class-new-products-table.php';

$table = new \PrintAndShip\NewProductsTable();
$result = $table->prepare_items();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && count($_POST['products_checkmark']) > 0) {
    // Add the new products
    $results = $table->process_bulk_action();
    $buffer = '<h2>The following actions are done</h2>' . implode('<br />', $results);
    $datalist = '';
} else {
    // Show the new products table
    ob_start();
    include __DIR__ . '/table-search.php';

    // Show the filter link
    if (isset($_GET['filter_existing'])) {
        echo '<a href="admin.php?page=print-and-ship-create-a-simple-product">Show all products</a>';
    } else {
        echo '<a href="admin.php?page=print-and-ship-create-a-simple-product&filter_existing=yes">Only show non-existing products</a>';
    }

    $table->display();
    $buffer = ob_get_clean();

    $datalist = $table->create_datalist();
}

$fields = array(
    'datalist'  => $datalist,
    'table'     => $buffer,
    'jsfile'    => plugins_url('assets/js/print_and_ship_admin_new_products.js', PRINT_AND_SHIP_BASEDIR . '/invition-print-and-ship.php')
);
$template = file_get_contents(PRINT_AND_SHIP_TEMPLATES . 'new-product-page.html');
echo applyTemplate($template, $fields);
