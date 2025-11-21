<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Settings_Page' ) ) {
    return;
}

/**
 * Adds a new settings tab to the WooCommerce settings pages.
 */
class WC_Settings_Map_Picker extends WC_Settings_Page {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->id    = 'map_picker';
        $this->label = __( 'Location Picker', 'wc-checkout-map-picker' );

        parent::__construct();
    }

    /**
     * Get settings array.
     *
     * @return array
     */
    public function get_settings( $current_section = '' ) {
        $settings = array(
            array(
                'title' => __( 'Map Settings', 'wc-checkout-map-picker' ),
                'type'  => 'title',
                'desc'  => __( 'Configure the behavior of the location picker on the checkout page.', 'wc-checkout-map-picker' ),
                'id'    => 'wc_map_picker_options',
            ),
            array(
                'title'   => __( 'Auto-detect Location', 'wc-checkout-map-picker' ),
                'desc'    => __( 'Automatically request the customer\'s location when the checkout page loads.', 'wc-checkout-map-picker' ),
                'id'      => 'wc_map_picker_auto_locate',
                'type'    => 'checkbox',
                'default' => 'no',
                'desc_tip' => true,
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'wc_map_picker_options',
            ),
        );

        return apply_filters( 'wc_settings_tab_map_picker_settings', $settings );
    }

    /**
     * Save settings.
     */
    public function save() {
        $settings = $this->get_settings();
        WC_Admin_Settings::save_fields( $settings );
    }
}

return new WC_Settings_Map_Picker();
