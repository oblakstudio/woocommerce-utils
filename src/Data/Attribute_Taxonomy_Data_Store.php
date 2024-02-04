<?php //phpcs:disable Squiz.Commenting.VariableComment.MissingVar
/**
 * Attribute_Taxonomy_Data_Store class file.
 *
 * @package WooCommerce Utils
 * @subpackage Data
 */

namespace Oblak\WooCommerce\Data;

use Oblak\WooCommerce\Data\Extended_Data_Store;

/**
 * Data store class for attribute taxonomies.
 */
class Attribute_Taxonomy_Data_Store extends Extended_Data_Store {
    /**
     * {@inheritDoc}
     */
    protected $object_id_field = 'attribute_id';

    /**
     * {@inheritDoc}
     */
    protected function get_table() {
        return $GLOBALS['wpdb']->prefix . 'woocommerce_attribute_taxonomies';
    }

    /**
     * {@inheritDoc}
     */
    protected function get_entity_name() {
        return 'attribute_taxonomy';
    }

    /**
     * {@inheritDoc}
     */
    protected function get_searchable_columns() {
        return array(
            'name',
            'label',
            'type',
            'public',
        );
    }

    /**
     * Reformats data for insert and update.
     *
     * Functions `wc_create_attribute` and `wc_update_attribute` expect data in a different format:
     * * `label` => `name`
     * * `name` => `slug`
     *
     * So we remap the keys, and remove the label key.
     *
     * @param  Attribute_Taxonomy $data Data object.
     * @return array
     */
    protected function reformat_data( Attribute_Taxonomy &$data ): array {
        $args = \array_merge(
            array( 'id' => $data->get_id() ),
            $data->get_core_data(),
        );

        $args = \array_merge(
            array(
                'name' => $args['label'],
                'slug' => $args['name'],
            ),
            \wp_array_diff_assoc( $args, array( 'label' ) ),
        );

        return $args;
    }

    /**
     * Creates a new attribute.
     *
     * We override this method to handle the WooCommerce way of creating attributes.
     *
     * @param  Attribute_Taxonomy $data The attribute to create.
     */
    public function create( &$data ) {
        $args = $this->reformat_data( $data );
        $id   = \wc_create_attribute( $args );

        if ( ! $id || \is_wp_error( $id ) ) {
            return;
        }

        $data->set_id( $id );

        $this->update_entity_meta( $data, true );
        $this->handle_updated_props( $data );
        $this->clear_caches( $data );

        $data->save_meta_data();
        $data->apply_changes();
    }

    /**
     * Updates an attribute.
     *
     * We override this method to handle the WooCommerce way of updating attributes.
     *
     * @param  Attribute_Taxonomy $data The attribute to update.
     */
    public function update( &$data ) {
        $changes = $data->get_changes();
        $ch_keys = \array_intersect( \array_keys( $changes ), $data->get_core_data_keys() );

        // Do not run attribute update if only meta data has changed.
        if ( $ch_keys ) {
            $args = \wp_array_diff_assoc( $this->reformat_data( $data ), array( 'id' ) );
            $ret  = \wc_update_attribute( $data->get_id(), $args );

            if ( ! $ret || \is_wp_error( $ret ) ) {
                return;
            }
        }

        $this->update_entity_meta( $data );
        $this->handle_updated_props( $data );
        $this->clear_caches( $data );

        $data->save_meta_data();
        $data->apply_changes();
    }

    /**
     * Deletes an attribute.
     *
     * We override this method to handle the WooCommerce way of deleting attributes.
     *
     * @param  Attribute_Taxonomy $data The attribute to delete.
     * @param  array              $args Additional arguments.
     */
    public function delete( &$data, $args = array() ) {
        if ( ! \wc_delete_attribute( $data->get_id() ) ) {
            return;
        }

        $this->delete_entity_meta( $data->get_id() );
    }

    /**
     * Reformat the where clause arguments.
     *
     * Each column in the table is prefixed with 'attribute_' so we need to prefix the keys in the where clause.
     *
     * @param  array<string, mixed> $args The where clause arguments.
     * @return array<string, mixed>       The reformatted where clause arguments.
     */
    protected function get_where_clauses_args( array $args ): array {
        $args = parent::get_where_clauses_args( $args );

        return \array_combine(
            \array_map(
                static fn( $k ) => \str_starts_with( $k, 'attribute_' ) ? $k : "attribute_$k",
                \array_keys( $args ),
            ),
            \array_values( $args ),
        );
    }

    /**
     * Checks if a value is unique.
     *
     * Each column in the table is prefixed with 'attribute_' so we need to prefix the keys in the where clause.
     *
     * @param  string $prop_or_column The property or column name.
     * @param  mixed  $value          The value to check.
     * @param  int    $current_id     The current ID.
     * @return bool                   Whether the value is unique.
     */
    public function is_value_unique( string $prop_or_column, $value, int $current_id ): bool {
        $prop_or_column = \str_starts_with(
            $prop_or_column,
            'attribute_',
        ) ? $prop_or_column : 'attribute_' . $prop_or_column;

        return parent::is_value_unique( $prop_or_column, $value, $current_id );
    }

    /**
     * Gets an attribute by its name.
     *
     * @param  string $taxonomy_name   The attribute name.
     * @param  string $ret             The return type.
     * @return int|Attribute_Taxonomy|null
     */
    public function get_by_taxonomy_name( string $taxonomy_name, string $ret = 'object' ): int|Attribute_Taxonomy|null {
        $attribute_name = \str_replace( 'pa_', '', $taxonomy_name );

        $attribute_id = (int) $this->get_entities(
            array(
                'name'     => $attribute_name,
                'per_page' => 1,
                'return'   => 'ids',
			),
        );

        return match ( true ) {
            0 === $attribute_id && 'object' === $ret => null,
            'object' === $ret => new Attribute_Taxonomy( $attribute_id ),
            default => $attribute_id,
        };
    }
}
