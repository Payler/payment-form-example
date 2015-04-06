<?php
class paylerPayment extends payment {
    
    public function validate() {
        return true;
    }

    public function process($template = null) {
        $this->order->order();
        $test = $this->object->test;
        $key = $this->object->key;

        if(!strlen($test) || !strlen($key)){
            throw new publicException(getLabel('error-payment-wrong-settings'));
        }
        
        $order_id = $this->order->getId();
        $data = array (
            'key' => $key,
            'type' => "Pay",
            'order_id' => $order_id,
            'amount' => (float) 100*$this->order->getActualPrice(),
        );
        $session_data = $this->POSTtoGateAPI($data, $test, "StartSession");
        if ($session_data["error"] <> null) {
            throw new publicException($session_data['error']['message']);
        }
        $session_id = $session_data['session_id'];
        
        $params = array();
        $params['session_id'] = $session_id;
        $params['formAction'] = $this->getURL($test)."Pay";
        $params['orderId'] = $order_id;
        $this->order->setPaymentStatus('initialized');
        list($templateString) = def_module::loadTemplates("emarket/payment/payler/".$template, "form_block");
        return def_module::parseTemplate($templateString, $params);
    }

    public function poll() {
        $order_id = getRequest('order_id');
        
        $cmsController = cmsController::getInstance();
        $protocol = !empty( $_SERVER['HTTPS'] ) ? 'https://' : 'http://';
        $www = $protocol . $cmsController->getCurrentDomain()->getHost();
        $www .= '/emarket/purchase/result/';
        if (preg_match('/^[0-9]*$/',$order_id)) {
            $key = $this->object->key;
            $test = $this->object->test;
            $data = array (
                "key" => $key,
                "order_id" => $order_id
            );
            $result = $this->POSTtoGateAPI($data, $test, "GetStatus");
                if ($result['status'] == 'Charged') {
                    $this->order->setPaymentStatus('accepted');
                    echo '<script type="text/javascript">
                    location.replace("'.$www.'successful/?order_id='.$order_id.'");
                    </script>';
                }
        }
        echo '<script type="text/javascript">
        location.replace("'.$www.'fail/?order_id='.$order_id.'");
        </script>';
    }
    
    /**
     * @desc Отправка POST-запроса при помощи curl.
     *
     * @param $data Массив отправляемых данных
     * @result Ассоциативный массив возвращаемых данных
     */
    private function CurlSendPost ($data, $url) {	
        $headers = array(
            'Content-type: application/x-www-form-urlencoded',
            'Cache-Control: no-cache',
            'charset="utf-8"',
	);
        
        $data = http_build_query($data, '', '&');

        $options = array (
            CURLOPT_URL => $url,
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_VERBOSE => 0,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $data,            
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
        );
        
        $ch = curl_init();
        curl_setopt_array($ch, $options);
		$json = curl_exec($ch);
        if ($json == FALSE) {
            die ('Curl error: ' . curl_error($ch) . '<br>');
        }
        //Преобразуем JSON в ассоциативный массив
        $result = json_decode($json, TRUE);
		curl_close($ch);
        
		return $result;
    }    
    
    /**
    * @desc Отправка запроса в Gate API
    *
    * @param array $data Массив отправляемых данных
    * @param boolean $test На тестовый или боевой хост отправляется запрос
    * @param string $method Метод вызова API
    * @result Ассоциативный массив возвращаемых данных
    */
    private function POSTtoGateAPI ($data, $test, $method) {        
        $url = $this->getURL($test).$method;
        $result = $this->CurlSendPost($data, $url);
        return $result;
    }    
  
    private function getURL ($test) {
        $host = ($test ? "sandbox" : "secure");
        $result = "https://" . $host . ".payler.com/gapi/";
        return $result;
    }
}