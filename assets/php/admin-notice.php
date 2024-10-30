<?php
/**
 * @version $Id$
 * @copyright 2018 Invition
 * @author Jacco Drabbe
 **/

 namespace PrintAndShip;

defined('ABSPATH') or die('No script kiddies please!');

$post_id = ( sanitize_text_field($_GET['post']) );
$image_id = get_field('print_and_ship_print_image', $post_id);

$data = wp_get_attachment_image_src($image_id, 'full');
$pixels_width = $data[1];
$pixels_height = $data[2];

$valid = checkImageDimensions($post_id, $pixels_width, $pixels_height);

$template = file_get_contents(PRINT_AND_SHIP_TEMPLATES . 'admin-notice.html');
$fields = array(
    'type'      => 'warning',
    'message'   => $valid,
    'style'     => ''
);
echo applyTemplate($template, $fields);
