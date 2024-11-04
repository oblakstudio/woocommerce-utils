<?php //phpcs:disable Squiz.Commenting.FunctionComment
/**
 * Template_Extender class file.
 *
 * @package WooCommerce Utils
 * @subpackage Core
 */

namespace Oblak\WooCommerce\Core;

use XWC\Template\Customizer_Base;

/**
 * Enables easy extending of WooCommerce templates.
 *
 * @since 1.1.0
 * @since 1.32.0 Switched to using the New Customizer Class.
 *
 * @deprecated 1.32.0 Use `\XWC\Template\Customizer_Base`
 */
abstract class Base_Template_Extender extends Customizer_Base {
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
     * Plugin ID.
     *
     * @var string
     */
    private string $id;

    /**
     * Constructor.
     *
     * Define the plugin ID.
     */
    public function __construct() {
		$this->id = (string) ( $this->id ?? \key( $this->path_tokens ) ?? $this->create_id() );

        parent::__construct();
    }

    /**
     * Create an ID from the class name.
     *
     * @return string
     */
    private function create_id(): string {
        return \strtolower(
            \basename(
                \strtr(
                    static::class,
                    array(
						'\\' => '/',
						'_'  => '-',
                    ),
                ),
            ),
        );
    }

    /**
     * Check if base path is set.
     *
     * @return bool
     */
    private function has_base_path(): bool {
        return \is_string( $this->base_path ) && $this->base_path && \is_dir( $this->base_path );
    }

    /**
     * Check if templates are set.
     *
     * @return bool
     */
    private function has_templates(): bool {
        return \is_array( $this->templates ) && \count( $this->templates ) > 0;
    }

    public function custom_path_tokens( array $tokens ): array {
        if ( ! $this->has_base_path() || ! $this->has_templates() ) {
            return $tokens;
        }

        $this->id = \key( $this->path_tokens ) ?? $this->create_id();

        $tokens[ $this->id ] = array(
            'dir' => $this->base_path,
            'key' => \strtoupper( \str_replace( '-', '_', $this->id ) ),
        );

        return $tokens;
    }

    public function custom_template_files( array $files ): array {
        if ( ! $this->has_base_path() || ! $this->has_templates() ) {
            return $files;
        }

        // Template filenames are the keys of the templates array, values are flags for static templates.
        // For each template we check if it is a static template and set the flag accordingly.
        $files[ $this->id ] = \array_combine(
            $this->templates,
            \array_map(
                fn( $t ) => \in_array( $t, $this->static_templates, true ),
                $this->templates,
            ),
        );

        return $files;
    }
}
