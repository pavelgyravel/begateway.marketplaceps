<?
namespace BeGateway\Module\Marketplaceps;

use Bitrix\Main,
  Bitrix\Main\ModuleManager,
	Bitrix\Main\Web\HttpClient,
	Bitrix\Main\Localization\Loc,
	Bitrix\Sale,
	Bitrix\Sale\Order,
	Bitrix\Sale\PaySystem,
	Bitrix\Main\Request,
	Bitrix\Sale\Payment,
	Bitrix\Sale\PaySystem\ServiceResult,
	Bitrix\Sale\PaymentCollection,
  Bitrix\Main\Diag\Debug,
	Bitrix\Sale\PriceMaths;

Loc::loadMessages(__FILE__);

\CModule::IncludeModule('begateway.marketplaceps');

class EventHandler {
  public static function OnBeforeSaleOrderSetField(\Bitrix\Main\Event $event)
  {

    if ($event->getParameter("NAME") != 'STATUS_ID')
      return;

    $order = $event->getParameter("ENTITY");
    $value = $event->getParameter("VALUE");

    # проверяем не находился ли заказ уже в статусе ORDER_AWAITING_STATUS
    # и не был ли создан заказ хэндлеров в автоматическом режиме
    if ($value == \BeGateway\Module\Marketplaceps\OrderStatuses::ORDER_AWAITING_STATUS &&
        $order->getField('STATUS_ID') != \BeGateway\Module\Marketplaceps\OrderStatuses::ORDER_AWAITING_STATUS) {

      $result = self::initiatePay($order);

      if ($result->isSuccess()) {
        // отсылаем письмо с инструкцией
        $data = $result->getData();
        for ($i = 0;$i < $data['counter']; $i++) {
          $collection = $order->getPaymentCollection();
          $payment = $collection->getItemById($data['ids'][$i]);

          self::sendMail($order, $payment, $data['params'][$i]);
        }
      } else {
        return new \Bitrix\Main\EventResult(
          \Bitrix\Main\EventResult::ERROR,
          new \Bitrix\Sale\ResultError(Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_EA_STATUS_CHANGE_ERROR'), 'BEGATEWAY_MARKETPLACEPS_CREATE_ERROR'),
          'sale'
        );
      }
    }

    # проверяем не находился ли заказ уже в статусе ORDER_CANCELED_STATUS
    # и был ли создан счет в bePaid marketplace для заказа ранее
    if ($value == \BeGateway\Module\Marketplaceps\OrderStatuses::ORDER_CANCELED_STATUS &&
        $order->getField('STATUS_ID') != \BeGateway\Module\Marketplaceps\OrderStatuses::ORDER_CANCELED_STATUS) {

      $result = self::cancelPay($order);

      if (!$result->isSuccess()) {
        return new \Bitrix\Main\EventResult(
          \Bitrix\Main\EventResult::ERROR,
          new \Bitrix\Sale\ResultError(Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_EC_STATUS_CHANGE_ERROR'), 'BEGATEWAY_MARKETPLACEPS_CANCEL_ERROR'),
          'sale'
        );
      }
    }

    return new \Bitrix\Main\EventResult(
      \Bitrix\Main\EventResult::SUCCESS
    );
  }

  /**
	 * @param Order $payment
	 * @param Request|null $request
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */

  public static function initiatePay(Order $order) {
    $result = new ServiceResult();

    $resultStorage = [
      'counter' => 0,
      'ids' => [],
      'params' => []
    ];

    $result->setData($resultStorage);

    $paymentCollection = $order->getPaymentCollection();

    foreach ($paymentCollection as $payment) {

      $ps = $payment->getPaySystem();
      $description = $ps->getHandlerDescription();

      if (!isset($description['CODES']['BEGATEWAY_MARKETPLACEPS_ID'])) { // не обработчик bePaid marketplace
        continue;
      }

      if ($payment->isPaid()) {// пропускаем уже оплаченные bePaid marketplace платежи
        continue;
      }

      // пропускаем счета уже выставленные в bePaid marketplace
      if (!empty($payment->getField('PS_INVOICE_ID'))) {
       continue;
      }

      $request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
      // вызываем обработчик платежной системы, чтобы создать счет
      $ps_status_message = $payment->getField('PS_STATUS_MESSAGE');
      $payment->setField('PS_STATUS_MESSAGE', 'manual');

      $result = $ps->initiatePay($payment, $request);

      if ($payment->getField('PS_STATUS_MESSAGE') == 'manual') {
        $payment->setField('PS_STATUS_MESSAGE', $ps_status_message);
      }

      if ($result->isSuccess()) {

        // сохраняем номер операции bePaid marketplace в данных способа оплаты
        $psData = $result->getPsData();
        if ($psData['PS_INVOICE_ID']) {
          $payment->setField('PS_INVOICE_ID', $psData['PS_INVOICE_ID']);
          $order->save();
          $resultStorage['ids'] []= $payment->getId();
          // сохраняем данные bePaid marketplace счета для шаблона письма
          $resultStorage['params'] []= $result->getData();
        }
      }

      $resultStorage['counter'] += 1;
    }

    # проверяем, что все обработчики завершились успешно
    if ($resultStorage['counter'] == count($resultStorage['ids'])) {
      $result->setData($resultStorage);
    } else {
      $result->addError(PaySystem\Error::create(Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_EA_STATUS_CHANGE_ERROR')));
    }

    return $result;
  }

  public static function cancelPay(Order $order) {
    $result = new ServiceResult();

    $resultStorage = [
      'counter' => 0,
      'ids' => [],
      'params' => []
    ];

    $result->setData($resultStorage);

    $paymentCollection = $order->getPaymentCollection();

    foreach ($paymentCollection as $payment) {

      $ps = $payment->getPaySystem();
      $description = $ps->getHandlerDescription();

      if (!isset($description['CODES']['BEGATEWAY_MARKETPLACEPS_ID'])) { // не обработчик bePaid marketplace
        continue;
      }

      if ($payment->isPaid()) {// пропускаем уже оплаченные bePaid marketplace платежи
        continue;
      }

      // пропускаем счета не выставленные в bePaid marketplace
      if (empty($payment->getField('PS_INVOICE_ID'))) {
       continue;
      }

      // вызываем обработчик платежной системы, чтобы отменить счет
      $result = $ps->cancel($payment);

      if ($result->isSuccess()) {

        // удаляем номер операции bePaid marketplace в данном способе оплаты
        $payment->setField('PS_INVOICE_ID', null);
        $order->save();
        $resultStorage['ids'] []= $payment->getId();
        // сохраняем данные bePaid marketplace счета для шаблона письма
        $resultStorage['params'] []= $result->getData();
      }

      $resultStorage['counter'] += 1;
    }

    # проверяем, что все обработчики завершились успешно
    if ($resultStorage['counter'] == count($resultStorage['ids'])) {
      $result->setData($resultStorage);
    } else {
      $result->addError(PaySystem\Error::create(Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_EC_STATUS_CHANGE_ERROR')));
    }

    return $result;
  }

  static public function sendMail(Order $order, Payment $payment, $params)
	{
    $info = self::getSiteInfo($order);
    $userEmail = $order->getPropertyCollection()->getUserEmail();
    $userName = $order->getPropertyCollection()->getPayerName();

		$fields = array(
				'EMAIL' => ($userEmail) ? $userEmail->getValue() : '',
				'NAME' => ($userName) ? $userName->getValue() : '',
				"ORDER_ID" => $order->getId(),
        'ORDER_NUMBER' => $order->getField('ACCOUNT_NUMBER'),
        'ORDER_DATE' => $order->getDateInsert()->toString(),
        'PAYMENT_NUMBER' => $payment->getField('ACCOUNT_NUMBER'),
        'PAYMENT_ID' => $payment->getId(),
				'SALE_EMAIL' => Main\Config\Option::get("sale", "order_email", "order@".$_SERVER["SERVER_NAME"]),
        'BCC' => Main\Config\Option::get("sale", "order_email", "order@".$_SERVER['SERVER_NAME']),
        'ORDER_PUBLIC_URL' => '',
        'INSTRUCTION' => $params['instruction'],
        'ACCOUNT_NUMBER' => $params['account_number'],
        'MARKETPLACEPS_SERVICE_CODE' => $params['service_no_marketplaceps'],
				'QR_CODE' => $params['qr_code'],
	  );

    if (!empty($info)) {
      $fields["SITE_NAME"] = $info['SITE_NAME'];
      $fields["SERVER_NAME"] = $info['SERVER_NAME'];
      $fields["ORDER_PUBLIC_URL"] = 'http://' . $info['SERVER_NAME'];
    }

    $public_link = self::getPublicLink($order);

    if (!empty($public_link)) {
      $fields["ORDER_PUBLIC_URL"] = $public_link;
    }

    \Bitrix\Main\Mail\Event::send(array(
      "EVENT_NAME" => \BeGateway\Module\Marketplaceps\Events::ORDER_STATUS_CHANGED_TO_EA,
      "LID" => $order->getField('LID'),
      "LANGUAGE_ID" => $info["LANGUAGE_ID"],
      "C_FIELDS" => $fields
    ));
	}

  static protected function getPublicLink(Order $order) {
    $link = '';
    if (method_exists('Bitrix\Sale\Helpers\Order', 'isAllowGuestView')) {
      $link = Sale\Helpers\Order::isAllowGuestView($order) ? Sale\Helpers\Order::getPublicLink($order) : "";
    }

    return $link;
  }

  static protected function getSiteInfo(Order $order) {
    $dbSite = \CSite::GetByID($order->getSiteId());
    $arFields =  $dbSite->Fetch();

    return ($arFields) ?: [];
  }
}
