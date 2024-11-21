<?php
class Toffi_custom_endpoint
{
    function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    function register_routes()
    {
        register_rest_route(
            'toffi/v1',
            '/update_order_status',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'update_order_status'),
                'permission_callback' => array($this, 'permissions_check'),
            )
        );
        register_rest_route(
            'toffi/v1',
            '/get_status',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_gateway_status'),
                'permission_callback' => array($this, 'permissions_check'),
            )
        );
        register_rest_route(
            'toffi/v1',
            '/enable',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'enable_gateway'),
                'permission_callback' => array($this, 'permissions_check'),
            )
        );
        register_rest_route(
            'toffi/v1',
            '/disable',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'disable_gateway'),
                'permission_callback' => array($this, 'permissions_check'),
            )
        );
    }

    function permissions_check($request)
    {
        if (
            isset($_SERVER['HTTP_AUTHORIZATION']) &&
            $_SERVER['HTTP_AUTHORIZATION'] == create_hash()
        ) {
            return true;
        }

        return new WP_Error('rest_forbidden', 'You are not authorized.', array('status' => 401));
    }

    function update_order_status(WP_REST_Request $request)
    {
        $data = $request->get_json_params();
        $order_id = $data['order_id'];
        $order_status = $data['order_status'];
        $forced = $data['forced'];

        // Validate order_id and order_status
        if (isset($order_id, $order_status)) {
            $order = wc_get_order($order_id);

            // Ensure the order status is a valid string and not empty
            if (empty($order_status) || !is_string($order_status)) {
               $order_status = "processing";
            }

            if ($order && ($order->get_payment_method() === 'toffi-source' || $forced == true)) {
                $order->update_status($order_status, 'Order status updated via API.');
                $order->save();

                $response = array(
                    'message' => 'Order status updated to ' . $order_status,
                    'id' => $order->id,
                    'order_key' => $order->order_key
                );

                return new WP_REST_Response(json_encode($response), 200);
            } else {
                return new WP_Error('rest_order_not_found', 'Order not found or not made with Toffi payment method.', array('status' => 404));
            }
        } else {
            return new WP_Error('rest_invalid', 'Missing order_id or order_status.', array('status' => 400));
        }
    }

    function get_gateway_status()
    {
        $settings = get_option('woocommerce_toffi-source_settings');
        $status = $settings['enabled'];
        if ($status === 'yes') {
            return new WP_REST_Response($status, 200);
        } else {
            return new WP_REST_Response($status, 202);
        }
    }

    function enable_gateway()
    {
        $settings = get_option('woocommerce_toffi-source_settings');
        $settings['enabled'] = 'yes';
        update_option('woocommerce_toffi-source_settings', $settings);
        $_POST['enabled'] = 'yes';
        $gateway = new WC_Toffi_Gateway();
        $gateway->process_admin_options();
        do_action('woocommerce_update_options_payment_gateways_toffi-source');
        return new WP_REST_Response('ENABLED', 200);
    }

    function disable_gateway()
    {
        $settings = get_option('woocommerce_toffi-source_settings');
        $settings['enabled'] = 'no';
        update_option('woocommerce_toffi-source_settings', $settings);
        $_POST['enabled'] = 'no';
        $gateway = new WC_Toffi_Gateway();
        $gateway->process_admin_options();
        do_action('woocommerce_update_options_payment_gateways_toffi-source');
        return new WP_REST_Response('DISABLED', 200);
    }
}

function get_website_domain()
{
    $site_url = get_site_url();
    $domain = parse_url($site_url, PHP_URL_HOST);
    return $domain;
}

add_filter('woocommerce_thankyou_order_received_text', 'modify_thank_you_text_for_specific_payment_method', 20, 2);

function modify_thank_you_text_for_specific_payment_method($text, $order)
{
    if (!$order instanceof WC_Order) {
        return $text;
    }

    // Check if the payment method is 'toffi-source'
    if ('toffi-source' === $order->get_payment_method()) {
        $new_text = 'Thank you. Your order has been received. Check your email to finish payment.';
        return $new_text;
    }

    return $text;
}



