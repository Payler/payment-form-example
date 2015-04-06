<?php
/*
* @package JoomShopping for Joomla!
* @subpackage payment
* @author Payler LLC
* @copyright Copyright (C) 2014 Payler LLC. All rights reserved.
* @license GNU General Public License version 2 or later
*/

//защита от прямого доступа
//defined('_JEXEC') or die();
include(dirname(__FILE__)."/lang/ru-RU.php");
//вывод настроек плагина
?>
<div class="col100">
	<fieldset class="adminform">
		<table class="admintable" width="100%">
			<tr>
				<td class="key" width="300">
					<?php echo _JSHOP_CFG_PAYLER_KEY; ?></td>
				<td>
					<input type="text" name="pm_params[payler_key]" class="inputbox" value="<?php echo $params['payler_key']; ?>" />
					<?php echo JHTML::tooltip(_JSHOP_CFG_PAYLER_KEY_DESCRIPTION); ?>
				</td>
			</tr>
			<tr>
				<td style="width:250px;" class="key">
				 <?php echo _JSHOP_CFG_PAYLER_TESTMODE;?>
				</td>
				<td>
				 <?php              
				 print JHTML::_('select.booleanlist', 'pm_params[payler_test_mode]', 'class = "inputbox" size = "1"', $params['payler_test_mode']);
				 echo " ".JHTML::tooltip(_JSHOP_CFG_PAYLER_TESTMODE_DESCRIPTION);
				 ?>
				</td>
			</tr>
            <tr>
                <td class="key">
                    <?php echo _JSHOP_TRANSACTION_END;?>:
                </td>
                <td>
                    <?php 
					print JHTML::_('select.genericlist', $orders->getAllOrderStatus(), 'pm_params[transaction_end_status]', 'class = "inputbox" size = "1"', 'status_id', 'name', $params['transaction_end_status'] );					
					?>
                </td>
            </tr>
            <tr>
                <td class="key">
                    <?php echo _JSHOP_TRANSACTION_PENDING;?>:
                </td>
                <td>
                    <?php 
					echo JHTML::_('select.genericlist',$orders->getAllOrderStatus(), 'pm_params[transaction_pending_status]', 'class = "inputbox" size = "1"', 'status_id', 'name', $params['transaction_pending_status']);
					?>
                </td>
            </tr>
            <tr>
                <td class="key">
                    <?php echo _JSHOP_TRANSACTION_FAILED;?>:
                </td>
                <td>
                    <?php 
					echo JHTML::_('select.genericlist',$orders->getAllOrderStatus(), 'pm_params[transaction_failed_status]', 'class = "inputbox" size = "1"', 'status_id', 'name', $params['transaction_failed_status']);
					?>
                </td>
            </tr>
		</table>
	</fieldset>
</div>
<div class="clr"></div>