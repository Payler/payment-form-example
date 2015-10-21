<?php session_start();?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<style type="text/css" media ="all">
	@import url('css/style.css'); 
	</style>
</head>
<body>
<?php

    echo '<table>';
    echo '<tr><th>Страница возврата</th></tr>';

    /*
    Страница, на которую пользователь вернется после оплаты на сайте Payler
    ("URL возврата в магазин" в настройках учетной записи Payler)
    
    Обычно, при открытии страницы Payler параметром передает order_id

    Берем order_id из URL, по которому открыли страницу
    По полученному order_id получаем статус оплаты заказа 
    По статусу смотрим, был ли создан шаблон рекурентных платежей
    Если да - совершаем по созданному шаблону еще один платеж.
    И отключаем шаблон для примера.
    Запрашиваем шаблон, чтобы увидеть его состояние
    */
    include 'payler_class.php';
    include 'settings.php';

    $payler = new Payler($test);
    $order_id = $_GET['order_id'];

    //Получаем расширенный статус платежа
    //В примерах используется одна страница возврата, потому он нам нужен, чтобы узнать, одностадийный это был платеж или нет.
    //Это надо, чтобы понять, какой пример мы используем
    //В реальном магазине подобная информация скорее всего хранится в базе с информацией о заказе
    // ***
    $data = array (
        "key" => $key,
        "order_id" => $order_id
    );
    $result_advanced = (array)$payler->POSTtoGateAPI($data, "GetAdvancedStatus");
    if(isset($result_advanced['type'])) {
        $type = $result_advanced['type'];
    }
    // ***

    if (preg_match('/^[0-9]*$/',$order_id) || true) {

        $data = array (
            "key" => $key,
            "order_id" => $order_id
        );

        //Получаем статус платежа
        $result = (array)$payler->POSTtoGateAPI($data, "GetStatus");

        echo '<tr><td>';
        echo 'Полученный ответ на GetStatus: <br/>';
        var_dump($result);
        echo '</td></tr><tr><td>';
        
        if($type == "OneStep") {
            // ******** ОДНОСТАДИЙНЫЙ ПЛАТЕЖ - ПРИМЕР ОДНОСТАДИЙНОГО ПЛАТЕЖА, РЕКУРЕНТНОГО ПЛАТЕЖА, ПОЛЬЗОВАТЕЛЬСКОЙ ФОРМЫ ОПЛАТЫ, IFRAME
            if ($result['status'] == 'Charged') {
                echo 'Заказ ' . $order_id . ' оплачен';

                // ******** ПРИМЕР НЕСТАНДАРТНОЙ ФОРМЫ ОПЛАТЫ
                //    Если на форме используются дополнительные поля, то статус заказа надо получать через GetAdvancedStatus
                //    В примере он вызывался ранее, потому просто смотрим его поля
                //Если user_entered_params заполнен, это нестандартная форма оплаты с дополнительными полями
                if(count((array)$result_advanced['user_entered_params']) > 0) {
                    echo '<br/>'.'Дополнительные поля, заполненные в форме оплаты: ';
                    
                    $params = (array)((array)$result_advanced['user_entered_params']);
                    
                    echo '<br/>'.'Лицевой счет: ' . $params['user_entered_account_number'];
                    echo '<br/>'.'Комментарий: ' . $params['user_entered_comment'];
                }
                
                echo '</td></tr>';
                
                // ******** ПРИМЕР РЕКУРРЕНТНОГО ПЛАТЕЖА
                //Если id заполнен, шаблон удалось создать
                if(isset($result["recurrent_template_id"])) {
                    echo '<tr><td>';
                    echo 'Создан шаблон рекуррентных платежей. Номер шаблона: ' . $result['recurrent_template_id'];
                    echo '</td></tr><tr><td>';

                    //Совершаем второй платеж по созданному шаблону

                    $new_order_id = time(); //Номер заказа для рекуррентного платежа
                    $new_amount = 200;      //Сумма нового заказа

                    $recurrent_data = array (         // Параметры, отмеченные в описании звездочкой (*), обязательны для заполнения

                        'key' => $key,                // *Идентификатор продавца, выдается продавцу при регистрации вместе с параметрами доступа
                        'order_id' => $new_order_id,  // *Идентификатор заказа (платежа). Для каждой сессии должен быть уникальным 
                                                      //     (строка, максимум 100 символов, только печатные символы ASCII)
                        'amount' => $new_amount,      // *Сумма платежа, может отличаться от суммы платежа, на основании которого создан шаблон
                                                      //     (целое число) в зависимости от валюты изначального платежа - в копейках|центах|евроцентах
                        'recurrent_template_id' => $result['recurrent_template_id'],
                                                      //  *Идентификатор шаблона рекурентных платежей
                                                      //     (строка, максимум 100 символов) должен соответствовать recurrent_template_id, 
                                                      //     возвращаемого GetStatus или GetTemplate

                    );                                // Параметры, отмеченные в описании звездочкой (*), обязательны для заполнения

                    $result_repeatpay = $payler->POSTtoGateAPI($recurrent_data, "RepeatPay");

                    echo 'Полученный ответ на RepeatPay: <br/>';
                    var_dump($result_repeatpay);
                    echo '</td></tr><tr><td>';

                    //в случае успеха, RepeatPay вернет массив с заполненными amount и order_id
                    if($new_order_id == $result_repeatpay['order_id']) {
                        echo 'Заказ ' . $new_order_id . ' оплачен. Успешно списанная сумма: '. $result_repeatpay['amount'];
                    }
                }
            } else {
                echo 'Заказ ' . $order_id . ' не оплачен';
            }
            echo '</td></tr>';
        } else
        {
        // ******** ПРИМЕР ДВУХСТАДИЙНОГО ПЛАТЕЖА
            //При двухстадийном платеже, в случае успеха, статус платежа будет Authorized
            if ($result['status'] == 'Authorized') {
                //некие действия при успешной первой стадии,
                echo 'Заказ ' . $order_id . ' принят, деньги на карте заблокированы. Менеджер проверит заказ и свяжется с вами';
                echo '</td></tr><tr><td>';

                //ПРИМЕЧАНИЕ: хотя деньги до операции Charge лишь задержаны на карте и будут возвращены через некоторое время,
                //владелец карты может считать, что они были списаны.

                if(true) {
                //СЛЕДУЮЩИЙ БЛОК В РЕАЛЬНОМ МАГАЗИНЕ ДОЛЖЕН РАСПОЛАГАТЬСЯ В ЧАСТИ АДМИНИСТРАТОРА
                //НО ДЛЯ ПРИМЕРА ВЫНЕСЕН НА ОБЩУЮ СТРАНИЦУ
            
                    $amount = 100; //сумма заказа

                    //Параметры для методов Charge и Retrieve
                    $twostep_data = array (      // Параметры, отмеченные в описании звездочкой (*), обязательны для заполнения

                        "password" => $password, // *Пароль продавца, выдается продавцу при регистрации вместе с параметрами доступа
                        "key" => $key,           // *Идентификатор продавца, выдается продавцу при регистрации вместе с параметрами доступа
                        "order_id" => $order_id, // *Идентификатор заказа (платежа). (строка, максимум 100 символов, только печатные символы ASCII)
                        "amount" => $amount,     // *Сумма списания (для Charge) или разблокировки (для Retrieve) в копейках|центах|евроцентах (зависит от валюты заказа)
                                                 //     Для Charge должна быть точно равна сумме заказа, для Retrive не должна превышать сумму, указанную в заказе, 
    
                    );                           // Параметры, отмеченные в описании звездочкой (*), обязательны для заполнения

                    //Создадим кнопки для подтверждения и отмены платежа. Должны быть доступны для администратора, не пользователя
                    echo $payler->TwoStepPOSTtoGateAPI("Charge", "Завершить заказ. Списать деньги");
                    echo $payler->TwoStepPOSTtoGateAPI("AllRetrieve", "Изменить заказ. Разблокировать всю сумму");
                    echo $payler->TwoStepPOSTtoGateAPI("Retrieve", "Изменить заказ. Разблокировать часть суммы");
                 //КОНЕЦ БЛОКА   
                 }
            
            } else {
                //некие действия в том случае, если оплата не прошла
            }
            echo '</td></tr>';
        
        }
    }

    // ******** ПРИМЕР ДВУХСТАДИЙНОГО ПЛАТЕЖА

    //СЛЕДУЮЩИЙ БЛОК В РЕАЛЬНОМ МАГАЗИНЕ ДОЛЖЕН РАСПОЛАГАТЬСЯ В ЧАСТИ АДМИНИСТРАТОРА
    //НО ДЛЯ ПРИМЕРА ВЫНЕСЕН НА ОБЩУЮ СТРАНИЦУ
    if (true) {
        //Обработка нажатий на кнопки "Завершить заказ" и "Отменить заказ"
        if(isset($_POST['submit_Charge'])) {
            echo '<tr><td>';
            echo 'Списываем деньги с карты пользователя... <br/>';

            //Списываем деньги
            $charge_result = $payler->POSTtoGateAPI($twostep_data, "Charge");

            echo 'Полученный ответ на Charge:<br/>';
            var_dump($charge_result);
            echo '</td></tr><tr><td>';
            
            //В случае успешного списания amount будет равно списанной сумме
            if (($charge_result['order_id'] == $order_id)&&($charge_result['amount'] > 0)) {
                //Статус платежа должен измениться на Charged, проверим
                $data = array (
                    "key" => $key,
                    "order_id" => $order_id
                );
                $result = $payler->POSTtoGateAPI($data, "GetStatus");
                echo 'Списание прошло успешно! Статус заказа после Charge: '.$result['status'];
                echo '</td></tr>';
            }
        } else if(isset($_POST['submit_AllRetrieve'])) {
            echo '<tr><td>';
            echo 'Разблокируем деньги на карте пользователя... <br/>';

            //Списываем деньги
            $charge_result = $payler->POSTtoGateAPI($twostep_data, "Retrieve");

            echo 'Полученный ответ на Retrieve:<br/>';
            var_dump($charge_result);
            echo '</td></tr><tr><td>';

            //В случае успешной разблокировки new_amount будет новой сумме заказа
            if (($charge_result['order_id'] == $order_id)&&($charge_result['new_amount'] >= 0)) {
                //Статус платежа при разблокировке всех средств, должен измениться, проверим
                $data = array (
                    "key" => $key,
                    "order_id" => $order_id
                );
                $result = $payler->POSTtoGateAPI($data, "GetStatus");
                echo 'Разблокировка прошла успешно! Статус заказа после Retrieve: '.$result['status'];
                echo '</td></tr>';
            }
        } else if(isset($_POST['submit_Retrieve'])) {
            echo '<tr><td>';
            echo 'Разблокируем деньги на карте пользователя... <br/>';

            //Списываем деньги
            $charge_result = $payler->POSTtoGateAPI($twostep_data, "Retrieve");

            echo 'Полученный ответ на Retrieve:<br/>';
            var_dump($charge_result);
            echo '</td></tr><tr><td>';

            //В случае успешной разблокировки new_amount будет новой сумме заказа
            if (($charge_result['order_id'] == $order_id)&&($charge_result['new_amount'] >= 0)) {
                //Статус платежа при разблокировке части средств средств, должен измениться, проверим
                $data = array (
                    "key" => $key,
                    "order_id" => $order_id
                );
                $result = $payler->POSTtoGateAPI($data, "GetStatus");
                echo 'Разблокировка прошла успешно! Статус заказа после Retrieve: '.$result['status'];
                echo '</td></tr>';
            }
        }
    };

    echo '<tr><td>'
         . '<form method ="GET" action="/index.php">'
         . '<input type="submit" name="button" value="На главную">'
         . '</form>'
         . '</td></tr>';
    
    echo '</table>';
    //КОНЕЦ БЛОКА  
