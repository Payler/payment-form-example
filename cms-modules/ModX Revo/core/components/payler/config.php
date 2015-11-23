<?
$payler_config = array (
    'key'         => "ПЛАТЕЖНЫЙ КЛЮЧ", //Платежный ключ Payler
    'testmode'    => 1,                //Тестовый режим 1- включен, 0 - выключен
    'spec_symbol' => '-',              //Специальный символ, для обеспечения уникальности order_id
                                       //order_id в шлюзе Payler - это order_id на сайте + spec_symbol + time()
);

