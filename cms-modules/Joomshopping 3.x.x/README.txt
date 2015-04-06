Модуль для интеграции Payler с Joomla+JoomShopping
==================================================

Модуль проверен под Joomla v.2.5 и JoomShopping 3.x.x

1. В разделе "Компоненты/JoomShopping/Установка и обновление"
   (administrator/index.php?option=com_jshopping&controller=update) загрузите
   файл "payler_joomla_joomshopping.zip".

2. В разделе "Компоненты/JoomShopping/Опции/Способ оплаты"
   (administrator/index.php?option=com_jshopping&controller=payments) выберите
   способ оплаты Payler. Можете изменить название на удобное вам.

   В разделе "Конфигурация":
   - "Ключ от payler.com" - введите ключ, предоставленный payler.com.
   - "Тестовый режим" - выберите будут ли платежи идти в тестовом режиме.

   Для полей со статусами заказа выберите то, что подходит по логике работы
   вашего сайта.  Сохраните сделанные изменения.

3. В качестве адреса для возврата клиента в системе Payler необходимо указать адрес:
   http://вашсайт/index.php?option=com_jshopping&controller=checkout&task=step7&js_paymentclass=pm_payler&order_id={order_id}
