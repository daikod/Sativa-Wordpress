<?php
function gemstone_theme_setup() {
    // Add WooCommerce support
    add_theme_support('woocommerce');
    
    // Add product gallery features
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
    
    // Add custom image sizes for gemstones
    add_image_size('gemstone-thumbnail', 300, 300, true);
    add_image_size('gemstone-large', 800, 800, true);
}
add_action('after_setup_theme', 'gemstone_theme_setup');

// Add custom shipping zones for Nigeria
function add_nigeria_shipping_zones() {
    // Check if WooCommerce is active
    if (!class_exists('WC_Shipping_Zones')) {
        return;
    }

    try {
        $zones = WC_Shipping_Zones::get_zones();
        
        // Check if Nigeria zone exists
        $nigeria_exists = false;
        foreach ($zones as $zone) {
            if ($zone['zone_name'] === 'Nigeria') {
                $nigeria_exists = true;
                break;
            }
        }
        
        // Create Nigeria zone if it doesn't exist
        if (!$nigeria_exists) {
            $zone = new WC_Shipping_Zone();
            $zone->set_zone_name('Nigeria');
            $zone->set_locations(array(
                array(
                    'type' => 'country',
                    'code' => 'NG'
                )
            ));
            
            // Save and check for errors
            $result = $zone->save();
            if (is_wp_error($result)) {
                error_log('Failed to create Nigeria shipping zone: ' . $result->get_error_message());
                return;
            }
            
            // Add shipping methods
            $zone->add_shipping_method('flat_rate');
            $zone->add_shipping_method('local_pickup');
        }
    } catch (Exception $e) {
        error_log('Error setting up Nigeria shipping zones: ' . $e->getMessage());
    }
}
add_action('woocommerce_init', 'add_nigeria_shipping_zones');

// Add Nigerian States to WooCommerce
function add_nigerian_states($states) {
    $states['NG'] = array(
        'AB' => 'Abia',
        'AD' => 'Adamawa',
        'AK' => 'Akwa Ibom',
        'AN' => 'Anambra',
        'BA' => 'Bauchi',
        'BY' => 'Bayelsa',
        'BE' => 'Benue',
        'BO' => 'Borno',
        'CR' => 'Cross River',
        'DE' => 'Delta',
        'EB' => 'Ebonyi',
        'ED' => 'Edo',
        'EK' => 'Ekiti',
        'EN' => 'Enugu',
        'FC' => 'FCT',
        'GO' => 'Gombe',
        'IM' => 'Imo',
        'JI' => 'Jigawa',
        'KD' => 'Kaduna',
        'KN' => 'Kano',
        'KT' => 'Katsina',
        'KE' => 'Kebbi',
        'KO' => 'Kogi',
        'KW' => 'Kwara',
        'LA' => 'Lagos',
        'NA' => 'Nasarawa',
        'NI' => 'Niger',
        'OG' => 'Ogun',
        'ON' => 'Ondo',
        'OS' => 'Osun',
        'OY' => 'Oyo',
        'PL' => 'Plateau',
        'RI' => 'Rivers',
        'SO' => 'Sokoto',
        'TA' => 'Taraba',
        'YO' => 'Yobe',
        'ZA' => 'Zamfara'
    );
    return $states;
}
add_filter('woocommerce_states', 'add_nigerian_states');

// Format Nigerian currency with error handling
function format_naira($price) {
    if (!is_numeric($price)) {
        return '₦0.00';
    }
    return '₦' . number_format((float)$price, 2, '.', ',');
}

// Add currency symbol filter with proper parameter handling
function modify_currency_symbol($currency_symbol, $currency) {
    if (!is_string($currency)) {
        return $currency_symbol;
    }
    return $currency === 'NGN' ? '₦' : $currency_symbol;
}
remove_filter('woocommerce_currency_symbol', 'format_naira');
add_filter('woocommerce_currency_symbol', 'modify_currency_symbol', 10, 2);

