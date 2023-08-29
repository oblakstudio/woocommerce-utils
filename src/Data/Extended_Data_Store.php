<?php //phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
/**
 * Extended_Data_Store class file
 *
 * @package    WooCommerce Utils
 * @subpackage Data
 */

namespace Oblak\WooCommerce\Data;

use WC_Data;
use WC_Data_Store_WP;
use WC_Object_Data_Store_Interface;

/**
 * Extended data store for searching and getting data from the database.
 */
abstract class Extended_Data_Store extends WC_Data_Store_WP implements WC_Object_Data_Store_Interface {


    /**
     * Data stored in meta keys, but not  considered meta
     *
     * @since 2.0.1
     * @var   array
     */
    protected $internal_meta_keys = array();

    /**
     * Meta keys which must exist
     *
     * @var array
     */
    protected $must_exist_meta_keys = array();

    /**
     * Meta keys to props array
     *
     * @var array
     */
    protected $meta_key_to_props = array();

    /**
     * Boolean props
     *
     * @var array
     */
    protected $boolean_props = array();

    /**
     * Check if we're parsing the first where clause
     *
     * @var bool
     */
    private $first_clause = true;

    /**
     * Get the database table for the data store.
     *
     * @return string Database table name.
     */
    abstract protected function get_table();

    /**
     * Get the entity name
     *
     * @return string
     */
    abstract protected function get_entity_name();

    /**
     * Get searchable columns for the data store
     *
     * @return string[] Array of column names.
     */
    abstract protected function get_searchable_columns();

    /**
     * Reads the entity data from the database.
     *
     * @param WC_Data $the_object Object.
     * @since 2.0.1
     */
    protected function read_entity_data( &$the_object ) {
        $the_object_id = $the_object->get_id();
        $meta_values   = get_metadata( $this->get_entity_name(), $the_object_id );

        $set_props = array();

        foreach ( $this->meta_key_to_props as $meta_key => $prop ) {
            $meta_value         = isset( $meta_values[ $meta_key ][0] ) ? $meta_values[ $meta_key ][0] : null;
            $set_props[ $prop ] = maybe_unserialize( $meta_value ); // get_post_meta only unserializes single values.
        }

        $the_object->set_props( $set_props );
    }

    /**
     * Updates the entity meta data in the database.
     *
     * @param WC_Data $the_object Map Object.
     * @param bool    $force  Force update. Used during create.
     *
     * @since 2.0.1
     */
    protected function update_entity_meta( &$the_object, $force = false ) {
        $props_to_update = $force
            ? $this->meta_key_to_props
            : $this->get_props_to_update( $the_object, $this->meta_key_to_props );

        foreach ( $props_to_update as $meta_key => $prop ) {
            $value = $the_object->{"get_$prop"}( 'edit' );

            if ( in_array( $prop, $this->boolean_props, true ) ) {
                $value = wc_bool_to_string( $value );
            }

            $updated = $this->update_or_delete_entity_meta( $the_object, $meta_key, $value );
        }
    }

    /**
     * Updates or deletes entity meta data
     *
     * @param  WC_Data $the_object     Object.
     * @param  string  $meta_key   Meta key.
     * @param  string  $meta_value Meta value.
     * @return bool                True if updated, false if not.
     */
    protected function update_or_delete_entity_meta( $the_object, $meta_key, $meta_value ) {
        if ( in_array( $meta_value, array( array(), '' ), true ) && ! in_array( $meta_key, $this->must_exist_meta_keys, true ) ) {
            $updated = delete_metadata( $this->get_entity_name(), $the_object->get_id(), $meta_key );
        } else {
            $updated = update_metadata( $this->get_entity_name(), $the_object->get_id(), $meta_key, $meta_value );
        }

        return (bool) $updated;
    }

