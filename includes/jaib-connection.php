<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_test_jaib_connection', 'test_jaib_connection_handler');

if (!function_exists('test_jaib_connection_handler')) {
    function test_jaib_connection_handler() {
        // التحقق من أن جميع الحقول موجودة
        if (!isset($_POST['userName'], $_POST['password'], $_POST['agentCode'], $_POST['loginUrl'])) {
            wp_send_json_error(['message' => 'Missing required fields.']);
            return;
        }

        $userName = sanitize_text_field($_POST['userName']);
        $password = sanitize_text_field($_POST['password']);
        $agentCode = sanitize_text_field($_POST['agentCode']);
        $loginUrl = sanitize_text_field($_POST['loginUrl']);

        // إعداد بيانات الطلب
        $data = array(
            "userName" => $userName,
            "password" => $password,
            "agentCode" => $agentCode
        );

        // إعداد جلسة cURL
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $loginUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept: application/json'
            ),
        ));

        // تنفيذ الطلب والحصول على الاستجابة
        $response = curl_exec($curl);
        if (!curl_errno($curl)) {
            $rData = json_decode($response, true);
            if ($rData['success'] && isset($rData['result'])) {
                // تخزين البيانات في الجلسة
                session_start();
                $_SESSION['accessToken'] = $rData['result']['accessToken'];
                $_SESSION['pinApi'] = $rData['result']['pinApi'];
                // تحديد صلاحية الجلسة بناءً على قيمة expire من الاستجابة
                $_SESSION['expire'] = time() + $rData['result']['expire'];
                error_log('@@login succeeded');

                update_option('woocommerce_jaib_connection_status', 'Connected');
                update_option('woocommerce_jaib_accessToken', $rData['result']['accessToken']);
                update_option('woocommerce_jaib_pinApi', $rData['result']['pinApi']);
                wp_send_json_success([
                    'message' => 'Connection successful!',
                    'accessToken' => $rData['result']['accessToken'],
                    'pinApi' => $rData['result']['pinApi']
                ]);
            } else {
                $error_message = isset($rData['error']['message']) ? $rData['error']['message'] : 'Unknown error';
                wp_send_json_error(['message' => 'Connection failed: ' . $error_message]);
            }
        } else {
            $error_message = curl_error($curl);
            wp_send_json_error(['message' => 'Curl error: ' . $error_message]);
        }
        curl_close($curl);
    }
}
?>