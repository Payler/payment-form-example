<?php

/**
 *
 * @author Roman Angelovskij
 * @name Payler
 * @description Payler pament module
 * @property-read string $payler_id
 * @property-read string $test
 * @property-read string $protocol
 * @property-read string $apiUrl
 * @property-read string $privateKey
 * @property-read string $paymentPass
 *
 * @see http://payler.com/docs/acquiring_docs/
 */
class paylerPayment extends waPayment implements waIPayment
{
    private $version = '1.0';
    private $request;

    public function allowedCurrency()
    {
        return 'RUB';
    }

    public function payment($payment_form_data, $order_data, $auto_submit = false)
    {
		$order_data = waOrder::factory($order_data);

		if ($order_data['currency_id'] != $this->allowedCurrency()) {
			return array(
				'type' => 'error',
				'data' => _w('Оплата производится в только в рублях (RUB) и в данный момент невозможна, так как эта валюта не определена в настройках.'),
			);
		}

		$Data = array(
			"key"		=> $this->privateKey,
			"type"		=> 'OneStep',
			"order_id"	=> $this->app_id . '_' . $this->merchant_id . '_' . $order_data['order_id'],
			"amount"	=> $order_data['subtotal'] * 100
		);

		$SessionData = $this->__POSTtoGateAPI($Data, 'StartSession');

		$view = wa()->getView();

		if(isset($SessionData['session_id'])){
			$view->assign('sessionID', $SessionData['session_id']);
			$view->assign('url', $this->getEndpointUrl() . 'Pay');

			return $view->fetch($this->path.'/templates/payment.html');
		}
		else{
                        $view->assign('url', wa()->getUrl(true));

                        return $view->fetch($this->path.'/templates/payment_session_error.html');
		};
    }

	protected function callbackInit($request)
    {
		list($this->app_id, $this->merchant_id, $this->order_id) = explode('_', $request['order_id']);

		return parent::callbackInit($request);
    }

    /**
     *
     * @param array $request - get from gateway
     * @throws waPaymentException
     * @return mixed
     */
    protected function callbackHandler($request)
    {
        $transactionData = $this->formalizeData($request);

		$Data = array('key' => $this->privateKey, 'order_id' => $this->app_id . '_' . $this->merchant_id . '_' . $transactionData['order_id']);

        $Status = $this->__POSTtoGateAPI(
			$Data,
			'GetStatus'
		);

		$app_payment_method = null;
		if ($Status['status'] == 'Charged'){
			$app_payment_method = self::CALLBACK_PAYMENT;
			$url = $this->getAdapter()->getBackUrl(waAppPayment::URL_SUCCESS, $transactionData);
		} else {
			$app_payment_method = self::CALLBACK_DECLINE;
			$url = $this->getAdapter()->getBackUrl(waAppPayment::URL_FAIL, $transactionData);
		}

		$transactionData = $this->saveTransaction($transactionData, $request);
		if ($app_payment_method) {
			$result = $this->execAppCallback($app_payment_method, $transactionData);
			self::addTransactionData($transactionData['id'], $result);
		}

		return array(
			'redirect' => $url
		);
    }

    protected function callbackExceptionHandler(Exception $ex)
    {
        self::log($this->id, $ex->getMessage());
        $message = '';
        if ($ex instanceof waPaymentException) {
            $code = $ex->getCode();
            $message = $ex->getMessage();
        } else {
            $code = self::XML_TEMPORAL_PROBLEMS;
        }
        return $this->getXMLResponse($this->request, $code, $message);
    }

    private function getEndpointUrl()
    {
        return ($this->test == true) ? $this->protocol . '://sandbox.' . $this->apiUrl : $this->protocol . '://secure.' . $this->apiUrl;
    }

    /**
     * Check MD5 hash of transfered data
     * @throws waPaymentException
     * @param array $request
     */
    private function verifySign($request)
    {

    }

    private function getPrivateKey()
    {
       return $this->privateKey;
    }
    /**
     * Convert transaction raw data to formatted data
     * @param array $transaction_raw_data
     * @return array $transaction_data
     */
    protected function formalizeData($transaction_raw_data)
    {
        $transaction_data = parent::formalizeData($transaction_raw_data);

        $transaction_data = array_merge(
            $transaction_data,
            array(
                'type'        => null,
                'native_id'   => ifset($transaction_raw_data['invoiceId']),
                'amount'      => ifset($transaction_raw_data['orderSumAmount']),
                'currency_id' => ifset($transaction_raw_data['orderSumCurrencyPaycash']) == 643 ? 'RUB' : 'N/A',
                'customer_id' => ifempty($transaction_raw_data['customerNumber'], ifset($transaction_raw_data['CustomerNumber'])),
                'result'      => 1,
                'order_id'    => $this->order_id,
                'view_data'   => 'Оплачено через Payler'
            )
        );

        return $transaction_data;
    }

    public function supportedOperations()
    {
        return array(
            self::OPERATION_CHECK,
            self::OPERATION_AUTH_CAPTURE,
        );
    }


    public static function settingsPaymentOptions()
    {
        return array(
            'PC' => 'платеж со счета в Яндекс.Деньгах',
            'AC' => 'платеж с банковской карты',
            'GP' => 'платеж по коду через терминал',
            'MC' => 'оплата со счета мобильного телефона',
            'WM' => 'оплата со счета WebMoney',
            'SB' => 'Оплата через Сбербанк Онлайн',
            'AB' => 'Оплата в Альфа-Клик',
        );
    }

	private function __POSTtoGateAPI ($Data, $method) {
		$result = $this->__CurlSendPost($Data, $method);
		return $result;
	}

	private function __CurlSendPost ($Data, $method = '') {
		$Headers = array(
			'Content-type: application/x-www-form-urlencoded',
			'Cache-Control: no-cache',
			'charset="utf-8"',
		);

		$data = http_build_query($Data);
		$options = array (
			CURLOPT_URL => $this->getEndpointUrl() . $method,
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
}
