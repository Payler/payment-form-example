<?php
include 'payler_class.php';
include 'settings.php';

$payler = new Payler($test);

$data = array (
	"key" => $key,
	//"recurrent_template_id" => "rec-pay-f75bf310-177c-457e-82f0-4307d532efd9",
	);
$result = $payler->POSTtoGateAPI($data, "GetTemplate");

print_r($result);