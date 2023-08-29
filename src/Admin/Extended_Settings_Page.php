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
    public function __construct( string $id, string $label, array $settings_array ) {
        $this->id    = $id;
        $this->label = $label;

        parent::__construct();

        $this->init_hooks( $settings_array );
    }

    /**
     * Initializes settings hooks.
     *
     * Adds filters to:
     *  * `woocommerce_get_settings_{id}` - to get the extended settings
     *
     * @param array $settings_array Array of settings.
     */
    private function init_hooks( $settings_array ) {
        add_filter( 'woocommerce_get_settings_' . $this->id, array( $this, 'get_extended_settings' ), 20, 2 );
        $this->settings = $this->parse_settings( $settings_array );
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
        $nested   = false;

        foreach ( $settings as $index => $field ) {
            if ( isset( $field['field_name'] ) ) {
                continue;
            }
            $settings[ $index ]['id'] = $this->get_setting_field_id( $this->get_option_key( $section ), $field );

            if ( str_ends_with( $field['id'], '[]' ) || str_ends_with( $field['field_name'] ?? '', '[]' ) ) {
                $nested = true;
            }
        }

        if ( $nested ) {
            add_filter( 'woocommerce_admin_settings_sanitize_option_' . $this->get_option_key( $section ), array( $this, 'sanitize_nested_array' ), 99, 3 );
        }

        /**
         * Filters the formated settings for the plugin
         *
         * @param array $settings Formated settings array
         * @param string $section Section ID
         * @return array Formated settings array
         *
         * @since 2.2.0
         */
        return apply_filters( "woocommerce_formatted_settings_{$this->id}", $settings, $section );
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
         * @param  array $settings Base settings array
         * @return array Settings array
         *
         * @since 1.0.0
         */
        $settings = apply_filters( "woocommerce_raw_settings_{$this->id}", $settings );

        uasort(
            $settings,
            function ( $a, $b ) {
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
    final public function sanitize_nested_array( mixed $value, array $option, $raw_value ) {
        if ( ! str_ends_with( $option['field_name'] ?? $option['id'], '[]' ) ) {
            return $value;
        }

        return array_filter( array_map( $option['sanitize'] ?? 'wc_clean', array_filter( $raw_value ) ) );
    }
}
