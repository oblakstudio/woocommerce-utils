<?php
/**
 * Singleton class trait file
 *
 * @package WooCommerce Utils
 */

namespace Oblak\WooCommerce\Misc;

/**
 * Singleton trait.
 */
trait Singleton {
    /**
     * Singleton instance
     *
     * @var static
     */
    protected static $instance = null;

    /**
     * Returns the singleton instance of this class.
     *
     * @return static
     */
    final public static function instance() {
        if ( is_null( static::$instance ) ) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
	 * Prevent cloning.
	 */
    private function __clone() {
    }

    /**
	 * Prevent unserializing.
	 */
	final public function __wakeup() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'woocommerce' ), '4.6' );
		die();
	}
}
