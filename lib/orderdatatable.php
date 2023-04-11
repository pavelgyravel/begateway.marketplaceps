<?php
namespace BeGateway\Module\Marketplaceps;

// use Bitrix\Main\Localization\Loc,
// 	Bitrix\Main\ORM\Data\DataManager,
// 	Bitrix\Main\ORM\Fields\IntegerField,
// 	Bitrix\Main\ORM\Fields\StringField,
// 	Bitrix\Main\ORM\Fields\TextField,
// 	Bitrix\Main\ORM\Fields\Validators\LengthValidator;

use Bitrix\Main;
use Bitrix\Main\Type;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Class OrderDataTable
 * 
 * Fields:
 * <ul>
 * <li> ID int mandatory
 * <li> PS_INVOICE_ID string(255) mandatory
 * <li> PARAMS text mandatory
 * </ul>
 *
 * @package Bitrix\Bemarketplaceps
 **/

class OrderDataTable extends Main\Entity\DataManager
{
	/**
	 * Returns DB table name for entity.
	 *
	 * @return string
	 */
	public static function getTableName()
	{
		return 'b_bemarketplaceps_order_data';
	}

	/**
	 * Returns entity map definition.
	 *
	 * @return array
	 */
	public static function getMap()
	{
		return [
			new Main\Entity\IntegerField(
				'ID',
				[
					'primary' => true,
					'autocomplete' => true,
					'title' => Loc::getMessage('ORDER_DATA_ENTITY_ID_FIELD')
				]
			),
			new Main\Entity\StringField(
				'PS_INVOICE_ID',
				[
					'required' => true,
					'validation' => [__CLASS__, 'validatePsInvoiceId'],
					'title' => Loc::getMessage('ORDER_DATA_ENTITY_PS_INVOICE_ID_FIELD')
				]
			),
			new Main\Entity\TextField(
				'PARAMS',
				[
					'required' => true,
					'title' => Loc::getMessage('ORDER_DATA_ENTITY_PARAMS_FIELD')
				]
			),
		];
	}

	/**
	 * Returns validators for PS_INVOICE_ID field.
	 *
	 * @return array
	 */
	public static function validatePsInvoiceId()
	{
		return [
			new Main\Entity\Validator\Length(null, 255),
		];
	}
}