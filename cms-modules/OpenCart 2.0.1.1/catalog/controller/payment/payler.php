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
    protected function CurlSendPost ($data, $url){
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

    public function index() {
        //Load the language file for this module
        $this->language->load('payment/payler');

        //Get the title from the language file
        $data['heading_title'] = $this->language->get('heading_title');

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
        $amount = round(100*$order_info ['total'], 0);
        $product = $this->language->get('text_order_desc').$order_id;
        $order_id .= '|' . time();

        //Prepare data and start session with Payler
        $payler_data = array (
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

        if (!isset($key, $order_id)) {
            die($this->language->get('text_no_key'));
        } else {
            $url = $this->GetBaseURL()."StartSession";
            $session_data = $this->CurlSendPost($payler_data, $url);
        }

        if (!isset($session_data['session_id'])) {
            var_dump($session_data);
            die();
        } else {
            $data['session_id'] = $session_data['session_id'];
            $this->model_checkout_order->addOrderHistory($order_id, 1);
        }
        
        $data['post_url'] = $this->GetBaseUrl()."Pay";
        $data['button_confirm'] = $this->language->get('button_confirm');
        
        //Choose which template to display this module with
        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/payler.tpl')) {
                $template = $this->config->get('config_template') . '/template/payment/payler.tpl';
        } else {
                $template = 'default/template/payment/payler.tpl';
        }

        //Render the page with the chosen template
        //$this->render();
        // $this->response->setOutput($this->load->view($template, $data));
        return $this->load->view($template, $data);
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
                //$this->request->get['order_id'];
                $this->model_checkout_order->addOrderHistory($order_id, $status);
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
            $template = $this->config->get('config_template') . '/template/payment/payler_success.tpl';
        } else {
            $template = 'default/template/payment/payler_success.tpl';
        }

        $text_strings = array(
            'text_main_page',
            'text_successful_payment',
        );

        $data = array();
        foreach ($text_strings as $text) {
            $data[$text] = $this->language->get($text);
        }

        /*
        $this->children = array(
            'common/column_left',
            'common/column_right',
            'common/content_top',
            'common/content_bottom',
            'common/footer',
            'common/header'
        );
        */

        $data['column_left'] =    $this->load->controller('common/column_left');
        $data['column_right'] =   $this->load->controller('common/column_right');
        $data['header'] =         $this->load->controller('common/header');
        $data['footer'] =         $this->load->controller('common/footer');
        $data['content_top'] =    $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');

        $this->response->setOutput($this->load->view($template, $data));
        //$this->response->setOutput($this->render());
        // return $this->load->view($template, $data);

    }

    public function fail() {
        $this->load->language('payment/payler');

        $this->document->setTitle($this->language->get('heading_title_fail'));
        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/payler_fail.tpl')) {
            $template = $this->config->get('config_template') . '/template/payment/payler_fail.tpl';
        } else {
            $template = 'default/template/payment/payler_fail.tpl';
        }

        $text_strings = array(
            'text_main_page',
            'text_unsuccessful_payment',
        );

        $data = array();
        foreach ($text_strings as $text) {
            $data[$text] = $this->language->get($text);
        }

        /*
        $this->children = array(
            'common/column_left',
            'common/column_right',
            'common/content_top',
            'common/content_bottom',
            'common/footer',
            'common/header'
        );
        */

        $data['column_left'] =    $this->load->controller('common/column_left');
        $data['column_right'] =   $this->load->controller('common/column_right');
        $data['header'] =         $this->load->controller('common/header');
        $data['footer'] =         $this->load->controller('common/footer');
        $data['content_top'] =    $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');

        //$this->response->setOutput($this->render());
        $this->response->setOutput($this->load->view($template, $data));
        //return $this->load->view($template, $data);
    }
}

?>
