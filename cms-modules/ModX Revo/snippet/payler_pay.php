<?php
//передаваемые параметры в сниплет
$p_order_id = $scriptProperties['order_id'];
$p_amount =   $scriptProperties['amount'];

if(!defined('PAYLER_PATH')) define('PAYLER_PATH', MODX_CORE_PATH."components/payler/");
require_once PAYLER_PATH.'model/payler.class.php';

$payler = new Payler($modx, array('type' => 'OneStep',));

//для каждой сессии надо создавать уникальное имя заказа
$p_order_id = $payler->ModxOrderID_To_PaylerOrderID($p_order_id);

$payler_session = $payler->StartSession(array('order_id' => $p_order_id, 'amount' => $p_amount ));

return $payler->Pay(array('session_id' => $payler_session['session_id']));