    /**
     * Get entity count
     *
     * @param  array  $args        Query arguments.
     * @param  string $clause_join SQL join clause. Can be AND or OR.
     * @return int                 Count.
     */
    public function get_entity_count( $args = array(), $clause_join = 'AND' ) {
        global $wpdb;

        $where_clauses = $this->get_sql_where_clauses( $args, $clause_join );

        return ! empty( $where_clauses )
            ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->get_table()} WHERE 1=1{$where_clauses}" )
            : (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->get_table()}" );
    }

    /**
     * Get entities from the database
     *
     * @param  array  $args        Query arguments.
     * @param  string $clause_join SQL join clause. Can be AND or OR.
     * @return object[]            Array of entities.
     */
    public function get_entities( $args = array(), $clause_join = 'AND' ) {
        global $wpdb;

        $defaults = array(
            'per_page' => 20,
            'page'     => 1,
            'orderby'  => 'ID',
            'order'    => 'DESC',
        );
        $fields   = '*';

        $args = wp_parse_args( $args, $defaults );

        $offset        = $args['per_page'] * ( $args['page'] - 1 );
        $where_clauses = $this->get_sql_where_clauses( $args, $clause_join );

        $callback = 1 === $args['per_page'] ? 'get_row' : 'get_results';

        if ( isset( $args['return'] ) ) {
            switch ( $args['return'] ) {
                case 'ids':
                    $callback = 1 === $args['per_page'] ? 'get_var' : 'get_col';
                    $fields   = 'ID';
                    break;
                default:
                    $fields = $args['return'];
                    break;
            }
        }

        return $wpdb->{"$callback"}(
            $wpdb->prepare(
                "SELECT {$fields} FROM {$this->get_table()} WHERE 1=1{$where_clauses} ORDER BY {$args['orderby']} {$args['order']} LIMIT %d, %d",
                $offset,
                $args['per_page']
            )
        );
    }

    /**
     * Get a single entity from the database
     *
     * @param  array  $args        Query arguments.
     * @param  string $clause_join SQL join clause. Can be AND or OR.
     * @return int|object|null     Entity ID or object. Null if not found.
     */
    public function get_entity( $args = array(), $clause_join = 'AND' ) {
        $args = array_merge(
            $args,
            array( 'per_page' => 1 )
        );

        return $this->get_entities( $args, $clause_join );
    }

    /**
     * Get the SQL WHERE clauses for a query.
     *
     * @param  array  $args        Query arguments.
     * @param  string $clause_join SQL join clause. Can be AND or OR.
     * @return string              SQL WHERE clauses.
     */
    protected function get_sql_where_clauses( $args, $clause_join ) {
        if ( empty( $args ) ) {
            return '';
        }

        $args    = array_intersect_key(
            $args,
            array_merge(
                array( 'ID' => 1010110 ),
                array_flip( $this->get_searchable_columns() )
            )
        );
        $clauses = '';

        $this->first_clause = true;

        foreach ( $args as $column => $value ) {
            // If value is 'all' or 'all' is the array of values - skip.
            if ( 'all' === $value || ( is_array( $value ) && in_array( 'all', $value, true ) ) ) {
                continue;
            }

            $escaped = '';
            $clause  = $this->first_clause ? 'AND' : $clause_join;

            $this->first_clause = false;

            $escaped = $this->get_where_clause_value( $value );

            $clauses .= " {$clause} {$column} {$escaped}";
        }

        return $clauses;
    }

    /**
     * Get the SQL WHERE clause value depending on the type
     *
     * @param string|array $value Value.
     */
    protected function get_where_clause_value( $value ) {
        global $wpdb;

        // Handle value as array.
        if ( is_array( $value ) ) {
            $escaped = implode( "','", array_map( 'esc_sql', $value ) );
            return "IN ('{$escaped}')";
        }

        // Value is a string, let's handle wildcards.
        $left_wildcard  = strpos( $value, '%' ) === 0 ? '%' : '';
        $right_wildcard = strrpos( $value, '%' ) === strlen( $value ) - 1 ? '%' : '';

        $value = trim( $value, '%' );

        if ( $left_wildcard || $right_wildcard ) {
            $value = $wpdb->esc_like( $value );
        }

        $escaped_like = $left_wildcard . $value . $right_wildcard;

        return "LIKE '{$escaped_like}'";
    }
}
