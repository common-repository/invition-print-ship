<?php
/**
 * @version $Id$
 * @copyright 2018 Printeers
 * @author Mike Sies
 **/

namespace PrintAndShip;

defined('ABSPATH') or die('No script kiddies please!');

/**
 * ProductUpdate
 *
 * @author Mike Sies
 * @copyright 2018 Printeers
 * @access public
 * @package PRINT_AND_SHIP
 */
class ProductUpdate
{

    private $db;
    private $woo;
    private $ipp;

    /**
     * constructor.
     */
    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
        $this->woo = new \PrintAndShip\Woo();
        $this->ipp = new \PrintAndShip\IPP();
    }

    /**
     * Prepare array of actions that may be executed.
     *
     * @return array
     */
    public function discoverActions()
    {
        $actions = array();
        $stockData = $this->ipp->getStockList();
        $attributeData = $this->ipp->getAttributesList();
        $caseTypeColourCombinations = $this->woo->createCaseTypeColourCombinations($stockData);
     
        // No valid Printeers API connection
        if (!is_object($stockData)) {
            return array("error" => "Could not fetch stock data from Printeers, are your API details correct?");
        }
        
        // Create an associative array of stock items using their sku as key.
        $invitionStockItems = array();
        foreach ($stockData->items as $item) {
            $invitionStockItems[$item->sku] = $item;
        }
        
        /**
         * SIMPLE PRODUCTS
         *
         * The code below selects all actions for simple products
         */

        // Get the settings from the DB
        $auto_update_srp_simple = get_option("print_and_ship_auto_update_srp_simple");
        
        // Select all Printeers enabled Simple products
        $querySimpleProducts = "
            SELECT
                DISTINCT(posts.ID),
                posts.post_title AS name,
                postmeta_stock.meta_value AS manage_stock,
                postmeta_invition_sku.meta_value AS invition_sku,
                COALESCE(postmeta_price.meta_value, '') AS price,
                COALESCE(postmeta_sale_price.meta_value, '') AS sale_price
            FROM " . $this->db->posts . " AS posts
                INNER JOIN " . $this->db->postmeta . " AS postmeta_invition_sku
                    ON posts.ID = postmeta_invition_sku.post_id
                    AND postmeta_invition_sku.meta_key = 'print_and_ship_sku'
                INNER JOIN " . $this->db->term_relationships . " AS term_relationships
                    ON posts.ID = term_relationships.object_id
                LEFT JOIN " . $this->db->term_taxonomy . " AS term_taxonomy
                        ON term_relationships.term_taxonomy_id = term_taxonomy.term_taxonomy_id
                        AND term_taxonomy.taxonomy = 'product_type'
                LEFT JOIN " . $this->db->terms . " AS product_type
                    ON term_taxonomy.term_id = product_type.term_id
                LEFT JOIN " . $this->db->postmeta . " AS postmeta_price
                    ON postmeta_price.post_id = posts.ID
                    AND postmeta_price.meta_key = '_price'
                LEFT JOIN " . $this->db->postmeta . " AS postmeta_sale_price
                    ON postmeta_sale_price.post_id = posts.ID
                    AND postmeta_sale_price.meta_key = '_sale_price'
                LEFT JOIN " . $this->db->postmeta . " AS postmeta_stock
                    ON postmeta_stock.post_id = posts.ID
                    AND postmeta_stock.meta_key = '_manage_stock'
            WHERE
                posts.post_type = 'product'
                AND posts.post_status != 'trash'
                AND product_type.slug = 'simple'";
        $resultsSimpleProducts = $this->db->get_results($querySimpleProducts);
        
        // Iterate over all found Simple products and check for actions
        foreach ($resultsSimpleProducts as $product) {
            // Check for manage stock setting on simple products (should be 'yes' to make our stock import work)
            if ($product->manage_stock == 'no') {
                $actions[$product->ID]['actions'][] = array(
                    "type"              => "simple-product-enable-manage-stock",
                    "subject"           => $product->ID,
                    "variable_product"  => $product->ID,
                    "explain"           => "Change setting 'manage stock' setting to 'yes' (otherwise automatic stock updates will not work).",
                );
            }
            
            // The price has changed, or was not set. Add correct price
            $stockItem = $invitionStockItems[$product->invition_sku];
            $shouldBePrice = $this->calculatePrice(
                $stockItem->suggested_retail_price, 
                $stockItem->price, 
                'simple'
            );
            
            if ($auto_update_srp_simple == "yes" 
                && $product->sale_price == ""
                && $product->price != $shouldBePrice
            ) {
                $actions[$product->ID]["actions"][] = array(
                    "type"              => "simple-product-change-price",
                    "subject"           => $product->ID,
                    "variable_product"  => $product->ID,
                    "arguments"         => array("price" => $shouldBePrice),
                    "explain"           => "Update price of '" . $product->name . "' from " . $product->price . " to " . $shouldBePrice,
                );
            }
        }

        // Add the product name and type to the actions array
        foreach ($actions as $id => $action) {
            if (!array_key_exists("name", $action)) {
                $actions[$id]["name"] = get_the_title($id);
            }
            if (!array_key_exists("product_type", $action)) {
                $actions[$id]["product_type"] = 'simple';
            }
        }
        
        /**
         * VARIABLE PRODUCTS
         *
         * The code below selects all actions for variable products
         */
        
        // Get the settings from the DB
        $auto_add_brands = get_option("print_and_ship_auto_add_brands");
        $auto_add_models = get_option("print_and_ship_auto_add_models");
        $auto_add_case_type_colour = get_option("print_and_ship_auto_add_case_type_colour");
        $auto_update_srp_variable = get_option("print_and_ship_auto_update_srp");
        $render_image_base_sku = get_option("print_and_ship_render_image_base_sku");
        $render_image_for_variable = get_option("print_and_ship_render_image_for_variable");

        // Get all Printeers enabled variable products
        $querySelectedVariableProducts = "
            SELECT 
                posts.ID,
                posts.post_title AS name,
                postmeta_stock.meta_value AS manage_stock,
                postmeta_thumbnail.meta_value AS thumbnail
            FROM " . $this->db->posts . " AS posts
                INNER JOIN " . $this->db->postmeta . " AS postmeta_enable
                    ON posts.ID = postmeta_enable.post_id
                        AND postmeta_enable.meta_key = 'print_and_ship_enable'
                LEFT JOIN " . $this->db->postmeta . " AS postmeta_stock
                    ON postmeta_stock.post_id = posts.ID
                        AND postmeta_stock.meta_key = '_manage_stock'
                LEFT JOIN " . $this->db->postmeta . " AS postmeta_thumbnail
                    ON postmeta_thumbnail.post_id = posts.ID 
                    AND postmeta_thumbnail.meta_key = '_thumbnail_id'
                INNER JOIN " . $this->db->term_relationships . " AS term_relationships
                    ON posts.ID = term_relationships.object_id
                INNER JOIN " . $this->db->term_taxonomy . " AS term_taxonomy
                    ON term_relationships.term_taxonomy_id = term_taxonomy.term_taxonomy_id
                        AND term_taxonomy.taxonomy = 'product_type'
                INNER JOIN " . $this->db->terms . " AS terms
                    ON term_taxonomy.term_id = terms.term_id
            WHERE
                posts.post_type = 'product'
                AND terms.slug = 'variable'
                AND posts.post_status != 'trash'
                AND postmeta_enable.meta_value = '1'";
        $resultsSelectedVariableProducts = $this->db->get_results($querySelectedVariableProducts);

        if (count($resultsSelectedVariableProducts)==0) {
            // No variable products, no need to resume here.
            return $actions;
        }
            
        // Create an associative array of initialized variable products using their ID as key
        $variableProducts = array();
        // Loop over variable products received from the db
        foreach ($resultsSelectedVariableProducts as $result) {
            $device_brands = $this->_getSelectedAttributes("device_brand", $result->ID);
            $device_models = $this->_getSelectedAttributes("device_model", $result->ID);
            $case_type_colours = $this->_getSelectedAttributes("case_type_colour", $result->ID);

            if (!empty($device_brands) || !empty($device_models) || !empty($case_type_colours)) {
                $variableProducts[$result->ID] = array(
                    'name'              => $result->name,
                    'manage_stock'      => $result->manage_stock,
                    'thumbnail'         => $result->thumbnail,
                    'variations'        => array(),
                    'device_brands'     => $device_brands,
                    'device_models'     => $device_models,
                    'case_type_colour'  => $case_type_colours,
                );
            }
        }

        // When a variable product was selected, but not returned in the variable products query with taxonomies query,
        // it is probably missing some taxonomy links. Add an init action for it.
        foreach ($resultsSelectedVariableProducts as $selectedVariableProduct) {
            if (!array_key_exists($selectedVariableProduct->ID, $variableProducts)) {
                $actions[$selectedVariableProduct->ID]["actions"][] = array(
                    "type"              => "variable-product-init",
                    "arguments"         => array(),
                    "subject"           => $selectedVariableProduct->ID,
                    "variable_product"  => $selectedVariableProduct->ID,
                    "explain"           => "Variable product '" .
                                            $selectedVariableProduct->name .
                                            "' needs to be initialized. This will link the attributes 'brands', 
                                            'models' and 'case type and colour' to it. Any variations for this variable 
                                            product will not be validated until this action has been executed.",
                );
            }
        }

        // There is only one variable product and it is not initialized.
        $variableProductIDsString = implode(",", array_keys($variableProducts));
        if (empty($variableProductIDsString)) {
            // Add the product names and type to the actions array
            foreach ($actions as $productID => $action) {
                if (!array_key_exists("name", $action)) {
                    $actions[$productID]["name"] = get_the_title($productID);
                }
                if (!array_key_exists("product_type", $action)) {
                    $actions[$id]["product_type"] = 'variable';
                }
            }

            return $actions;
        }

        // Loop through the variable products to check for actions
        foreach ($variableProducts as $variableProductID => $variableProduct) {
            // Add brands when they are not set on a variable product
            if ($auto_add_brands == "yes") {
                $missingBrands = array_diff($attributeData->brands, $variableProduct['device_brands']);
                $missingBrands = $this->_filterMissingTerms($missingBrands, 'pa_device_brand');

                if (count($missingBrands) !== 0) {
                    $actions[$variableProductID]['actions'][] = array(
                        "type"              => "variable-product-add-missing-attribute-values",
                        "arguments"         => array(
                            "attribute"     => "brands",
                            "values"        => $missingBrands,
                        ),
                        "subject"           => $variableProductID,
                        "variable_product"  => $variableProductID,
                        "explain"           => "Add missing brands to variable product "
                                               . $variableProduct['name']
                                               . ". Missing brands: "
                                               . implode(", ", $missingBrands),
                    );
                }
            }

            // Add models when they are not set on a variable product
            if ($auto_add_models == "yes") {
                $missingModels = array_diff($attributeData->models, $variableProduct['device_models']);
                $missingModels = $this->_filterMissingTerms($missingModels, 'pa_device_model');
                    
                if (count($missingModels) !== 0) {
                    $actions[$variableProductID]['actions'][] = array(
                        "type"              => "variable-product-add-missing-attribute-values",
                        "arguments"         => array(
                            "attribute"     => "models",
                            "values"        => $missingModels,
                        ),
                        "subject"           => $variableProductID,
                        "variable_product"  => $variableProductID,
                        "explain"           => "Add missing models to variable product " .
                                                $variableProduct['name'] .
                                                ". Missing models: " .
                                                implode(", ", $missingModels),
                    );
                }
            }

            // Add case_type and colour when they are not set on a variable product
            if ($auto_add_case_type_colour == "yes") {
                $missingCaseTypesColours = array_diff($caseTypeColourCombinations, $variableProduct['case_type_colour']);

                if (is_array($missingCaseTypesColours)) {
                    $missingCaseTypesColours = $this->_filterMissingTerms($missingCaseTypesColours, 'pa_case_type_colour');
                }
                    
                if (count($missingCaseTypesColours) !== 0) {
                    $actions[$variableProductID]['actions'][] = array(
                        "type"              => "variable-product-add-missing-attribute-values",
                        "arguments"         => array(
                            "attribute"     => "case_type_colour",
                            "values"        => $missingCaseTypesColours,
                        ),
                        "subject"           => $variableProductID,
                        "variable_product"  => $variableProductID,
                        "explain"           => "Add missing case types to variable product " .
                                                $variableProduct['name'] .
                                                ". Missing case type and colours: " .
                                                implode(", ", $missingCaseTypesColours),
                    );
                }
            }

            // Render image for the main product
            if (
                $render_image_for_variable == "yes"
                && $render_image_base_sku != "" 
                && $variableProduct['thumbnail'] == ""
            ) {
                $actions[$variableProductID]['actions'][] = array(
                    "type"              => "variable-product-render-image",
                    "subject"           => $variableProductID,
                    "variable_product"  => $variableProductID,
                    "arguments"         => array("sku" => $render_image_base_sku),
                    "explain"           => "Render main image for variable product " . $variableProduct['name'],
                );
            }

            // Check for manage stock setting on variable products (should be 'no' to make our stock import work on variations)
            if ($variableProduct['manage_stock'] == 'yes') {
                $actions[$variableProductID]['actions'][] = array(
                    "type"              => "variable-product-disable-manage-stock",
                    "subject"           => $variableProductID,
                    "variable_product"  => $variableProductID,
                    "explain"           => "Change setting 'manage stock' setting to 'no' (otherwise automatic stock updates will not work).",
                );
            }
        }

        // Select all product variations we want to check
        $queryVariations = "
                SELECT
                    posts.post_parent AS variable_product_id,
                    posts.post_title AS name,
                    posts.ID AS variation_id,
                    COALESCE(postmeta_sku.meta_value, '') AS sku,
					COALESCE(postmeta_price.meta_value, '') AS price,
					COALESCE(postmeta_device_brand.meta_value, '') AS device_brand,
					COALESCE(postmeta_device_model.meta_value, '') AS device_model,
					COALESCE(postmeta_case_type_colour.meta_value, '') AS case_type_colour
                FROM " . $this->db->posts . " as posts
                    LEFT JOIN " . $this->db->postmeta . " AS postmeta_sku
                        ON postmeta_sku.post_id = posts.ID 
                        AND postmeta_sku.meta_key = 'print_and_ship_sku'
					LEFT JOIN " . $this->db->postmeta . " AS postmeta_price
                        ON postmeta_price.post_id = posts.ID 
                        AND postmeta_price.meta_key = '_price'
					LEFT JOIN " . $this->db->postmeta . " AS postmeta_device_brand
                        ON postmeta_device_brand.post_id = posts.ID 
                        AND postmeta_device_brand.meta_key = 'attribute_pa_device_brand'
					LEFT JOIN " . $this->db->postmeta . " AS postmeta_device_model
                        ON postmeta_device_model.post_id = posts.ID 
                        AND postmeta_device_model.meta_key = 'attribute_pa_device_model'
					LEFT JOIN " . $this->db->postmeta . " AS postmeta_case_type_colour
                        ON postmeta_case_type_colour.post_id = posts.ID 
                        AND postmeta_case_type_colour.meta_key = 'attribute_pa_case_type_colour'
				WHERE 
					posts.post_parent IN (".$variableProductIDsString.")
					AND post_type = 'product_variation'
			";

        $resultsVariations = $this->db->get_results($queryVariations);
        if ($this->db->last_error) {
            echo 'You done bad! ' . $this->db->last_error;
        }
            
        // Loop over variations we received from database and add them to the correct variable product in our array.
        foreach ($resultsVariations as $result) {
            // The variation does not have a parent (anymore)
            if (!array_key_exists($result->variable_product_id, $variableProducts)) {
                debuglog("Variation parent missing. parent: " . $result->variable_product_id." variation: ".$result->variation_id);
                continue;
            }
                
            // The variation is part of an Printeers enabled variable product but doesn't have a Printeers SKU set
            if ($result->sku == '') {
                $actions[$result->variable_product_id]["actions"][] = array(
                    "type"              => "variation-remove",
                    "subject"           => $result->variation_id,
                    "variable_product"  => $result->variable_product_id,
                    "explain"           => "Remove variation '" .
                                            $result->name . "', " .
                                            $result->device_brand .", " .
                                            $result->device_model . ", " .
                                            $result->case_type_colour . " because it has no Printeers SKU set.",
                );
                continue;
            }

            // We have a match, add the details to the array
            $variableProducts[$result->variable_product_id]['variations'][$result->sku][] = array(
                'variation_id'      => $result->variation_id,
                'name'              => $result->name,
                'price'             => $result->price,
                'device_brand'      => $result->device_brand,
                'device_model'      => $result->device_model,
                'case_type_colour'  => $result->case_type_colour,
            );
        }

        // Loop over all existing variations
        foreach ($variableProducts as $variableProductID => $variableProduct) {
            foreach ($variableProduct["variations"] as $variationSKU => $variations) {
                foreach ($variations as $variation) {
                    // This Printeers SKU is not present (anymore) in the API stock list
                    if (!array_key_exists($variationSKU, $invitionStockItems)) {
                        $actions[$variableProductID]["actions"][] = array(
                            "type"              => "variation-remove",
                            "subject"           => $variation["variation_id"],
                            "variable_product"  => $variableProductID,
                            "explain"           => "Remove variation '" .
                                                    $variation["name"]. "', " .
                                                    $variation['device_brand']. ", " .
                                                    $variation['device_model']. ", " .
                                                    $variation['case_type_colour'] .
                                                    " because sku is not present in Printeers  
                                                    stock list (SKU: ".$variationSKU.").",
                        );
                        continue;
                    }
                        
                    // Get the right stock item
                    $invitionItem = $invitionStockItems[$variationSKU];
                        
                    // This product is discontinued. Remove the variation
                    if ($invitionItem->availability->can_backorder != 1
                        && $invitionItem->availability->status == 'out-of-stock'
                    ) {
                        $actions[$variableProductID]["actions"][] = array(
                            "type"              => "variation-remove",
                            "subject"           => $variation["variation_id"],
                            "variable_product"  => $variableProductID,
                            "explain"           => "Remove variation '" .
                                                    $variation["name"] .
                                                    "', ".$variation['device_brand'] .
                                                    ", ".$variation['device_model'] .
                                                    ", ".$variation['case_type_colour'] .
                                                    " because its sold out and the product is discontinued.",
                        );
                        continue;
                    }
                        
                    // The brand is invalid for this SKU
                    if ($variation["device_brand"] != sanitize_title($invitionItem->attributes->device_brand)) {
                        $actions[$variableProductID]["actions"][] = array(
                            "type"              => "variation-remove",
                            "subject"           => $variation["variation_id"],
                            "variable_product"  => $variableProductID,
                            "explain"           => "Remove variation '" .
                                                    $variation["name"] .
                                                    "', ".$variation['device_brand'] .
                                                    ", ".$variation['device_model'] .
                                                    ", ".$variation['case_type_colour'] .
                                                    " because the brand attribute is not valid 
                                                    for the variation sku '".$variationSKU."'.",
                        );
                        continue;
                    }

                    // The model is invalid for this SKU
                    if (!in_array(
                        $variation["device_model"], 
                        array_map(
                            function ($v) {
                                return sanitize_title($v);
                            }, 
                            $invitionItem->attributes->device_models
                        )
                    )) {
                        $actions[$variableProductID]["actions"][] = array(
                            "type"              => "variation-remove",
                            "subject"           => $variation["variation_id"],
                            "variable_product"  => $variableProductID,
                            "explain"           => "Remove variation '" .
                                                    $variation["name"] . "', " .
                                                    $variation['device_brand'] . ", " .
                                                    $variation['device_model'] . ", " .
                                                    $variation['case_type_colour'] .
                                                    " because the model attribute is not valid 
                                                    for the variation sku '" . $variationSKU . "'.",
                        );
                        continue;
                    }

                    // The Case type & colour are invalid for this SKU
                    if ($variation["case_type_colour"] != sanitize_title($this->woo->createCaseTypeColour($invitionItem))) {
                        $actions[$variableProductID]["actions"][] = array(
                            "type"              => "variation-remove",
                            "subject"           => $variation["variation_id"],
                            "variable_product"  => $variableProductID,
                            "explain"           => "Remove variation '" .
                                                    $variation["name"] . "', " .
                                                    $variation['device_brand'] . ", " .
                                                    $variation['device_model'] . ", " .
                                                    $variation['case_type_colour']."  be
                                                    cause the case type and colour attribute is not valid 
                                                    for the variation sku '" . $variationSKU . "'.",
                        );
                        continue;
                    }

                    // The price has changed, or was not set. Add correct SRP
                    if ($auto_update_srp_variable == "yes"
                        && $this->calculatePrice(
                            $invitionItem->suggested_retail_price, 
                            $invitionItem->price, 
                            'variable'
                        ) != $variation["price"]
                    ) {
                        $actions[$variableProductID]["actions"][] = array(
                            "type"              => "variation-change-price",
                            "subject"           => $variation["variation_id"],
                            "variable_product"  => $variableProductID,
                            "arguments"         => array("price" => $this->calculatePrice(
                                $invitionItem->suggested_retail_price, 
                                $invitionItem->price, 
                                'variable'
                            )),
                            "explain"           => "Update price for variation '" .
                                                    $variation["name"] . "' from " .
                                                    $variation["price"] . " to " .
                                                    $this->calculatePrice(
                                                        $invitionItem->suggested_retail_price, 
                                                        $invitionItem->price, 
                                                        'variable'
                                                    ),
                        );
                    }
                }
            }
        }

        // First, we iterate over the complete Printeers stock list to see which variations can be added
        foreach ($invitionStockItems as $invitionSKU => $invitionItem) {
            // We only want the print items here, skip all others
            if ($invitionItem->kind != "print") {
                continue;
            }

            // Secondly, we iterate over the models for which a SKU can be used.
            // Since a single SKU can match multiple models, multiple variations can be created in wordpress.
            foreach ($invitionItem->attributes->device_models as $invitionItemModel) {
                // We then iterate over all the variable products to see if the invition item might be added to that variable product as a variation.
                foreach ($variableProducts as $variableProductID => $variableProduct) {
                    // Iterate over all variations (if any) in this variable product to see if the sku/model exists.
                    if (array_key_exists($invitionSKU, $variableProduct["variations"])) {
                        $variationExists = false;
                        foreach ($variableProduct["variations"][$invitionSKU] as $variation) {
                            if (sanitize_title($invitionItem->attributes->device_brand) == $variation["device_brand"] 
                                && sanitize_title($invitionItemModel) == $variation["device_model"] 
                                && sanitize_title($this->woo->createCaseTypeColour($invitionItem)) == $variation["case_type_colour"]
                            ) {
                                $variationExists = true;
                                break;
                            }
                        }
                        if ($variationExists) {
                            continue; // go to next variable product
                        }
                    }

                    // We don't want to generate variations outside the attribute scope of the parent product
                    // Was the brand selected by the user?
                    if (!in_array($invitionItem->attributes->device_brand, $variableProduct['device_brands'])) {
                        continue; // go to next variable product
                    }

                    // Was the model selected by the user?
                    if (!in_array($invitionItemModel, $variableProduct['device_models'])) {
                        continue; // go to next variable product
                    }

                    // Was the case type & colour selected by the user?
                    if (!in_array($this->woo->createCaseTypeColour($invitionItem), $variableProduct['case_type_colour'])) {
                        continue; // go to next variable product
                    }

                    // We don't want to generate variations for discontinued products
                    if ($invitionItem->availability->can_backorder != 1 && $invitionItem->availability->status == 'out-of-stock') {
                        continue; // go to next variable product
                    }

                    // Are there example images? If not, there are probably no rendering layers. Product cannot be rendered
                    if (empty($invitionItem->example_images)) {
                        continue; // go to next variable product
                    }

                    $stockQuantity = property_exists($invitionItem->availability, 'amount_left') ? $invitionItem->availability->amount_left : 0;

                    $actions[$variableProductID]["actions"][] = array(
                        "type"              => "variation-add",
                        "subject"           => $variableProductID,
                        "variable_product"  => $variableProductID,
                        "arguments" => array(
                            "device_brand"      => $invitionItem->attributes->device_brand,
                            "device_model"      => $invitionItemModel,
                            "case_type_colour"  => $this->woo->createCaseTypeColour($invitionItem),
                            "price"             => $this->calculatePrice(
                                $invitionItem->suggested_retail_price, 
                                $invitionItem->price, 
                                'variable'
                            ),
                            "invition_sku"      => $invitionSKU,
                            "stock"             => $stockQuantity,
                            "stock_status"      => $stockQuantity > 0 ? 'instock' : 'outofstock',
                            "backorders"        => ($invitionItem->availability->can_backorder) ? get_option("print_and_ship_allow_backorders", "notify") : "no",
                            "print_size"        => $invitionItem->dimension_width_mm . 'x' . $invitionItem->dimension_height_mm,
                        ),
                        "explain"           => "Add missing variation " .
                                                $invitionItemModel . " " .
                                                $this->woo->createCaseTypeColour($invitionItem) .
                                                " to " . $variableProduct["name"],
                    );
                }
            }
        }

        // Add the product names and type to the actions array
        foreach ($actions as $id => $action) {
            if (!array_key_exists("name", $action)) {
                $actions[$id]["name"] = get_the_title($id);
            }
            if (!array_key_exists("product_type", $action)) {
                $actions[$id]["product_type"] = 'variable';
            }
        }

        return $actions; // TODO remove rendered images that are not used anymore
    }

    /**
     * Returns attributes connected to product based on taxonomy and product ID
     *
     * @param string $taxonomy   The Taxonomy
     * @param int    $product_id The product ID
     * 
     * @return array
     */
    private function _getSelectedAttributes($taxonomy, $product_id)
    {
        $values = array();

        $query = "
            SELECT
                terms.name
            FROM
                " . $this->db->term_relationships . " AS term_relationships
            INNER JOIN " . $this->db->term_taxonomy . " AS term_taxonomy
                ON term_taxonomy.term_taxonomy_id = term_relationships.term_taxonomy_id
            INNER JOIN " . $this->db->terms . " AS terms
                ON terms.term_id = term_taxonomy.term_id
            WHERE
                term_relationships.object_id = " . $product_id . "
                AND term_taxonomy.taxonomy = 'pa_" . $taxonomy . "'";
            
        $results = $this->db->get_results($query);
        if ($this->db->last_error) {
            echo 'You done bad! ' . $this->db->last_error;
        }

        // Make an array from all the objects
        foreach ($results as $result) {
            $values[] = $result->name;
        }

        return($values);
    }


    /**
     * Iterate through given terms and strip terms that are not in the database yet or conflicting terms
     * 
     * @param array  $missingTerms The terms missing in the db
     * @param string $slug         The term slug to compare
     * 
     * @return array The filtered array with only missing terms
     */
    private function _filterMissingTerms($missingTerms, $slug)
    {

        // Check if the model exists in the attributes list before we suggest to add it
        foreach ($missingTerms as $key => $missingTerm) {
            $term = get_term_by('slug', $missingTerm, $slug);

            // Term does not exist.
            if (!is_object($term)) {
                debuglog($missingTerm . " is not in the database");
                unset($missingTerms[$key]);
                continue;
            }

            // Term does exist but Name is not the same
            // This can happen when the slug is the same as a different term.
            // Example: Sony Xperia Z3 <-> Sony Xperia Z3+
            if ($term->name != $missingTerm) {
                debuglog($missingTerm . " has a slug conflict");
                unset($missingTerms[$key]);
                continue;
            }
        }

        return $missingTerms;
    }

    /**
     * Calculates the price of a product, based on SRP and price settings
     *
     * @param float  $suggestedRetailPrice Suggested retail price
     * @param float  $purchasePrice        Purchase price
     * @param string $productType          Type of product
     *
     * @return float $price Price after price rules processing
     */
    public function calculatePrice($suggestedRetailPrice, $purchasePrice, $productType)
    {
        // Validate product type and select all options
        switch ($productType) {
        case 'simple':
        case 'variable':
            $action = get_option("print_and_ship_" . $productType . "_prices_action");
            $amount = get_option("print_and_ship_" . $productType . "_prices_amount");
            $type = get_option("print_and_ship_" . $productType . "_prices_type");
            $base = get_option("print_and_ship_" . $productType . "_prices_base");
            $round = get_option("print_and_ship_" . $productType . "_prices_round");
            break;
        }

        // Calculate based on SRP or purchase price?
        switch ($base) {
        case 'suggested_retail_price':
            $price = $suggestedRetailPrice;
            break;
        
        case 'purchase_price':
            $price = $purchasePrice;
            break;
        
        default:
            $price = $suggestedRetailPrice;
            break;
        }

        // We need 3 variables to complete calculation, are they all set?
        if ($action != "" && $amount != "" && $type != "") {
            switch ($type) {
            // Calculate by percentage
            case 'percent':
                if ($action == 'add') {
                    $price = $price + (($price / 100) * $amount);
                }
                if ($action == 'subtract') {
                    $price = $price - (($price / 100) * $amount);
                }
                break;
            // Calculate by amount
            case 'currency':
                if ($action == 'add') {
                    $price = $price + $amount;
                }
                if ($action == 'subtract') {
                    $price = $price - $amount;
                }
                break;
            }
        }

        // Round prices is also possible without the calculation above
        switch ($round) {
        case 'dot95':
            return $price = round($price + 0.05) - 0.05;
        case 'dot99':
            return $price = round($price + 0.01) - 0.01;
        case 'round':
            return $price = round($price);
        default:
            return $price;
            break;
        }
    }

    /**
     * Executes the requested action
     *
     * @param array $action Required data to process the action requested
     * 
     * @return array result of the action
     */
    public function executeAction($action)
    {

        $action["subject"] = sanitize_text_field($action["subject"]);
        $action["type"] = sanitize_text_field($action["type"]);
        $action["row"] = sanitize_text_field($action["row"]);

        if (isset($action["arguments"])) {
            foreach ($action["arguments"] as $key => $argument) {
                if (gettype($action["arguments"][$key]) == "array") {
                    // The array is multidimensional (no recursive support for now)
                    foreach ($action["arguments"][$key] as $subkey => $subvalue) {
                        $action["arguments"][$key][$subkey] = sanitize_text_field($subvalue);
                    }
                } else {
                    $action["arguments"][$key] = sanitize_text_field($argument);
                }
            }
        }
            
        switch ($action["type"]) {
        // Simple product actions
        case "simple-product-enable-manage-stock":
            return $this->_simpleProductEnableManageStock($action);
            break;

        case "simple-product-change-price":
            return $this->_updateProductPrice($action);
            break;

        // Variable product actions
        case "variable-product-init":
            return $this->_variableProductInit($action);
            break;

        case "variable-product-add-missing-attribute-values":
            return $this->_variableProductAddMissingAttributeValues($action);
            break;

        case "variable-product-disable-manage-stock":
            return $this->_variableProductDisableManageStock($action);
            break;

        case "variable-product-render-image":
            return $this->_variableProductRenderImage($action);
            break;

        // Variation actions
        case "variation-add":
            return $this->_variationAdd($action);
            break;

        case "variation-remove":
            return $this->_variationRemove($action);
            break;

        case "variation-change-price":
            return $this->_updateProductPrice($action);
            break;
        }
    }

    /**
     * Enables manage_stock setting on product
     *
     * @param array $action All the instructions to run the action
     * 
     * @return array Result of the action
     */
    private function _simpleProductEnableManageStock($action)
    {
        if (!update_post_meta($action["subject"], '_manage_stock', 'yes')) {
            return array(
                "success"   => false,
                "message"   => "Could not change setting.",
                "row"       => $action["row"],
            );
        }
        return array(
            "success"   => true,
            "message"   => "Succesfully changed.",
            "row"       => $action["row"],
        );
    }

    /**
     * Connect essential missing attributes to variable product
     *
     * @param array $action All the instructions to run the action
     * 
     * @return array result of the action
     */
    private function _variableProductInit($action)
    {
            
        $product = wc_get_product($action["subject"]);
        $attributeData = array();
            
        // All required attributes to make a valid Printeers combination
        $initAttributes = array(
            "pa_device_brand",
            "pa_device_model",
            "pa_case_type_colour",
        );
            
        // Make sure we have a clean start
        update_post_meta($action["subject"], '_product_attributes', null);
            
        // Walk through list of required attributes and add everything
        foreach ($initAttributes as $initAttribute) {
            $attributeIDs = array();

            $terms = get_terms($initAttribute, array( "taxonomy" => $initAttribute, "hide_empty" => false, ));
            foreach ($terms as $term) {
                $attributeIDs[] = $term->term_id;
            }

            $attributeData[$initAttribute] = array(
                'name' => $initAttribute,
                'value' => '',
                'is_visible' => '0',
                'is_variation' => '1',
                'is_taxonomy' => '1'
            );
                                
            if (!wp_set_object_terms($action["subject"], $attributeIDs, $initAttribute, false)) {
                return array(
                    "success"   => false,
                    "message"   => "Some attributes are missing, please run import first (you can run the import 
                                    by clicking the Save button in your Printeers Print & Ship settings).",
                    "row"       => $action["row"],
                );
            }
        }

        if (!update_post_meta($action["subject"], '_product_attributes', $attributeData)) {
            return array(
                "success"   => false,
                "message"   => "Could not update _product_attributes post meta.",
                "row"       => $action["row"],
            );
        }

        return array(
            "success"   => true,
            "message"   => "Variable product was succesfully initialized. 
                            Refresh this page to see newly generated actions for this product.",
            "row"       => $action["row"],
        );
    }
        
    /**
     * Adds missing attribute values to the Variable product
     *
     * @param array $action All the instructions to run the action
     * 
     * @return array Result of the action
     */
    private function _variableProductAddMissingAttributeValues($action)
    {

        $taxonomy = null;
            
        switch ($action["arguments"]["attribute"]) {
        case "brands":
            $taxonomy = "pa_device_brand";
            break;

        case "models":
            $taxonomy = "pa_device_model";
            break;

        case "case_type_colour":
            $taxonomy = "pa_case_type_colour";
            break;
        }

        if ($taxonomy !== null) {
            foreach ($action["arguments"]["values"] as $actionvalues) {
                $term = get_term_by("name", $actionvalues, $taxonomy, ARRAY_A);
                if (!wp_set_object_terms($action["subject"], $term["term_id"], $taxonomy, true)) {
                    $response = array(
                        "success"   => false,
                        "message"   => "Some attributes are missing, please run import first (you can run the 
                                        import by clicking the Save button in your Printeers Print & Ship settings).",
                        "row"       => $action["row"],
                    );
                
                    return $response;
                }
                $data = array(
                    $taxonomy => array(
                        'name' => $taxonomy,
                        'value' => $term["name"],
                        'is_visible' => '0',
                        'is_variation' => '1',
                        'is_taxonomy' => '1'
                    )
                );
                $_product_attributes = get_post_meta($action["subject"], '_product_attributes', true);
                update_post_meta($product_id, '_product_attributes', array_merge($_product_attributes, $data));
            }
    
            $response = array(
                "success"   => true,
                "message"   => "Succesfully added " . $action["arguments"]["attribute"] . ".",
                "row"       => $action["row"],
            );
        
            return $response;
        }
    }

    /**
     * Disables manage_stock setting on variable product
     *
     * @param array $action All the instructions to run the action
     * 
     * @return array Result of the action
     */
    private function _variableProductDisableManageStock($action)
    {

        if (!update_post_meta($action["subject"], '_manage_stock', 'no')) {
            return array(
                "success"   => false,
                "message"   => "Could not change setting.",
                "row"       => $action["row"],
            );
        }
        return array(
            "success"   => true,
            "message"   => "Succesfully changed.",
            "row"       => $action["row"],
        );
    }

    /**
     * Renders the main image for a variable product
     *
     * @param array $action All the instructions to run the action
     * 
     * @return array Result of the action
     */
    private function _variableProductRenderImage($action)
    {
        if (!$this->woo->renderProductImage($action["subject"], "variable")) {
            return array(
                "success"   => false,
                "message"   => "Something went wrong rendering the image. Please try again later.",
                "row"       => $action["row"],
            );
        }
        return array(
            "success"   => true,
            "message"   => "Image was rendered succesfully",
            "row"       => $action["row"],
        );
    }

    /**
     * Adds a variation to a variable product
     *
     * @param array $action All the instructions to run the action
     * 
     * @return array Result of the action
     */
    private function _variationAdd($action)
    {
        $auto_enable_variations = get_option("print_and_ship_auto_enable_variations");
            
        // Get the Variable product object (parent)
        $product = wc_get_product($action["subject"]);
            
        $variation_post = array(
            'post_title'  => $product->get_title(),
            'post_name'   => 'product-'.$action["subject"].'-variation',
            'post_status' => ($auto_enable_variations == "yes") ? 'publish' : 'private',
            'post_parent' => $action["subject"],
            'post_type'   => 'product_variation',
            'guid'        => $product->get_permalink()
        );

        // Creating the product variation
        $variation_id = wp_insert_post($variation_post, true);

        if (is_wp_error($variation_id)) {
            return array(
                "success"   => false,
                "message"   => "Could not add variation. " . $variation_id->get_error_message(),
                "row"       => $action["row"],
            );
        }

        // Get an instance of the WC_Product_Variation object
        $variation = new \WC_Product_Variation($variation_id);

        // Add device brand attribute to variation
        update_post_meta($variation_id, 'attribute_pa_device_brand', sanitize_title($action["arguments"]["device_brand"]));
            
        // Add models attribute to variation
        update_post_meta($variation_id, 'attribute_pa_device_model', sanitize_title($action["arguments"]["device_model"]));

        // Add case type and colour attribute to variation
        update_post_meta($variation_id, 'attribute_pa_case_type_colour', sanitize_title($action["arguments"]["case_type_colour"]));

        $variation->set_price($action['arguments']['price']);
        $variation->set_regular_price($action['arguments']['price']);
        $variation->set_stock_quantity($action['arguments']['stock']);
        $variation->set_manage_stock(true);
        $variation->set_stock_status();
        $variation->set_backorders($action['arguments']['backorders']);

        $variation->save();

        $fields = array(
            'print_and_ship_sku'            => $action["arguments"]["invition_sku"],
            'print_and_ship_print_size'     => $action["arguments"]["print_size"],
        );
            
        foreach ($fields as $meta_key => $meta_value) {
            update_post_meta($variation_id, $meta_key, $meta_value);
        }

        // Render image for variation?
        if (get_option("print_and_ship_render_image_for_variation") == "yes") {

            // Render a product image
            if (!$this->woo->renderProductImage($variation_id, "variation")) {
                // Delete variation again so it will be listed later when the image can be rendered
                $this->_variationRemove(array("subject" => $variation_id, "row" => 0));
                
                return array(
                    "success"   => false,
                    "message"   => "Something went wrong rendering the image. Please try again later.",
                    "row"       => $action["row"],
                );
            }
        }
        
        return array(
            "success"   => true,
            "message"   => "Variation was added succesfully",
            "row"       => $action["row"],
        );
    }
        
    /**
     * Removes a variation from the database
     *
     * @param array $action All the instructions to run the action
     * 
     * @return array $response The response of the action
     **/
    private function _variationRemove($action)
    {
        if (wp_delete_post($action["subject"], true)) {
            $response = array(
                "success"   => true,
                "message"   => $action["subject"] . " was removed succesfully",
                "row"       => $action["row"],
            );
        } else {
            $response = array(
                "success"   => true,
                "message"   => $action["subject"] . " could not be removed!",
                "row"       => $action["row"],
            );
        }
    
        return $response;
    }

    /**
     * Changes the price of a product
     *
     * @param array $action All the instructions to run the action
     * 
     * @return array Result of the action
     */
    private function _updateProductPrice($action)
    {
        $fields = array(
            '_regular_price'    => $action["arguments"]["price"],
            '_price'            => $action["arguments"]["price"],
        );

        foreach ($fields as $meta_key => $meta_value) {
            update_post_meta($action["subject"], $meta_key, $meta_value);
        }

        return array(
            "success"   => true,
            "message"   => "Price was changed to " . $action["arguments"]["price"],
            "row"       => $action["row"],
        );
    }
}
