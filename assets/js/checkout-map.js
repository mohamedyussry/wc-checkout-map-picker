jQuery(document).ready(function($) {
    if (!$('#checkout-map').length) {
        return;
    }

    // Default coordinates (e.g., center of a country)
    var defaultLat = 24.7136;
    var defaultLng = 46.6753;

    var map = L.map('checkout-map').setView([defaultLat, defaultLng], 5);
    map.attributionControl.setPrefix(false); // Remove Leaflet prefix
    var marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: 'by | <a href="https://mohamedyussry.github.io/" target="_blank">mohamed yusrry</a>'
    }).addTo(map);

    // Add Geosearch control
    var searchControl = new GeoSearch.GeoSearchControl({
        provider: new GeoSearch.OpenStreetMapProvider(),
        style: 'bar',
        showMarker: false, // We will use our own marker
        autoClose: true,
    });
    map.addControl(searchControl);

    // --- FUNCTIONS ---

    function updateAllFields(lat, lng, address) {
        // 1. Update hidden fields for saving
        $('#latitude').val(lat);
        $('#longitude').val(lng);
        $('#full_address').val(address.label);

        // 2. Update WooCommerce checkout fields
        // Use 'shipping_' prefix as default, fallback to 'billing_' if needed
        var prefix = $('#ship-to-different-address-checkbox').is(':checked') ? 'shipping' : 'billing';

        $('#' + prefix + '_address_1').val(address.address1 || '');
        $('#' + prefix + '_address_2').val(address.address2 || '');
        $('#' + prefix + '_city').val(address.city || '');
        $('#' + prefix + '_postcode').val(address.postcode || '');

        // Handle country and state
        var countryCode = (address.country_code || '').toUpperCase();
        if ($('#' + prefix + '_country option[value="' + countryCode + '"]').length) {
            $('#' + prefix + '_country').val(countryCode).trigger('change');
        }

        // Delay state update to allow ajax state list to load
        setTimeout(function() {
             var $stateField = $('#' + prefix + '_state');
             if ($stateField.is('select')) {
                 if ($stateField.find('option[value="' + address.state + '"]').length) {
                    $stateField.val(address.state).trigger('change');
                 }
             } else {
                 $stateField.val(address.state || '');
             }
        }, 500); // 500ms delay
    }

    function parseAddress(result) {
        var address = result.raw.address;
        var road = address.road || '';
        var neighbourhood = address.neighbourhood || '';
        var suburb = address.suburb || '';

        return {
            label: result.label,
            address1: road ? road + (address.house_number ? ', ' + address.house_number : '') : suburb,
            address2: neighbourhood,
            city: address.city || address.town || address.village || '',
            postcode: address.postcode || '',
            state: address.state || '',
            country_code: address.country_code || ''
        };
    }

    function processResult(lat, lng, label) {
        map.setView([lat, lng], 16);
        marker.setLatLng([lat, lng]);

        // Use Nominatim API for detailed reverse geocoding
        var url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`;
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data && data.address) {
                    var parsedAddr = parseAddress({ raw: data, label: data.display_name });
                    updateAllFields(lat, lng, parsedAddr);
                }
            })
            .catch(error => console.error('Error with reverse geocoding:', error));
    }


    // --- EVENT LISTENERS ---

    // When a search result is selected
    map.on('geosearch/showlocation', function(result) {
        var lat = result.location.y;
        var lng = result.location.x;
        var label = result.location.label;
        processResult(lat, lng, label);
    });

    // When the marker is dragged
    marker.on('dragend', function(e) {
        var latLng = e.target.getLatLng();
        processResult(latLng.lat, latLng.lng, 'Location from map');
    });

    // When 'Get My Current Location' is clicked
    $('#get-current-location').on('click', function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                var lat = position.coords.latitude;
                var lng = position.coords.longitude;
                processResult(lat, lng, 'Current Location');
            }, function() {
                alert('Could not get your location. Please search for it or set it manually.');
            });
        } else {
            alert('Geolocation is not supported by this browser.');
        }
    });
    
    // When user toggles shipping address, ensure map fields are still valid
    $('body').on('change', '#ship-to-different-address-checkbox', function() {
        // Re-trigger a change to repopulate the correct fields if a location is already selected
        if ($('#latitude').val() && $('#longitude').val()) {
            var lat = $('#latitude').val();
            var lng = $('#longitude').val();
            processResult(lat, lng, 'Selected Location');
        }
    });
});
