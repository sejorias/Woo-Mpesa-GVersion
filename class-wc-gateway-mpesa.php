<?php
if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('WC_Payment_Gateway')) {

    class WC_Gateway_Mpesa extends WC_Payment_Gateway {

        public function __construct() {
            $this->id = 'woocommerce_mpesa';
            $this->icon = '';
            $this->has_fields = true;
            $this->method_title = __('M-Pesa', 'woocommerce-mpesa-gateway');
            $this->method_description = __('Pay with M-Pesa using STK Push.', 'woocommerce-mpesa-gateway');

            $this->supports = array(
                'products',
                'block_checkout',
            );

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->business_shortcode = $this->get_option('business_shortcode');
            $this->passkey = $this->get_option('passkey');
            $this->consumer_key = $this->get_option('consumer_key');
            $this->consumer_secret = $this->get_option('consumer_secret');
            $this->test_mode = 'yes' === $this->get_option('test_mode', 'yes');

            error_log('M-Pesa Constructor - Settings saved: ' . print_r($this->settings, true));

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_mpesa_callback', array($this, 'handle_callback'));
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce-mpesa-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable M-Pesa Gateway', 'woocommerce-mpesa-gateway'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce-mpesa-gateway'),
                    'type' => 'text',
                    'description' => __('The title displayed at checkout.', 'woocommerce-mpesa-gateway'),
                    'default' => __('M-Pesa', 'woocommerce-mpesa-gateway'),
                    'desc_tip' => true
                ),
                'description' => array(
                    'title' => __('Description', 'woocommerce-mpesa-gateway'),
                    'type' => 'textarea',
                    'description' => __('The description shown at checkout.', 'woocommerce-mpesa-gateway'),
                    'default' => __('Pay securely with M-Pesa via STK Push.', 'woocommerce-mpesa-gateway')
                ),
                'test_mode' => array(
                    'title' => __('Test Mode', 'woocommerce-mpesa-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable Test Mode (Sandbox)', 'woocommerce-mpesa-gateway'),
                    'default' => 'yes',
                    'description' => __('Use the M-Pesa sandbox for testing.', 'woocommerce-mpesa-gateway')
                ),
                'business_shortcode' => array(
                    'title' => __('Business Shortcode', 'woocommerce-mpesa-gateway'),
                    'type' => 'text',
                    'description' => __('Your M-Pesa Business Shortcode.', 'woocommerce-mpesa-gateway'),
                    'default' => ''
                ),
                'passkey' => array(
                    'title' => __('Passkey', 'woocommerce-mpesa-gateway'),
                    'type' => 'password',
                    'description' => __('Your M-Pesa Passkey.', 'woocommerce-mpesa-gateway'),
                    'default' => ''
                ),
                'consumer_key' => array(
                    'title' => __('Consumer Key', 'woocommerce-mpesa-gateway'),
                    'type' => 'text',
                    'description' => __('Your M-Pesa Consumer Key.', 'woocommerce-mpesa-gateway'),
                    'default' => ''
                ),
                'consumer_secret' => array(
                    'title' => __('Consumer Secret', 'woocommerce-mpesa-gateway'),
                    'type' => 'password',
                    'description' => __('Your M-Pesa Consumer Secret.', 'woocommerce-mpesa-gateway'),
                    'default' => ''
                ),
            );
        }

        public function payment_fields() {
            if ($this->description) {
                echo '<p>' . wp_kses_post($this->description) . '</p>';
            }
            echo '<p class="form-row form-row-wide"><label for="mpesa_phone">' . esc_html__('Phone Number', 'woocommerce-mpesa-gateway') . ' <span class="required">*</span></label><input type="text" class="input-text" name="mpesa_phone" id="mpesa_phone" placeholder="e.g., 254712345678 or 0712345678" required /></p>';
            error_log('M-Pesa payment_fields() called');
        }

        public function process_payment($order_id) {
            error_log('M-Pesa process_payment() called for order ID: ' . $order_id);
            error_log('POST data: ' . print_r($_POST, true)); // Debug full POST data
            $order = wc_get_order($order_id);
            $raw_phone = sanitize_text_field($_POST['mpesa_phone'] ?? '');
            error_log('Raw phone input: ' . ($raw_phone ?: 'Empty'));

            // Normalize phone number
            $phone = $this->normalize_phone_number($raw_phone);
            error_log('Normalized phone: ' . ($phone ?: 'Invalid'));

            if (!$phone) {
                wc_add_notice(__('Invalid phone number. Use formats like 2547XXXXXXXX, +2547XXXXXXXX, or 07XXXXXXXX.', 'woocommerce-mpesa-gateway'), 'error');
                return array('result' => 'failure');
            }

            $amount = $order->get_total();
            $response = $this->initiate_stk_push($phone, $amount, $order_id);
            if ($response && isset($response['ResponseCode']) && $response['ResponseCode'] == '0') {
                update_post_meta($order_id, '_mpesa_checkout_request_id', sanitize_text_field($response['CheckoutRequestID']));
                $order->add_order_note(__('M-Pesa STK Push initiated.', 'woocommerce-mpesa-gateway'));
                $order->update_status('on-hold');
                wc_reduce_stock_levels($order_id);
                WC()->cart->empty_cart();
                return array('result' => 'success', 'redirect' => $this->get_return_url($order));
            } else {
                $error = isset($response['errorMessage']) ? $response['errorMessage'] : 'Unknown error';
                wc_add_notice(__('Payment failed: ', 'woocommerce-mpesa-gateway') . $error, 'error');
                return array('result' => 'failure');
            }
        }

        private function normalize_phone_number($raw_phone) {
            // Remove spaces, dashes, and other non-digit characters except leading +
            $phone = preg_replace('/[^0-9+]/', '', $raw_phone);

            // Handle common Kenyan formats
            if (preg_match('/^\+2547\d{8}$/', $phone)) {
                // +254723443434 -> 254723443434
                return substr($phone, 1);
            } elseif (preg_match('/^2547\d{8}$/', $phone)) {
                // 254723443434 -> Already correct
                return $phone;
            } elseif (preg_match('/^07\d{8}$/', $phone)) {
                // 0723443434 -> 254723443434
                return '254' . substr($phone, 1);
            }

            // Invalid format
            return false;
        }

        private function initiate_stk_push($phone, $amount, $order_id) {
            $token = $this->get_access_token();
            if (!$token) {
                error_log('Failed to get M-Pesa access token');
                return false;
            }
            $url = $this->test_mode ? 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest' : 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
            $timestamp = date('YmdHis');
            $password = base64_encode($this->business_shortcode . $this->passkey . $timestamp);
            $data = array(
                'BusinessShortCode' => $this->business_shortcode,
                'Password'          => $password,
                'Timestamp'         => $timestamp,
                'TransactionType'   => 'CustomerPayBillOnline',
                'Amount'            => round($amount),
                'PartyA'            => $phone,
                'PartyB'            => $this->business_shortcode,
                'PhoneNumber'       => $phone,
                'CallBackURL'       => home_url('/wc-api/mpesa_callback'),
                'AccountReference'  => 'Order#' . $order_id,
                'TransactionDesc'   => 'Payment for Order #' . $order_id,
            );
            $response = wp_remote_post($url, array(
                'headers' => array('Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'),
                'body'    => json_encode($data),
                'timeout' => 30,
            ));
            if (is_wp_error($response)) {
                error_log('M-Pesa STK Push failed: ' . $response->get_error_message());
                return false;
            }
            $body = json_decode(wp_remote_retrieve_body($response), true);
            error_log('M-Pesa STK Push response: ' . print_r($body, true));
            return $body;
        }

        private function get_access_token() {
            $url = $this->test_mode ? 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials' : 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
            $credentials = base64_encode($this->consumer_key . ':' . $this->consumer_secret);
            $response = wp_remote_get($url, array(
                'headers' => array('Authorization' => 'Basic ' . $credentials),
                'timeout' => 30,
            ));
            if (is_wp_error($response)) {
                error_log('M-Pesa Access Token request failed: ' . $response->get_error_message());
                return false;
            }
            $body = json_decode(wp_remote_retrieve_body($response), true);
            error_log('M-Pesa Access Token response: ' . print_r($body, true));
            return isset($body['access_token']) ? $body['access_token'] : false;
        }

        public function handle_callback() {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') wp_die('Invalid method', '', array('response' => 405));
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['Body']['stkCallback']['CheckoutRequestID']) || !isset($data['Body']['stkCallback']['ResultCode'])) {
                wp_die('Invalid callback', '', array('response' => 400));
            }
            $checkout_request_id = sanitize_text_field($data['Body']['stkCallback']['CheckoutRequestID']);
            $result_code = $data['Body']['stkCallback']['ResultCode'];
            $result_desc = sanitize_text_field($data['Body']['stkCallback']['ResultDesc']);
            $orders = wc_get_orders(array('meta_key' => '_mpesa_checkout_request_id', 'meta_value' => $checkout_request_id, 'limit' => 1));
            if (empty($orders)) wp_die('Order not found', '', array('response' => 404));
            $order = $orders[0];
            if ($result_code == '0') {
                $order->payment_complete();
                $order->add_order_note(__('M-Pesa payment completed.', 'woocommerce-mpesa-gateway'));
            } else {
                $order->update_status('failed', sprintf(__('M-Pesa payment failed: %s', 'woocommerce-mpesa-gateway'), $result_desc));
            }
            wp_send_json(array('ResultCode' => 0, 'ResultDesc' => 'Processed'));
            exit;
        }

        public function is_available() {
            error_log('M-Pesa is_available() called');
            $is_available = 'yes' === $this->enabled;
            error_log('M-Pesa Enabled Check: ' . ($is_available ? 'Yes' : 'No'));

            if (empty($this->business_shortcode) || empty($this->passkey) || 
                empty($this->consumer_key) || empty($this->consumer_secret)) {
                error_log('M-Pesa Gateway not available: Missing settings.');
                $is_available = false;
            } else {
                error_log('M-Pesa Settings: All present');
            }

            $parent_available = parent::is_available();
            error_log('M-Pesa Parent is_available(): ' . ($parent_available ? 'Yes' : 'No'));
            if (!$parent_available) {
                error_log('Currency: ' . get_woocommerce_currency());
                error_log('Cart total: ' . (WC()->cart ? WC()->cart->get_total() : 'No cart'));
                error_log('Checkout page: ' . (is_checkout() ? 'Yes' : 'No'));
            }

            $final_result = $is_available && $parent_available;
            error_log('M-Pesa Final Availability: ' . ($final_result ? 'Yes' : 'No'));
            return $final_result;
        }
    }
}