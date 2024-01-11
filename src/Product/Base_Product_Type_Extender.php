<?php
/**
 * Product_Type_Extender class file.
 *
 * @package WooCommerce Utils
 * @subpackage Product
 */

namespace Oblak\WooCommerce\Product;

/**
 * Enables easy extension of product types.
 *
 * @since 1.1.0
 */
abstract class Base_Product_Type_Extender {
    /**
     * Types to remove from the product type selector
     *
     * @var string[]
     */
    protected array $types_to_remove = array();

    /**
     * Options to remove from the options selector
     *
     * @var string[]
     */
    protected array $options_to_remove = array();

    /**
     * Class constructor
     */
    public function __construct() {
        add_filter( 'product_type_selector', array( $this, 'add_custom_product_types' ) );
        add_filter( 'product_type_options', array( $this, 'add_custom_product_options' ), 99, 1 );
        add_filter( 'woocommerce_product_class', array( $this, 'modify_product_classnames' ), 99, 2 );
        add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_type_data_tabs' ), 999, 1 );
        add_filter( 'woocommerce_product_data_panels', array( $this, 'add_product_type_data_panels' ), 999 );
        add_action( 'woocommerce_admin_process_product_object', array( $this, 'set_custom_options_status' ), 99, 1 );
        add_action( 'admin_print_styles', array( $this, 'add_custom_product_css' ), 90 );
        add_action( 'admin_footer', array( $this, 'add_custom_product_types_js' ), 90, 1 );
        add_action( 'admin_footer', array( $this, 'add_custom_product_options_js' ), 99, 1 );
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
     * Checks if we're on the product edit page
     *
     * @return bool
     */
    private function is_product_edit_page() {
        global $pagenow, $typenow;

        return in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) && 'product' === $typenow;
    }

    /**
     * Adds custom product types to the product type selector.
     *
     * @param  array $types Product types.
     * @return array        Modified product types.
     */
    public function add_custom_product_types( $types ) {
        $new_types = array();
        $to_set    = array_filter(
            $this->get_product_types(),
            fn ( $slug ) => ! in_array( $slug, array_keys( $types ), true ) && 'variation' !== $slug,
            ARRAY_FILTER_USE_KEY
        );

        foreach ( $to_set as $slug => $type ) {
            $new_types[ $slug ] = $type['name'];

            if ( ! get_term_by( 'slug', $slug, 'product_type' ) ) {
                wp_insert_term( $slug, 'product_type' );
            }
        }

        return wp_array_diff_assoc( array_merge( $types, $new_types ), $this->types_to_remove );
    }

    /**
     * Modifies product classnames.
     *
     * @param  string $classname    Product classname.
     * @param  string $product_type Product type.
     * @return string               Modified classname.
     */
    public function modify_product_classnames( $classname, $product_type ) {
        return $this->get_product_types()[ $product_type ]['class'] ?? $classname;
    }

    /**
     * Adds the custom product options checkboxes
     *
     * @param  array $options Product options.
     * @return array          Modified product options.
     */
    public function add_custom_product_options( $options ) {
        $options = array_merge(
            $options,
            wp_array_flatmap(
                array_values( $this->get_product_options() ),
                fn( $opt ) => array(
                    $opt['key'] => array(
                        'id'            => "_{$opt['key']}",
                        'wrapper_class' => implode( ' ', array_map( fn( $t ) => "show_if_{$t}", $opt['for'] ) ),
                        'label'         => $opt['label'] ?? $opt['key'],
                        'description'   => $opt['description'] ?? '',
                        'default'       => wc_bool_to_string( $opt['default'] ?? false ),
                    ),
                ),
            )
        );

        return wp_array_diff_assoc( $options, $this->options_to_remove );
    }

    /**
     * Add product type data tabs
     *
     * @param  array $tabs Product data tabs.
     * @return array       Modified product data tabs.
     */
    public function add_product_type_data_tabs( $tabs ) {
        return array_merge(
            $tabs,
            wp_array_flatmap(
                $this->get_product_tabs(),
                fn( $tab ) => array(
					( $tab['key'] ?? $tab['id'] ) => array(
						'label'    => $tab['label'],
						'target'   => "{$tab['id']}_product_data",
						'class'    => array_map( fn( $t ) => "show_if_{$t}", $tab['for'] ),
						'priority' => $tab['priority'] ?? 100,
					),
                ),
            )
        );
    }

    /**
     * Adds the custom product type data panels
     */
    public function add_product_type_data_panels() {
        foreach ( wp_list_pluck( $this->get_product_tabs(), 'id' ) as $tab ) {
            printf(
                '<div id="%s_product_data" class="panel woocommerce_options_panel" style="display: none;">',
                esc_attr( $tab )
            );

            /**
             * Display the product type / option data fields
             *
             * @since 1.0.0
             */
            do_action( "woocommerce_product_options_{$tab}" );

            echo '</div>';
        }
    }

