<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template for displaying the map in the admin order view.
 *
 * @var WC_Order $order
 * @var float    $latitude
 * @var float    $longitude
 * @var string   $address
 * @var string   $map_link
 */
?>
<div class="order_data_column address-map-container">
    <h4><?php esc_html_e( 'Customer Location', 'wc-checkout-map-picker' ); ?></h4>
    <div class="address">
        <p>
            <strong><?php esc_html_e( 'Address from map:', 'wc-checkout-map-picker' ); ?></strong><br>
            <?php echo isset( $address ) ? esc_html( $address ) : esc_html_e( 'Not available', 'wc-checkout-map-picker' ); ?>
        </p>
    </div>
    <p>
        <strong><?php esc_html_e( 'Coordinates:', 'wc-checkout-map-picker' ); ?></strong><br>
        <?php 
        if ( isset( $latitude ) && isset( $longitude ) ) {
            echo esc_html( $latitude ) . ', ' . esc_html( $longitude );
        } else {
            esc_html_e( 'Not available', 'wc-checkout-map-picker' );
        }
        ?>
    </p>
    <?php if ( isset( $latitude ) && isset( $longitude ) ) : ?>
        <div id="admin-order-map" class="admin-order-map" style="height: 200px; width: 100%;"></div>
        <p style="text-align: center; margin-top: 10px;">
            <a href="<?php echo esc_url( $map_link ); ?>" target="_blank" class="button button-primary">
                <?php esc_html_e( 'Open in Google Maps', 'wc-checkout-map-picker' ); ?>
            </a>
        </p>
    <?php endif; ?>
</div>

<?php if ( isset( $latitude ) && isset( $longitude ) ) : ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var lat = <?php echo json_encode( $latitude ); ?>;
        var lng = <?php echo json_encode( $longitude ); ?>;

        if (lat && lng) {
            var map = L.map('admin-order-map', { zoomControl: false }).setView([lat, lng], 15);
            map.attributionControl.setPrefix(false); // Remove Leaflet prefix

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: 'by | <a href="https://mohamedyussry.github.io/" target="_blank">mohamed yusrry</a>'
            }).addTo(map);

            L.marker([lat, lng]).addTo(map);

            // Disable map interactions
            map.dragging.disable();
            map.touchZoom.disable();
            map.doubleClickZoom.disable();
            map.scrollWheelZoom.disable();
        }
    });
</script>
<?php endif; ?>
