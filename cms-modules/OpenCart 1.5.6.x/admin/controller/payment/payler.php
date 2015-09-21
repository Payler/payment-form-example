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
//most of the comments taken from to DIY Module Builder from HostJars (https://hostjars.com/)

class ControllerPaymentPayler extends Controller {

    private $error = array();

    public function index() {
        //Load the language file for this module
        $this->load->language('payment/payler');
        
        //Set the title from the language file $_['heading_title'] string
        $this->document->setTitle($this->language->get('heading_title'));
        
        //Load the settings model. You can also add any other models you want to load here.
        $this->load->model('setting/setting');
        $this->load->model('localisation/order_status');
        $this->load->model('localisation/geo_zone');
        
        //Save the settings if the user has submitted the admin form (ie if someone has pressed save).
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payler', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->redirect($this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL'));
        }
        
        //This is how the language gets pulled through from the language file.
        //
        // If you want to use any extra language items - ie extra text on your admin page for any reason,
        // then just add an extra line to the $text_strings array with the name you want to call the extra text,
        // then add the same named item to the $_[] array in the language file.
        $text_strings = array(
            'heading_title',
            'text_payler',
            'text_payment',
            'text_success',
            'text_enabled',
            'text_disabled',
            'text_all_zones',
            'text_on',
            'text_off',
            'text_return_url',
            'button_save',
            'button_cancel',            
            'entry_key',
            'entry_test_mode',
            'entry_order_status',
            'entry_module_status',
            'entry_geo_zone',
            'entry_sort_order',
            'error_permission',
            'error_key',
        );

        foreach ($text_strings as $text) {
            $this->data[$text] = $this->language->get($text);
        }

        //The following code pulls in the required data from either config files or user
        //submitted data (when the user presses save in admin). Add any extra config data
        // you want to store.
        //
        // NOTE: These must have the same names as the form data in your *.tpl file
        //
        $config_data = array (
            'payler_key',
            'payler_test_mode',
            'payler_order_status',
            'payler_status',
            'payler_geo_zone_id',
            'payler_sort_order',
        );
        
        foreach ($config_data as $conf) {
            if (isset($this->request->post[$conf])) {
                $this->data[$conf] = $this->request->post[$conf];
            } else {
                $this->data[$conf] = $this->config->get($conf);
            }
        }
        
        //This creates an error message. The error['warning'] variable is set by the call to function validate() in this controller (below)
        if (isset($this->error['warning'])) {
            $this->data['error_warning'] = $this->error['warning'];
        } else {
            $this->data['error_warning'] = '';
        }

        if (isset($this->error['key'])) {
            $this->data['error_key'] = $this->error['key'];
        } else {
            $this->data['error_key'] = '';
        }

        //SET UP BREADCRUMB TRAIL. YOU WILL NOT NEED TO MODIFY THIS UNLESS YOU CHANGE YOUR MODULE NAME.
        $this->data['breadcrumbs'] = array();

        $this->data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => false
        );

        $this->data['breadcrumbs'][] = array(
            'text'      => $this->language->get('text_payment'),
            'href'      => $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => ' :: '
        );

        $this->data['breadcrumbs'][] = array(
            'text'      => $this->language->get('heading_title'),
            'href'      => $this->url->link('payment/payler', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => ' :: '
        );
                        
        $this->data['action'] = $this->url->link('payment/payler', 'token=' . $this->session->data['token'], 'SSL');
        $this->data['cancel'] = $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL');

        //Localize order_statuses and geo_zones
        $this->data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $this->data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
        
        //Choose which template file will be used to display this request.
        $this->template = 'payment/payler.tpl';
        $this->children = array(
        'common/header',
        'common/footer'
        );
        
        //Send the output.
        $this->response->setOutput($this->render());

    }
    
    /*
     * 
     * This function is called to ensure that the settings chosen by the admin user are allowed/valid.
     * You can add checks in here of your own.
     * 
     */
    private function validate() {
        if (!$this->user->hasPermission('modify', 'payment/payler')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        
        if (!$this->request->post['payler_key']) {
            $this->error['key'] = $this->language->get('error_key');
        }

        if (!$this->error) {
            return TRUE;
        } else {
            return FALSE;
        }	
    }

}

?>
