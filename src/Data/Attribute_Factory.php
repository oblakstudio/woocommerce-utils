<?php
/**
 * Attribute_Factory class file.
 *
 * @package WooCommerce Utils
 * @subpackage Data
 */

namespace Oblak\WooCommerce\Data;

use Oblak\WP\Traits\Singleton;
use WC_Data_Store;
use WC_Product_Attribute;

/**
 * Standardized methods for attribute taxonomy data.
 */
class Attribute_Factory {
    use Singleton;

    /**
     * Get an Attribute Taxonomy object
     *
     * @param  string|int|WC_Product_Attribute|false $attribute_id Attribute ID, Attribute object or Attribute name / slug.
     * @return Attribute_Taxonomy|false
     */
    public function get_attribute( $attribute_id = false ): Attribute_Taxonomy|false {
        $attribute_id = $this->get_attribute_id( $attribute_id );

        if ( ! $attribute_id ) {
            return false;
        }

        $classname = self::get_attribute_classname( $attribute_id );

        try {
            return new $classname( $attribute_id );
        } catch ( \Exception ) {
            return false;
        }
    }

    /**
     * Get the attribute class name
     *
     * @param  int $attribute_id Attribute ID.
     * @return class-string      Class name
     */
    public static function get_attribute_classname( int $attribute_id ): string {
        /**
         * Filters the attribute class name
         *
         * @param  class-string $classname    Class name.
         * @param  int          $attribute_id Attribute ID.
         * @return class-string
         *
         * @since 1.30.0
         */
        $classname = \apply_filters( 'woocommerce_attribute_class', Attribute_Taxonomy::class, $attribute_id );

        if ( ! $classname || ! \class_exists( $classname ) ) {
            $classname = Attribute_Taxonomy::class;
        }

        return $classname;
    }

    /**
     * Determines the attribute ID
     *
     * @param  string|int|WC_Product_Attribute|false $attribute_id Attribute ID, Attribute object or Attribute name / slug.
     * @return int|false
     */
    protected function get_attribute_id( string|int|WC_Product_Attribute|false $attribute_id ): int|false {
        return match ( true ) {
            default                                       => false,
            \is_numeric( $attribute_id )                  => (int) $attribute_id,
            $attribute_id instanceof WC_Product_Attribute => $attribute_id->get_id(),
            $attribute_id instanceof Attribute_Taxonomy   => $attribute_id->get_id(),
            \is_string( $attribute_id )                   => $this->get_attribute_by_string( $attribute_id ),

        };
    }

    /**
     * Get the attribute ID by name or label
     *
     * @param  string $ident Attribute name / slug.
     * @return int
     */
    public function get_attribute_by_string( string $ident ): int {
        $id = \wc_attribute_taxonomy_id_by_name( $ident );

        if ( $id ) {
            return $id;
        }

        return (int) WC_Data_Store::load( 'attribute_taxonomy' )->get_entities(
            array(
                'label'    => $ident,
                'per_page' => 1,
                'return'   => 'ids',
            ),
            'OR',
        );
    }
}
