<?php
/**
 * @version $Id$
 * @copyright 2018 Printeers
 * @author Mike Sies
 **/

register_field_group(
    array (
    'id' => 'acf_print-image',
    'title' => 'Printeers Print and Ship',
    'fields' => array (
        array (
            'key' => 'field_73v7h2bgew8',
            'name' => 'print_and_ship_enable',
            'type' => 'true_false',
            'message' => 'Enable Printeers services for this product',
        ),
        array (
            'key' => 'field_ssy453bh',
            'label' => 'Printeers SKU (only for simple products)',
            'name' => 'print_and_ship_sku',
            'type' => 'text',
            'conditional_logic' => array (
                'status' => 1,
                'rules' => array (
                    array (
                        'field' => 'field_73v7h2bgew8',
                        'operator' => '==',
                        'value' => 1,
                    ),
                ),
                'allorany' => 'all',
            ),
        ),
        array (
            'key' => 'field_5adafd63544bc',
            'label' => 'Print image',
            'name' => 'print_and_ship_print_image',
            'type' => 'image',
            'conditional_logic' => array (
                'status' => 1,
                'rules' => array (
                    array (
                        'field' => 'field_73v7h2bgew8',
                        'operator' => '==',
                        'value' => '1',
                    ),
                ),
                'allorany' => 'all',
            ),
            'save_format' => 'id',
            'preview_size' => 'thumbnail',
            'library' => 'all',
        ),
    ),
    'location' => array (
        array (
            array (
                'param' => 'post_type',
                'operator' => '==',
                'value' => 'product',
                'order_no' => 0,
                'group_no' => 0,
            ),
        ),
    ),
    'options' => array (
        'position' => 'normal',
        'layout' => 'default',
        'hide_on_screen' => array (
        ),
    ),
    'menu_order' => 0,
    )
);
