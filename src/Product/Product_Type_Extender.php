<?php
/**
 * Product_Type_Extender class file.
 *
 * @package WooCommerce Utils
 * @subpackage Product
 */

namespace Oblak\WooCommerce\Product;

/**
 * Enables easy extension of product types.
 *
 * @since 1.1.0
 */
abstract class Product_Type_Extender {

    /**
     * Product types array
     *
     * Product type is an array keyed by product type slug, with the following properties:
     *  * name:  Product type name.
     *  * class: Product type class name.
     *
     * @var array
     */
    protected $product_types = array();

    /**
     * Class constructor
     */
    public function __construct() {
        add_filter( 'product_type_selector', array( $this, 'add_custom_product_types' ) );
        add_filter( 'woocommerce_product_class', array( $this, 'modify_product_classnames' ), 99, 2 );
    }

    /**
     * Adds custom product types to the product type selector.
     *
     * @param  array $types Product types.
     * @return array        Modified product types.
     */
    public function add_custom_product_types( $types ) {
        $new_types = array();

        foreach ( $this->product_types as $slug => $type ) {
            if ( in_array( $slug, array_keys( $types ), true ) ) {
                continue;
            }

            $new_types[ $slug ] = $type['name'];

            if ( ! get_term_by( 'slug', $slug, 'product_type' ) ) {
                wp_insert_term( $slug, 'product_type' );
            }
        }

        return array_merge( $types, $new_types );
    }

    /**
     * Modifies product classnames.
     *
     * @param  string $classname    Product classname.
     * @param  string $product_type Product type.
     * @return string               Modified classname.
     */
    public function modify_product_classnames( $classname, $product_type ) {
        if ( ! isset( $this->product_types[ $product_type ] ) ) {
            return $classname;
        }

        return $this->product_types[ $product_type ]['class'];
    }
}
