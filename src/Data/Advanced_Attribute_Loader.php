<?php
/**
 * Advanced_Attibute_Loader class file
 *
 * @package WooCommerce Utils
 */

namespace Oblak\WooCommerce\Data;

/**
 * Handles loading and creating of the advanced attribute tables
 */
class Advanced_Attribute_Loader {
    /**
     * Constructor
     */
    public function __construct() {
        \add_action( 'before_woocommerce_init', array( $this, 'define_tables' ), 20 );
        \add_action( 'before_woocommerce_init', array( $this, 'maybe_create_tables' ), 30 );

        \add_action( 'woocommerce_data_stores', array( $this, 'register_data_store' ), 0 );

        \add_action( 'woocommerce_attribute_added', array( $this, 'register_attribute_taxonomy' ), 10, 2 );
    }

    /**
     * Register the attribute taxonomy on attribute creation.
     *
     * @param  int                  $attribute_id          The attribute id.
     * @param  array<string, mixed> $data The attribute data.
     */
    public function register_attribute_taxonomy( $attribute_id, $data ) {
        $taxonomy = \wc_attribute_taxonomy_name( $data['attribute_name'] );
        $args     = array(
            array(
                'hierarchical' => true,
                'labels'       => array( 'name' => $data['attribute_label'] ),
                'query_var'    => true,
                'rewrite'      => false,
                'show_ui'      => false,
            ),
        );

        if ( \taxonomy_exists( $taxonomy ) ) {
            return;
        }

        \register_taxonomy(
			$taxonomy,
            // Documented in woocommerce.
			\apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy, array( 'product' ) ),
            // Documented in woocommerce.
			\apply_filters( 'woocommerce_taxonomy_args_' . $taxonomy, $args ),
		);
    }

    /**
     * Defines the tables
     */
    public function define_tables() {
        global $wpdb;

        $tables = array(
            'attribute_taxonomymeta' => 'woocommerce_attribute_taxonomymeta',
        );

        foreach ( $tables as $name => $table ) {
            $wpdb->$name    = $wpdb->prefix . $table;
            $wpdb->tables[] = $table;
        }
    }

    /**
     * Maybe create the tables
     */
    public function maybe_create_tables() {
        if ( 'yes' === \get_option( 'woocommerce_atsd_tables_created', 'no' ) ) {
            return;
        }

        $this->create_tables();
        $this->verify_tables();
    }

    /**
     * Runs the table creation
     */
    private function create_tables() {
        global $wpdb;

        $wpdb->hide_errors();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        \dbDelta( $this->get_schema() );
    }

    /**
     * Verifies if the database tables have been created.
     *
     * @param  bool $execute       Are we executing table creation.
     * @return string[]            List of missing tables.
     */
    private function verify_tables( $execute = false ) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        if ( $execute ) {
            $this->create_tables();
        }

        $queries        = \dbDelta( $this->get_schema(), false );
        $missing_tables = array();

        foreach ( $queries as $table_name => $result ) {
            if ( "Created table {$table_name}" !== $result ) {
                continue;
            }

            $missing_tables[] = $table_name;
        }

        if ( 0 === \count( $missing_tables ) ) {
            \update_option( 'woocommerce_atsd_tables_created', 'yes' );
        }

        return $missing_tables;
    }

    /**
     * Get the table schema.
     */
    protected function get_schema() {
        global $wpdb;

        $collate = '';

        if ( $wpdb->has_cap( 'collation' ) ) {
            $collate = $wpdb->get_charset_collate();
        }

        $tables =
        "CREATE TABLE {$wpdb->attribute_taxonomymeta} (
            meta_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            attribute_taxonomy_id bigint(20) UNSIGNED NOT NULL,
            meta_key varchar(255) DEFAULT NULL,
            meta_value longtext,
            PRIMARY KEY  (meta_id)
        ) {$collate};";

        return $tables;
    }

    /**
     * Registers the data store
     *
     * @param  Array<string, string> $stores List of data stores.
     * @return Array<string, string>         Modified list of data stores.
     */
    public function register_data_store( $stores ) {
        $stores['attribute_taxonomy'] = Attribute_Taxonomy_Data_Store::class;

        return $stores;
    }
}
