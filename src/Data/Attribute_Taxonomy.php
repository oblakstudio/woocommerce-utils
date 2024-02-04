<?php //phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag, Squiz.Commenting.VariableComment
/**
 * Attribute_Taxonomy class file.
 *
 * @package WooCommerce Utils
 * @subpackage Data
 */

namespace Oblak\WooCommerce\Data;

use Oblak\WooCommerce\Data\Extended_Data;

/**
 * Attribute Taxonomy class.
 *
 * Based on `Extended_Data` class.
 * Provides standardized methods for attribute taxonomy data.
 *
 * !Setters
 *
 * @method void set_label( string $label ) Set the label.
 * @method void set_name( string $name ) Set the name.
 * @method void set_orderby( string $orderby ) Set the orderby.
 * @method void set_public( int $public ) Set the attribute public flag
 * @method void set_type( string $type ) Set the attribute selector type
 *
 * !Getters
 *
 * @method string get_label( string $context='view' ) Get the label.
 * @method string get_name( string $context='view' ) Get the name.
 * @method string get_orderby( string $context='view' ) Get the orderby.
 * @method int    get_public( string $context='view' ) Get the public.
 * @method string get_type( string $context='view' ) Get the type.
 */
class Attribute_Taxonomy extends Extended_Data {
    protected $object_type = 'attribute_taxonomy';

    protected array $core_data = array(
        'label'   => '',
        'name'    => '',
        'orderby' => 'menu_order',
        'public'  => 0,
        'type'    => 'select',
    );

    protected array $unique_keys = array(
        'name',
    );

    /**
     * {@inheritDoc}
     */
    public function __call( $name, $arguments ) {
        $name = \str_replace( 'attribute_', '', $name );

        return parent::__call( $name, $arguments );
    }

    /**
     * Backport for ID getter.
     *
     * @return int
     */
    public function get_attribute_id() {
        return $this->get_id();
    }

    /**
     * Backport for ID setter.
     *
     * @param  int $id ID.
     */
    public function set_attribute_id( $id ) {
        return $this->set_id( $id );
    }

    /**
     * If the context is db, we need to prefix the keys with 'attribute_'
     *
     * @param  string $context Context.
     * @return array           Core data.
     */
    public function get_core_data( string $context = 'view' ): array {
        if ( 'db' === $context ) {
            return \array_combine(
                \array_map(
                    static fn( $k ) => "attribute_$k",
                    $this->get_core_data_keys(),
                ),
                parent::get_core_data( $context ),
            );
        }

        return parent::get_core_data( $context );
    }
}
