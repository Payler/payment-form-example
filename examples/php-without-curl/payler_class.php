<?php

class Payler {
    
    function __construct($test) {
        $this->test = $test;
        $host = ($test ? "sandbox" : "secure");
        $this->base_url = "https://" . $host . ".payler.com/gapi/";
    }
    
    /**
     * @desc Отправка POST-запроса без помощи curl.
     *
     * @param $data Массив отправляемых данных
     * @result Ассоциативный массив возвращаемых данных
     */
    function SendPost ($data) {	
        $json = file_get_contents($this->url, false, stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($data)
            )
            )));        

        if ($json == false) {
            die ('Error! ');
        }
        //Преобразуем JSON в ассоциативный массив
        $result = json_decode($json);

        if(empty($result)) {
            return array();
        }

        return (array)$result;
/*        return array(
            'order_id' => $result->order_id,
            'amount' => $result->amount,
            'session_id' => $result->session_id
        );
*/
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
        $result = $this->SendPost($data);
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

    /**
    * @desc Кнопка, отправит POST-запрос на ту же страницу с submit_<имя метода>
    *
    * @param string $method Метод API
    * @param string $button_text Текст на кнопке
    * @result Кнопка на форме
    */

    public function TwoStepPOSTtoGateAPI ($method, $button_text) {

        $result = '<form method ="POST">'
                . '<input type="hidden" name="'.$method.'" '
                . 'value= "">'
                . '<input type="submit" name="submit_'.$method.'" value="'.$button_text.'">'
                . '</form>';
        return $result;
    }

}
