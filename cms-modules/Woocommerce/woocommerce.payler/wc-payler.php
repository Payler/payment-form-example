<?php 
/*
  Plugin Name: Payler Payment Gateway
  Plugin URI: 
  Description: Allows you to use Payler payment gateway with the WooCommerce plugin.
  Version: 0.1
  Author: Sergey Khodko
  Author URI: https://vk.com/tranceiteasy
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
 
 /**
 * Add roubles in currencies
 * 
 */
function payler_rub_currency_symbol( $currency_symbol, $currency ) {
    if($currency == "RUB") {
        $currency_symbol = 'р.';
    }
    return $currency_symbol;
}

function payler_rub_currency( $currencies ) {
    $currencies["RUB"] = 'Russian Roubles';
    return $currencies;
}

add_filter( 'woocommerce_currency_symbol', 'payler_rub_currency_symbol', 10, 2 );
add_filter( 'woocommerce_currencies', 'payler_rub_currency', 10, 1 );


/* Add a custom payment class to WC */

add_action('plugins_loaded', 'woocommerce_payler', 0);
function woocommerce_payler(){
	if (!class_exists('WC_Payment_Gateway'))
		return; // if the WC payment gateway class is not available, do nothing
	if(class_exists('WC_PAYLER'))
		return;
class WC_PAYLER extends WC_Payment_Gateway{
	public function __construct(){
		
		$plugin_dir = plugin_dir_url(__FILE__);

		global $woocommerce;

		$this->id = 'payler';
		$this->icon = apply_filters('woocommerce_payler_icon', ''.$plugin_dir.'payler.png');
		$this->has_fields = false;
        $this->liveurl = 'https://secure.payler.com';
		$this->testurl = 'https://sandbox.payler.com';

		// Load the settings
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title = $this->get_option('title');
		$this->payler_key = $this->get_option('payler_key');
		$this->testmode = $this->get_option('testmode');
		$this->description = $this->get_option('description');
		$this->instructions = $this->get_option('instructions');

		// Actions
		add_action('valid-payler-standard-ipn-reques', array($this, 'successful_request') );
		add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

		// Save options
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Payment listener/API hook
		add_action('woocommerce_api_wc_' . $this->id, array($this, 'check_ipn_response'));

		if (!$this->is_valid_for_use()){
			$this->enabled = false;
		}
	}
	
	/**
	 * Check if this gateway is enabled and available in the user's country
	 */
	function is_valid_for_use(){
		if (!in_array(get_option('woocommerce_currency'), array('RUB'))){
			return false;
		}
		return true;
	}
	
	/**
	* Admin Panel Options 
	* - Options for bits like 'title' and availability on a country-by-country basis
	**/
	public function admin_options() {
		?>
		<h3><?php _e('PAYLER', 'woocommerce'); ?></h3>
		<p><?php _e('Настройка приема электронных платежей через PAYLER.', 'woocommerce'); ?></p>

	  <?php if ( $this->is_valid_for_use() ) : ?>

		<table class="form-table">

		<?php    	
    			// Generate the HTML For the settings form.
    			$this->generate_settings_html();
    ?>
    </table><!--/.form-table-->
    		
    <?php else : ?>
		<div class="inline error"><p><strong><?php _e('Шлюз отключен', 'woocommerce'); ?></strong>: <?php _e('PAYLER не поддерживает валюты Вашего магазина.', 'woocommerce' ); ?></p></div>
		<?php
			endif;

    } // End admin_options()

  /**
  * Initialise Gateway Settings Form Fields
  *
  * @access public
  * @return void
  */
	function init_form_fields(){
		$this->form_fields = array(
				'enabled' => array(
					'title' => __('Включить/Выключить', 'woocommerce'),
					'type' => 'checkbox',
					'label' => __('Включен', 'woocommerce'),
					'default' => 'yes'
				),
				'title' => array(
					'title' => __('Название', 'woocommerce'),
					'type' => 'text', 
					'description' => __( 'Это название, которое пользователь видит во время проверки.', 'woocommerce' ), 
					'default' => __('PAYLER', 'woocommerce')
				),
				'payler_key' => array(
					'title' => __('Платежный ключ', 'woocommerce'),
					'type' => 'text',
					'description' => __('Пожалуйста введите Платежный ключ', 'woocommerce'),
					'default' => ''
				),
				'testmode' => array(
					'title' => __('Тест режим', 'woocommerce'),
					'type' => 'checkbox', 
					'label' => __('Включен', 'woocommerce'),
					'description' => __('В этом режиме плата за товар не снимается.', 'woocommerce'),
					'default' => 'no'
				),
				'description' => array(
					'title' => __( 'Description', 'woocommerce' ),
					'type' => 'textarea',
					'description' => __( 'Описанием метода оплаты которое клиент будет видеть на вашем сайте.', 'woocommerce' ),
					'default' => 'Оплата с помощью payler.'
				),
				'instructions' => array(
					'title' => __( 'Instructions', 'woocommerce' ),
					'type' => 'textarea',
					'description' => __( 'Инструкции, которые будут добавлены на страницу благодарностей.', 'woocommerce' ),
					'default' => 'Оплата с помощью payler.'
				)
			);
	}

	/**
	* There are no payment fields for sprypay, but we want to show the description if set.
	**/
	function payment_fields(){
		if ($this->description){
			echo wpautop(wptexturize($this->description));
		}
	}
	
	/**
	* Generate the dibs button link
	**/
	public function generate_form($order_id){
		global $woocommerce;

		$order = new WC_Order( $order_id );

		$out_summ = number_format($order->order_total, 2, '.', '');

		$crc = $this->payler_merchant.':'.$out_summ.':'.$order_id.':'.$this->payler_key1;
		
		$args = array(
				// Merchant
				'MrchLogin' => $this->payler_merchant,
				'OutSum' => $out_summ,
				'InvId' => $order_id,
				'SignatureValue' => md5($crc),
				'Culture' => 'ru',
			);
			
		foreach ($args as $key => $value){
			$args_array[] = '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'" />';
		}
		
		$Headers = array(
			'Content-type: application/x-www-form-urlencoded',
			'Cache-Control: no-cache',
			'charset="utf-8"',
		);
		
		$Data = array(
			"key"		=> $this->payler_key,
			"type"		=> 1,
			"order_id"	=> $order->id.'|'.time(),
			"amount"	=> $order->order_total * 100,
			"userdata"  => $order->order_key
		);
        $payler_url = $this->testmode == 'yes' ? $this->testurl : $this->liveurl;
        
		$data = http_build_query($Data);
		$options = array (
			CURLOPT_URL => $payler_url . '/gapi/StartSession',
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
		}else{
                        $payler_result = json_decode($json, TRUE);

                        if(isset($payler_result['session_id'])) {
                           $order->add_order_note('PaylerOderID:'.$Data['order_id']);
                           curl_close($ch);
         		   return
	        		'<form action="' . $payler_url . '/gapi/Pay" method="POST" id="payler_payment_form">'."\n".
		        	implode("\n", $args_array).
			        '<input type="submit" class="button alt" id="submit_payler_payment_form" value="'.__('Оплатить', 'woocommerce').'" /> <a class="button cancel" href="'.$woocommerce->cart->get_cart_url().'">'.__('Отказаться от оплаты & вернуться в корзину', 'woocommerce').'</a>'."\n".
			        '<input type="hidden" value="' . $payler_result['session_id'] . '" name="session_id">'.
			        '</form>';
                        }
                }

                $order->add_order_note('Ошибка при оплате через Payler: не удалось создать платежную сессию. OrderID:'.$Data['order_id']);
		return
                        '<label>Не удалось начать оплату через Payler. Пожалуйста, сообщите об этом администратору</label><br>'.
			' <a class="button cancel" href="'.$woocommerce->cart->get_cart_url().'">'.__('Вернуться в корзину', 'woocommerce').'</a>'."\n".
			'<input type="hidden" value="' . $payler_result['session_id'] . '" name="session_id">'.
			'</form>';
                    
	}
	
	/**
	 * Process the payment and return the result
	 **/
	
	function process_payment($order_id){
		$order = new WC_Order($order_id);

		return array(
			'result' => 'success',
			'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
		);
	}
	
	/**
	* receipt_page
	**/
	function receipt_page($order){
		echo '<p>'.__('Спасибо за Ваш заказ, пожалуйста, нажмите кнопку ниже, чтобы заплатить.', 'woocommerce').'</p>';
		echo $this->generate_form($order);
	}
	
	
	/**
	* Check Response
	**/
	
	function check_ipn_response(){
		global $woocommerce;
		

                $args = array(
                            'post_id' => $_GET['order_id'],
                            );
                $payler_order_id = $_GET['order_id'];
                $order_notes = get_comments($args);
                foreach($comments as $comment)
                {
                   $pos = strpos($comment->comment_content, 'PaylerOrderID:');
                   if(!($pos === false))
                   {
                       $payler_order_id = substr($comment->comment_content, 14); //14 = length('PaylerOrderID:) 
                       break;
                   }
                }
                                
		$Headers = array(
			'Content-type: application/x-www-form-urlencoded',
			'Cache-Control: no-cache',
			'charset="utf-8"',
		);
		
		$Data = array(
			"key"		=> $this->payler_key,
			"order_id"  => $payler_order_id
		);
		
        $payler_url = $this->testmode == 'yes' ? $this->testurl : $this->liveurl;
        
		$data = http_build_query($Data);
		$options = array (
			CURLOPT_URL => $payler_url . '/gapi/GetStatus',
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
	        
                $payler_edit_order_id = substr($payler_status['order_id'], 0, strpos($payler_status['order_id'], '|'));
                $our_edit_order_id    = substr($_GET['order_id'], 0, strpos($_GET['order_id'], '|'));

		if ($our_edit_order_id == $payler_edit_order_id){
		    if ($payler_status['status'] == 'Charged'){
		        $order = new WC_Order($payler_edit_order_id);
			    $order->update_status('processing', __('Платеж успешно оплачен', 'woocommerce'));
			    WC()->cart->empty_cart();
			    wp_redirect( $this->get_return_url( $order ) );
		    } else {
		    	$order = new WC_Order($payler_edit_order_id);
			    $order->update_status('failed', __('Платеж не оплачен', 'woocommerce'));
			    wp_redirect($order->get_cancel_order_url());
		            exit;
		    }
		}
		
	}
	
}

/**
 * Add the gateway to WooCommerce
 **/
function add_payler_gateway($methods){
	$methods[] = 'WC_PAYLER';
	return $methods;
}


add_filter('woocommerce_payment_gateways', 'add_payler_gateway');
}
?>
