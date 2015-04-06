#!/usr/bin/php
<?php
	$LOG_FILE = fopen("/usr/local/ispmgr/var/pmpayler.php.log", "a");

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
       
       class DB extends mysqli {
           public function __construct($host, $user, $pass, $db) {
               parent::init();
               if (!parent::options(MYSQLI_INIT_COMMAND, "SET AUTOCOMMIT = 0")) {
                   die("Установка MYSQLI_INIT_COMMAND завершилась провалом");
               }
               if (!parent::options(MYSQLI_OPT_CONNECT_TIMEOUT, 5)) {
                   die("Установка MYSQLI_OPT_CONNECT_TIMEOUT завершилась провалом");
               }
               if (!parent::real_connect($host, $user, $pass, $db)) {
                   die("Ошибка подключения (".mysqli_connect_errno().") ".mysqli_connect_error());
               }
               Debug("MySQL connection established");
           }
           
           public function __destruct() {
               parent::close();
               Debug("MySQL connection closed");
           }
       }
       
       function DBConnect() {
           $config = file_get_contents("/usr/local/ispmgr/etc/billmgr.conf");
           $lines = explode("\n", $config);
           $params = array();
           $params["DBHost"] = "localhost";
           $params["DBUser"] = "root";
           $params["DBName"] = "billmgr";
           foreach ($lines as $line) {
               $param_line = preg_split("/\s+/", $line, 2);
               if (count($param_line) == 2) {
                   $params[$param_line[0]] = $param_line[1];
               }
           }
           
           return new DB($params["DBHost"], $params["DBUser"], $params["DBPassword"], $params["DBName"]);
       }
       
       function GetInput() {
           return file_get_contents("php://stdin");
       }

       /*function PMValidate() {
           $input = GetInput();
           Debug("input: ".$input);
           $pm_params = new SimpleXMLElement($input);
           if (!filter_var((string)$pm_params->aemail, FILTER_VALIDATE_EMAIL)) {
               echo "aemail";
           } else {
               echo "pmok";
           }
       }*/
       
       function CRGet() {
           $db = DBConnect();
           $input = GetInput();
           Debug("input: ".$input);
           $cr_params = new SimpleXMLElement($input);
           $res = $db->query("select concat(cr.nativeamount, ' ', cu.iso) from credit cr join companycrtype type on type.id=cr.type join currency cu on cu.id=type.currency where cr.id=".(string)$cr_params->elid);
           $res_array = $res->fetch_array();
           $cr_params->addChild("amount", $res_array[0]);
           $res->close();
           echo $cr_params->asXML();
       }

       function CRTune() {
           $db = DBConnect();
           $input = GetInput();
           Debug("input: ".$input);
           $cr_params = new SimpleXMLElement($input);
           $res = $db->query("select cr.state from credit cr where cr.id=".(string)$cr_params->elid);
           $res_array = $res->fetch_array();
           if ($res_array[0] == "4") {
               $cr_params->metadata->form->addAttribute("nosubmit", "yes");
           }
           $res->close();
           echo $cr_params->asXML();
       }

	set_error_handler("defErrorHandler");

       $request_str = "";
       foreach ($argv as $arg) {
           $request_str .= $arg;
       }
       
       Debug("Request: ".$request_str);
       $cmd = ($argc > 1 ? $argv[1] : "");
       Debug("Command: ".$cmd);
       
       switch ($cmd) {
           case "feature":
               echo "crget crtune";
               break;
           /*case "pmvalidate":
               PMValidate();
               break;*/
           case "crget":
               CRGet();
               break;
           case "crtune":
               CRTune();
               break;
       }
?>