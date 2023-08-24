<?php
/**
 * Extended_Settings_Page class file
 *
 * @package WooCommerce Sync Service
 * @subpackage WooCommerce
 */

namespace Oblak\WooCommerce\Admin;

use WC_Settings_Page;

/**
 * Extended settings page
 */
abstract class Extended_Settings_Page extends WC_Settings_Page {

    /**
     * Array of extended settings
     *
     * @var array
     */
    protected array $settings;

    /**
     * Class constructor
     *
     * @param string $id             Settings page ID.
     * @param string $label          Settings page label.
     * @param array  $settings_array Array of settings.
     */
    public function __construct(
        protected $id,
        protected $label,
        array $settings_array,
    ) {
        parent::__construct();

        $this->settings = $this->parse_settings( $settings_array );

        $this->init_hooks();
    }

    /**
     * Initializes settings hooks.
     *
     * Adds filters to:
     *  * `woocommerce_get_settings_{id}` - to get the extended settings
     *  * `woocommerce_admin_settings_sanitize_option_{option_key}` - to sanitize nested arrays
     */
    private function init_hooks() {
        add_filter( 'woocommerce_get_settings_' . $this->id, array( $this, 'get_extended_settings' ), 20, 2 );

        foreach ( $this->settings as $section => $data ) {
            /**
             * Fuck my life and call me sunshine.
             *
             * Due to crazy array nesting, we have the following flow:
             *  1. We first columnize the `custom_attributes` field from the fields list
             *  2. We apply null filter
             *  3. We then get only the values, and merge it so we get a flat map
             *  4. Then we have the keys.
             *  5. We then unique them for good measure
             *
             *  @var string[] $multiples Array containing all of the custom attribute_keys
             */
            $multiples = array_unique( array_keys( array_merge( ...array_values( array_filter( array_column( $data['fields'], 'custom_attributes' ) ) ) ) ) );

            if ( in_array( 'multiple', $multiples, true ) ) {
                add_filter( 'woocommerce_admin_settings_sanitize_option_' . $this->get_option_key( $section ), array( $this, 'sanitize_nested_array' ), 99, 3 );
            }
        }
    }

    /**
     * Parses the raw settings array
     *
     * @param  array $settings Raw settings array.
     * @return array           Parsed settings array.
     */
    final protected function parse_settings( array $settings ): array {
        /**
         * Filter the settings array
         *
         * @since 4.0.0
         * @param array $base_settings Base settings array
         */
        $settings = apply_filters( 'woocommerce_raw_settings_' . $this->id, $settings );

        uasort(
            $settings,
            function( $a, $b ) {
                return $a['priority'] - $b['priority'];
            }
        );

        return $settings;
    }

    /**
     * Get the option key for a section
     *
     * @param  string $section Section ID.
     * @return string          Option key.
     */
    final protected function get_option_key( string $section ): string {
        return '' !== $section ? "{$this->id}_settings_{$section}" : "{$this->id}_settings_general";
    }

    /**
     * Get the formatted setting field ID.
     *
     * @param  string $option_key Option key.
     * @param  array  $field      Field array.
     * @return string             Formatted setting field ID.
     */
    final protected function get_setting_field_id( string $option_key, array $field ): string {
        $is_multiselect = 'select' === $field['type'] && array_key_exists( 'multiple', ( $field['custom_attributes'] ?? array() ) );
        return sprintf(
            '%s[%s]%s',
            $option_key,
            $field['id'],
            $is_multiselect ? '[]' : ''
        );
    }

    /**
     * Get the settings fields
     *
     * @param  array  $settings Settings array.
     * @param  string $section  Section ID.
     * @return array            Settings fields array.
     */
    public function get_extended_settings( array $settings, string $section ): array {
        $settings = $this->settings[ $section ]['fields'];

        foreach ( $settings as $index => $field ) {
            $settings[ $index ]['id'] = $this->get_setting_field_id( $this->get_option_key( $section ), $field );
        }

        /**
         * Filters the formated settings for the plugin
         *
         * @since 2.2.0
         * @param array $settings Formated settings array
         */
        return apply_filters( 'woocommerce_formatted_settings_' . $this->id, $settings, $section );
    }

    /**
     * {@inheritDoc}
     */
    final public function get_own_sections() {
        foreach ( $this->settings as $section => $data ) {
            if ( ! $data['enabled'] ) {
                continue;
            }
            $sections[ $section ] = $data['section_name'];
        }

        return $sections;
    }

    /**
     * Santizes the double nested arrays, since WooCommerce doesn't support them
     *
     * @param  mixed $value     Sanitized value.
     * @param  array $option    Option array.
     * @param  mixed $raw_value Raw value.
     */
    final public function sanitize_nested_array( mixed $value, array $option, mixed $raw_value ) {
        if (
            ! str_ends_with( $option['id'], '[]' ) ||
            ( isset( $option['field_name'] ) && ! str_ends_with( $option['field_name'], '[]' ) )
            ) {
            return $value;
        }

        return array_filter( array_map( $option['sanitize'] ?? 'wc_clean', array_filter( $raw_value ) ) );
    }
}
