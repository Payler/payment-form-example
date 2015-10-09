<?php
return array(

	'test' => array(
		'value' => true,
        'title'        => 'Тестовый режим',
        'description'  => 'Включите, если используете sandbox',
        'control_type' => waHtmlControl::CHECKBOX,
	),
	'protocol' => array(
		'value' => 'https',
	),
	'apiUrl' => array(
		'value' => 'payler.com/gapi/'
	),
    'privateKey' => array(
        'value'        => '',
        'title'        => 'Платёжный ключ',
        'description'  => 'Платёжный ключ Payler.',
        'control_type' => 'input',
        'class'        => 'js-payler-private-key',
    ),
    'merchant_id' => array(
        'value'        => 1138,
//        'title'        => 'ID',
//        'description'  => 'ID',
//        'control_type' => 'input',
//        'class'        => 'js-payler-private-key',
    ),
//    'paymentPass' => array(
//        'value'        => '',
//        'title'        => 'Платёжный пароль',
//        'description'  => 'Платёжный пароль (для возвратов)',
//		'control_type' => waHtmlControl::PASSWORD,
//        'class'        => 'js-payler-payment-pass',
//    ),
);
