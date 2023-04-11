<?php
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$sum = round($params['sum'], 2);
?>

<div class="mb-4" id="begateway-marketplaceps">
	<p><?= Loc::getMessage('SALE_HANDLERS_PAY_SYSTEM_TEMPLATE_BEGATEWAY_MARKETPLACEPS_CHECKOUT_DESCRIPTION') ?></p>
	<p><?= Loc::getMessage('SALE_HANDLERS_PAY_SYSTEM_TEMPLATE_BEGATEWAY_MARKETPLACEPS_INSTRUCTION') ?></p>

	<p><?= Loc::getMessage('SALE_HANDLERS_PAY_SYSTEM_TEMPLATE_BEGATEWAY_MARKETPLACEPS_CHECKOUT_WARNING_RETURN') ?></p>
</div>
