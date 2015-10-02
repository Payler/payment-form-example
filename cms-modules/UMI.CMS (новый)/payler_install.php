<?php 
$ini_array = parse_ini_file("config.ini", true);

$host = $ini_array['connections']['core.host'];
$user = $ini_array['connections']['core.login'];
$pass = $ini_array['connections']['core.password'];
$dbase = $ini_array['connections']['core.dbname'];

$link = mysql_connect($host,$user,$pass);

mysql_select_db($dbase);
mysql_set_charset('utf8');

mysql_query("SELECT @parent_id:=id FROM `cms3_object_types` WHERE `guid`='emarket-payment'");
mysql_query("SELECT @hierarchy_type_id:=id FROM `cms3_hierarchy_types` WHERE `name`='emarket' AND `ext`='payment'");
mysql_query("SELECT @type_id:=id FROM `cms3_object_types` WHERE `guid`='emarket-paymenttype'");
mysql_query("SELECT @payment_type_id:=id FROM `cms3_object_fields` WHERE `name`='payment_type_id'");

mysql_query("INSERT INTO `cms3_object_types` VALUES(NULL, 'emarket-payment-payler', 'payler', 1, @parent_id, 0, 0, @hierarchy_type_id, 0)");
mysql_query("SET @obj_type = LAST_INSERT_ID()");
mysql_query("INSERT INTO `cms3_import_types` VALUES (1, 'payler', @obj_type)");
mysql_query("INSERT INTO `cms3_objects` VALUES(NULL, 'emarket-paymenttype-payler', 'payler', 0, @type_id, 9, NULL)");
mysql_query("SET @obj = LAST_INSERT_ID()");
mysql_query("INSERT INTO `cms3_import_objects`  VALUES(1, 'payler', @obj)");

mysql_query("SELECT @field_id:=new_id FROM `cms3_import_fields` WHERE `source_id`='1' AND `field_name`='class_name' AND `type_id`=@type_id");
mysql_query("INSERT INTO `cms3_object_content` VALUES(@obj, @field_id, NULL, 'payler', NULL, NULL, NULL, NULL)");
mysql_query("SELECT @field_id:=new_id FROM `cms3_import_fields` WHERE `source_id`='1' AND `field_name`='payment_type_id' AND `type_id`=@type_id");
mysql_query("INSERT INTO `cms3_object_content` VALUES(@obj, @field_id, @obj_type, NULL, NULL, NULL, NULL, NULL)");
mysql_query("SELECT @field_id:=new_id FROM `cms3_import_fields` WHERE `source_id`='1' AND `field_name`='payment_type_guid' AND `type_id`=@type_id");
mysql_query("INSERT INTO `cms3_object_content` VALUES(@obj, @field_id, NULL, 'emarket-payment-payler', NULL, NULL, NULL, NULL)");

mysql_query("INSERT INTO `cms3_object_field_groups` VALUES(NULL, 'payment_props', 'Свойства способа оплаты', @obj_type, 1, 1, 5, 0)");
mysql_query("SET @field_group = LAST_INSERT_ID()");
mysql_query("INSERT INTO `cms3_fields_controller` VALUES(5, @payment_type_id, @field_group)");

mysql_query("INSERT INTO `cms3_object_field_groups` VALUES(NULL, 'settings', 'Параметры', @obj_type, 1, 1, 10, 0)");
mysql_query("SET @field_group = LAST_INSERT_ID()");

mysql_query("INSERT INTO `cms3_object_fields` VALUES(NULL, 'fk_merchant_id', 'Платежный ключ', 0, 13, 0, 1, NULL, 0, 0, 'Платежный ключ', 1, NULL, 0, 0)");
mysql_query("SET @field = LAST_INSERT_ID()");
mysql_query("INSERT INTO `cms3_fields_controller` VALUES (5, @field, @field_group)");

mysql_query("INSERT INTO `cms3_object_fields` VALUES(NULL, 'fk_test_mode', 'Тестовый режим', 0, 1, 0, 1, NULL, 0, 0, 'Тестовый режим', 1, NULL, 0, 0)");
mysql_query("SET @field = LAST_INSERT_ID()");
mysql_query("INSERT INTO `cms3_fields_controller` VALUES (5, @field, @field_group)");

mysql_query("SELECT @order_field_group:=id FROM `cms3_object_field_groups` WHERE `name`='order_props'");
mysql_query("INSERT INTO `cms3_object_fields` VALUES(NULL, 'payler_order_id', 'Номер заказа в системе Payler', 0, 13, 0, 1, NULL, 0, 0, 'Номер заказа в Payler', 1, NULL, 0, 0)");
mysql_query("SET @field = LAST_INSERT_ID()");
mysql_query("INSERT INTO `cms3_fields_controller` VALUES (5, @field, @order_field_group)");

echo "Complete!";
?>
