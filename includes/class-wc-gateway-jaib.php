<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WC_Payment_Gateway')) {
    return;
}

class WC_Gateway_Jaib extends WC_Payment_Gateway {
    private $loginUrl, $userName, $password, $agentCode, $exeBuyUrl, $requestID;

    public function __construct() {
        $this->id = 'jaib';
        $this->method_title = __('Jaib Payment Gateway', 'jaib');
        $this->method_description = __('Jaib payment gateway.', 'jaib');
        $this->icon = plugins_url('jaib.png', __FILE__); // Set the icon URL if needed
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->loginUrl = $this->get_option('loginUrl');
        $this->userName = $this->get_option('userName');
        $this->password = $this->get_option('password');
        $this->agentCode = $this->get_option('agentCode');
        $this->exeBuyUrl = $this->get_option('exeBuyUrl');
        $this->requestID = $this->get_option('requestID');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('admin_footer', [$this, 'admin_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
        add_action('wp', [$this, 'init_connection']); // استدعاء دالة الاتصال عند تحميل أي صفحة

        // استدعاء دالة الاتصال بمجرد تحميل الإضافة
        $this->init_connection();
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
            'description' => [
                'title' => __('Description', 'jaib'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'jaib'),
                'default' => __('Pay with your Jaib eWallet.', 'jaib'),
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
            'exeBuyUrl' => [
                'title' => __('Execute Buy URL', 'jaib'),
                'type' => 'text',
                'description' => __('URL for executing buy online by code.'),
                'default' => ''
            ],
            'requestID' => [
                'title' => __('Request ID', 'jaib'),
                'type' => 'text',
                'description' => __('Request ID for the transaction.'),
                'default' => ''
            ],
            'test_connection' => [
                'title' => __('Test Connection', 'jaib'),
                'type' => 'button',
                'description' => '<div id="jaib-connection-status">' . jaib_get_connection_status() . '</div>',
                'custom_attributes' => ['onclick' => 'testConnection()'],
                'default' => __('Test Connection'),
            ],
        ];
    }

    public function admin_scripts() {
        ?>
        <script type="text/javascript">
            function testConnection() {
                jQuery.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    method: 'POST',
                    data: {
                        action: 'test_jaib_connection',
                        userName: '<?php echo esc_js($this->userName); ?>',
                        password: '<?php echo esc_js($this->password); ?>',
                        agentCode: '<?php echo esc_js($this->agentCode); ?>',
                        loginUrl: '<?php echo esc_js($this->loginUrl); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            jQuery('#jaib-connection-status').text('Connection successful!');
                        } else {
                            jQuery('#jaib-connection-status').text('Connection failed: ' + response.data.message);
                        }
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
                var data = {
                    pinApi: pinApi,
                    requestID: requestID,
                    code: code,
                    amount: amount,
                    currencyID: currencyID,
                    mobile: mobile,
                    notes: notes
                };

                jQuery.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'execute_jaib_buy',
                        ...data
                    },
                    success: function(response) {
                        document.getElementById('payment_result').innerHTML = response;
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
            <input type="hidden" id="currencyID" name="currencyID" value="1"> <!-- YER=1, USD=2, SAR=3 -->
            <input type="hidden" id="notes" name="notes" value="<?php echo 'Order #' . get_the_ID(); ?>">
            <input type="hidden" id="mobile" name="mobile" value="<?php echo WC()->customer->get_billing_phone(); ?>">
            <input type="hidden" id="requestID" name="requestID" value="<?php echo esc_js($this->requestID); ?>">
            <input type="hidden" id="pinApi" name="pinApi" value="<?php echo get_option('woocommerce_jaib_pinApi'); ?>">
            <div id="payment_result"></div>
            <button type="button" id="jaib-payment-button" onclick="testPayment()">Pay with Jaib</button>
        </form>
        <?php
    }

    // دالة الاتصال التي سيتم استدعاؤها عند تحميل الإضافة
    public function init_connection() {
        session_start();

        // التحقق من وجود الجلسة وصلاحيتها
        if (!isset($_SESSION['accessToken']) || !isset($_SESSION['pinApi']) || !isset($_SESSION['expire']) || time() > $_SESSION['expire']) {
            // إذا كانت الجلسة غير موجودة أو انتهت صلاحيتها، نقوم بإعادة الاتصال بسيرفر جيب
            $connection_result = $this->testConnection();

            // تحقق من نجاح الاتصال
            if ($connection_result) {
                // تحديث الجلسة
                $_SESSION['accessToken'] = $connection_result['accessToken'];
                $_SESSION['pinApi'] = $connection_result['pinApi'];
                $_SESSION['expire'] = time() + 3600; // صلاحية الجلسة لمدة ساعة
            } else {
                error_log("فشل الاتصال بسيرفر جيب عند تحميل الإضافة.");
            }
        }
    }

    // دالة الاتصال بسيرفر جيب
    public function testConnection() {
        // منطق الاتصال بسيرفر جيب والحصول على accessToken و pinApi
        // مثال على كيفية الاتصال
        $response = wp_remote_post($this->loginUrl, array(
            'method' => 'POST',
            'body' => json_encode(array(
                'userName' => $this->userName,
                'password' => $this->password,
                'agentCode' => $this->agentCode,
            )),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['accessToken']) && isset($data['pinApi'])) {
            return array(
                'accessToken' => $data['accessToken'],
                'pinApi' => $data['pinApi'],
            );
        }

        return false;
    }
}

// تعريف الدالة jaib_get_connection_status
if (!function_exists('jaib_get_connection_status')) {
    function jaib_get_connection_status() {
        $accessToken = get_option('woocommerce_jaib_accessToken');
        return $accessToken ? 'Connected' : 'Not connected';
    }
}
?>