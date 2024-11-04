<?php
/**
 * Product_Type_Extender class file.
 *
 * @package WooCommerce Utils
 * @subpackage Product
 */

namespace Oblak\WooCommerce\Product;

use XWC\Product\Customizer_Base;

/**
 * Enables easy extension of product types.
 *
 * @since 1.1.0
 * @since 1.32.0 Switched to using the New Customizer Class.
 *
 * @deprecated 1.32.0 Use `\XWC\Product\Customizer_Base`
 */
class Base_Product_Type_Extender extends Customizer_Base {
    /**
     * Types to remove from the product type selector
     *
     * @var array<int,string>     */
    protected array $types_to_remove = array();

    /**
     * Options to remove from the options selector
     *
     * @var array<int,string>
     */
    protected array $options_to_remove = array();

    /**
     * Product types to remove
     *
     * @var array<int,string>
     */
    private static array $rm_types = array();

    /**
     * Product options to remove
     *
     * @var array<int,string>
     */
    private static array $rm_opts = array();

    /**
     * Constructor
     *
     * Adds the types and options to remove to the static arrays.
     */
    public function __construct() {
        self::$rm_types = $this->init_rm( self::$rm_types, $this->types_to_remove );
        self::$rm_opts  = $this->init_rm( self::$rm_opts, $this->options_to_remove );

        parent::__construct();
    }

    /**
     * Initializes the array of items to remove
     *
     * @param  array<int,string> $summed Summed array of items to remove.
     * @param  array<int,string> $to_rm  Array of items to remove.
     * @return array<int,string>
     */
    private function init_rm( array $summed, array $to_rm ): array {
        return \array_values( \array_unique( \array_merge( $summed, $to_rm ) ) );
    }

    /**
     * Initializes the customizer framework
     *
     * Adds the legacy actions to the product data tabs.
     *
     * @return bool
     */
    protected function init(): bool {
        if ( \is_admin() ) {
			\add_action( 'woocommerce_product_write_panel_tabs', array( $this, 'add_panel_actions' ), 0, 0 );
            \add_filter( 'product_type_selector', array( $this, 'remove_types' ), 9999 );
			\add_filter( 'product_type_options', array( $this, 'remove_opts' ), 9999 );
        }

        return parent::init();
    }

    /**
     * Adds the panel actions to the product data tabs.
     */
    public function add_panel_actions() {
        $legacy = 'woocommerce_product_options';
        $modern = 'xwc_product_options';

        foreach ( \array_keys( static::$tabs ) as $key ) {
            //phpcs:ignore WooCommerce.Commenting
            \add_action( "{$modern}_{$key}", static fn() => \do_action( "{$legacy}_{$key}" ) );
        }
    }

    /**
     * Removes the product types from the selector
     *
     * @param array<string> $types Product types.
     *
     * @return array<string>
     */
    public function remove_types( array $types ): array {
        return \xwp_array_diff_assoc( $types, ...self::$rm_types );
    }

    /**
     * Removes the product options from the selector
     *
     * @param array $opts Product options.
     *
     * @return array
     */
    public function remove_opts( array $opts ): array {
        return \xwp_array_diff_assoc( $opts, ...self::$rm_opts );
    }

    /**
     * Returns the product types array
     *
     * Product type is an array keyed by product type slug, with the following properties:
     *  - **name**:     Product type name.
     *  - **class**:    Product type class name.
     *  - **tabs**:     Array of tabs to add to the product type.
     *  - **inherits**: Array of product type slugs from which to inherit the tabs and option visibility
     *
     * @return array
     */
    protected function get_product_types(): array {
        return array();
    }

    /**
     * Adds the custom product types from legacy method.
     *
     * @param  array $types Product types.
     * @return array
     */
    public function custom_product_types( array $types ): array {
        return \array_merge( $types, $this->get_product_types() );
    }

    /**
     * Get the product options array
     *
     * Product option is an array keyed by product option slug, with the following properties:
     *  - **key**:         Product option key.
     *  - **label**:       Label for the option.
     *  - **description**: Description for the option.
     *  - **for**:         Array of product type slugs for which the option is available.
     *  - **default**:     Default value for the option. Can be `yes` or `no`, or a boolean.
     *  - **is_prop**:     Whether the option is a product property, or a meta data
     *
     * @return array<string, array<string, mixed>>
     */
    protected function get_product_options(): array {
        return array();
    }

    /**
     * Adds the custom product options from legacy method.
     *
     * @param  array $opts Product options.
     * @return array
     */
    public function custom_product_opts( array $opts ): array {
        return \array_merge( $opts, $this->get_product_options() );
    }

    /**
     * Get the product data tabs array
     *
     * Product tab is an array of arrays with the following properties:
     *  - **key**:   Product tab key.
     *  - **id**:    Product tab id.
     *  - **label**: Label for the tab.
     *  - **for**:   Array of product type slugs for which the tab is available.
     *  - **icon**:  Icon for the tab. Can be a Dashicon or a WooCommerce icon.
     *
     * @return array<array, array<string, mixed>>
     */
    protected function get_product_tabs(): array {
        return array();
    }

    /**
     * Adds the custom product tabs from legacy method.
     *
     * @param  array $tabs Product tabs.
     * @return array
     */
    public function custom_product_tabs( array $tabs ): array {
        foreach ( $this->get_product_tabs() as $tab ) {
            $tab['prio'] ??= $tab['priority'] ?? 21;

            $tabs[] = \xwp_array_diff_assoc( $tab, 'priority' );
        }

        return $tabs;
    }
}
