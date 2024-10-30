<?php
/**
 * @author Mike Sies <support@printeers.com>
 * @copyright 2017 Printeers
 * @version $Id$
 **/

namespace PrintAndShip;

defined('ABSPATH') or die('No script kiddies please!');

$importResult = "";

// A tool was started
if (isset($_POST['job'])) {
    if ($_POST['job'] == 'runImport') {
        runImportCron(true);
        $importResult = "Import was executed succesfully";
    }
}

$fields = array(
    'strTools'      => 'Tools',
    'strImport'     => 'Force import stock data',
    'strStartImport' => 'Start import',
    'strImportText' => 'If you made a lot of changes in your products or you are updating 
                        from an old version and are not sure if the data in your database 
                        is correct, you can use this tool to force update all product stock data.
                        Updating the stock might take a while if you have a lot of products. It 
                        will only update products with Printeers Print &amp; Ship enabled.',
    'strImportResult' => $importResult,
);

$template = file_get_contents(PRINT_AND_SHIP_TEMPLATES . 'tools-page.html');
echo applyTemplate($template, $fields);