// Add custom product fields for gemstones
function add_gemstone_product_fields() {
    woocommerce_wp_text_input(array(
        'id' => '_gemstone_carat',
        'label' => 'Carat Weight',
        'desc_tip' => true,
        'description' => 'Enter the carat weight of the gemstone'
    ));
    
    woocommerce_wp_select(array(
        'id' => '_gemstone_clarity',
        'label' => 'Clarity Grade',
        'options' => array(
            '' => 'Select clarity',
            'FL' => 'Flawless',
            'IF' => 'Internally Flawless',
            'VVS1' => 'Very Very Slightly Included 1',
            'VVS2' => 'Very Very Slightly Included 2',
            'VS1' => 'Very Slightly Included 1',
            'VS2' => 'Very Slightly Included 2',
            'SI1' => 'Slightly Included 1',
            'SI2' => 'Slightly Included 2',
        )
    ));
}
add_action('woocommerce_product_options_general_product_data', 'add_gemstone_product_fields');

// Save gemstone custom fields with validation
function save_gemstone_fields($post_id) {
    // Verify nonce and permissions (existing checks remain the same)
    if (!isset($_POST['woocommerce_meta_nonce']) || 
        !wp_verify_nonce($_POST['woocommerce_meta_nonce'], 'woocommerce_save_data')) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Prevent autosave from triggering validation
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Enhanced validation for gemstone data
    if (isset($_POST['_gemstone_carat']) && isset($_POST['_gemstone_clarity'])) {
        $carat = sanitize_text_field($_POST['_gemstone_carat']);
        $clarity = sanitize_text_field($_POST['_gemstone_clarity']);
        
        $validation = validate_gemstone_data($carat, $clarity);
        
        if ($validation === true) {
            update_post_meta($post_id, '_gemstone_carat', $carat);
            update_post_meta($post_id, '_gemstone_clarity', $clarity);
        } else {
            // Log validation errors
            foreach ($validation as $error) {
                error_log("Gemstone validation error for post $post_id: $error");
            }
            
            // Add admin notice for validation errors
            add_action('admin_notices', function() use ($validation) {
                echo '<div class="error"><p>' . 
                     esc_html__('Gemstone data validation failed: ', 'gemstone-theme') . 
                     implode(', ', array_map('esc_html', $validation)) . 
                     '</p></div>';
            });
        }
    }
}

// Add sanitization for price inputs
function sanitize_price_input($price) {
    // Remove any non-numeric characters except decimal point
    $price = preg_replace('/[^0-9.]/', '', $price);
    // Ensure only one decimal point
    $parts = explode('.', $price);
    if (count($parts) > 2) {
        $price = $parts[0] . '.' . $parts[1];
    }
    return number_format((float)$price, 2, '.', '');
}

// Add AJAX endpoint for real-time price conversion
function add_price_conversion_endpoint() {
    add_action('wp_ajax_convert_price', 'convert_price_callback');
    add_action('wp_ajax_nopriv_convert_price', 'convert_price_callback');
}
add_action('init', 'add_price_conversion_endpoint');

function convert_price_callback() {
    check_ajax_referer('price_conversion_nonce', 'nonce');
    
    $amount = isset($_POST['amount']) ? sanitize_price_input($_POST['amount']) : 0;
    $formatted = format_naira($amount);
    
    wp_send_json_success(array('formatted' => $formatted));
}

