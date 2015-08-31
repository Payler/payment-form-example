<?php
defined ('_JEXEC') or die('Restricted access');


if (!class_exists ('vmPSPlugin')) {
	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}




class Payler {
	const PAYLER_STATUS_CHARGED = 'Charged';

	function __construct($debug_mode, $merchant_id='') {
		$this->sandbox = $debug_mode;
		$this->merchant_id = $merchant_id;
		$host = ($debug_mode ? "sandbox" : "secure");
		$this->base_url = "https://" . $host . ".payler.com/gapi/";
	}
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
		curl_close($ch);
		if ($json == false) {
			die ('Curl error: ' . curl_error($ch) . '<br>');
		}
		//Преобразуем JSON в ассоциативный массив
		$result = json_decode($json, TRUE);
		return $result;
	}
	public function POSTtoGateAPI ($data, $method) {
		$this->url = $this->base_url.$method;
		$result = $this->CurlSendPost($data);
		return $result;
	}
	function Pay ($session_id) {
		$this->url = $this->base_url."Pay";
		$result = '<html><head><title>Redirection</title></head><body><div style="margin: auto; text-align: center;">'
			. '<form method ="POST" action="'.$this->url.'" name="payler_form_redirect">'
			. '<input type="hidden" name="session_id" '
			. 'value="'.$session_id.'">'
			. '<input type="submit" name="submit" value="Оплатить заказ">'
			. '</form></div><script type="text/javascript">document.payler_form_redirect.submit();</script></body></html>';
		return $result;
	}

	function Status($order_id) {
		$this->url = $this->base_url."GetStatus";
		$data = array (
			'key' => $this->merchant_id,
			'order_id' => $order_id
		);

		$result = $this->CurlSendPost($data);
		return $result['status'];
	}

	function paymentResponseReceived ($data) {
		$order_number = $data;
		if (empty($order_number)) {
			$this->plugin->debugLog($order_number, 'getOrderNumber not correct', 'debug', false);
			return FALSE;
		}
		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
			return FALSE;
		}

		$orderModel = VmModel::getModel('orders');
		$order = $orderModel->getOrder($virtuemart_order_id);
		$success = ($this->Status($order_number) == self::PAYLER_STATUS_CHARGED);

		if ($success) {
			$cart = VirtueMartCart::getCart();
			$cart->emptyCart();
		}
		return $success;
	}

}

class plgVmPaymentPayler extends vmPSPlugin {

	function __construct (& $subject, $config) {
		parent::__construct ($subject, $config);

		$jlang = JFactory::getLanguage();
		$jlang->load('plg_vmpayment_payler', JPATH_ADMINISTRATOR, NULL, TRUE);

		$this->_loggable = TRUE;
		$this->tableFields = array_keys($this->getTableSQLFields());
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';

		$varsToPush = array(
			'merchant_id' => array('', 'char'),
			'merchant_password' => array('', 'char'),
			'sandbox' => array(0, 'int'),
			'status_pending' => array('', 'char'),
			'status_success' => array('', 'char'),
			'status_canceled' => array('', 'char'),
		);

		$this->setConfigParameterable ($this->_configTableFieldName, $varsToPush);
	}

	public function getVmPluginCreateTableSQL () {

		return $this->createTableSQL('Payment Payler Table');
	}

	function getTableSQLFields () {
			$SQLfields = array(
					'id'                          => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
					'virtuemart_order_id'         => 'int(1) UNSIGNED',
					'order_number'                => 'char(64)',
					'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
					'payment_name'                => 'varchar(5000)',
					'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
					'payment_currency'            => 'char(3)',
					'email_currency'              => 'char(3)',
					'cost_per_transaction'        => 'decimal(10,2)',
					'cost_percent_total'          => 'decimal(10,2)',
					'tax_id'                      => 'smallint(1)'
					);
			return $SQLfields;
 	}

	function plgVmConfirmedOrder($cart, $order)
	{
		if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		$this->merchant_id = $method->merchant_id;
		$this->secret_word = $method->merchant_password;
		$this->sandbox  = intval($method->sandbox);
		$this->payment_type = 'OneStep';

		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}

		$session = JFactory::getSession();
		$return_context = $session->getId();
		$order_number = $order['details']['BT']->order_number;
		$this->logInfo('plgVmConfirmedOrder order number: '.$order_number, 'message');

