<?php
$FORMS = Array();

$FORMS['form_block'] = <<<END

<form action="%formAction%" method="post">
	<input type="hidden" name="session_id" value="%session_id%" />
        <!-- NB! This field should exist for proper system working -->
	<input type="hidden" name="order-id"    value="%orderId%" />
        <p>
            Нажмите кнопку "Оплатить" для перехода на сайт платежной системы 
            <strong>Payler</strong>.
	</p>        
	<p>
		<input type="submit" value="Оплатить" />
	</p>
</form>
END;

?>