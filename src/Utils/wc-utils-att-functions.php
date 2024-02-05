<?php
/**
 * Attribute Taxonomy functions
 *
 * @package WooCommerce Utils
 */

use Oblak\WooCommerce\Data\Attribute_Factory;
use Oblak\WooCommerce\Data\Attribute_Taxonomy;

/**
 * Get an attribute taxonomy object
 *
 * @param  int|WC_Product_Attribute|string|false $attribute_id Attribute ID.
 * @return Attribute_Taxonomy|false
 */
function wc_get_attribute_taxonomy( $attribute_id ): Attribute_Taxonomy|false {
    if (
        ! did_action( 'woocommerce_init' ) ||
        ! did_action( 'woocommerce_after_register_taxonomy' ) ||
        ! did_action( 'woocommerce_after_register_post_type' )
    ) {

		wc_doing_it_wrong(
            __FUNCTION__,
            sprintf(
                /* translators: 1: wc_get_product 2: woocommerce_init 3: woocommerce_after_register_taxonomy 4: woocommerce_after_register_post_type */
                __(
                    '%1$s should not be called before the %2$s, %3$s and %4$s actions have finished.',
                    'woocommerce',
                ),
                'wc_get_attribute_taxonomy',
                'woocommerce_init',
                'woocommerce_after_register_taxonomy',
                'woocommerce_after_register_post_type',
            ),
            '3.9',
        );
		return false;
	}

    return Attribute_Factory::instance()->get_attribute( $attribute_id );
}

/**
 * Get an attribute taxonomy object
 *
 * @param  int $attribute_id Attribute ID.
 */
function wc_get_attribute_taxonomy_object( int $attribute_id ) {
    $classname = Attribute_Factory::get_attribute_classname( $attribute_id );

    return new $classname( $attribute_id );
}
