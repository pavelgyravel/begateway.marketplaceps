<?php
use Bitrix\Main\Loader,
	Bitrix\Main\Localization\Loc,
	Bitrix\Sale\PaySystem;

Loc::loadMessages(__FILE__);

$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
$protocol = $request->isHttps() ? 'https://' : 'http://';


$isAvailable = PaySystem\Manager::HANDLER_AVAILABLE_TRUE;

$licensePrefix = Loader::includeModule('bitrix24') ? \CBitrix24::getLicensePrefix() : '';
$portalZone = Loader::includeModule('intranet') ? CIntranetUtils::getPortalZone() : '';

if (Loader::includeModule('bitrix24'))
{
	if ($licensePrefix !== 'ru')
	{
		$isAvailable = PaySystem\Manager::HANDLER_AVAILABLE_FALSE;
	}
}
elseif (Loader::includeModule('intranet') && $portalZone !== 'ru')
{
	$isAvailable = PaySystem\Manager::HANDLER_AVAILABLE_FALSE;
}

$data = [
	'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS'),
	'SORT' => 500,
	'IS_AVAILABLE' => $isAvailable,
	'CODES' => [
		'BEGATEWAY_MARKETPLACEPS_ID' => [
			'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_SHOP_ID'),
			'DESCRIPTION' => Loc::getMessage('SALE_HPS_BEGATEWAY_SHOP_ID_DESC'),
			'SORT' => 100,
			'GROUP' => 'GENERAL_SETTINGS',
		],
		'BEGATEWAY_MARKETPLACEPS_SECRET_KEY' => [
			'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_SECRET_KEY'),
			'DESCRIPTION' => Loc::getMessage('SALE_HPS_BEGATEWAY_SECRET_KEY_DESC'),
			'SORT' => 200,
			'GROUP' => 'GENERAL_SETTINGS',
		],
		'BEGATEWAY_MARKETPLACEPS_PUBLIC_KEY' => [
			'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_PUBLIC_KEY'),
			'DESCRIPTION' => Loc::getMessage('SALE_HPS_BEGATEWAY_PUBLIC_KEY_DESC'),
			'SORT' => 300,
			'GROUP' => 'GENERAL_SETTINGS',
		],
		// 'BEGATEWAY_MARKETPLACEPS_SERVICE_CODE' => [
		// 	'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_SERVICE_CODE'),
		// 	'DESCRIPTION' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_SERVICE_CODE_DESC'),
		// 	'SORT' => 400,
		// 	'GROUP' => 'GENERAL_SETTINGS',
		// ],
		'BEGATEWAY_MARKETPLACEPS_PAYMENT_DESCRIPTION' => [
			'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_PAYMENT_DESCRIPTION'),
			'DESCRIPTION' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_PAYMENT_DESCRIPTION_DESC'),
			'SORT' => 500,
			'GROUP' => 'GENERAL_SETTINGS',
			'DEFAULT' => [
				'PROVIDER_KEY' => 'VALUE',
				'PROVIDER_VALUE' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_PAYMENT_DESCRIPTION_TEMPLATE'),
			],
		],
		'BEGATEWAY_MARKETPLACEPS_PAYMENT_ACCOUNT' => [
			'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_PAYMENT_ACCOUNT'),
			'DESCRIPTION' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_PAYMENT_ACCOUNT_DESC'),
			'SORT' => 600,
			'GROUP' => 'GENERAL_SETTINGS',
			'DEFAULT' => [
				'PROVIDER_KEY' => 'VALUE',
				'PROVIDER_VALUE' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_PAYMENT_ACCOUNT_TEMPLATE'),
			],
		],

		'BEGATEWAY_MARKETPLACEPS_PAYMENT_BAPB_ACCOUNT' => [
			'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_PAYMENT_BAPB_ACCOUNT'),
			'DESCRIPTION' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_PAYMENT_BAPB_ACCOUNT_DESC'),
			'SORT' => 610,
			'GROUP' => 'GENERAL_SETTINGS',
		],
		'BEGATEWAY_MARKETPLACEPS_PAYMENT_BAPB_NAME' => [
			'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_PAYMENT_BAPB_NAME'),
			'DESCRIPTION' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_PAYMENT_BAPB_NAME_DESC'),
			'SORT' => 620,
			'GROUP' => 'GENERAL_SETTINGS',
		],
		'BEGATEWAY_MARKETPLACEPS_PAYMENT_BAPB_UNP' => [
			'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_PAYMENT_BAPB_UNP'),
			'DESCRIPTION' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_PAYMENT_BAPB_UNP_DESC'),
			'SORT' => 630,
			'GROUP' => 'GENERAL_SETTINGS',
		],

		'BEGATEWAY_MARKETPLACEPS_RECEIPT_PAYMENT_DESC' => [
			'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_RECEIPT_PAYMENT_DESC'),
			'DESCRIPTION' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_RECEIPT_PAYMENT_DESC_DESC'),
			'SORT' => 700,
			'GROUP' => 'GENERAL_SETTINGS',
			'DEFAULT' => [
				'PROVIDER_KEY' => 'VALUE',
				'PROVIDER_VALUE' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_RECEIPT_PAYMENT_DESC_TEMPLATE'),
			],
		],
		'BEGATEWAY_MARKETPLACEPS_NOTIFICATION_URL' => [
			'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_NOTIFICATION_URL'),
			'SORT' => 800,
			'GROUP' => 'GENERAL_SETTINGS',
			'DEFAULT' => [
				'PROVIDER_KEY' => 'VALUE',
				'PROVIDER_VALUE' => $protocol.$request->getHttpHost().'/bitrixxxxxxxxx/tools/sale_ps_result.php',
			],
		],
		'BEGATEWAY_MARKETPLACEPS_AUTO_BILL' => [
			'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_AUTO_BILL'),
			'DESCRIPTION' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_AUTO_BILL_DESC'),
			'SORT' => 900,
			'GROUP' => 'GENERAL_SETTINGS',
			'INPUT' => [
				'TYPE' => 'Y/N',
			],
			'DEFAULT' => [
				'PROVIDER_KEY' => 'INPUT',
				'PROVIDER_VALUE' => 'Y',
			],
		],
		'BEGATEWAY_MARKETPLACEPS_EXPIRY' => [
			'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_EXPIRY'),
			'DESCRIPTION' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_EXPIRY_DESC'),
			'SORT' => 1000,
			'GROUP' => 'GENERAL_SETTINGS',
		],
    // 'BUYER_PERSON_NAME_FIRST' => [
    //   'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_FIRST_NAME'),
    //   'SORT' => 1100,
    //   'GROUP' => 'BUYER_PERSON'
    // ],
    // 'BUYER_PERSON_NAME_MIDDLE' => [
    //   'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_MIDDLE_NAME'),
    //   'SORT' => 1200,
    //   'GROUP' => 'BUYER_PERSON'
    // ],
    // 'BUYER_PERSON_NAME_LAST' => [
    //   'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_LAST_NAME'),
    //   'SORT' => 1300,
    //   'GROUP' => 'BUYER_PERSON'
    // ],
    // 'BUYER_PERSON_EMAIL' => [
    //   'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_EMAIL'),
    //   'SORT' => 1400,
    //   'GROUP' => 'BUYER_PERSON'
    // ],
    // 'BUYER_PERSON_ADDRESS' => [
    //   'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_ADDRESS'),
    //   'SORT' => 1500,
    //   'GROUP' => 'BUYER_PERSON'
    // ],
    // 'BUYER_PERSON_CITY' => [
    //   'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_CITY'),
    //   'SORT' => 1700,
    //   'GROUP' => 'BUYER_PERSON'
    // ],
    // 'BUYER_PERSON_ZIP' => [
    //   'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_ZIP'),
    //   'SORT' => 1800,
    //   'GROUP' => 'BUYER_PERSON'
    // ],
    // 'BUYER_PERSON_PHONE' => [
    //   'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_PHONE'),
    //   'SORT' => 1900,
    //   'GROUP' => 'BUYER_PERSON'
    // ],
		'PS_IS_TEST' => [
			'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_IS_TEST'),
			'SORT' => 2000,
			'GROUP' => 'GENERAL_SETTINGS',
			'INPUT' => [
				'TYPE' => 'Y/N'
			],
		],
		'PS_CHANGE_STATUS_PAY' => [
			'NAME' => Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_CHANGE_STATUS_PAY'),
			'SORT' => 2100,
			'GROUP' => 'GENERAL_SETTINGS',
			'INPUT' => [
				'TYPE' => 'Y/N',
			],
			'DEFAULT' => [
				'PROVIDER_KEY' => 'INPUT',
				'PROVIDER_VALUE' => 'Y',
			]
		]
	]
];
