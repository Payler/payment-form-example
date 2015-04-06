#!/usr/bin/php
<?php

    echo "Content-type: text/html\n\n";

    $LOG_FILE = fopen("/usr/local/ispmgr/var/paypalresult.php.log", "a");

    function Debug($log_str) {
        fwrite($GLOBALS["LOG_FILE"], date("M d H:i:s")." [".posix_getpid()."] ".$log_str."\n");
    }

    function defErrorHandler($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) {
           return;
       }
         Debug($errfile.":".$errline." Error: ".$errno.", error message: ".$errstr);
        return true;
    }

    function GetMgrObject($func, $elid, $auth) {
        Debug("execute: ".$func.", elid: ".$elid);
        $out = shell_exec("/usr/local/ispmgr/sbin/mgrctl -m billmgr -o json ".$func." elid=".$elid.($auth != "" ? " auth=".$auth : ""));
        Debug($out);
        return json_decode($out);
    }

    function SetPaymentInfo($func, $elid, $info) {
        Debug("execute: ".$func.", elid: ".$elid);
        $out = shell_exec("/usr/local/ispmgr/sbin/mgrctl -m billmgr -o json ".$func." elid=".$elid.($info != "" ? " info=".escapeshellarg($info) : ""));
        Debug($out);
        return json_decode($out);
    }
    
    /**
     * @desc Отправка POST-запроса при помощи curl.
     *
     * @param arrat $data Массив отправляемых данных
     * @param string $posturl Адрес для отправки запроса
     * @result Ассоциативный массив возвращаемых данных
     */
    function CurlSendPost ($data, $posturl) {	
        $headers = array(
            'Content-type: application/x-www-form-urlencoded',
            'Cache-Control: no-cache',
            'charset="utf-8"',
	);
        
        $data = http_build_query($data, '', '&');

        $options = array (
            CURLOPT_URL => $posturl,
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

    set_error_handler("defErrorHandler");

    if ($_SERVER['REQUEST_METHOD'] == 'POST'){
        $input = file_get_contents("php://stdin");
    } elseif ($_SERVER['REQUEST_METHOD'] == 'GET'){
        $input = $_SERVER['QUERY_STRING'];
    }

    Debug(print_r($_SERVER, true));

    Debug("input data: ".$input);

    $param = array();
    parse_str($input, $param);

    $order_id = $param["order_id"];
    $credit_id = substr($order_id, 0, strpos($order_id, ' '));

    if ($credit_id != "") {
        $credit = GetMgrObject("credit.info", $credit_id, "");

        Debug(print_r($credit, true));

        $url = $credit->{"posturl"};
        $key = $credit->{"key"};
        $amount = (int)100*$credit->{"nativeamount"};
        
        $data =array (
            'key' => $key,
            'order_id' => $order_id,
        );
        $result = CurlSendPost($data, $url."GetStatus");

        Debug($result);

        if ($result['status'] == 'Charged') {
            if($result['amount'] == $amount) {
                SetPaymentInfo("credit.setpaid", $credit_id, $input);
                header("Location: ../manimg/userdata/paysuccess_ru.html");
            } else {
                Debug($credit_id . " wrong amount");
                header("Location: ../manimg/userdata/payfail_ru.html");
            }
        } else {
            Debug($credit_id . "payment failed");
            header("Location: ../manimg/userdata/payfail_ru.html");
        }
    } else {
        Debug($credit_id . "order_id not given");
        header("Location: ../manimg/userdata/payfail_ru.html");
    }
?>