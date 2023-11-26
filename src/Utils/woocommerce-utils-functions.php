<?php
/**
 * Utility functions
 *
 * @package WooCommerce Utils
 * @subpackage Utils
 */

/**
 * Get the Attribute Taxonomy Data Store.
 *
 * @return Attribute_Taxonomy_Data_Store
 */
function wc_atds() {
    return WC_Data_Store::load( 'attribute_taxonomy' );
}
