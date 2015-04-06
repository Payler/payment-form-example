	<xsl:template match="purchasing[@stage = 'payment'][@step = 'payler']">
		<form action="{formAction}" method="post">
			<input type="hidden" name="session_id" value="{session_id}" />
			<input type="hidden" name="order-id" value="{orderId}" />

			<div>
				<xsl:text>&payment-redirect-text; Payler.</xsl:text>
			</div>

			<div>
				<input type="submit" value="&pay;" class="button big" />
			</div>
		</form>
		<xsl:call-template name="form-send" />
	</xsl:template>