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
     * By default, the table prefix is null.
     * This means that it will be set by default to the base `WC_Data_Store_WP` prefix which is `woocommerce_`
     *
     * Change this if you want to have a different prefix for your table. For instance `wc_`
     *
     * @var string|null
     */
    protected ?string $table_prefix = null;

    /**
     * Field name used for object IDs
     *
     * @var string
     */
    protected $object_id_field = 'ID';

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
     * Term props
     *
     * @var array
     */
    protected $term_props = array();
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
	 * Get and store terms from a taxonomy.
	 *
	 * @param  WC_Data|integer $obj      WC_Data object or object ID.
	 * @param  string          $taxonomy Taxonomy name e.g. product_cat.
	 * @return array of terms
	 */
	protected function get_term_ids( $obj, $taxonomy ) {
		if ( is_numeric( $obj ) ) {
			$object_id = $obj;
		} else {
			$object_id = $obj->get_id();
		}
		$terms = wp_get_object_terms( $object_id, $taxonomy );
		if ( false === $terms || is_wp_error( $terms ) ) {
			return array();
		}
		return wp_list_pluck( $terms, 'term_id' );
	}

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

        foreach ( $this->term_props as $term_prop => $taxonomy ) {
            $set_props[ $term_prop ] = $this->get_term_ids( $the_object, $taxonomy );
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
     * For all stored terms in all taxonomies save them to the DB.
     *
     * @param WC_Data $the_object Data Object.
     * @param bool    $force  Force update. Used during create.
     */
    protected function update_terms( &$the_object, $force = false ) {
        $changes = $the_object->get_changes();
        $props   = $this->term_props;

        // If we don't have term props or there are no changes, and we're not forcing an update, return.
        if ( empty( $props ) || ( empty( array_intersect_key( $changes, $props ) ) && ! $force ) ) {
            return;
        }

        foreach ( $props as $term_prop => $taxonomy ) {
            $terms = $the_object->{"get_$term_prop"}( 'edit' );

            if ( empty( $terms ) ) {
                continue;
            }

            $terms = is_array( $terms ) ? $terms : array( $terms );

            wp_set_object_terms( $the_object->get_id(), $terms, $taxonomy, false );

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
            'orderby'  => $this->object_id_field,
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
                    $fields   = $this->object_id_field;
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

        $value = trim( esc_sql( $value ), '%' );

        if ( $left_wildcard || $right_wildcard ) {
            $value = $wpdb->esc_like( $value );
        }

        $escaped_like = $left_wildcard . $value . $right_wildcard;

        return "LIKE '{$escaped_like}'";
    }

    /**
	 * Table structure is slightly different between meta types, this function will return what we need to know.
	 *
	 * @since  3.0.0
	 * @return array Array elements: table, object_id_field, meta_id_field
	 */
	protected function get_db_info() {
		global $wpdb;

		$meta_id_field = 'meta_id'; // for some reason users calls this umeta_id so we need to track this as well.
		$table         = $wpdb->prefix;

		// If we are dealing with a type of metadata that is not a core type, the table should be prefixed.
		if ( ! in_array( $this->meta_type, array( 'post', 'user', 'comment', 'term' ), true ) ) {
			$table .= $this->table_prefix ?? 'woocommerce_';
		}

		$table          .= $this->meta_type . 'meta';
		$object_id_field = $this->meta_type . '_id';

		// Figure out our field names.
		if ( 'user' === $this->meta_type ) {
			$meta_id_field = 'umeta_id';
			$table         = $wpdb->usermeta;
		}

		if ( ! empty( $this->object_id_field_for_meta ) ) {
			$object_id_field = $this->object_id_field_for_meta;
		}

		return array(
			'table'           => $table,
			'object_id_field' => $object_id_field,
			'meta_id_field'   => $meta_id_field,
		);
	}
}
