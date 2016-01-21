<?php

ini_set( 'display_errors', 1 );
error_reporting( E_ALL );

class Payler {
    
    private $key;
    private $type;
    private $testmode;
    private $url;
    private $spec_symbol;

    public function __construct( modX &$modx, $data = array() ){

        if(!defined('PAYLER_PATH')) define('PAYLER_PATH', MODX_CORE_PATH."components/payler/");
        require PAYLER_PATH.'config.php';

        $this->modx = &$modx;

        $this->key      = $payler_config['key'];
        $this->testmode = $payler_config['testmode'];
        $this->url      = ($this->testmode)?'https://sandbox.payler.com/gapi/':'https://secure.payler.com/gapi/';
        $this->spec_symbol    = $payler_config['spec_symbol'];
        
        if(isset($data['type'])) {
            $this->type = $data['type'];
        } else {
            $this->type = 'OneStep';
        }
    }

    public function StartSession($params) {
        $data = array('key'      => $this->key, 
                      'type'     => $this->type,
                      'order_id' => $params['order_id'],
                      'amount'   => $params['amount'],
                      );
        $result = $this->__POSTtoGateAPI ($data, 'StartSession');
        if(!isset($result['session_id'])) {

            die ("Не удалось создать платежную сессию");
        };
        
        return $result;
    }

    public function Pay($params) {
    
        if(!isset($params['session_id'])) {
            return 'Сессия не найдена, платеж не может быть осуществлен';
        }

        $pay_url = $this->url."Pay";
        $result = '<form method ="POST" action="'.$pay_url.'">'
                . '<input type="hidden" name="session_id" '
                . 'value="'.$params['session_id'].'">'
                . '<input type="submit" name="submit" value="Оплатить через Payler">'
                . '</form>';
        return $result;    
    }
    
    public function GetStatus($params) {
        $data = array('key'      => $this->key, 
                      'order_id' => $params['order_id'],
                      );
        $result = $this->__POSTtoGateAPI ($data, 'GetStatus');
        if(!isset($result['status'])) {
            die ('Не удалось получить статус заказа');
        };

        return $result;
    }

    private function __POSTtoGateAPI($Data, $method) {
        $result = $this->__CurlSendPost($Data, $method);
        return $result;
    }

    private function __CurlSendPost($Data, $method = '') {
        $Headers = array(
            'Content-type: application/x-www-form-urlencoded',
            'Cache-Control: no-cache',
            'charset="utf-8"',
        );

        $data = http_build_query($Data);
        $options = array (
            CURLOPT_URL => $this->url . $method,
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_VERBOSE => 0,
            CURLOPT_HTTPHEADER => $Headers,
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

        $result = json_decode($json, TRUE);
        curl_close($ch);
        return $result;
    }
    
    public function ModxOrderID_To_PaylerOrderID($order_id) {
        return $order_id.$this->spec_symbol.time();
    }
    
    public function PaylerOrderID_To_ModxOrderID($order_id) {
        $pos = strripos( $order_id, $this->spec_symbol);
        if($pos === false) {
            return $order_id;
        }
        return substr($order_id, 0, $pos);
        
    }

    
}

