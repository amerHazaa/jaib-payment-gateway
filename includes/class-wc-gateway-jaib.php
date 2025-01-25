<?php
if (!defined('ABSPATH')) {
    exit;
}
add_action('wp_loaded', 'jaib_initiate_connection', 10);
function jaib_initiate_connection() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    class WC_Gateway_Jaib extends WC_Payment_Gateway {
        private $loginUrl, $userName, $password, $agentCode, $exeBuyUrl;
        public function __construct() {
            $this->id = 'jaib';
            $this->method_title = __('Jaib Payment Gateway', 'jaib');
            $this->method_description = __('Jaib payment gateway.', 'jaib');
            $this->icon = plugins_url('jaib.png', __FILE__);
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->loginUrl = $this->get_option('loginUrl');
            $this->userName = $this->get_option('userName');
            $this->password = $this->get_option('password');
            $this->agentCode = $this->get_option('agentCode');
            $this->exeBuyUrl = $this->get_option('exe_buy_url');
            
            // توليد قيمة requestID عشوائيًا وتخزينها في الجلسة
            if (!isset($_SESSION)) {
                session_start();
            }
            $orderTime = time();
            $randDigits = rand(10, 99);
            $_SESSION['requestID'] = "10004{$orderTime}{$randDigits}";
            
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
            add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
            add_action('admin_footer', [$this, 'admin_scripts']);
            $this->init_connection();
        }
        public function get_userName() {
            return $this->userName;
        }
        public function set_userName($userName) {
            $this->userName = $userName;
        }
        public function get_password() {
            return $this->password;
        }
        public function set_password($password) {
            $this->password = $password;
        }
        public function get_agentCode() {
            return $this->agentCode;
        }
        public function set_agentCode($agentCode) {
            $this->agentCode = $agentCode;
        }
        public function get_loginUrl() {
            return $this->loginUrl;
        }
        public function set_loginUrl($loginUrl) {
            $this->loginUrl = $loginUrl;
        }
        public function get_exeBuyUrl() {
            return $this->exeBuyUrl;
        }
        public function set_exeBuyUrl($exeBuyUrl) {
            $this->exeBuyUrl = $exeBuyUrl;
        }
        public function update_jaib_settings() {
            global $wpdb;
            $encrypted_password = md5($this->get_password());
            $data = array(
                'status' => $this->get_option('enabled'),
                'username' => $this->get_userName(),
                'password' => $encrypted_password,
                'agent_code' => $this->get_agentCode(),
                'login_url' => $this->get_loginUrl(),
                'exe_buy_url' => $this->get_exeBuyUrl()
            );
            $format = array('%s', '%s', '%s', '%s', '%s', '%s');
            $settings_table = $wpdb->prefix . 'jaib_settings';
            $existing_record = $wpdb->get_row("SELECT id FROM $settings_table LIMIT 1");
            if ($existing_record) {
                $wpdb->update($settings_table, $data, array('id' => $existing_record->id), $format, array('%d'));
            } else {
                $wpdb->insert($settings_table, $data, $format);
            }
        }
        public function process_admin_options() {
            parent::process_admin_options();
            $this->update_jaib_settings();
        }
        public function init_form_fields() {
            $this->form_fields = [
                'enabled' => [
                    'title' => __('Enable/Disable', 'jaib'),
                    'type' => 'checkbox',
                    'label' => __('Enable Jaib Payment Gateway', 'jaib'),
                    'default' => 'yes'
                ],
                'title' => [
                    'title' => __('Title', 'jaib'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'jaib'),
                    'default' => __('Jaib eWallet', 'jaib'),
                    'desc_tip' => true,
                ],
                'loginUrl' => [
                    'title' => __('Login URL', 'jaib'),
                    'type' => 'text',
                    'description' => __('URL for Jaib API login.'),
                    'default' => ''
                ],
                'userName' => [
                    'title' => __('User Name', 'jaib'),
                    'type' => 'text',
                    'description' => __('The user name for the Jaib API.', 'jaib'),
                ],
                'password' => [
                    'title' => __('Password', 'jaib'),
                    'type' => 'password',
                    'description' => __('The password for the Jaib API.', 'jaib'),
                ],
                'agentCode' => [
                    'title' => __('Agent Code', 'jaib'),
                    'type' => 'text',
                    'description' => __('The agent code for the Jaib API.', 'jaib'),
                ],
                'exe_buy_url' => [
                    'title' => __('Execute Buy URL', 'jaib'),
                    'type' => 'text',
                    'description' => __('URL for executing buy online by code.'),
                    'default' => ''
                ],
                'connect' => [
                    'title' => __('Connect Manually', 'jaib'),
                    'type' => 'button',
                    'description' => '<div id="jaib-connection-status">' . jaib_get_connection_status() . '</div>',
                    'custom_attributes' => ['onclick' => 'connect()'],
                    'default' => __('Connect Manually'),
                ],
            ];
        }
        public function init_connection() {
            if (!isset($_SESSION)) {
                session_start();
            }
            $userName = $this->get_userName();
            $password = $this->get_password();
            $agentCode = $this->get_agentCode();
            $loginUrl = $this->get_loginUrl();
            $data = array(
                "userName" => $userName,
                "password" => $password,
                "agentCode" => $agentCode
            );
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $loginUrl,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Accept: application/json'
                ),
            ));
            $response = curl_exec($curl);
            $tryTime = current_time('mysql');
            if (!curl_errno($curl)) {
                $rData = json_decode($response, true);
                if (isset($rData['result'])) {
                    $_SESSION['accessToken'] = $rData['result']['accessToken'];
                    $_SESSION['pinApi'] = $rData['result']['pinApi'];
                    $_SESSION['expire'] = time() + 86400;
                    error_log('@@login succeeded');
                    update_option('woocommerce_jaib_connection_status', 'Connected');
                    update_option('woocommerce_jaib_accessToken', $rData['result']['accessToken']);
                    update_option('woocommerce_jaib_pinApi', $rData['result']['pinApi']);
                    $this->log_connection_attempt($tryTime, 'success', json_encode($data), $response, $rData['result']['pinApi']);
                } else {
                    $error_message = isset($rData['message']) ? $rData['message'] : 'Unknown error';
                    error_log('@@login failed: ' . $error_message);
                    update_option('woocommerce_jaib_connection_status', 'Failed');
                    $this->log_connection_attempt($tryTime, 'fail', json_encode($data), $response, $loginUrl);
                }
            } else {
                $error_message = curl_error($curl);
                error_log("@@Curl error in connection setup: " . $error_message);
                update_option('woocommerce_jaib_connection_status', 'Failed');
                $this->log_connection_attempt($tryTime, 'fail', json_encode($data), $error_message, $loginUrl);
            }
            curl_close($curl);
        }
        private function log_connection_attempt($tryTime, $result, $payload, $response, $pinApi) {
            global $wpdb;
            $data = array(
                'try_time' => $tryTime,
                'result' => $result,
                'payload' => $payload,
                'response' => $response,
                'pin_api' => $pinApi
            );
            $format = array('%s', '%s', '%s', '%s', '%s');
            $connection_history_table = $wpdb->prefix . 'jaib_connection_history';
            $wpdb->insert($connection_history_table, $data, $format);
        }
        public function log_payment_attempt($data) {
            global $wpdb;
            $format = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d');
            $payment_history_table = $wpdb->prefix . 'jaib_payment_history';
            $wpdb->insert($payment_history_table, $data, $format);
        }
        public function log_successful_payment($data) {
            global $wpdb;
            $format = array('%d', '%s', '%d', '%s', '%d');
            $payment_table = $wpdb->prefix . 'jaib_payment';
            $wpdb->insert($payment_table, $data, $format);
        }
        public function admin_scripts() {
            ?>
            <script type="text/javascript">
                function connect() {
                    jQuery.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        method: 'POST',
                        data: {
                            action: 'init_connection',
                            userName: '<?php echo esc_js($this->get_userName()); ?>',
                            password: '<?php echo esc_js($this->get_password()); ?>',
                            agentCode: '<?php echo esc_js($this->get_agentCode()); ?>',
                            loginUrl: '<?php echo esc_js($this->get_loginUrl()); ?>'
                        },
                        success: function(response) {
                            console.log("AJAX Response:", response);
                            if (response.success && response.data) {
                                jQuery('#jaib-connection-status').text('Connection successful! Access Token: ' + response.data.accessToken + ', Pin API: ' + response.data.pinApi);
                            } else {
                                console.error("Connection failed:", response.message || 'Unknown error');
                                jQuery('#jaib-connection-status').text('Connection failed: ' + (response.message || 'Unknown error'));
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error("AJAX Error:", error);
                            jQuery('#jaib-connection-status').text('Connection failed: ' + error);
                        }
                    });
                }
            </script>
            <?php
        }
        public function payment_scripts() {
            if (!is_checkout()) {
                return;
            }
            ?>
            <script type="text/javascript">
                function testPayment() {
                    var pinApi = document.getElementById('pinApi').value;
                    var requestID = document.getElementById('requestID').value;
                    var amount = document.getElementById('amount').value;
                    var mobile = document.getElementById('mobile').value;
                    var currencyID = document.getElementById('currencyID').value;
                    var code = document.getElementById('code').value;
                    var notes = document.getElementById('notes').value;
                    var exeBuyUrl = document.getElementById('exeBuyUrl').value;
                    var data = {
                        pinApi: pinApi,
                        requestID: requestID,
                        code: code,
                        amount: amount,
                        currencyID: currencyID,
                        mobile: mobile,
                        notes: notes,
                        exeBuyUrl: exeBuyUrl
                    };
                    jQuery.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'execute_jaib_buy',
                            ...data
                        },
                        success: function(response) {
                            try {
                                var result = response.data.success;
                                var errorMessage = response.data.error ? response.data.error.message : "حدث خطأ أثناء معالجة الاستجابة.";
                                if (response.success && result) {
                                    document.getElementById('payment_result').innerHTML = "تمت المعاملة بنجاح";
                                    jQuery.ajax({
                                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                                        type: 'POST',
                                        data: {
                                            action: 'log_successful_payment',
                                            requestID: requestID,
                                            payment_datetime: new Date().toISOString(),
                                            amount: amount,
                                            mobile: mobile,
                                            order_id: <?php echo get_the_ID(); ?>
                                        }
                                    });
                                } else {
                                    document.getElementById('payment_result').innerHTML = "فشلت المعاملة: " + errorMessage;
                                }
                                jQuery.ajax({
                                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                                    type: 'POST',
                                    data: {
                                        action: 'log_payment_attempt',
                                        try_time: new Date().toISOString(),
                                        result: result ? 'success' : 'fail',
                                        payload: JSON.stringify(data),
                                        response: JSON.stringify(response),
                                        pin_api: pinApi,
                                        request_id: requestID,
                                        amount: amount,
                                        order_id: <?php echo get_the_ID(); ?>,
                                        mobile: mobile
                                    }
                                });
                            } catch (e) {
                                document.getElementById('payment_result').innerHTML = "حدث خطأ أثناء معالجة الاستجابة.";
                            }
                        },
                        error: function(error) {
                            document.getElementById('payment_result').innerHTML = "حدث خطأ أثناء المعالجة.";
                        }
                    });
                }
            </script>
            <?php
        }
        public function payment_fields() {
            if ($this->description) {
                echo wpautop(wptexturize($this->description));
            }
            ?>
           <form id="jaib-payment-form">
                <label for="code">Enter Payment Code:</label>
                <input type="text" id="code" name="code" required>
                <input type="hidden" id="amount" name="amount" value="<?php echo WC()->cart->total; ?>">
                <input type="hidden" id="currencyID" name="currencyID" value="1">
                <input type="hidden" id="notes" name="notes" value="<?php echo 'Order #' . get_the_ID(); ?>">
                <input type="hidden" id="mobile" name="mobile" value="<?php echo WC()->customer->get_billing_phone(); ?>">
                <input type="hidden" id="requestID" name="requestID" value="<?php echo esc_js($_SESSION['requestID']); ?>">
                <input type="hidden" id="pinApi" name="pinApi" value="<?php echo get_option('woocommerce_jaib_pinApi'); ?>">
                <input type="hidden" id="accessToken" name="accessToken" value="<?php echo get_option('woocommerce_jaib_accessToken'); ?>">
                <input type="hidden" id="loginUrl" name="loginUrl" value="<?php echo get_option('woocommerce_jaib_loginUrl'); ?>">
                <input type="hidden" id="exeBuyUrl" name="exeBuyUrl" value="<?php echo esc_js($this->get_exeBuyUrl()); ?>">
                <div id="payment_result"></div>
                <button type="button" id="jaib-payment-button" onclick="testPayment()">Pay with Jaib</button>
            </form>
            <?php
        }
    }
    add_filter('woocommerce_payment_gateways', 'add_jaib_gateway');
    if (!function_exists('add_jaib_gateway')) {
        function add_jaib_gateway($methods) {
            $methods[] = 'WC_Gateway_Jaib';
            return $methods;
        }
    }
}
if (!function_exists('jaib_get_connection_status')) {
    function jaib_get_connection_status() {
        return get_option('woocommerce_jaib_connection_status', 'Not connected');
    }
}
add_action('wp_ajax_init_connection', function() {
    if (!isset($_POST['userName']) || !isset($_POST['password']) || !isset($_POST['agentCode']) || !isset($_POST['loginUrl'])) {
        wp_send_json_error(['message' => 'Missing required fields.']);
    }
    $gateway = new WC_Gateway_Jaib();
    $gateway->set_userName(sanitize_text_field($_POST['userName']));
    $gateway->set_password(sanitize_text_field($_POST['password']));
    $gateway->set_agentCode(sanitize_text_field($_POST['agentCode']));
    $gateway->set_loginUrl(sanitize_text_field($_POST['loginUrl']));
    $gateway->init_connection();
    if (isset($_SESSION['accessToken']) && isset($_SESSION['pinApi'])) {
        wp_send_json_success([
            'accessToken' => $_SESSION['accessToken'],
            'pinApi' => $_SESSION['pinApi']
        ]);
    } else {
        wp_send_json_error(['message' => 'Connection failed.']);
    }
});

