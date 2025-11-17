<?php
/**
 * Plugin Name: WooCommerce Location Picker (OpenStreetMap)
 * Description: Adds a map to the checkout page for customers to pick their location, with address search and auto-fill.
 * Version: 1.3.3
 * Author: mohamed yussry
 * Author URI: https://mohamedyussry.github.io/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wc-checkout-map-picker
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class WC_Checkout_Map_Picker {

	protected static $_instance = null;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct() {
		$this->define_constants();
		$this->init_hooks();
	}

	private function define_constants() {
		define( 'WC_MAP_PICKER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		define( 'WC_MAP_PICKER_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
	}

	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Enqueue scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Hooks for checkout page
		add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'add_checkout_map' ), 10 );
		add_action( 'woocommerce_checkout_process', array( $this, 'checkout_process_validation' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'update_order_meta' ), 10, 1 );

		// Admin and email hooks
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'display_order_location_in_admin' ) );
		add_filter( 'woocommerce_email_order_meta_fields', array( $this, 'email_order_meta_fields' ), 10, 3 );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'wc-checkout-map-picker', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	public function enqueue_scripts() {
		if ( is_checkout() && ! is_order_received_page() ) {
			// Base Leaflet library
			wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css', array(), '1.7.1' );
			wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js', array(), '1.7.1', true );

			// Leaflet Geosearch for address lookup
			wp_enqueue_style( 'leaflet-geosearch', 'https://unpkg.com/leaflet-geosearch@3.6.1/dist/geosearch.css', array(), '3.6.1' );
			wp_enqueue_script( 'leaflet-geosearch', 'https://unpkg.com/leaflet-geosearch@3.6.1/dist/geosearch.umd.js', array( 'leaflet' ), '3.6.1', true );

			// Plugin's custom files
			wp_enqueue_style( 'wc-checkout-map-picker', WC_MAP_PICKER_PLUGIN_URL . 'assets/css/checkout-map.css', array(), '1.3.3' );
			wp_enqueue_script( 'wc-checkout-map-picker', WC_MAP_PICKER_PLUGIN_URL . 'assets/js/checkout-map.js', array( 'jquery', 'leaflet', 'leaflet-geosearch' ), '1.3.3', true );
		}
	}

	public function enqueue_admin_scripts( $hook ) {
		global $post_type;
		if ( ( 'post.php' == $hook || 'post-new.php' == $hook ) && 'shop_order' === $post_type ) {
			wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css', array(), '1.7.1' );
			wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js', array(), '1.7.1', true );
			wp_enqueue_style( 'wc-checkout-map-picker-admin', WC_MAP_PICKER_PLUGIN_URL . 'assets/css/checkout-map.css', array(), '1.3.3' );
		}
	}

	public function add_checkout_map( $checkout ) {
        // Compatibility fix: Ensure we have the checkout object.
        if ( ! is_object( $checkout ) ) {
            $checkout = WC()->checkout;
        }

        // If checkout is still not an object, something is very wrong. Don't proceed.
        if ( ! is_object( $checkout ) ) {
            return;
        }

		echo '<div id="checkout-map-container">';
		echo '<h3>' . esc_html__( 'Select Your Location', 'wc-checkout-map-picker' ) . '</h3>';
		echo '<p>' . esc_html__( 'Search for your address or move the pin on the map.', 'wc-checkout-map-picker' ) . '</p>';
		echo '<div id="checkout-map" style="height: 350px; width: 100%; margin-bottom: 15px;"></div>';
		echo '<p><button type="button" id="get-current-location" class="button alt">' . esc_html__( 'Get My Current Location', 'wc-checkout-map-picker' ) . '</button></p>';
		
		// Add hidden fields for latitude, longitude, and full address
		woocommerce_form_field( 'latitude', array( 'type' => 'hidden' ), $checkout->get_value( 'latitude' ) );
		woocommerce_form_field( 'longitude', array( 'type' => 'hidden' ), $checkout->get_value( 'longitude' ) );
		woocommerce_form_field( 'full_address', array( 'type' => 'hidden' ), $checkout->get_value( 'full_address' ) );

		echo '</div>';
	}

	public function checkout_process_validation() {
		// Only validate if the map was supposed to be shown.
		if ( ! empty( $_POST['is_pick_from_map_checkout'] ) && empty( $_POST['latitude'] ) ) {
			wc_add_notice( __( 'Please select your location on the map.', 'wc-checkout-map-picker' ), 'error' );
		}
	}

	public function update_order_meta( $order_id ) {
		$latitude = ! empty( $_POST['latitude'] ) ? sanitize_text_field( $_POST['latitude'] ) : '';
		$longitude = ! empty( $_POST['longitude'] ) ? sanitize_text_field( $_POST['longitude'] ) : '';
		$full_address = ! empty( $_POST['full_address'] ) ? sanitize_text_field( wp_unslash( $_POST['full_address'] ) ) : '';

		if ( $latitude && $longitude ) {
			$order = wc_get_order($order_id);
			$order->update_meta_data( '_customer_latitude', $latitude );
			$order->update_meta_data( '_customer_longitude', $longitude );
			$order->update_meta_data( '_customer_address_text', $full_address );
			
			$google_maps_link = 'https://www.google.com/maps/search/?api=1&query=' . $latitude . ',' . $longitude;
			$order->update_meta_data( '_customer_map_link', esc_url( $google_maps_link ) );
			$order->save();
		}
	}

	public function display_order_location_in_admin( $order ) {
		$latitude = $order->get_meta( '_customer_latitude' );
		$longitude = $order->get_meta( '_customer_longitude' );

		if ( $latitude && $longitude ) {
			$address = $order->get_meta( '_customer_address_text' );
			$map_link = $order->get_meta( '_customer_map_link' );
			wc_get_template(
				'order-map-preview.php',
				array(
					'latitude'  => $latitude,
					'longitude' => $longitude,
					'address'   => $address,
					'map_link'  => $map_link,
				),
				'',
				WC_MAP_PICKER_PLUGIN_PATH . 'templates/'
			);
		}
	}

	public function email_order_meta_fields( $fields, $sent_to_admin, $order ) {
		$address = $order->get_meta( '_customer_address_text' );
		if ( $address ) {
			$fields['customer_address_text'] = array(
				'label' => __( 'Delivery Address (from Map)', 'wc-checkout-map-picker' ),
				'value' => wp_kses_post( $address ),
			);
		}

		$map_link = $order->get_meta( '_customer_map_link' );
		if ( $map_link ) {
			$fields['customer_map_link'] = array(
				'label' => __( 'Map Location', 'wc-checkout-map-picker' ),
				'value' => '<a href="' . esc_url( $map_link ) . '" target="_blank">' . __( 'View on Google Maps', 'wc-checkout-map-picker' ) . '</a>',
			);
		}
		
		return $fields;
	}
}

// Initialize the plugin
function wc_checkout_map_picker() {
	return WC_Checkout_Map_Picker::instance();
}
add_action( 'plugins_loaded', 'wc_checkout_map_picker' );
