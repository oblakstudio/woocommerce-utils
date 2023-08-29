<?php
/**
 * Admin View: Page - EDSL
 *
 * @package WooCommerce Utils
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;

$active_type = wc_clean( wp_unslash( $_REQUEST['active'] ?? 'all' ) ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended

$this->table->process_bulk_actions();
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <?php
    /**
     * Allows adding actions to the heading of the page.
     *
     * @since 2.0.0
     */
    do_action( "esdl_{$this->entity}_heading_actions" );
    ?>
    <hr class="wp-header-end">

    <?php
    /**
     * Allows adding actions to the heading of the page.
     *
     * @since 2.0.0
     */
    do_action( "esdl_{$this->entity}_after_heading" );
    ?>

    <?php settings_errors(); ?>
    <div id="poststuff">
        <div id="post-body" class="metabox-holder columns-1">
            <div id="post-body-content">
                <div class="meta-box-sortables ui-sortable">
                    <form method="GET">
                        <input type="hidden" name="post_type" value="product">
                        <input type="hidden" name="page" value="woosync-supplier-map">
                        <?php
                        $this->table->views();
                        ?>
                        <input
                            type="hidden"
                            name="active"
                            class="post_status_page"
                            value="<?php echo esc_attr( $active_type ); ?>"
                        >
                        <?php
                        $this->table->prepare_items();
                        $this->table->display();
                        ?>
                    </form>
                    <?php
                    if ( $this->inline_edit ) {
                        $this->table->inline_edit();
                    }
                    ?>
                    <div id="ajax-response"></div>
                    <div class="clear"></div>
                </div>
            </div>
        </div>
        <br class="clear">
    </div>
</div>
