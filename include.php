<?
$classes = array(
				'\BeGateway\Module\Marketplaceps\EventHandler' => 'lib/event_handler.php',
				'\BeGateway\Module\Marketplaceps\Encoder' => 'lib/encoder.php',
				'\BeGateway\Module\Marketplaceps\OrderStatuses' => 'lib/order_statuses.php',
				'\BeGateway\Module\Marketplaceps\Events' => 'lib/order_statuses.php',
				'\BeGateway\Module\Marketplaceps\Money' => 'lib/money.php',
				'\BeGateway\Module\Marketplaceps\OrderDataTable' => 'lib/orderdatatable.php'
		   );

CModule::AddAutoloadClasses('begateway.marketplaceps', $classes);
