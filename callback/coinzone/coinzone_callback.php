<?php
include('../../includes/application_top_callback.php');

$headers = getallheaders();
$content = file_get_contents("php://input");
$input = json_decode($content);

$schema = isset($_SERVER['HTTPS']) ? "https://" : "http://";
$currentUrl = $schema . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

$sqlApiKey = xtc_db_query("SELECT configuration_value
                                           FROM " . TABLE_CONFIGURATION . "
                                          WHERE configuration_key = 'MODULE_PAYMENT_COINZONE_API_KEY'");
$apiKey = xtc_db_fetch_array($sqlApiKey)['configuration_value'];

$stringToSign = $content . $currentUrl . $headers['timestamp'];
$signature = hash_hmac('sha256', $stringToSign, $apiKey);
if ($signature !== $headers['signature']) {
    header("HTTP/1.0 400 Bad Request");
    exit("Invalid callback");
}

switch($input->status) {
    case "PAID":
    case "COMPLETE":
        $_statusID = 163;
        xtc_db_query("update ". TABLE_ORDERS . " set orders_status = '" . (int)$_statusID . "' where orders_id='" . (int)$input->merchantReference . "'");
        $sql_data_array = array(
            'orders_id' => $input->merchantReference,
            'orders_status_id' => $_statusID,
            'date_added' => 'now()',
            'customer_notified' => '0',
            'comments' => 'Bitcoin Payment Received');

        xtc_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
        exit('OK_PAID');
        break;
}
header("HTTP/1.0 400 Bad Request");
exit("Error");
