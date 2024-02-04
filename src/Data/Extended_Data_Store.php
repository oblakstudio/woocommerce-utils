<?php //phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
/**
 * Extended_Data_Store class file
 *
 * @package    WooCommerce Utils
 * @subpackage Data
 */

namespace Oblak\WooCommerce\Data;

use Exception;
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
     * Data stored in meta keys, but not considered meta
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
     * Array of updated props
     *
     * @var string[]
     */
	protected array $updated_props = array();

    /**
     * Lookup table data keys
     *
     * @var string[]
     */
	protected array $lookup_data_keys = array();

    /**
     * Clause for joining WHERE
     *
     * @var string
     */
    protected $clause_join = '';

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
     * {@inheritDoc}
     *
     * @param Extended_Data $data Data object.
     */
	public function create( &$data ) {
		global $wpdb;

		if ( $data->has_created_prop() && ! $data->get_date_created( 'edit' ) ) {
			$data->set_date_created( \time() );
		}

		$wpdb->insert( $this->get_table(), $data->get_core_data( 'db' ) );

		if ( ! $wpdb->insert_id ) {
			return;
		}

		$data->set_id( $wpdb->insert_id );

		$this->update_entity_meta( $data, true );
		$this->update_terms( $data, true );
		$this->handle_updated_props( $data );
		$this->clear_caches( $data );

        $data->save_meta_data();
        $data->apply_changes();

        // Documented in `WC_Data_Store_WP`.
        \do_action( 'woocommerce_new_' . $this->get_entity_name(), $data->get_id(), $data );
	}

    /**
     * {@inheritDoc}
     *
     * @param  Extended_Data $data Package object.
     *
     * @throws \Exception If invalid Entity.
     */
    public function read( &$data ) {
        $data->set_defaults();

        $data_row = $this->get_entities(
            array(
				$this->object_id_field => $data->get_id(),
				'per_page'             => 1,
			),
        );

        if ( ! $data->get_id() || ! $data_row ) {
            throw new \Exception( 'Invalid Entity' );
        }

        $data->set_props( (array) $data_row );

        $this->read_entity_data( $data );
        $this->read_extra_data( $data );

        $data->set_object_read( true );

        // Documented in `WC_Data_Store_WP`.
        \do_action( "woocommerce_{$this->get_entity_name()}_read", $data->get_id() );
    }

    /**
     * {@inheritDoc}
     *
     * @param  Extended_Data $data Data Object.
     */
    public function update( &$data ) {
        global $wpdb;

        $changes = $data->get_changes();
        $ch_keys = \array_intersect( \array_keys( $changes ), $data->get_core_data_keys() );

        $core_data = \count( $ch_keys ) > 0
            ? \array_merge( $data->get_core_data( 'db' ), $this->get_date_modified_prop( $data ) )
            : array();

        if ( \count( $core_data ) > 0 ) {
            $wpdb->update(
                $this->get_table(),
                $core_data,
                array( $this->object_id_field => $data->get_id() ),
            );
        }

        $this->update_entity_meta( $data );
        $this->update_terms( $data );
        $this->handle_updated_props( $data );
        $this->clear_caches( $data );

        $data->save_meta_data();
        $data->apply_changes();

        // Documented in `WC_Data_Store_WP`.
        \do_action( 'woocommerce_update_' . $this->get_entity_name(), $data->get_id(), $data );
    }

    /**
     * Get the date modified core data (if it exists).
     *
     * @param  Extended_Data $data    Data object.
     * @return array                  Array of props.
     */
    protected function get_date_modified_prop( Extended_Data &$data ): array {
        $props = array();

        if ( ! $data->has_modified_prop() ) {
            return $props;
        }

        $props['date_modified'] = $data->get_date_modified( 'db' ) ?? \current_time( 'mysql' );

        if ( $data->has_modified_prop( true ) ) {
            $props['date_modified_gmt'] = $data->get_date_modified_gmt( 'db' ) ?? \current_time( 'mysql', 1 );
        }

        return $props;
    }

    /**
     * {@inheritDoc}
     *
     * @param  Extended_Data $data Data object.
     * @param  array         $args Array of args to pass to delete method.
     */
    public function delete( &$data, $args = array() ) {
        global $wpdb;

        $id = $data->get_id();

        $args = \wp_parse_args( $args, array( 'force' => false ) );

        if ( ! $id ) {
            return;
        }

        if ( ! $args['force_delete'] ) {
            return;
        }

        //phpcs:ignore WooCommerce.Commenting
        \do_action( 'woocommerce_before_delete_' . $this->get_entity_name(), $id, $args );

        $wpdb->delete( $this->get_table(), array( $this->object_id_field => $data->get_id() ) );
        $data->set_id( 0 );

        $this->delete_entity_meta( $id );

        //phpcs:ignore WooCommerce.Commenting
        \do_action( 'woocommerce_delete_' . $this->get_entity_name(), $id, $data, $args );
    }

    /**
     * Get the lookup table for the data store.
     *
     * @return string|null Database table name.
     */
    protected function get_lookup_table(): ?string {
        return null;
    }

    /**
     * Get Object ID field.
     *
     * @return string
     */
    public function get_object_id_field(): string {
        return $this->object_id_field;
    }

    /**
     * Format the meta key to props array.
     *
     * Used as a compatibility layer for older versions of WooCommerce Utils.
     *
     * @return array<string, string>
     *
     * @throws \Exception If invalid meta key to prop mapping.
     *
     * phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
     */
	protected function format_key_to_props(): array {
		$formatted = array();

		foreach ( $this->meta_key_to_props as $maybe_meta_key => $maybe_prop ) {
			if ( \is_int( $maybe_meta_key ) && \is_string( $maybe_prop ) ) {
				$formatted[ $maybe_prop ] = \ltrim( $maybe_prop, '_' );
			} elseif ( \is_string( $maybe_meta_key ) && \is_string( $maybe_prop ) ) {
				$formatted[ $maybe_meta_key ] = $maybe_prop;
			} else {
				throw new \Exception( 'Invalid meta key to prop mapping' );
			}
		}

		return $formatted;

        // phpcs:enable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
	}

    /**
	 * Get and store terms from a taxonomy.
	 *
	 * @param  WC_Data|integer $obj      WC_Data object or object ID.
	 * @param  string          $taxonomy Taxonomy name e.g. product_cat.
	 * @return array of terms
	 */
	protected function get_term_ids( $obj, $taxonomy ) {
		$object_id = \is_numeric( $obj ) ? $obj : $obj->get_id();
		$terms     = \wp_get_object_terms( $object_id, $taxonomy );
		if ( false === $terms || \is_wp_error( $terms ) ) {
			return array();
		}
		return \wp_list_pluck( $terms, 'term_id' );
	}

    /**
     * Reads the entity data from the database.
     *
     * @param Extended_Data $data Object.
     * @since 2.0.1
     */
    protected function read_entity_data( &$data ) {
        $object_id   = $data->get_id();
        $meta_values = \get_metadata( $this->get_entity_name(), $object_id );

        $set_props = array();

        foreach ( $this->format_key_to_props() as $meta_key => $prop ) {
            $meta_value         = $meta_values[ $meta_key ][0] ?? null;
            $set_props[ $prop ] = \maybe_unserialize(
                $meta_value,
            ); // get_post_meta only unserializes single values.
        }

        foreach ( $this->term_props as $term_prop => $taxonomy ) {
            $set_props[ $term_prop ] = $this->get_term_ids( $object_id, $taxonomy );
        }

        $data->set_props( $set_props );
    }

    /**
     * Reads the extra entity Data
     *
     * @param  Extended_Data $data Data object.
     */
    protected function read_extra_data( &$data ) {
        foreach ( $data->get_extra_data_keys() as $key ) {
            try {
                $data->{"set_{$key}"}(
                    \get_metadata(
                        $this->get_entity_name(),
                        $data->get_id(),
                        '_' . $key,
                        true,
                    )
                );
            } catch ( \Exception ) {
                continue;
            }
        }
    }

    /**
     * Update the entity meta in the DB.
     *
     * We first update the meta data defined as prop data, then we update the extra data.
     * Extra data is data that is not considered meta, but is stored in the meta table.
     *
     * @param  Extended_Data $data  Data object.
     * @param  bool          $force Force update.
     */
	protected function update_entity_meta( &$data, $force = false ) {
		$this->update_meta_data( $data, $force );

        $props = \array_filter(
            \array_intersect(
                $data->get_extra_data_keys(),
                \array_keys( $data->get_changes() ),
            ),
            fn( $p ) => ! \in_array( $p, $this->updated_props, true )
        );

        if ( \count( $props ) <= 0 ) {
            return;
        }

        $this->update_extra_data( $data, $props );
	}

    /**
     * Update meta data.
     *
     * @param  Extended_Data $data  Data object.
     * @param  bool          $force Force update.
     */
    protected function update_meta_data( Extended_Data &$data, bool $force ) {
        $meta_key_to_props = $this->format_key_to_props();
		$props_to_update   = ! $force
            ? $this->get_props_to_update( $data, $meta_key_to_props, $this->meta_type )
            : $meta_key_to_props;

		foreach ( $props_to_update as $meta_key => $prop ) {
            $this->update_meta_prop( $data, $meta_key, $prop );
		}
    }

    /**
     * Update extra data.
     *
     * @param  Extended_Data $data  Data object.
     * @param  string[]      $props Extra data props.
     */
    protected function update_extra_data( Extended_Data &$data, array $props ) {
		foreach ( $props as $prop ) {
			$meta_key = '_' . $prop;

			try {
                $this->update_meta_prop( $data, $meta_key, $prop );
			} catch ( \Exception ) {
				continue;
			}
		}
    }

    /**
     * Updates a meta prop.
     *
     * @param  Extended_Data $data     Data object.
     * @param  string        $meta_key Meta key.
     * @param  string        $prop     Property.
     */
    protected function update_meta_prop( &$data, $meta_key, $prop ) {
        $value = $data->{"get_$prop"}( 'db' );
        $value = \is_string( $value ) ? \wp_slash( $value ) : $value;

        if ( ! $this->update_or_delete_entity_meta( $data, $meta_key, $value ) ) {
            return;
        }

        $this->updated_props[] = $prop;
    }

    /**
     * For all stored terms in all taxonomies save them to the DB.
     *
     * @param WC_Data $the_object Data Object.
     * @param bool    $force  Force update. Used during create.
     */
    protected function update_terms( &$the_object, $force = false ) {
        $props   = $this->term_props;
        $changes = \array_intersect_key( $the_object->get_changes(), $props );

        // If we don't have term props or there are no changes, and we're not forcing an update, return.
        if ( 0 === \count( $props ) || ( \count( $changes ) && ! $force ) ) {
            return;
        }

        foreach ( $props as $term_prop => $taxonomy ) {
            $terms = \wc_string_to_array( $the_object->{"get_$term_prop"}( 'edit' ) );

            \wp_set_object_terms( $the_object->get_id(), $terms, $taxonomy, false );
        }
    }

    /**
     * Handle updated meta props after updating entity meta.
     *
     * @param  Extended_Data $data Data object.
     */
	protected function handle_updated_props( &$data ) {
		if ( \array_intersect( $this->updated_props, $this->lookup_data_keys ) && ! \is_null(
            $this->get_lookup_table(),
        ) ) {
            $this->update_lookup_table( $data->get_id(), $this->get_lookup_table() );
		}

        $this->updated_props = array();
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
        $updated = \in_array( $meta_value, array( array(), '' ), true ) && ! \in_array(
            $meta_key,
            $this->must_exist_meta_keys,
            true,
        ) ? \delete_metadata( $this->get_entity_name(), $the_object->get_id(), $meta_key ) : \update_metadata(
            $this->get_entity_name(),
            $the_object->get_id(),
            $meta_key,
            $meta_value,
        );

        return (bool) $updated;
    }

    /**
     * Delete metadata for a given object.
     *
     * @param  int $object_id Object ID.
     */
    protected function delete_entity_meta( int $object_id ) {
        if ( ! \_get_meta_table( $this->get_entity_name() ) ) {
            return;
        }

        $GLOBALS['wpdb']->delete(
            \_get_meta_table( $this->get_entity_name() ),
            array(
                "{$this->meta_type}_id" => $object_id,
            ),
        );
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

        return '' !== $where_clauses
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

        $this->clause_join = '';

        $defaults = array(
            'order'    => 'DESC',
            'orderby'  => $this->object_id_field,
            'page'     => 1,
            'per_page' => 20,
            'return'   => '*',
        );
        $args     = \wp_parse_args( $args, $defaults );

        if ( 'ids' === $args['return'] ) {
            $args['return'] = $this->object_id_field;
        }

        $offset        = $args['per_page'] * ( $args['page'] - 1 );
        $where_clauses = $this->get_sql_where_clauses( $args, $clause_join );
        $callback      = $this->get_wpdb_callback( $args['return'], $args['per_page'] );

        return $wpdb->{"$callback"}(
            $wpdb->prepare(
                "SELECT {$args['return']} FROM {$this->get_table()} WHERE 1=1 AND ({$where_clauses}) ORDER BY {$args['orderby']} {$args['order']} LIMIT %d, %d",
                $offset,
                $args['per_page'],
            )
        );
    }

    /**
     * Get the wpdb callback based on the fields required and per_page
     *
     * @param  string $fields   Fields to return.
     * @param  int    $per_page Number of items per page.
     * @return string           wpdb callback.
     */
    protected function get_wpdb_callback( $fields, int $per_page ) {
        $has_comma       = \str_contains( ',', $fields );
        $paged_callbacks = match ( $per_page ) {
            1       => array( 'get_row', 'get_var' ),
            default => array( 'get_results', 'get_col' ),
        };

        return match ( true ) {
            $has_comma      => $paged_callbacks[0],
            '*' === $fields => $paged_callbacks[0],
            default         => $paged_callbacks[1],
        };
    }

    /**
     * Checks if a value is unique in the database
     *
     * @param  string $prop_or_column Property or column name.
     * @param  mixed  $value          Value to check.
     * @param  int    $current_id     Current ID.
     * @return bool
     */
    public function is_value_unique( string $prop_or_column, $value, int $current_id ): bool {
        global $wpdb;

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                <<<'SQL'
                    SELECT COUNT(*) FROM %i
                    WHERE %i = %s AND %i != %d;
                SQL,
                $this->get_table(),
                $prop_or_column,
                $value,
                $this->object_id_field,
                $current_id,
            ),
        );

        return 0 === $count;
    }

    /**
     * Get a single entity from the database
     *
     * @param  array  $args        Query arguments.
     * @param  string $clause_join SQL join clause. Can be AND or OR.
     * @return int|object|null     Entity ID or object. Null if not found.
     */
    public function get_entity( $args = array(), $clause_join = 'AND' ) {
        $args = \array_merge( $args, array( 'per_page' => 1 ) );

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
        $clauses = array();
        $args    = $this->get_where_clauses_args( $args );

        if ( 0 === \count( $args ) ) {
            return '';
        }

        foreach ( $args as $column => $value ) {
            // If value is 'all' or 'all' is the array of values - skip.
            if ( \in_array( 'all', \wc_string_to_array( $value ), true ) ) {
                continue;
            }
            $clauses[] = \sprintf(
                '%1$s %2$s %3$s',
                $this->clause_join,
                $column,
                $this->get_where_clause_value( $value ),
            );

            $this->clause_join = $clause_join;
        }

        return \implode( ' ', $clauses );
    }

    /**
     * Get valid argument keys for the SQL WHERE clause
     *
     * Valid arguments are the object_id_field and the searchable columns.
     *
     * @param  array<string, mixed> $args Arguments.
     * @return array<string, mixed>       Valid arguments.
     */
    protected function get_where_clauses_args( array $args ): array {
        return \wp_array_slice_assoc(
            $args,
            \array_merge(
                array( $this->object_id_field ),
                $this->get_searchable_columns(),
            ),
        );
    }

    /**
     * Get the SQL WHERE clause value depending on the type
     *
     * @param string|array $value Value.
     */
    protected function get_where_clause_value( $value ) {
        global $wpdb;

        // Handle value as array.
        if ( \is_array( $value ) ) {
            $escaped = \implode( "','", \array_map( 'esc_sql', $value ) );
            return "IN ('{$escaped}')";
        }

        // Value is a string, let's handle wildcards.
        $left_wildcard  = 0 === \strpos( $value, '%' ) ? '%' : '';
        $right_wildcard = \strrpos( $value, '%' ) === \strlen( $value ) - 1 ? '%' : '';

        $value = \trim( \esc_sql( $value ), '%' );

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
		if ( ! \in_array( $this->meta_type, array( 'post', 'user', 'comment', 'term' ), true ) ) {
			$table .= $this->table_prefix ?? 'woocommerce_';
		}

		$table          .= $this->meta_type . 'meta';
		$object_id_field = $this->meta_type . '_id';

		// Figure out our field names.
		if ( 'user' === $this->meta_type ) {
			$meta_id_field = 'umeta_id';
			$table         = $wpdb->usermeta;
		}

		if ( '' !== $this->object_id_field_for_meta ) {
			$object_id_field = $this->object_id_field_for_meta;
		}

		return array(
            'meta_id_field'   => $meta_id_field,
            'object_id_field' => $object_id_field,
            'table'           => $table,
		);
	}

    /**
     * Clear caches.
     *
     * @param  Extended_Data $data Data object.
     */
	protected function clear_caches( &$data ) {
		\WC_Cache_Helper::invalidate_cache_group( $this->get_entity_name() . '_' . $data->get_id() );
	}
}
