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
            echo "الجلسة غير صالحة. يرجى تحديث الصفحة والمحاولة مرة أخرى.";
            wp_die();
        }

        // تأكد من أن البيانات تم إرسالها بشكل صحيح
        if (isset($_POST['amount']) && isset($_POST['mobile']) && isset($_POST['code']) && isset($_POST['notes']) && isset($_POST['requestID']) && isset($_POST['exeBuyUrl'])) {
            // استرجاع القيم الأحدث من الجلسة
            $accessToken = $_SESSION['accessToken'];
            $pinApi = $_SESSION['pinApi'];

            $amount = sanitize_text_field($_POST['amount']);
            $mobile = sanitize_text_field($_POST['mobile']);
            $currencyID = sanitize_text_field($_POST['currencyID']);
            $code = sanitize_text_field($_POST['code']);
            $notes = sanitize_textarea_field($_POST['notes']);
            $requestId = sanitize_textarea_field($_POST['requestID']);
            $exeBuyUrl = sanitize_text_field($_POST['exeBuyUrl']);

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

            // إرجاع النتيجة بالنصوص الأصلية من السيرفر
            echo $response;
        } else {
            echo "بيانات غير صحيحة.";
        }

        wp_die(); // مهم لتجنب الاستجابة غير الصالحة من AJAX
    }
}

// إرسال الطلب إلى سيرفر جيب
if (!function_exists('jaib_send_request')) {
    function jaib_send_request($url, $requestBody, $accessToken)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ),
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_TIMEOUT => 0,
        ));
        $response = curl_exec($curl);

        // تشخيص الخطأ في حالة حدوثه
        if (curl_errno($curl)) {
            $error_message = curl_error($curl);
            error_log("Curl error during API request: " . $error_message);
        } else {
            $response_data = json_decode($response, true);
            if (isset($response_data['error']) && !empty($response_data['error'])) {
                error_log("API Error: " . print_r($response_data['error'], true));
            } else {
                error_log("@@API SUCCEED");
            }
        }

        curl_close($curl);
        return $response;
    }
}
?>