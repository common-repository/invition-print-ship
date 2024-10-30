<?php
/**
 * @version $Id$
 * @copyright 2018 Printeers
 * @author Jacco Drabbe
 **/

namespace PrintAndShip;

defined('ABSPATH') or die('No script kiddies please!');

ob_start();
    $table->search_box('search', 'search_id');
$buffer = ob_get_clean();

$fields = array(
    'search_box'   => $buffer
);
$template = file_get_contents(PRINT_AND_SHIP_TEMPLATES . 'table-search.html');

echo applyTemplate($template, $fields);