add_action('wp_ajax_log_payment_attempt', function() {
    if (!isset($_POST['try_time']) || !isset($_POST['result']) || !isset($_POST['payload']) || !isset($_POST['response']) || !isset($_POST['pin_api']) || !isset($_POST['request_id']) || !isset($_POST['amount']) || !isset($_POST['order_id']) || !isset($_POST['mobile'])) {
        wp_send_json_error(['message' => 'Missing required fields.']);
    }
    $gateway = new WC_Gateway_Jaib();
    $data = array(
        'try_time' => sanitize_text_field($_POST['try_time']),
        'result' => sanitize_text_field($_POST['result']),
        'payload' => sanitize_text_field($_POST['payload']),
        'response' => sanitize_text_field($_POST['response']),
        'pin_api' => sanitize_text_field($_POST['pin_api']),
        'request_id' => sanitize_text_field($_POST['request_id']),
        'amount' => sanitize_text_field($_POST['amount']),
        'order_id' => sanitize_text_field($_POST['order_id']),
        'mobile' => sanitize_text_field($_POST['mobile'])
    );
    $gateway->log_payment_attempt($data);
    wp_send_json_success(['message' => 'Payment attempt logged successfully.']);
});

add_action('wp_ajax_log_successful_payment', function() {
    if (!isset($_POST['requestID']) || !isset($_POST['payment_datetime']) || !isset($_POST['amount']) || !isset($_POST['mobile']) || !isset($_POST['order_id'])) {
        wp_send_json_error(['message' => 'Missing required fields.']);
    }
    $gateway = new WC_Gateway_Jaib();
    $data = array(
        'order_id' => intval($_POST['order_id']),
        'request_id' => sanitize_text_field($_POST['requestID']),
        'amount' => floatval($_POST['amount']),
        'payment_datetime' => sanitize_text_field($_POST['payment_datetime']),
        'mobile' => sanitize_text_field($_POST['mobile'])
    );
    $gateway->log_successful_payment($data);
    wp_send_json_success(['message' => 'Successful payment logged successfully.']);

    // تحديث requestID وزيادته بمقدار 1
    global $wpdb;
    $settings_table = $wpdb->prefix . 'jaib_settings';
    $existing_record = $wpdb->get_row("SELECT id, request_id FROM $settings_table LIMIT 1");
    if ($existing_record) {
        $new_request_id = intval($existing_record->request_id) + 1;
        $wpdb->update($settings_table, array('request_id' => $new_request_id), array('id' => $existing_record->id));
        // تحديث الجلسة بقيمة requestID الجديدة
        $_SESSION['requestID'] = $new_request_id;
    } else {
        wp_send_json_error(['message' => 'Failed to retrieve request ID from the settings table.']);
    }
});