		if (!class_exists('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR.DS.'models'.DS.'orders.php');
		}

		if (!class_exists('VirtueMartModelCurrency')) {
			require(JPATH_VM_ADMINISTRATOR.DS.'models'.DS.'currency.php');
		}

		if (!class_exists('TableVendors')) {
			require(JPATH_VM_ADMINISTRATOR.DS.'table'.DS.'vendors.php');
		}

		$vendorModel = VmModel::getModel('Vendor');
		$vendorModel->setId(1);
		$vendor = $vendorModel->getVendor();
		$vendorModel->addImages($vendor, 1);
		$this->getPaymentCurrency($method);
		$currency_code_3 = shopFunctions::getCurrencyByID($method->payment_currency, 'currency_code_3');

		$paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
		$totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);
		$cd = CurrencyDisplay::getInstance($cart->pricesCurrency);

		if ($totalInPaymentCurrency <= 0) {
			vmInfo(JText::_('PLG_PAYLER_SUMM_EQL_ZERO'));
			return false;
		}


		if (empty($this->merchant_id)) {
			vmInfo(JText::_('PLG_PAYLER_INCORRECT_SETUP'));
			return false;
		}

		$email_currency = $this->getEmailCurrency($this->_currentMethod);
		$payment_name = $this->renderPluginName($this->_currentMethod, $order);
		// Prepare data that should be stored in the database
		$dbValues['order_number'] = $order['details']['BT']->order_number;
		$dbValues['payment_name'] = $payment_name;
		$dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
		$dbValues['payment_currency'] = $this->_currentMethod->payment_currency;
		$dbValues['email_currency'] = $email_currency;
		$dbValues['payment_order_total'] = $order['details']['BT']->order_total;
		$dbValues['tax_id'] = $this->_currentMethod->tax_id;
		$this->storePSPluginInternalData($dbValues);
		VmConfig::loadJLang('com_virtuemart_orders',TRUE);

		$virtuemart_paymentmethod_id = $order['details']['BT']->virtuemart_paymentmethod_id;

		$payler = new Payler($this->sandbox, $this->merchant_id);
		$amount = round($order['details']['BT']->order_total * 100);
		$product = "Оплата заказа №".$order_number;
		$data = array (
			'key' => $this->merchant_id,
			'type' => $this->payment_type,
			'order_id' => $order_number,
			'amount' => $amount,
			'product' => $product
		);

		$return_url = urlencode(substr(JURI::root(false, ''), 0, -1).JROUTE::_('index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on='.$order_number.'&pm='.$virtuemart_paymentmethod_id.'&Itemid='.JRequest::getInt('Itemid'), false));
		$fail_url = urlencode(substr(JURI::root(false, ''), 0, -1).JROUTE::_('index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on='.$order_number.'&pm='.$virtuemart_paymentmethod_id.'&Itemid='.JRequest::getInt('Itemid'), false));
		$price_final = $method->price_final ? 'true' : 'false';
		$add_params = trim($method->add_params, '&');
		$total_md5 = $this->to_float($totalInPaymentCurrency);
		$md5check = md5('fix;'.$total_md5.';'.$currency_code_3.';'.$order_number.';yes;'.$secret_word);
		$user_email = $order['details']['BT']->email;
		$user_phone = $order['details']['BT']->phone_1;
		$design = $method->design;

		$lang = '';
		if ($method->lang) {
			$lang = '&ln='.$method->lang;
		}

		$session_data = $payler->POSTtoGateAPI($data, "StartSession");
		$session_id = $session_data['session_id'];
		$html = $payler->Pay($session_id);

