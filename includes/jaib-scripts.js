function connect() {
    jQuery.ajax({
        url: ajaxurl,
        method: 'POST',
        data: {
            action: 'init_connection',
            userName: jaib_script_vars.userName,
            password: jaib_script_vars.password,
            agentCode: jaib_script_vars.agentCode,
            loginUrl: jaib_script_vars.loginUrl
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
        url: ajaxurl,
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
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'log_successful_payment',
                            requestID: requestID,
                            payment_datetime: new Date().toISOString(),
                            amount: amount,
                            mobile: mobile,
                            order_id: jaib_script_vars.order_id
                        }
                    });
                } else {
                    document.getElementById('payment_result').innerHTML = "فشلت المعاملة: " + errorMessage;
                }
                jQuery.ajax({
                    url: ajaxurl,
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
                        order_id: jaib_script_vars.order_id,
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
