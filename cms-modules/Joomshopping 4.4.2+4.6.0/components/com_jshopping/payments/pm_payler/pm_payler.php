<?php
/*
* @package JoomShopping for Joomla!
* @subpackage payment
* @author Payler LLC
* @copyright Copyright (C) 2014 Payler LLC. All rights reserved.
* @license GNU General Public License version 2 or later
*/

//защита от прямого доступа
defined('_JEXEC') or die();

class pm_payler extends PaymentRoot
{
    function showPaymentForm($params, $pmconfigs) {
        include(dirname(__FILE__).'/paymentform.php');
    }

    /**
     * Подключение необходимого языкового файла для модуля
     */
    function loadLanguageFile() {
        $lang = JFactory::getLanguage();
        $langtag = $lang->getTag(); //определяем текущий язык

        if (file_exists(JPATH_ROOT.'/components/com_jshopping/payments/pm_payler/lang/'.$langtag.'.php')) {
            require_once(JPATH_ROOT.'/components/com_jshopping/payments/pm_payler/lang/'.$langtag.'.php');
        } else {
            //если языковый файл не найден, то подключаем en-GB.php
            require_once(JPATH_ROOT.'/components/com_jshopping/payments/pm_payler/lang/en-GB.php');
        }
    }
    
    /**
     * Данный метод отвечает за настройки плагина в админ. части
     * @param $params Параметры настроек плагина
     */
    function showAdminFormParams($params) {
        $array_params = array(
            'payler_key', 
            'payler_test_mode',
            'transaction_end_status',
            'transaction_pending_status',
            'transaction_failed_status'
        );

        foreach ($array_params as $key) {
            if (!isset($params[$key])) {
                $params[$key] = '';
            }
        }

        $orders = JModelLegacy::getInstance('orders', 'JshoppingModel');		
        $this->loadLanguageFile(); //подключаем нужный язык		
        include(dirname(__FILE__).'/adminparamsform.php');
    }
    
    /**
     * Собирает информацию о заказе и настройках модуля, инициализирует сессию
     * Из полученных данных генерирует HTML форму и выводит её.
     * @param array $pmconfigs Массив настроек модуля оплаты
     * @param object $order Объект текущего заказа, по которому происходит оформление
     */
    function showEndForm($pmconfigs, $order) {
        $data = array (
            'key' => $pmconfigs['payler_key'],
            'type' => 'Pay',
            'order_id' => $order->order_id.'|'.time(),
            'amount' => 100*number_format(floatval($order->order_total), 2, '.', ''),
            'product' => substr(sprintf(_JSHOP_PAYMENT_NUMBER, (int)$order->order_number),0,255),
            /* 'total' => $total,
            'template' => $template,
            'lang' => $lang,
             */
        );
        
        $session_data = $this->POSTtoGateAPI($pmconfigs, $data, "StartSession");

        $session_id = $session_data['session_id'];
        
        if(isset($session_data['session_id'])) {
           $test = $pmconfigs['payler_test_mode'];
           $host = ($test ? "sandbox" : "secure");
           $url = "https://" . $host . ".payler.com/gapi/Pay";
        }
        ?>
        <html>
            <head>
                <meta http-equiv="content-type" content="text/html; charset=utf-8" />
            </head>
            <body>
                <form id="paymentform" method ="POST" action="<?=$url?>">
                    <input type="hidden" name="session_id" value="<?=$session_id?>">
                </form>
            <?php echo _JSHOP_REDIRECT_TO_PAYMENT_PAGE; ?>
            <br>
            <script type="text/javascript">document.getElementById('paymentform').submit();</script>
            </body>
        </html>
        <?php
        die();
    }
  
    /**
     * Вызывается при обработке запросов на Success Url, Fail Url и Result Url перед методом CheckTransaction().
     * Инициализирует массив с параметрами обработки входящего запроса, содержащий:
     * 'order_id' - идентификатор заказа
     * 'hash' - хэш строка для проверки подлинности
     * 'checkHash' - флаг, указывающий осуществлять ли проверку хэша
     * 'checkReturnParams' - флаг, указывающий осуществлять ли проверку входных параметров
     * @param $pmconfigs Массив настроек способа оплаты
     * @return array Массив с параметрами обработки входящего запроса
     */
    function getUrlParams($pmconfigs) {                        
        $params = array(); 
        $input = JFactory::$application->input;
        $info = $input->getString('order_id', null);
        $order_info = explode("|", $info);
        $params['order_id'] = $order_info[0];
        $params['hash'] = '';
        $params['checkHash'] = 0;
        $params['checkReturnParams'] = 0;

        return $params;
    }
    
    /**
     * Выполняет "проверку" транзакций (обработку запросов на Success Url, Fail Url и Result Url).
     * В результате возвращает двумерный массив с результатом проверки транзакции в виде array($rescode, $restext), где
     * $rescode - код результата транзакции (1 - оплата завершена, 2 - ожидание, 3 - отмена, 0 - ошибка)
     * $restext - текстовое сообщение о результате транзакции
     * В зависимости от переданного кода происходит создание заказа, изменение статуса заказа и email оповещение администратора магазина и покупателя.
     * Дальнейшее управление передаётся на метод nofityFinish().
     * @param $pmconfig Массив настроек модуля оплаты
     * @param $order Объект текущего заказа, по которому происходит оформление
     * @param $rescode Тип запроса (notify, return, cancel)
     * @return array Двумерный массив с результатом проверки транзакции
     */
    function checkTransaction($pmconfigs, $order, $rescode) {
        // подгружаем языковой файл для описания возможных ошибок
        $this->loadLanguageFile();
        // получаем объект, содержащий входные данные (GET и POST), исп. вместо deprecated JRequest::getInt('var')
        $inputObj = JFactory::$application->input;
        $order_id = $inputObj->getString('order_id', null);
        $data = array (
            "key" => $pmconfigs['payler_key'],
            "order_id" => $order_id
        );
        $result = $this->POSTtoGateAPI($pmconfigs, $data, "GetStatus");
        if ($result['status'] == 'Charged') {
            return array(1, '');
        }
    }
        
    /**
     * @desc Отправка POST-запроса при помощи curl.
     *
     * @param $data Массив отправляемых данных
     * @result Ассоциативный массив возвращаемых данных
     */
    function CurlSendPost ($data) {	
        $headers = array(
            'Content-type: application/x-www-form-urlencoded',
            'Cache-Control: no-cache',
            'charset="utf-8"',
	);
        
        $data = http_build_query($data, '', '&');
        
        $options = array (
            CURLOPT_URL => $this->url,
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_VERBOSE => 0,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $data,            
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
        );
        
        $ch = curl_init();
        curl_setopt_array($ch, $options);
	$json = curl_exec($ch);
        if ($json == false) {
            die ('Curl error: ' . curl_error($ch) . '<br>');
        }
        //Преобразуем JSON в ассоциативный массив
        $result = json_decode($json, TRUE);
	curl_close($ch);
        
	return $result;
    }    
    
    /**
    * @desc Обмен данными с Gate API Payler
    *
    * @param array $data Массив отправляемых данных
    * @param string $method Метод API
    * @result Ассоциативный массив возвращаемых данных
    */
    public function POSTtoGateAPI ($pmconfigs, $data, $method) {
        $test = $pmconfigs['payler_test_mode'];
        $host = ($test ? "sandbox" : "secure");
        $this->url = "https://" . $host . ".payler.com/gapi/".$method;
        $result = $this->CurlSendPost($data);
        return $result;
    }
}
?>