add_action('template_redirect', 'toffi_redirect');
function toffi_redirect()
{
    if (is_wc_endpoint_url('order-received') && !isset($_GET['order_id']) && !isset($_GET['stop'])) {
        $stop = $_GET['stop'];
        if ($stop == 1) {
            exit();
        }

        $order_id = absint(get_query_var('order-received'));
        $order = wc_get_order($order_id);
        $payment_title = $order->get_payment_method();

        $domain = get_website_domain();

        if (!$order) {
            return;
        }

        $ip = get_customer_ip_address();

        if ($ip) {
            $order->update_meta_data('_customer_ip_address', $ip);
            $order->save();
        }

        $items = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items[] = array(
                'sku' => $product->get_sku(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total()
            );
        }

        $fees = array();
        foreach ($order->get_fees() as $fee) {
            $fees[] = array(
                'name' => $fee->get_name(),
                'amount' => $fee->get_amount(),
            );
        }

        $data = array(
            'billing' => $order->get_address('billing'),
            'shipping' => $order->get_address('shipping'),
            'line_items' => $items,
            'fees' => $fees,
            'discount_total' => $order->get_discount_total(),
            'shipping_total' => $order->get_shipping_total(),
            'cart_tax' => $order->get_cart_tax(),
            'shipping_tax' => $order->get_shipping_tax(),
            'order_total' => $order->get_total(),
            'customer_ip' => $ip,
            'currency' => $order->get_currency(),
            'order_received_url' => $order->get_checkout_order_received_url(),
            'payment_method' => $payment_title,
            'meta_data' => array(
                array(
                    'key' => 'site1_order_id',
                    'value' => (string) $order_id,
                ),
                array(
                    'key' => 'site1_order_key',
                    'value' => $order->order_key,
                ),
                array(
                    'key' => 'source_site_domain',
                    'value' => $domain,
                ),
            ),
        );

        
        if ($payment_title == 'toffi-source') {

            $url = 'https://toffi.live/Toffi/RedirectOrder';
            $response = do_wp_remote_post($url, $data);

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $error_code = $response->get_error_code();
                log_woocommerce_message($error_message);
                log_woocommerce_message($error_code);
                return;
            }

            $status_code = wp_remote_retrieve_response_code($response);

            // Get the HTTP status code
            if ($status_code == 200) {
                wc_clear_notices();
                return;
            }

            // Get the HTTP status code
            if ($status_code == 201) {
                wc_clear_notices();
                $headers = wp_remote_retrieve_headers($response);
                $location = $headers['location'];
            
                if (!empty($location)) {
                    // Embed the JavaScript directly
                    echo "<script type='text/javascript'>
                        document.referrerPolicy = 'no-referrer';
                        window.location.href = " . json_encode($location) . ";
                        </script>";
                    exit;
                } else {
                    wc_add_notice('Please try creating an order again.', 'notice');
                    wp_redirect(wc_get_cart_url());
                    exit;
                }
            }
            

            if ($status_code == 406) {
                wc_add_notice('You have been banned from Toffi Credit Card processor, please try an alternative payment method', 'notice');
                wp_redirect(wc_get_cart_url());
                exit();
            }

            if ($status_code == 409) {
                wc_add_notice('Duplicate order, please check your email inbox/spam folder or try at a later time.', 'notice');
                wp_redirect(wc_get_cart_url());
                exit();
            }

            if ($status_code == 205) {
                wc_add_notice('Processors currently unavailable. Please try again at a later time.', 'notice');
                wp_redirect(wc_get_cart_url());
                exit();
            }

            if ($status_code == 412) {
                $body = wp_remote_retrieve_body($response);
                wc_add_notice('This payment processor does not accept orders over' . $body . $order->get_currency() . '. Please reach out to support to accomodate your request.', 'notice');
                wp_redirect(wc_get_cart_url());
                exit();
            }

            if ($status_code == 405 || $status_code == 503 || $status_code == 500) {
                wc_add_notice('Please try again, invoicing failed.', 'notice');
                wp_redirect(wc_get_cart_url());
                exit();
            }
        }
    }
}

function do_wp_remote_post($url, $data)
{
    $args = array(
        'headers' => array(
            'Content-Type' => 'application/json; charset=utf-8',
            'Authorization' => create_hash(),
        ),
        'timeout' => 60,
        //default 5
        'body' => json_encode($data),
        'method' => 'POST',
        'data_format' => 'body',
    );

    $proxy_enabled = get_setting('enableproxy');
    if ($proxy_enabled) {
        $proxy_username = get_setting('proxyusername');
        if ($proxy_username) {
            $args['proxy'] = "socks5://" . get_setting('proxyusername') . ":" . get_setting('proxypassword') . "@" . get_setting('proxyhost') . ":" . get_setting('proxyport');
        } else {
            $args['proxy'] = "socks4://" . get_setting('proxyhost') . ":" . get_setting('proxyport');
        }
    }

    $response = wp_remote_post($url, $args);
    return $response;
}

function get_customer_ip_address()
{
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_addresses = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ip_addresses[0]); // The first IP is the original client IP
    }
    return $_SERVER['REMOTE_ADDR'];
}

function create_hash()
{
    $string = 'e3b0c44298fc1c149' .
        'afbf4c8996fb92427ae41e4649b' .
        '934ca495991b7852b855f8b4a99' .
        'd56f734d95d25659915b1dbfe59' .
        '3ec0789b9199e0b3b503f7da6fd' .
        'c53' .
        gmdate("H-d-m-y");
    $utf8String = mb_convert_encoding($string, 'UTF-8');
    $hash = hash('sha256', $utf8String);
    return $hash;
}

function get_setting($setting_key)
{
    $settings = get_option('woocommerce_toffi-source_settings');
    return $settings[$setting_key];
}

function log_woocommerce_message($message)
{
    $logger = wc_get_logger();
    $context = array('source' => 'toffi'); // You can change 'my-custom-addon' to a unique identifier for your addon.
    if (is_array($message)) {
        $message = print_r($message, true); // Convert the array to a readable string format.
        $logger->info($message, $context);

    } else {
        $logger->info($message, $context);

    }
}

new Toffi_custom_endpoint();

?>