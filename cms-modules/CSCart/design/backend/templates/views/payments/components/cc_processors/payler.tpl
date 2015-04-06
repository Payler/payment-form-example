{assign var="r_url" value="payment_notification.result?payment=payler&order_id={order_id}"|fn_url:'C':'http'}
<p>{__("text_payler_notice", ["[result_url]" => $r_url])}</p>

<div class="control-group">
    <label class="control-label" for="key">{__("key")}:</label>
    <div class="controls">
        <input type="text" name="payment_data[processor_params][key]" id="key" value="{$processor_params.key}" >
    </div>
</div>
    
<div class="control-group">
    <label class="control-label" for="mode">{__("test_live_mode")}:</label>
    <div class="controls">
        <select name="payment_data[processor_params][mode]" id="mode">
            <option value="test" {if $processor_params.mode == "test"}selected="selected"{/if}>{__("test")}</option>
            <option value="live" {if $processor_params.mode == "live"}selected="selected"{/if}>{__("live")}</option>
        </select>
    </div>
</div>