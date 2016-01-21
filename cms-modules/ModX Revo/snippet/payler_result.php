<?php
//order_id из адресной строки
$p_order_id = $_GET['order_id'];

if(!defined('PAYLER_PATH')) define('PAYLER_PATH', MODX_CORE_PATH."components/payler/");
require_once PAYLER_PATH.'model/payler.class.php';

$payler = new Payler($modx, array('type' => 'OneStep',));

$payler_status = $payler->GetStatus(array('order_id' => $p_order_id));

//переводим order_id в тот, что используется в магазине
$order_id = $payler->PaylerOrderID_To_ModxOrderID($p_order_id);

if($payler_status['status'] == 'Charged') {

    //Заказ успешно оплачен, изменить состояние заказа
    //И совершить какие-то действия - сообщить пользователю/перевести на другую страницу
    /*
    // Этот код идет для примера, должен работать с ShopKeeper 3
    $modx->addPackage('shopkeeper3', $modx->getOption('core_path').'components/shopkeeper3/model/');
    $order = $modx->getObject('shk_order',$order_id);
    if($order){
        $order->set('status', 5);
        $order->save();
        return 'Заказ '.$order_id.' оплачен';
    }
    return 'Заказ '.$order_id.' оплачен, но возникла ошибка. Сообщите об этом администратору';
    */
    return 'Заказ '.$order_id.' оплачен';
}
return 'Заказ '.$order_id.' не оплачен';
