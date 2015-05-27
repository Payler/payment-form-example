<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
</head>
<body>

<?php
include 'payler_class.php';
include 'settings.php';

$payler = new Payler($test);

// номер заказа
// должен быть уникальным
$order_id = time();

// стоимость в копейках
$amount = 100;

// описание товара или заказа
$product = "велосипед для программиста";

$data = array (
    'key' => $key,
    'type' => $type,
    'order_id' => $order_id,
    'amount' => $amount,
    'product' => $product,
    /*'recurrent' => 'TRUE',
    'template' => $template,
     */
);

$session_data = $payler->POSTtoGateAPI($data, "StartSession");
var_dump($session_data);
$session_id = $session_data['session_id'];
$pay = $payler->Pay($session_id);

echo $pay;
?>

</body>
</html>
