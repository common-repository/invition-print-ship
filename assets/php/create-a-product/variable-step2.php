<?php
/**
 * @author Mike Sies <support@printeers.com>
 * @copyright 2019 Printeers
 * @version $Id$
 **/

namespace PrintAndShip;

defined('ABSPATH') or die('No script kiddies please!');

/**
 * selectOptions() Selects all the available terms and returns selectable options
 *
 * @return string
 */
function selectOptions($taxonomy)
{
    $options = "";
    $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));

    foreach ($terms as $term) {
        $options .= '<option value="' . $term->slug . '">' . $term->name . "</option>";
    }
    
    return $options;
}

$fields = array(
'optionsBrand'          => selectOptions('pa_device_brand'),
'optionsModel'          => selectOptions('pa_device_model'),
'optionsCaseTypeColour' => selectOptions('pa_case_type_colour'),
);

$template = file_get_contents(PRINT_AND_SHIP_TEMPLATES . 'create-a-product/variable-step2.html');
echo applyTemplate($template, $fields);