    /**
     * Sets the custom options status
     *
     * @param  \WC_Product $product Product object.
     */
    public function set_custom_options_status( $product ) {
        foreach ( $this->get_product_options() as $slug => $option ) {
            $option_status = wc_bool_to_string( 'on' === wc_clean( wp_unslash( $_POST[ "_{$slug}" ] ?? 'no' ) ) ); //phpcs:ignore WordPress.Security.NonceVerification.Missing

            if ( ( $option['is_prop'] ?? false ) || is_callable( array( $product, "set_{$slug}" ) ) ) {
                $product->{"set_{$slug}"}( $option_status );
            } else {
                $product->update_meta_data( $slug, $option_status );
            }
        }

        $product->save();
    }

    /**
     * Adds custom css needed for the custom product tab icons to work
     */
    public function add_custom_product_css() {
        if ( empty( $this->get_product_types() ) && empty( $this->get_product_options() ) ) {
            return;
        }

        echo '<' . 'style type="text/css">'; //phpcs:ignore
        foreach ( $this->get_product_tabs() as $tab ) {
            if ( ! isset( $tab['icon'] ) ) {
                continue;
            }

            $icon_font = str_starts_with( $tab['icon'], 'woo' ) ? 'woocommerce' : 'Dashicons';
            $icon_str  = str_replace( 'woo:', '', $tab['icon'] );

            printf(
                '#woocommerce-product-data ul.wc-tabs li.%1$s_options a::before { content: "%2$s"; font-family: %3$s, sans-serif; }%4$s',
                esc_attr( $tab['key'] ?? $tab['id'] ),
                esc_attr( $icon_str ),
                esc_attr( $icon_font ),
                "\n",
            );

        }

        echo '</style>';
    }

    /**
     * Adds custom javascript needed for the custom product types to work
     */
    public function add_custom_product_types_js() {
        if ( ! $this->is_product_edit_page() || empty( $this->get_product_types() ) ) {
            return;
        }

        $opt_groups = array();

        foreach ( $this->get_product_types() as $slug => $type ) {
            $opt_groups[ $slug ] = array(
                'groups'   => $type['show_groups'] ?? array(),
                'tabs'     => $type['show_tabs'] ?? array(),
                'inherits' => $type['inherits'] ?? array(),
            );
        }

        $opt_groups = wp_json_encode( $opt_groups );
        echo '<' . 'script>'; //phpcs:ignore Generic.Strings.UnnecessaryStringConcat.Found
        echo "var utilAdditionalTypes = {$opt_groups};\n"; //phpcs:ignore

        echo <<<'JS'
        jQuery(document).ready(($) => {
            for (const[productType, optData] of Object.entries(utilAdditionalTypes)) {
                optData.groups.forEach((group) => {
                    $(`.options_group.${group}`).addClass(`show_if_${productType}`).show();
                });

                optData.tabs.forEach((tab) => {
                    if (['general', 'inventory'].includes(tab)) {
                        jQuery(`.${tab}_options`).addClass(`show_if_${productType}`).addClass('show_if_simple').show();

                    } else {
                        jQuery(`.${tab}_options`).addClass(`show_if_${productType}`).show();
                    }
                });

                optData.inherits.forEach((parentType) => {
                    $(`.show_if_${parentType}`).addClass(`show_if_${productType}`).show();
                    $(`.hide_if_${parentType}`).addClass(`hide_if_${productType}`).hide();
                })
            }
        })
        JS;

        echo '</script>';
    }

    /**
     * Adds the javascript needed for the custom options selectors to work
     */
    public function add_custom_product_options_js() {
        if ( ! $this->is_product_edit_page() && empty( $this->get_product_options() ) ) {
            return;
        }
        ?>
        <script type="text/javascript" id="pte-po-js">
            var utilAdditionalOpts = <?php echo wp_json_encode( array_keys( $this->get_product_options() ) ); ?>;
            jQuery(document).ready(() => {

                utilAdditionalOpts.forEach((opt) => {
                    jQuery(`input#_${opt}`).on('change', (e) => {
                        var checked = jQuery(e.target).prop('checked');

                        if (checked) {
                            jQuery(`.show_if_${opt}`).show();
                            jQuery(`.hide_if_${opt}`).hide();
                        } else {
                            jQuery(`.show_if_${opt}`).hide();
                            jQuery(`.hide_if_${opt}`).show();
                        }
                    });

                    if (jQuery(`input#_${opt}`).prop('checked')) {
                        jQuery(`.show_if_${opt}`).show();
                        jQuery(`.hide_if_${opt}`).hide();
                    } else {
                        jQuery(`.show_if_${opt}`).hide();
                        jQuery(`.hide_if_${opt}`).show();
                    }
                });
            });
        </script>
        <?php
    }
}
