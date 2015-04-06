<?php
class Payler {
    
    function __construct($test) {
        $this->test = $test;
        $host = ($test ? "sandbox" : "secure");
        $this->base_url = "https://" . $host . ".payler.com/gapi/";
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
    public function POSTtoGateAPI ($data, $method) {
        $this->url = $this->base_url.$method;
        $result = $this->CurlSendPost($data);
        return $result;
    }
    
    /**
    * @desc Редирект пользователя на оплату
    *
    * @param $session_id Строка содержащая идентификатор сессии
    * @result Ассоциативный массив возвращаемых данных
    */
    function Pay ($session_id) {
        $this->url = $this->base_url."Pay";
        $result = '<form method ="POST" action="'.$this->url.'">'
                . '<input type="hidden" name="session_id" '
                . 'value="'.$session_id.'">'
                . '<input type="submit" name="submit" value="Оплатить заказ">'
                . '</form>';
        return $result;
    }
}