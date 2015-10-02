<?php
	class paylerPayment extends payment {
		public function validate() { return true; }

		public static function getOrderId() {
			return (int) getRequest('MERCHANT_ORDER_ID');
		}

		public function process($template = null) {

		    $this->order->order();
			$merchant_id = $this->object->fk_merchant_id;
			$secret_1 = $this->object->fk_secret_1;
			$mode = $this->object->fk_test_mode;
			$out_amount = number_format($this->order->getActualPrice(), 2, '.', '');
			$order_id = $this->order->id;
			$sign = md5($merchant_id.":".$out_amount.":".$secret_1.":".$order_id);
                        
			$this->order->setPaymentStatus('initialized');
		    
		    $param['formAction'] = $mode == 1 ? "https://sandbox.payler.com" : "https://secure.payler.com";
		    $param['merchant_id'] = $merchant_id;
		    $param['out_amount'] = $out_amount;
			$param['order_id'] = $this->order->payler_order_id;
			
			$controller = cmsController::getInstance();
			$module = $controller->getModule("emarket");
			
		    if(isset($_GET['order_id'])){
                        
                        $real_order_id = substr($_GET['order_id'], 0, strpos($_GET['order_id'], '|'));
		        if ($real_order_id  == $this->order->id){
		            
		            $Headers = array(
			            'Content-type: application/x-www-form-urlencoded',
			            'Cache-Control: no-cache',
			            'charset="utf-8"',
		            );
		            
		            $this->order->order();
		            
		            $Data = array(
			            "key"		=> $merchant_id,
			            "order_id"  => $_GET['order_id']
		            );
		            
		            $data = http_build_query($Data);
		            $options = array (
			            CURLOPT_URL => $param['formAction'] . '/gapi/GetStatus',
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
		            $payler_status = json_decode($json, TRUE);
		            curl_close($ch);
		            print_r($payler_status);
		            if ($payler_status['status'] == 'Charged'){
		                setcookie('payler_sid', '');
		                $this->order->setPaymentStatus("accepted");
			            $module->redirect($controller->pre_lang . '/emarket/purchase/result/successful/');
		            } else {
			            $module->redirect($controller->pre_lang . '/emarket/purchase/result/failed/');
			            setcookie('payler_sid', '');
		            }
		        } else {
		            setcookie('payler_sid', '');
			        $module->redirect($controller->pre_lang . '/emarket/purchase/result/failed/');
		        }
		        die;
		    }
			
            if (!isset($this->order->payler_order_id)){
                $Headers = array(
			        'Content-type: application/x-www-form-urlencoded',
			        'Cache-Control: no-cache',
			        'charset="utf-8"',
		        );
		
		        $Data = array(
			        "key"		=> $merchant_id,
			        "type"		=> 1,
			        "order_id"	=> $order_id.'|'.time(),
			        "amount"	=> $out_amount * 100
		        );
                

                        $this->order->setValue('payler_order_id',$Data["order_id"]);
                        $this->order->commit();

		        $data = http_build_query($Data);
		        $options = array (
			        CURLOPT_URL => $param['formAction'] . '/gapi/StartSession',
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
		        $payler_result = json_decode($json, TRUE);
		        curl_close($ch);
		        $param['session_id']    = $payler_result['session_id'];
		        setcookie('payler_sid', $payler_result['session_id'], time()+36000);
		    } else {
                $param['session_id'] = $_COOKIE['payler_sid'];  
		    }
            $FORMS = Array();
            $FORMS = "<form action=". $param['formAction'] . "/gapi/Pay method='post' name='form_payler'>
            <input type='hidden' name='session_id' value=" . $param['session_id'] . " />
            <input type='submit' value='Оплатить' />
            <script type='text/javascript'>document.form_payler.submit();</script>
            </form>";

            echo $FORMS; exit();

			list($templateString) = def_module::loadTemplates("emarket/payment/payler/".$template, "form_block");
			return def_module::parseTemplate($templateString, $param);

		}

		public function poll() {
			$out_amount  = getRequest("AMOUNT");
			$sign    = getRequest("SIGN");
			$order_id = getRequest("MERCHANT_ORDER_ID");
			$secret_2 = $this->object->fk_secret_2;
			$merchant_id = $this->object->fk_merchant_id;
			$out_amount = (float) $out_amount;
			$orderActualPrice = number_format($this->order->getActualPrice(), 2, '.', '');
			
			$my_sign = md5($merchant_id.":".$out_amount.":".$secret_2.":".$order_id);

			$buffer = outputBuffer::current();
			$buffer->clear();
			$buffer->contentType("text/plain");
			if ( ($my_sign == $sign) && ($orderActualPrice == $out_amount) ) {
				$this->order->setPaymentStatus("accepted");
				$buffer->push("OK $order_id");
			} else {
				$buffer->push("failed");
			}
			$buffer->end();
		}
	};
?>
