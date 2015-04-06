<?php
include 'payler_class.php';
include 'settings.php';

$payler = new Payler($test);

//номер заказа в интернет-магазине, должен быть уникальным
$order_id = time();
//стоимость в копейках
$amount = 100;

$data = array (
    'key' => $key,
    'type' => $type,
    'order_id' => $order_id,
    'amount' => $amount,
	/*'recurrent' => 'TRUE',
    'product' => $product,
    'total' => $total,
    'template' => $template,
    'lang' => $lang,
     */
);

$session_data = $payler->POSTtoGateAPI($data, "StartSession");
var_dump($session_data);
$session_id = $session_data['session_id'];
$pay = $payler->Pay($session_id);

echo $pay;