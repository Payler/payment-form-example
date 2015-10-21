<?php
    include 'payler_class.php';
    include 'settings.php';

    $payler = new Payler($test);

    $data = array (     // Параметры, отмеченные в описании звездочкой (*), обязательны для заполнения

        "key" => $key,  // *Идентификатор продавца, выдается продавцу при регистрации вместе с параметрами доступа

        //"recurrent_template_id" => "rec-pay-f75bf310-177c-457e-82f0-4307d532efd9",//
                        // Идентификатор шаблона рекурентных платежей
                        //     (строка, максимум 100 символов) Должен соответствовать recurrent_template_id см. API Payler - GetStatus  

        );              // Параметры, отмеченные в описании звездочкой (*), обязательны для заполнения 

    //Пытаемся получить шаблон
    $result = $payler->POSTtoGateAPI($data, "GetTemplate");

    print_r($result);
