<?php
/**
 * @author Mike Sies <support@printeers.com>
 * @copyright 2019 Printeers
 * @version $Id$
 **/

namespace PrintAndShip;

defined('ABSPATH') or die('No script kiddies please!');

/**
 * Create a draft product with the settings requested by the user
 *
 * @return int
 */
function createVariableDraft()
{
    // All required attributes to make a valid Printeers combination
    $initAttributes = array(
        "pa_device_brand",
        "pa_device_model",
        "pa_case_type_colour",
    );
    $attributeData = array();

    // Add the post
    $post_id = wp_insert_post(
        array(
            'post_title'    => sanitize_text_field($_POST['product_name']),
            'post_status'   => 'draft',
            'post_type'     => "product",
        )
    );

    // Make it a variable product
    wp_set_object_terms($post_id, 'variable', 'product_type', false);

    // Enable Printeers services
    update_post_meta($post_id, 'print_and_ship_enable', 1);
    update_post_meta($post_id, '_print_and_ship_enable', 'field_73v7h2bgew8');

    // The print image field
    update_post_meta($post_id, 'print_and_ship_print_image', '');
    update_post_meta($post_id, '_print_and_ship_print_image', 'field_5adafd63544bc');

    // Disable _manage_stock because we want to manage stock on variation level
    update_post_meta($post_id, '_manage_stock', 'no');

    // Walk through list of required attributes make an array of all data to be added
    foreach ($initAttributes as $initAttribute) {
        $attributeIDs = array();

        foreach ($_POST[$initAttribute] as $postedTerm) {
            $term = get_term_by('slug', $postedTerm, $initAttribute);

            if ($term->term_id != 0) {
                $attributeIDs[] = $term->term_id;
            }
        }

        $attributeData[$initAttribute] = array(
            'name' => $initAttribute,
            'value' => '',
            'is_visible' => '0',
            'is_variation' => '1',
            'is_taxonomy' => '1'
        );
            
        wp_set_object_terms($post_id, $attributeIDs, $initAttribute, false);
    }

    update_post_meta($post_id, '_product_attributes', $attributeData);

    return $post_id;
}

// Create the product and add the attributes
$product_id = createVariableDraft();

// Get the full template
$fields = array(
    'productId' => $product_id,
);

$template = file_get_contents(PRINT_AND_SHIP_TEMPLATES . '/create-a-product/variable-step3.html');
echo applyTemplate($template, $fields);