// Add security headers
function add_security_headers() {
    if (!is_admin()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        if (is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
add_action('send_headers', 'add_security_headers');

// Disable XML-RPC
add_filter('xmlrpc_enabled', '__return_false');

// Remove WordPress version from various places
function remove_version_info() {
    return '';
}
add_filter('the_generator', 'remove_version_info');

// Disable file editing in WordPress admin
if (!defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}

// Add rate limiting for failed login attempts
function check_failed_login($user, $username) {
    if (!empty($username)) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $failed_login_count = get_transient('failed_login_' . $ip);
        
        if ($failed_login_count === false) {
            set_transient('failed_login_' . $ip, 1, HOUR_IN_SECONDS);
        } else if ($failed_login_count >= 5) {
            return new WP_Error(
                'too_many_attempts',
                'Too many failed login attempts. Please try again in an hour.'
            );
        } else {
            set_transient('failed_login_' . $ip, $failed_login_count + 1, HOUR_IN_SECONDS);
        }
    }
    return $user;
}
add_filter('authenticate', 'check_failed_login', 30, 2);

// Enhanced gemstone validation
function validate_gemstone_data($carat, $clarity) {
    $errors = array();
    
    // Validate carat
    if (!is_numeric($carat)) {
        $errors[] = 'Carat weight must be a number';
    } else if ($carat < 0 || $carat > 1000) {
        $errors[] = 'Carat weight must be between 0 and 1000';
    }
    
    // Validate clarity
    $valid_grades = array('FL', 'IF', 'VVS1', 'VVS2', 'VS1', 'VS2', 'SI1', 'SI2', '');
    if (!in_array($clarity, $valid_grades)) {
        $errors[] = 'Invalid clarity grade';
    }
    
    return empty($errors) ? true : $errors;
}

// Update save_gemstone_fields with enhanced validation
function save_gemstone_fields($post_id) {
    // Verify nonce and permissions (existing checks remain the same)
    if (!isset($_POST['woocommerce_meta_nonce']) || 
        !wp_verify_nonce($_POST['woocommerce_meta_nonce'], 'woocommerce_save_data')) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Prevent autosave from triggering validation
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Enhanced validation for gemstone data
    if (isset($_POST['_gemstone_carat']) && isset($_POST['_gemstone_clarity'])) {
        $carat = sanitize_text_field($_POST['_gemstone_carat']);
        $clarity = sanitize_text_field($_POST['_gemstone_clarity']);
        
        $validation = validate_gemstone_data($carat, $clarity);
        
        if ($validation === true) {
            update_post_meta($post_id, '_gemstone_carat', $carat);
            update_post_meta($post_id, '_gemstone_clarity', $clarity);
        } else {
            // Log validation errors
            foreach ($validation as $error) {
                error_log("Gemstone validation error for post $post_id: $error");
            }
            
            // Add admin notice for validation errors
            add_action('admin_notices', function() use ($validation) {
                echo '<div class="error"><p>' . 
                     esc_html__('Gemstone data validation failed: ', 'gemstone-theme') . 
                     implode(', ', array_map('esc_html', $validation)) . 
                     '</p></div>';
            });
        }
    }
}

// Add sanitization for price inputs
function sanitize_price_input($price) {
    // Remove any non-numeric characters except decimal point
    $price = preg_replace('/[^0-9.]/', '', $price);
    // Ensure only one decimal point
    $parts = explode('.', $price);
    if (count($parts) > 2) {
        $price = $parts[0] . '.' . $parts[1];
    }
    return number_format((float)$price, 2, '.', '');
}

// Add AJAX endpoint for real-time price conversion
function add_price_conversion_endpoint() {
    add_action('wp_ajax_convert_price', 'convert_price_callback');
    add_action('wp_ajax_nopriv_convert_price', 'convert_price_callback');
}
add_action('init', 'add_price_conversion_endpoint');

function convert_price_callback() {
    check_ajax_referer('price_conversion_nonce', 'nonce');
    
    $amount = isset($_POST['amount']) ? sanitize_price_input($_POST['amount']) : 0;
    $formatted = format_naira($amount);
    
    wp_send_json_success(array('formatted' => $formatted));
}

// Add this to the existing file
function enqueue_price_conversion_script() {
    wp_enqueue_script('price-conversion', get_template_directory_uri() . '/js/price-conversion.js', array('jquery'), '1.0', true);
    wp_localize_script('price-conversion', 'priceConversionData', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('price_conversion_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_price_conversion_script');
 