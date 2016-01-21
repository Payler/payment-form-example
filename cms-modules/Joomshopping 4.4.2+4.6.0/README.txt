Модуль для интеграции Payler с Joomla+JoomShopping
==================================================

Модуль проверен под Joomla v.3.3.* и JoomShopping 4.4.2 и 4.6.0

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

3. Если не удалось установить модуль, вручную скопируйте каталог components из архива в корень сайта. Затем в "Components/JoomShopping/Options/Payments" надо будет добавить модуль оплаты. указав в качестве alias - “pm_payler” и указать type - “extended”. После сохранения способа оплаты на вкладке "Конфигурация" станут доступны настройки модуля Payler.

4. В качестве адреса для возврата клиента в системе Payler необходимо указать адрес:
   http://вашсайт/index.php?option=com_jshopping&controller=checkout&task=step7&js_paymentclass=pm_payler&order_id={order_id}
