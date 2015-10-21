<?php session_start(); $_SESSION['CustomForm'] = false; ?>
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
    /*
    Страница, на которую пользователь вернется после оплаты на сайте Payler
    ("URL возврата в магазин" в настройках учетной записи Payler)
    */
    include 'payler_class.php';
    include 'settings.php';

    $payler = new Payler($test);
    $type = "OneStep";    // тип платежа

    $data = array (     // Параметры, отмеченные в описании звездочкой (*), обязательны для заполнения

        "key" => $key,  // *Идентификатор продавца, выдается продавцу при регистрации вместе с параметрами доступа

        //"recurrent_template_id" => "rec-pay-f75bf310-177c-457e-82f0-4307d532efd9",//
                        // Идентификатор шаблона рекурентных платежей
                        //     (строка, максимум 100 символов) Должен соответствовать recurrent_template_id см. API Payler - GetStatus  
                        //     Если не заполнен, метод возвращает список всех шаблонов

        );              // Параметры, отмеченные в описании звездочкой (*), обязательны для заполнения 

    //Получаем шаблоны рекурентных платежей
    $result = $payler->POSTtoGateAPI($data, "GetTemplate");
    
    echo '<table>';
    echo '<tr><th>Рекуррентные платежи. Проведение рекуррентного платежа по шаблону</th></tr>';
    foreach($result['templates'] as $tmpl_tm) {
    $tmpl = (array)$tmpl_tm;
        echo '<tr><td>';
        echo 'id шаблона:'.$tmpl['recurrent_template_id'].'<br/>'
             .' Создан:'.$tmpl['created'].'<br/>'
             .' Шаблон активен:'.$tmpl['active'].'<br/>'
             .' Номер карты:'.$tmpl['card_number'].'<br/>'             
             .' Держатель карты:'.$tmpl['card_holder']
             .' Срок действия:'.$tmpl['expiry'].'<br/>';
        echo '</td></tr>';             
    };

    echo '<tr><td>';
    echo '<form method ="POST">'
         . 'Введите id шаблона, по которому надо провести рекуррентный платеж<br/>'.'<input type="text" size = 100 name="template_name" value="">'
         . '<input type="submit" name="RepeatPay" value="Провести">'
         . '</form>';
    echo '</td></tr>';
    
    $order_id = time(); //Номер заказа для рекуррентного платежа
    $amount = 200;      //Сумма нового заказа

    if(isset($_POST['RepeatPay'])) {
        //Совершаем второй платеж по созданному шаблону
        $recurrent_data = array (         // Параметры, отмеченные в описании звездочкой (*), обязательны для заполнения

            'key' => $key,                // *Идентификатор продавца, выдается продавцу при регистрации вместе с параметрами доступа
            'order_id' => $order_id,      // *Идентификатор заказа (платежа). Для каждой сессии должен быть уникальным 
                                          //     (строка, максимум 100 символов, только печатные символы ASCII)
            'amount' => $amount,          // *Сумма платежа, может отличаться от суммы платежа, на основании которого создан шаблон
                                          //     (целое число) в зависимости от валюты изначального платежа - в копейках|центах|евроцентах
            'recurrent_template_id' => $_POST['template_name'],
                                          //  *Идентификатор шаблона рекурентных платежей
                                          //     (строка, максимум 100 символов) должен соответствовать recurrent_template_id, 
                                          //     возвращаемого GetStatus или GetTemplate

        );                                // Параметры, отмеченные в описании звездочкой (*), обязательны для заполнения

        $result_repeatpay = (array)$payler->POSTtoGateAPI($recurrent_data, "RepeatPay");

        echo '<tr><td>';
        echo 'Полученный ответ на RepeatPay: <br/>';
        var_dump($result_repeatpay);
        echo '</td></tr><tr><td>';
        
        //в случае успеха, RepeatPay вернет массив с заполненными amount и order_id
        if($order_id == $result_repeatpay['order_id']) {
            echo 'Заказ ' . $new_order_id . ' оплачен. Успешно списанная сумма: '. $result_repeatpay['amount'];
        }
        else
        {
            echo 'Заказ ' . $new_order_id . ' не удалось оплатить.';        
        }
        echo '</td></tr>';
    }
    echo '<tr><td>'
         . '<form method ="GET" action="/index.php">'
         . '<input type="submit" name="button" value="На главную">'
         . '</form>'
         . '</td></tr>';
?>
</body>
</html>
