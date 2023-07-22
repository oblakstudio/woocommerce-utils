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
abstract class Product_Type_Extender {

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
     *  * name:  Product type name.
     *  * class: Product type class name.
     *  * tabs:  Array of tabs to add to the product type.
     *
     * @return array
     */
    abstract protected function get_product_types();

    /**
     * Get the product options array
     *
     * Product option is an array keyed by product option slug, with the following properties:
     *  * name: Product option name,
     *  * for: Array of product type slugs for which the option is available.
     *  * label: Label for the option.
     *  * description: Description for the option.
     *  * default: Default value for the option. Can be `yes` or `no`, or a boolean.
     *  * is_prop: Whether the option is a product property, or a meta data
     *
     * @return array
     */
    abstract protected function get_product_options();

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

        foreach ( $this->get_product_types() as $slug => $type ) {
            if ( in_array( $slug, array_keys( $types ), true ) ) {
                continue;
            }

            $new_types[ $slug ] = $type['name'];

            if ( ! get_term_by( 'slug', $slug, 'product_type' ) ) {
                wp_insert_term( $slug, 'product_type' );
            }
        }

        return array_merge( $types, $new_types );
    }

    /**
     * Adds the custom product options checkboxes
     *
     * @param  array $options Product options.
     * @return array          Modified product options.
     */
    public function add_custom_product_options( $options ) {
        $new_options = array();

        foreach ( $this->get_product_options() as $slug => $option ) {
            if ( in_array( $slug, array_keys( $options ), true ) ) {
                continue;
            }

            $new_options[ $slug ] = array(
                'id'            => "_{$slug}",
                'wrapper_class' => implode(
                    ' ',
                    array_map(
                        function( $type ) {
                            return "show_if_{$type}"; },
                        $option['for']
                    )
                ),
                'label'         => $option['label'],
                'description'   => $option['description'],
                'default'       => wc_bool_to_string( $option['default'] ),
            );

        }

        return array_merge( $options, $new_options );
    }

    /**
     * Modifies product classnames.
     *
     * @param  string $classname    Product classname.
     * @param  string $product_type Product type.
     * @return string               Modified classname.
     */
    public function modify_product_classnames( $classname, $product_type ) {
        if ( ! isset( $this->get_product_types()[ $product_type ] ) ) {
            return $classname;
        }

        return $this->get_product_types()[ $product_type ]['class'];
    }

    /**
     * Add product type data tabs
     *
     * @param  array $tabs Product data tabs.
     * @return array       Modified product data tabs.
     */
    public function add_product_type_data_tabs( $tabs ) {
        foreach ( array_merge( $this->get_product_types(), $this->get_product_options() ) as $slug => $type ) {
            $type_tabs = $type['tabs'] ?? array();

            foreach ( $type_tabs as $tab_to_add ) {
                $tab_id          = "{$slug}_{$tab_to_add['id']}";
                $tabs[ $tab_id ] = array(
                    'label'    => $tab_to_add['label'],
                    'target'   => "{$tab_id}-options",
                    'class'    => "show_if_{$slug}",
                    'priority' => $tab_to_add['priority'],
                );
            }
        }

        return $tabs;
    }

    /**
     * Adds the custom product type data panels
     */
    public function add_product_type_data_panels() {
        foreach ( array_merge( $this->get_product_types(), $this->get_product_options() ) as $slug => $type ) {
            $type_tabs = $type['tabs'] ?? array();
            foreach ( $type_tabs as $tab_to_add ) {
                $tab_id = "{$slug}_{$tab_to_add['id']}";
                ?>
                <div id="<?php echo esc_attr( "{$tab_id}-options" ); ?>" class="panel woocommerce_options_panel" style="display:none">
                    <?php
                    /**
                     * Display the product type / option data fields
                     *
                     * @param string $tab_suffix The tab suffix.
                     * @since 1.0.0
                     */
                    do_action( "woocommerce_product_options_{$slug}", $tab_to_add['id'] );
                    ?>
                </div>
                <?php
            }
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

            if ( $option['is_prop'] ?? false ) {
                ( $option_status );
                $product->{"set_{$slug}"}( $option_status );
            } else {
                $product->add_meta_data( $slug, $option_status );
            }
        }

        $product->save();

    }

    /**
     * Adds custom css needed for the custom product tab icons to work
     */
    public function add_custom_product_css() {
        if ( empty( $this->get_product_types() ) || empty( $this->get_product_options() ) ) {
            return;
        }

        echo '<' . 'style type="text/css">'; //phpcs:ignore
        foreach ( array_merge( $this->get_product_types(), $this->get_product_options() ) as $slug => $type ) {
            if ( empty( $type['tabs'] ) ) {
                continue;
            }

            foreach ( $type['tabs'] as $tab ) {
                if ( empty( $tab['icon'] ?? '' ) ) {
                    continue;
                }

                printf(
                    '#woocommerce-product-data ul.wc-tabs li.%s_%s_options a::before { content: "%s"; }%s',
                    esc_attr( $slug ),
                    esc_attr( $tab['id'] ),
                    esc_attr( $tab['icon'] ),
                    "\n",
                );
            }
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
                'groups' => $type['show_groups'] ?? array(),
                'tabs'   => $type['show_tabs'] ?? array(),
            );
        }

        ?>
        <script type="text/javascript" id="pte-pt-js">
            var utilAdditionalTypes = <?php echo wp_json_encode( $opt_groups ); ?>;

            jQuery(document).ready(() => {
                for (const[productType, optData] of Object.entries(utilAdditionalTypes)) {
                    if (optData.groups) {
                        optData.groups.forEach((group) => {
                            jQuery(`.options_group.${group}`).addClass(`show_if_${productType}`).show();
                        });
                    }

                    if (optData.tabs) {
                        optData.tabs.forEach((tab) => {
                            if (['general', 'inventory'].includes(tab)) {
                                jQuery(`.${tab}_options`).addClass(`show_if_${productType}`).addClass('show_if_simple').show();

                            } else {
                                jQuery(`.${tab}_options`).addClass(`show_if_${productType}`).show();
                            }
                        });
                    }
                }
            });
        </script>
        <?php
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
