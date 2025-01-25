<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_execute_jaib_buy', 'test_jaib_payment_handler');
add_action('wp_ajax_nopriv_execute_jaib_buy', 'test_jaib_payment_handler'); // للسماح للمستخدمين غير المسجلين بتنفيذ الطلب

if (!function_exists('test_jaib_payment_handler')) {
    function test_jaib_payment_handler() {
        session_start();

        // التأكد من أن الجلسة صالحة
        if (!isset($_SESSION['accessToken']) || !isset($_SESSION['pinApi']) || !isset($_SESSION['expire']) || time() > $_SESSION['expire']) {
            wp_send_json_error('الجلسة غير صالحة. يرجى تحديث الصفحة والمحاولة مرة أخرى.');
        }

        // تأكد من أن البيانات تم إرسالها بشكل صحيح
        $required_fields = ['amount', 'mobile', 'code', 'notes', 'requestID', 'currencyID', 'exeBuyUrl', 'order_id'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field])) {
                wp_send_json_error("الحقل $field مفقود.");
            }
        }

        // استرجاع القيم الأحدث من الجلسة
        $accessToken = $_SESSION['accessToken'];
        $pinApi = $_SESSION['pinApi'];

        // تعريف القيم
        $amount = sanitize_text_field($_POST['amount']);
        $mobile = sanitize_text_field($_POST['mobile']);
        $currencyID = sanitize_text_field($_POST['currencyID']);
        $code = sanitize_text_field($_POST['code']);
        $notes = sanitize_textarea_field($_POST['notes']);
        $requestId = sanitize_textarea_field($_POST['requestID']);
        $exeBuyUrl = sanitize_text_field($_POST['exeBuyUrl']);
        $order_id = intval($_POST['order_id']); // تأكد من وجود معرف الطلب في البيانات المرسلة

        // تحقق من أن exeBuyUrl ليس فارغًا
        if (empty($exeBuyUrl)) {
            wp_send_json_error("عنوان URL مفقود أو غير صالح.");
        }

        // إعداد البيانات لإرسالها إلى السيرفر
        $data = array(
            'pinApi' => $pinApi,
            'requestID' => $requestId,
            'amount' => $amount,
            'code' => $code,
            'currencyID' => $currencyID,
            'mobile' => $mobile,
            'notes' => $notes,
        );

        // تحويل البيانات إلى JSON
        $requestBody = json_encode($data);

        error_log("Request Body: " . $requestBody);
        
        // إرسال البيانات إلى السيرفر
        $response = jaib_send_request($exeBuyUrl, $requestBody, $accessToken);

        // تحقق مما إذا كانت عملية الدفع ناجحة
        $payment_successful = isset($response['success']) && $response['success'];

        // إذا كانت عملية الدفع ناجحة، قم بتحديث حالة الطلب
        if ($payment_successful) {
            update_order_status_after_payment($order_id);
        }

        // إرجاع النتيجة بالنصوص الأصلية من السيرفر
        wp_send_json_success($response);
    }
}

// إرسال الطلب إلى سيرفر جيب
if (!function_exists('jaib_send_request')) {
    function jaib_send_request($url, $requestBody, $accessToken) {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ),
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_TIMEOUT => 30, // زيادة مهلة الاتصال إلى 30 ثانية
        ));
        $response = curl_exec($curl);

        // تشخيص الخطأ في حالة حدوثه
        if (curl_errno($curl)) {
            $error_message = curl_error($curl);
            error_log("Curl error during API request: " . $error_message);
            return ['error' => $error_message];
        } else {
            $response_data = json_decode($response, true);
            if (isset($response_data['error']) && !empty($response_data['error'])) {
                error_log("API Error: " . print_r($response_data['error'], true));
                return $response_data;
            } else {
                error_log("@@API SUCCEED");
                return $response_data;
            }
        }

        curl_close($curl);
        return $response;
    }
}

// تحديث حالة الطلب بعد الدفع الناجح
function update_order_status_after_payment($order_id) {
    $order = wc_get_order($order_id);

    if (!empty($order)) {
        $order->update_status('completed'); // أو 'processing' إذا كنت تفضل ذلك
    }
}

// تأكد من استدعاء الدالة بعد الدفع الناجح
add_action('woocommerce_payment_complete', 'update_order_status_after_payment');

// أو يمكنك استخدام هذا الكود لتغيير حالة الطلب بناءً على شروط معينة
add_action('woocommerce_order_status_changed', 'change_order_status_conditionally', 10, 4);
function change_order_status_conditionally($order_id, $status_from, $status_to, $order) {
    if ($order->get_payment_method() === 'jaib' && $status_from === 'delivery-unpaid' && $status_to === 'delivery-paid') {
        $order->update_status('completed'); // أو 'processing' إذا كنت تفضل ذلك
    }
}
?>
