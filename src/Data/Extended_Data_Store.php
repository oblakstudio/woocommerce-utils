<?php //phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
/**
 * Extended_Data_Store class file
 *
 * @package    WooCommerce Utils
 * @subpackage Data
 */

namespace Oblak\WooCommerce\Data;

use Oblak\WooCommerce\Interfaces\Extended_Data_Store_Interface;

/**
 * Extended data store for searching and getting data from the database.
 */
abstract class Extended_Data_Store extends \XWC\Data_Store_CT implements Extended_Data_Store_Interface {
    //phpcs:ignore Squiz.Commenting
    public function get_entities( $args = array(), $clause_join = 'AND' ) {
        \_doing_it_wrong( __METHOD__, 'Use query() instead.', '2.0.0' );
        return $this->query( $args );
    }

    //phpcs:ignore Squiz.Commenting
    public function get_entity_count( $args = array(), $clause_join = 'AND' ) {
        \_doing_it_wrong( __METHOD__, 'Use count() instead.', '2.0.0' );
        return $this->count( $args );
    }

    //phpcs:ignore Squiz.Commenting
    public function get_entity( $args, $clause_join = 'AND' ) {
        \_doing_it_wrong( __METHOD__, 'Use query() instead.', '2.0.0' );
        return null;
    }
}
