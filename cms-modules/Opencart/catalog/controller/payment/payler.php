<?php

/* 
 * The MIT License
 *
 * Copyright 2014 Payler LLC
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

class ControllerPaymentPayler extends Controller {
    
    function GetBaseUrl() {
        $test = $this->config->get('payler_test_mode');
        $host = ($test ? "sandbox" : "secure");
        $base_url = "https://" . $host . ".payler.com/gapi/";
        return $base_url;
    }
    
    /**
     * @desc Отправка POST-запроса при помощи curl.
     *
     * @param $data Массив отправляемых данных
     * @result Ассоциативный массив возвращаемых данных
     */
    protected function CurlSendPost ($data, $url) {	
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
    
    protected function index() {
        //Load the language file for this module
        $this->language->load('payment/payler');

        //Get the title from the language file
        $this->data['heading_title'] = $this->language->get('heading_title');

        //Load any required model files
        $this->load->model('checkout/order');
        
        //Get order info and Payler settings
        if (isset($this->session->data['order_id'])) {
            $order_id = $this->session->data['order_id'];
        }
        if (!$order_id) {
            die($this->language->get('text_no_order_id'));
        }

        $order_info = $this->model_checkout_order->getOrder($order_id);
        if (!$order_info) {
            die($this->language->get('text_no_order'));
        }
        $key = $this->config->get('payler_key');
        $type = 'OneStep';
        $amount = 100*$order_info ['total'];
        $product = $this->language->get('text_order_desc').$order_id;
        $order_id .= '|' . time();
        
        //Prepare data and start session with Payler
        $data = array (
            'key' => $key,
            'type' => $type,
            'order_id' => $order_id,
            'amount' => $amount,
            'product' => substr($product,0,255),
            /*'recurrent' => 'TRUE',            
            'total' => $total,
            'template' => $template,
            'lang' => $lang,
             */
        );
        
        $url = $this->GetBaseURL()."StartSession";
        $session_data = $this->CurlSendPost($data, $url);
        
        if (!isset($session_data['session_id'])) {
            var_dump($session_data);
            die();
        } else {
            $this->data['session_id'] = $session_data['session_id'];
            $this->model_checkout_order->confirm($order_id, 1, '', TRUE);
        }
        
        $this->data['post_url'] = $this->GetBaseUrl()."Pay";
        $this->data['button_confirm'] = $this->language->get('button_confirm');
        
        //Choose which template to display this module with
        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/payler.tpl')) {
                $this->template = $this->config->get('config_template') . '/template/payment/payler.tpl';
        } else {
                $this->template = 'default/template/payment/payler.tpl';
        }

        //Render the page with the chosen template
        $this->render();
    }
    
    public function result() {
        $success = FALSE;
        
        $this->load->model('checkout/order');
        if (isset($this->request->get['order_id'])) {
            $order_id = $this->request->get['order_id'];
        }
        if (isset($order_id)) {
            if (preg_match('/^[0-9|]*$/',$order_id)) {
                $key = $this->config->get('payler_key');
                $data = array (
                    "key" => $key,
                    "order_id" => $order_id
                );
                $url = $this->GetBaseURL()."GetStatus";
                $result = $this->CurlSendPost($data, $url);
            }
        }
        
        if (isset($result['status'])){
            if ($result['status'] == 'Charged') {
                $status = $this->config->get('payler_order_status');
                $order_id = substr($order_id, 0, strpos($order_id, '|'));
                $this->model_checkout_order->update($order_id, $status, '', TRUE);
                $success = TRUE;
            }
        }
        
        $success ? $this->success() : $this->fail();
    }
    
    public function success() {
        $this->load->language('payment/payler');
        
        $this->cart->clear();

        $this->document->setTitle($this->language->get('heading_title_success'));
        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/payler_success.tpl')) {
            $this->template = $this->config->get('config_template') . '/template/payment/payler_success.tpl';
        } else {
            $this->template = 'default/template/payment/payler_success.tpl';
        }
        
        $text_strings = array(
            'text_main_page',
            'text_successful_payment',
        );

        foreach ($text_strings as $text) {
            $this->data[$text] = $this->language->get($text);
        }
        
        $this->children = array(
            'common/column_left',
            'common/column_right',
            'common/content_top',
            'common/content_bottom',
            'common/footer',
            'common/header'
        );

        $this->response->setOutput($this->render());
    }

    public function fail() {
        $this->load->language('payment/payler');

        $this->document->setTitle($this->language->get('heading_title_fail'));
        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/payler_fail.tpl')) {
            $this->template = $this->config->get('config_template') . '/template/payment/payler_fail.tpl';
        } else {
            $this->template = 'default/template/payment/payler_fail.tpl';
        }
        
        $text_strings = array(
            'text_main_page',
            'text_unsuccessful_payment',
        );

        foreach ($text_strings as $text) {
            $this->data[$text] = $this->language->get($text);
        }

        $this->children = array(
            'common/column_left',
            'common/column_right',
            'common/content_top',
            'common/content_bottom',
            'common/footer',
            'common/header'
        );

        $this->response->setOutput($this->render());
    }
}

?>