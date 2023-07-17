<?php
/**
 * Template_Extender class file.
 *
 * @package WooCommerce Utils
 * @subpackage Core
 */

namespace Oblak\WooCommerce\Core;

/**
 * Enables easy extending of WooCommerce templates.
 *
 * @since 1.1.0
 */
abstract class Template_Extender {

    /**
     * Base path
     *
     * @var string
     */
    protected $base_path = '';

    /**
     * Template filename array
     *
     * @var array
     */
    protected $templates = array();

    /**
     * Template filenames that are static
     *
     * Static templates, are templates that cannot be overriden in the theme.
     *
     * @var array
     */
    protected $static_templates = array();

    /**
     * Path tokens array
     *
     * @var array
     */
    protected $path_tokens = array();

    /**
     * Class constructor
     */
    public function __construct() {
        if ( '' === $this->base_path ) {
            return;
        }
        add_filter( 'woocommerce_get_path_define_tokens', array( $this, 'add_path_define_tokens' ), 99, 1 );
        add_filter( 'woocommerce_locate_template', array( $this, 'modify_template_path' ), 99, 2 );
    }

    /**
     * Adds custom path define tokens to the existing WooCommerce tokens.
     *
     * @param  array $tokens Existing path define tokens.
     * @return array         Modified array of tokens.
     */
    public function add_path_define_tokens( $tokens ) {
        return array_merge( $tokens, $this->path_tokens );
    }

    /**
     * Locate a template and return the path for inclusion.
     *
     * This is the load order:
     *
     * yourtheme/$template_path/$template_name
     * yourtheme/$template_name
     * yourplugin/$template_path/$template_name
     *
     * @param  string $template      Full template path.
     * @param  string $template_name Template name.
     * @return string                Modified template path.
     */
    public function modify_template_path( $template, $template_name ) {
        // If not one of our templates, bail out.
        if ( ! in_array( $template_name, $this->templates, true ) ) {
            return $template;
        }

        // If template is static, set default path to plugin.
        if ( in_array( $template_name, $this->static_templates, true ) ) {
            return trailingslashit( $this->base_path ) . $template_name;
        }

        // Try to locate the template file in the theme.
        $template = locate_template(
            array(
                trailingslashit( WC()->template_path() ) . $template_name,
                $template_name,
            )
        );

        // If template is found within the theme return it, otherwise return the plugin template file.
        return $template
            ? $template
            : trailingslashit( $this->base_path ) . $template_name;

    }

}
