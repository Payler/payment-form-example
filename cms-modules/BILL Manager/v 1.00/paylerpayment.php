<?php

    $LOG_FILE = fopen("/usr/local/ispmgr/var/paylerpayment.php.log", "a");

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
        $out = shell_exec("/usr/local/ispmgr/sbin/mgrctl -m billmgr -o json ".$func." elid=".$elid.($auth != "" ? " sesid=".$auth : ""));
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
   
    if($_SERVER['REQUEST_METHOD'] == 'POST'){
        $input = file_get_contents("php://stdin");
    }elseif($_SERVER['REQUEST_METHOD'] == 'GET'){
        $input = $_SERVER['QUERY_STRING'];
    }

    $param = array();
    parse_str($input, $param);
   
    $auth = "";
    $lang = "";
   
    if (isset($param["auth"])) {
        Debug("by auth param");
        $auth = $param["auth"];
    } elseif (isset($_COOKIE["billmgr4"])) {
        Debug("by standart cookie");
        $cookie = $_COOKIE["billmgr4"];
        $cookie_param = explode(":", $cookie);
        $lang = $cookie_param[1];
        $auth = $cookie_param[2];
    } elseif (isset($_SERVER["HTTP_COOKIE"])) {
        Debug("by server cookie");
        $cookies = explode("; ", $_SERVER["HTTP_COOKIE"]);
        foreach ($cookies as $cookie) {
            $param_line = explode("=", $cookie);
            if (count($param_line) > 1 && $param_line[0] == "billmgr4") {
                $cookies_bill = explode(":", $param_line[1]);
                $lang = $cookies_bill[1];
                $auth = $cookies_bill[2];
            }
        }
    }

    if ($auth == "") {
        echo "Not authorized";
        exit();
    }

    $credit = GetMgrObject("credit.info", $param["elid"], $auth);
    $key = $credit->{"key"};
    $url = $credit->{"posturl"};
    $amount = (int)100*$credit->{"nativeamount"};
    $order_id = $param["elid"]." timestamp:".time();
    $product = $credit->{"description"};
    $data = array (
        'key' => $key,
        'type' => "OneStep",
        'order_id' => $order_id,
        'amount' => $amount,
        'product' => substr($product,0,255),
    );
    
    $session_data = CurlSendPost($data, $url."StartSession");
    $session_id = $session_data['session_id'];

    echo "<html>\n";
    echo "<head>\n\t<meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>\n";
    echo "\t<link rel='shortcut icon' href='billmgr.ico' type='image/x-icon'/>\n";
    echo "\t<script language='JavaScript'>\n";
    echo "\t\tfunction SubmitForm() {\n";
    echo "\t\t\tdocument.ppform.submit();\n";
    echo "\t\t}\n";
    echo "\t</script>\n";
    echo "</head>\n";
    echo "<body onload='SubmitForm()'>\n";
    echo "\t<form name='ppform' action='".$url."Pay' method='post'>\n";
    //echo "\t\t<input type='hidden' name='cmd' value='_xclick'>\n";
    echo "\t\t<input type='hidden' name='session_id' value='".$session_id."'>\n";
    echo "\t</form>\n";
    echo "</body>\n";
    echo "</html>\n"; 
?>