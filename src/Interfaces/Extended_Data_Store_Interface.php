<?php //phpcs:disable SlevomatCodingStandard.Classes.SuperfluousInterfaceNaming
/**
 * Extended_Data_Store_Interface interface file.
 *
 * @package    WooCommerce Utils
 * @subpackage Interfaces
 */

namespace Oblak\WooCommerce\Interfaces;

use XWC\Interfaces\Data_Repository;

/**
 * Compatibility interface for extended data stores.
 */
interface Extended_Data_Store_Interface extends Data_Repository {
    /**
     * Get entity count
     *
     * @param  array  $args        Query arguments.
     * @param  string $clause_join SQL join clause. Can be AND or OR.
     * @return int                 Count.
     *
     * @deprecated 1.0.0 Use count() instead.
     */
    public function get_entity_count( $args = array(), $clause_join = 'AND' );

    /**
     * Get entities from the database
     *
     * @param  array  $args        Query arguments.
     * @param  string $clause_join SQL join clause. Can be AND or OR.
     * @return object[]            Array of entities.
     *
     * @deprecated 1.0.0 Use query() instead.
     */
    public function get_entities( $args = array(), $clause_join = 'AND' );

    /**
     * Get a single entity from the database
     *
     * @param  array  $args        Query arguments.
     * @param  string $clause_join SQL join clause. Can be AND or OR.
     * @return int|object|null     Entity ID or object. Null if not found.
     *
     * @deprecated 1.0.0 Use query() instead.
     */
    public function get_entity( $args, $clause_join = 'AND' );
}