		$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
		$modelOrder = VmModel::getModel('orders');
		$order['customer_notified'] = 1;
		$order['order_status'] = $method->status_pending;
		$modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);

		$cart->_confirmDone = false;
		$cart->_dataValidated = false;
		$cart->setCartIntoSession();
		JRequest::setVar('html', $html);
	}

	/*
		 * Keep backwards compatibility
		 * a new parameter has been added in the xml file
		 */
	function getNewStatus ($method) {

		if (isset($method->status_pending) and $method->status_pending!="") {
			return $method->status_pending;
		} else {
			return 'P';
		}
	}

	/**
	 * Display stored payment data for an order
	 *
	 */
	function plgVmOnShowOrderBEPayment ($virtuemart_order_id, $virtuemart_payment_id) {

		if (!$this->selectedThisByMethodId ($virtuemart_payment_id)) {
			return NULL; // Another method was selected, do nothing
		}

		if (!($paymentTable = $this->getDataByOrderId ($virtuemart_order_id))) {
			return NULL;
		}
		VmConfig::loadJLang('com_virtuemart');

		$html = '<table class="adminlist table">' . "\n";
		$html .= $this->getHtmlHeaderBE ();
		$html .= $this->getHtmlRowBE ('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
		$html .= $this->getHtmlRowBE ('Payler_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
		if ($paymentTable->email_currency) {
			$html .= $this->getHtmlRowBE ('Payler_EMAIL_CURRENCY', $paymentTable->email_currency );
		}
		$html .= '</table>' . "\n";
		return $html;
	}

	/**
	 * Check if the payment conditions are fulfilled for this payment method
	 *
	 * @author: Valerie Isaksen
	 *
	 * @param $cart_prices: cart prices
	 * @param $payment
	 * @return true: if the conditions are fulfilled, false otherwise
	 *
	 */
	protected function checkConditions ($cart, $method, $cart_prices) {
		return TRUE;
	}


	/*
	* We must reimplement this triggers for joomla 1.7
	*/

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the Payler method to create the tables
	 *
	 * @author Valérie Isaksen
	 *
	 */
	function plgVmOnStoreInstallPaymentPluginTable ($jplugin_id) {
        if ($jplugin_id != $this->_jid) {
            return FALSE;
        }
        return $this->onStoreInstallPluginTable ($jplugin_id);
	}

	/**
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.
	 *
	 * @author Max Milbers
	 * @author Valérie isaksen
	 *
	 * @param VirtueMartCart $cart: the actual cart
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
	 *
	 */
	public function plgVmOnSelectCheckPayment (VirtueMartCart $cart, &$msg) {

		if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
			return null; // Another method was selected, do nothing
		}

		if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
			return FALSE;
		}

		$this->OnSelectCheck ($cart);
		return true;
	}

	/**
	 * plgVmDisplayListFEPayment
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
	 *
	 * @param object  $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on succes, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 *
	 * @author Valerie Isaksen
	 * @author Max Milbers
	 */
	public function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn) {

		return $this->displayListFE ($cart, $selected, $htmlIn);
	}

	/*
	* plgVmonSelectedCalculatePricePayment
	* Calculate the price (value, tax_id) of the selected method
	* It is called by the calculator
	* This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
	* @author Valerie Isaksen
	* @cart: VirtueMartCart the current cart
	* @cart_prices: array the new cart prices
	* @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
	*
	*
	*/

	public function plgVmonSelectedCalculatePricePayment (VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {

		return $this->onSelectedCalculatePrice ($cart, $cart_prices, $cart_prices_name);
	}

	function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

		if (!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
			return FALSE;
		}
		$this->getPaymentCurrency($this->_currentMethod);
		$paymentCurrencyId = $this->_currentMethod->payment_currency;
	}

	/**
	 * plgVmOnCheckAutomaticSelectedPayment
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 *
	 * @author Valerie Isaksen
	 * @param VirtueMartCart cart: the cart object
	 * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
	 *
	 */
	function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {
		return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the method-specific data.
	 *
	 * @param integer $order_id The order ID
	 * @return mixed Null for methods that aren't active, text (HTML) otherwise
	 * @author Max Milbers
	 * @author Valerie Isaksen
	 */
	public function plgVmOnShowOrderFEPayment ($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {

		$this->onShowOrderFE ($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}

	/**
	 * @param $orderDetails
	 * @param $data
	 * @return null
	 */
	function plgVmOnUserInvoice ($orderDetails, &$data) {

		if (!($method = $this->getVmPluginMethod ($orderDetails['virtuemart_paymentmethod_id']))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement ($method->payment_element)) {
			return NULL;
		}
		//vmdebug('plgVmOnUserInvoice',$orderDetails, $method);

		if (!isset($method->send_invoice_on_order_null) or $method->send_invoice_on_order_null==1 or $orderDetails['order_total'] > 0.00){
			return NULL;
		}

		if ($orderDetails['order_salesPrice']==0.00) {
			$data['invoice_number'] = 'reservedByPayment_' . $orderDetails['order_number']; // Nerver send the invoice via email
		}

	}

	/**
	 * @param $virtuemart_paymentmethod_id
	 * @param $paymentCurrencyId
	 * @return bool|null
	 */
	function plgVmgetEmailCurrency($virtuemart_paymentmethod_id, $virtuemart_order_id, &$emailCurrencyId) {

		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return FALSE;
		}
		if (!($payments = $this->getDatasByOrderId($virtuemart_order_id))) {
			// JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}
		if (empty($payments[0]->email_currency)) {
			$vendorId = 1; //VirtueMartModelVendor::getLoggedVendor();
			$db = JFactory::getDBO();
			$q = 'SELECT   `vendor_currency` FROM `#__virtuemart_vendors` WHERE `virtuemart_vendor_id`=' . $vendorId;
			$db->setQuery($q);
			$emailCurrencyId = $db->loadResult();
		} else {
			$emailCurrencyId = $payments[0]->email_currency;
		}

	}
	/**
	 * This event is fired during the checkout process. It can be used to validate the
	 * method data as entered by the user.
	 *
	 * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
	 * @author Max Milbers

	public function plgVmOnCheckoutCheckDataPayment(  VirtueMartCart $cart) {
	return null;
	}
	 */

	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id  method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	function plgVmonShowOrderPrintPayment ($order_number, $method_id) {

		return $this->onShowOrderPrint ($order_number, $method_id);
	}

	function plgVmDeclarePluginParamsPayment($name, $id, &$data) {

		return $this->declarePluginParams('payment', $name, $id, $data);
	}

	function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
		return $this->setOnTablePluginParams($name, $id, $table);
	}

	//функция переводит число в нужный формат
	function to_float($sum)
	{
		$sum = round(floatval($sum), 2);
		$sum = sprintf('%01.2f', $sum);

		if (substr($sum, -1) == '0') {
			$sum = sprintf('%01.1f', $sum);
		}

		return $sum;
	}

	function getCosts(VirtueMartCart $cart, $method, $cart_prices)
	{
		if (preg_match('/%$/', $method->cost_percent_total)) {
			$cost_percent_total = substr($method->cost_percent_total, 0, -1);
		} else {
			$cost_percent_total = $method->cost_percent_total;
		}

		return $method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01);
	}



	//Notice: We only need to add the events, which should work for the specific plugin, when an event is doing nothing, it should not be added

	/**
	 * Save updated order data to the method specific table
	 *
	 * @param array   $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.
	 *
	public function plgVmOnUpdateOrderPayment(  $_formData) {
	return null;
	}

	/**
	 * Save updated orderline data to the method specific table
	 *
	 * @param array   $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.
	 *
	public function plgVmOnUpdateOrderLine(  $_formData) {
	return null;
	}

	/**
	 * plgVmOnEditOrderLineBE
	 * This method is fired when editing the order line details in the backend.
	 * It can be used to add line specific package codes
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise
	 *
	public function plgVmOnEditOrderLineBEPayment(  $_orderId, $_lineId) {
	return null;
	}

	/**
	 * This method is fired when showing the order details in the frontend, for every orderline.
	 * It can be used to display line specific package codes, e.g. with a link to external tracking and
	 * tracing systems
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise
	 *
	public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
	return null;
	}

	/**
	 * This event is fired when the  method notifies you when an event occurs that affects the order.
	 * Typically,  the events  represents for payment authorizations, Fraud Management Filter actions and other actions,
	 * such as refunds, disputes, and chargebacks.
	 *
	 * NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
	 *
	 * @param         $return_context: it was given and sent in the payment form. The notification should return it back.
	 * Used to know which cart should be emptied, in case it is still in the session.
	 * @param int     $virtuemart_order_id : payment  order id
	 * @param char    $new_status : new_status for this order id.
	 * @return mixed Null when this method was not selected, otherwise the true or false
	 *
	 * @author Valerie Isaksen
	 *
	 *
	public function plgVmOnPaymentNotification() {
	return null;
	}

	/**
	 * plgVmOnPaymentResponseReceived
	 * This event is fired when the  method returns to the shop after the transaction
	 *
	 *  the method itself should send in the URL the parameters needed
	 * NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
	 *
	 * @param int     $virtuemart_order_id : should return the virtuemart_order_id
	 * @param text    $html: the html to display
	 * @return mixed Null when this method was not selected, otherwise the true or false
	 *
	 * @author Valerie Isaksen
	 *
	 */
	function plgVmOnPaymentResponseReceived(&$html) {
		if (!class_exists('VirtueMartCart')) {
			require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
		}
		if (!class_exists('shopFunctionsF')) {
			require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
		}
		if (!class_exists('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}

		VmConfig::loadJLang('com_virtuemart_orders', TRUE);

		$virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);

		if (!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		$this->merchant_id = $this->_currentMethod->merchant_id;
		$this->sandbox  = intval($this->_currentMethod->sandbox);
		$this->payment_type = 'OneStep';

		if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
			return NULL;
		}
		$order_id = JRequest::getString("order_id", "");
		$payler = new Payler($this->sandbox, $this->merchant_id);
		$order_paid_success = $payler->paymentResponseReceived($order_id);
		if($order_paid_success) {
			$html = 'Заказ оплачен успешно';
			$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_id);
			$modelOrder = VmModel::getModel('orders');
			$order['customer_notified'] = 0;
			$order['order_status'] = $this->_currentMethod->status_success;
			$modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, false);
		} else {
			$html = 'Заказ не оплачен. Повторите попытку позже';
		}
		JRequest::setVar('display_title', false);
		JRequest::setVar('html', $html);
		return true;
	}

}

// No closing tag
