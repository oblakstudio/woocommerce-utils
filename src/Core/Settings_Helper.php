<?php
/**
 * Settings_Helper trait file
 *
 * @package WooCommerce Sync Service
 * @subpackage Utils
 */

namespace Oblak\WooCommerce\Core;

trait Settings_Helper {

    /**
     * Array of settings
     *
     * @var array
     */
    protected array $settings;

    /**
     * Get the settings array from the database
     *
     * @param  string $prefix        The settings prefix.
     * @param  array  $raw_settings  The settings fields.
     * @param  mixed  $default_value The default value for the settings.
     * @return array                 The settings array.
     */
    protected function load_settings( string $prefix, array $raw_settings, $default_value ): array {
        $defaults   = $this->get_defaults( $raw_settings, $default_value );
        $settings   = array();
        $option_key = $prefix . '_settings_';

        foreach ( $defaults as $section => $default_values ) {
            $section_settings     = wp_parse_args(
                get_option( $option_key . $section, array() ),
                $default_values
            );
            $settings[ $section ] = array();

            foreach ( $section_settings as $raw_key => $raw_value ) {
                $value = in_array( $raw_value, array( 'yes', 'no' ), true ) ? ( 'yes' === $raw_value ) : $raw_value;

                if ( str_contains( $raw_key, '-' ) ) {
                    $keys = explode( '-', $raw_key );

                    if ( ! isset( $settings[ $section ][ $keys[0] ] ) ) {
                        $settings[ $section ][ $keys[0] ] = array();
                    }

                    $settings[ $section ][ $keys[0] ][ $keys[1] ] = $value;

                    continue;
                }

                $settings[ $section ][ $raw_key ] = $value;
            }
        }

        return $settings;
    }

    /**
     * Iterate over the settings array and get the default values
     *
     * @param  array $settings      The settings fields.
     * @param  mixed $default_value The default value for the settings.
     * @return array                The default values.
     */
    protected function get_defaults( array $settings, $default_value = false ): array {
        $defaults = array();
        foreach ( $settings as $section => $data ) {
            $section_data = array();

            foreach ( $data['fields'] as $field ) {
                if ( in_array( $field['type'], array( 'title', 'sectionend', 'info' ), true ) ) {
                    continue;
                }

                $section_data[ $field['id'] ] = $field['default'] ?? $default_value;
            }

            $defaults[ '' !== $section ? $section : 'general' ] = $section_data;
        }

        return $defaults;
    }

    /**
     * Get the settings array
     *
     * @param  string $section The section to get.
     * @param  string ...$args The sub-sections to get.
     * @return mixed           Array of settings or a single setting.
     */
    public function get_settings( string $section = 'all', string ...$args ) {
        if ( 'all' === $section ) {
            return $this->settings;
        }

        $sub_section = $this->settings[ $section ] ?? array();

        foreach ( $args as $arg ) {
            $sub_section = $sub_section[ $arg ] ?? array();
        }

        return $sub_section;
    }
}
