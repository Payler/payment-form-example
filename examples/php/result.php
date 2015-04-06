<?php
//header('Content-type: text/html; charset=utf-8');
include 'payler_class.php';
include 'settings.php';

$payler = new Payler($test);

$order_id = $_GET['order_id'];
if (preg_match('/^[0-9]*$/',$order_id)) {
    $data = array (
        "key" => $key,
        "order_id" => $order_id
    );
    $result = $payler->POSTtoGateAPI($data, "GetStatus");
        if ($result['status'] == 'Charged') {
            //некие действия при успешной оплате
            echo 'Заказ ' . $order_id . ' оплачен';
        }
}