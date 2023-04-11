<?php
namespace Sale\Handlers\PaySystem;

use Bitrix\Main,
  Bitrix\Main\ModuleManager,
	Bitrix\Main\Web\HttpClient,
	Bitrix\Main\Localization\Loc,
	Bitrix\Sale,
	Bitrix\Sale\PaySystem,
	Bitrix\Main\Request,
	Bitrix\Sale\Payment,
  Bitrix\Main\Diag\Debug,
	Bitrix\Sale\PaySystem\ServiceResult,
	Bitrix\Sale\PaymentCollection,
	Bitrix\Sale\PriceMaths;

Loc::loadMessages(__FILE__);

\CModule::IncludeModule('begateway.marketplaceps');
\CModule::IncludeModule("begateway.marketplace");

/**
 * Class BePaidHandler
 * @package Sale\Handlers\PaySystem
 */
class begateway_marketplacepsHandler
  extends PaySystem\ServiceHandler
  implements PaySystem\IHold, PaySystem\ICheckable
{
	private const API_URL                 = 'https://api.bepaid.by';

	private const TRACKING_ID_DELIMITER   = '#';


	private const STATUS_SUCCESSFUL_CODE  = 'successful';
	private const STATUS_FAILED_CODE      = 'failed';
	private const STATUS_EXPIRED_CODE     = 'expired';
	private const STATUS_PENDING_CODE     = 'pending';

	private const STATUS_ERROR_CODE       = 'error';

	private const SEND_METHOD_HTTP_POST   = 'POST';
	private const SEND_METHOD_HTTP_GET    = 'GET';
	private const SEND_METHOD_HTTP_DELETE = 'DELETE';

	/**
	 * @param Payment $payment
	 * @param Request|null $request
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	public function initiatePay(Payment $payment, Request $request = null): ServiceResult
	{
		$result = new ServiceResult();

    # смена статуса заказа по событию из админки
    $ajaxMode = ($request) ? $this->isAdminChangeStatus($payment) : false;

    if ($this->isAutoMode($payment) || $ajaxMode) {
      if (empty($payment->getField('PS_INVOICE_ID'))) {
    		$createMarketplacepsBillResult = $this->createMarketplacepsBill($payment);
      } else {
        # счет был уже создан и нужно получить данные для шаблона
    		$createMarketplacepsBillResult = $this->getBeGatewayMarketplacepsPayment($payment);
      }

  		if (!$createMarketplacepsBillResult->isSuccess())
  		{
  			$result->addErrors($createMarketplacepsBillResult->getErrors());
  			return $result;
  		}

  		$createMarketplacepsBillData = $createMarketplacepsBillResult->getData();
			
  		if (!empty($createMarketplacepsBillData['transaction']['uid']))
  		{
  			$result->setPsData(['PS_INVOICE_ID' => $createMarketplacepsBillData['transaction']['uid']]);
        $result->setData($this->getTemplateParams($payment, $createMarketplacepsBillData));
  		}

  		$this->setExtraParams($this->getTemplateParams($payment, $createMarketplacepsBillData));
    }

    $showTemplateResult = $this->showTemplate($payment, $this->getTemplateName($payment));
		if ($showTemplateResult->isSuccess())
		{
			$result->setTemplate($showTemplateResult->getTemplate());
		}
		else
		{
			$result->addErrors($showTemplateResult->getErrors());
		}

		return $result;
	}

	/**
	 * @param Payment $payment
	 * @param Request|null $request
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	public function cancel(Payment $payment): ServiceResult
	{
		$result = new ServiceResult();
		$deleteMarketplacepsBillResult = $this->deleteMarketplacepsBill($payment);
    if (!$deleteMarketplacepsBillResult->isSuccess())
    {
      $result->addErrors($deleteMarketplacepsBillResult->getErrors());
      return $result;
    }
		$deleteMarketplacepsBillData = $deleteMarketplacepsBillResult->getData();

    $result->setData($deleteMarketplacepsBillData);

    return $result;
  }

  /**
   * @param Payment $payment
   * @return PaySystem\ServiceResult
   */
  public function confirm(Payment $payment): ServiceResult
  {
    $result = new ServiceResult();
		$result->addError(PaySystem\Error::create(Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_CONFIRM_ERROR')));
    return $result;
  }

  /**
   * @param Payment $payment
   * @return PaySystem\ServiceResult
   */
  public function check(Payment $payment): ServiceResult
  {
    $result = $this->processPayment($payment);

    return $result;
  }

	/**
	 * @param Payment $payment
	 * @return string
	 */
	private function getTemplateName(Payment $payment): string
	{	
		return 'redirect';
    // return $this->isAutoMode($payment) ? 'auto' : 'manual';
	}

	/**
	 * @param Payment $payment
	 * @return boolean
	 */
  private function isAutoMode(Payment $payment)
  {
    $autoModeSetting = $this->getBusinessValue($payment, 'BEGATEWAY_MARKETPLACEPS_AUTO_BILL');
    $createdMarketplacepsBill = !empty($payment->getField('PS_INVOICE_ID'));

    return $autoModeSetting == 'Y' || $createdMarketplacepsBill;
  }

	/**
	 * @param Request $request
	 * @return boolean
	 */
  private function isAdminChangeStatus(Payment $payment)
  {
    return $payment->getField('PS_STATUS_MESSAGE') == 'manual';
  }

	/**
	 * @param Payment $payment
	 * @param array $paymentTokenData
	 * @return array
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	private function getTemplateParams(Payment $payment, array $marketplacepsBillData): array
  {
		$params = [
			'sum' => PriceMaths::roundPrecision($payment->getSum() - $payment->getSumPaid()),
			'currency' => $payment->getField('CURRENCY'),
      'instruction' => $marketplacepsBillData['transaction']['marketplaceps']['instruction'],
      'qr_code' => $marketplacepsBillData['transaction']['marketplaceps']['qr_code'],
      'account_number' =>  $marketplacepsBillData['transaction']['marketplaceps']['account_number'],
      'service_no_marketplaceps' => $marketplacepsBillData['transaction']['marketplaceps']['service_no_marketplaceps'],
      'first_name' => $this->getBusinessValue($payment, 'BUYER_PERSON_NAME_FIRST'),
      'middle_name' => $this->getBusinessValue($payment, 'BUYER_PERSON_NAME_MIDDLE'),
			'form' => $marketplacepsBillData['transaction']['form'],
		];

		return $params;
	}

	/**
	 * @param Payment $payment
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	private function createMarketplacepsBill(Payment $payment): ServiceResult {
		$result = new ServiceResult();

		$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
		$protocol = $request->isHttps() ? 'https://' : 'http://';

		$url = $this->getUrl($payment, 'sendMarketplacepsBill');

    $money = new \BeGateway\Module\Marketplaceps\Money;
    $money->setCurrency($payment->getField('CURRENCY'));
    $money->setAmount($payment->getSum());

		AddMessage2Log(json_encode($payment), 'begateway.marketplaceps');

		$params = [
			'request' => [
				'test' => $this->isTestMode($payment),
				'amount' => $money->getCents(),
				'currency' => $payment->getField('CURRENCY'),
				'description' => \BeGateway\Module\Marketplaceps\Encoder::toUtf8($this->getPaymentDescription($payment), 255),
				'tracking_id' => $payment->getId().self::TRACKING_ID_DELIMITER.$this->service->getField('ID'),
				'notification_url' => $protocol.$request->getHttpHost().'/bitrix/tools/sale_ps_result.php',
				'return_url' => $protocol.$request->getHttpHost().'/bitrix/tools/sale_ps_result.php',
				'language' => LANGUAGE_ID,
				'method' => [
					'type' => $this->paymentMethod(),
					'bank_transfer' => [
						'beneficiary' => [
							'account_number' => \BeGateway\Module\Marketplaceps\Encoder::toUtf8($this->getBusinessValue($payment, 'BEGATEWAY_MARKETPLACEPS_PAYMENT_BAPB_ACCOUNT')),
							'name' => \BeGateway\Module\Marketplaceps\Encoder::toUtf8($this->getBusinessValue($payment, 'BEGATEWAY_MARKETPLACEPS_PAYMENT_BAPB_NAME')),
							'unp' => \BeGateway\Module\Marketplaceps\Encoder::toUtf8($this->getBusinessValue($payment, 'BEGATEWAY_MARKETPLACEPS_PAYMENT_BAPB_UNP'))
						]
					]
				],
        'additional_data' => [
					'customer' => [
						'id' => $this->customerId()
					]
        ]
			]
		];

		AddMessage2Log(json_encode($params), 'begateway.marketplaceps');
		
    $service_code = trim($this->getBusinessValue($payment, 'BEGATEWAY_MARKETPLACEPS_SERVICE_CODE'));
    if (isset($service_code) && !empty($service_code)) {
      $params['request']['payment_method']['service_no'] = $service_code;
    }

    $timeout = intval($this->getBusinessValue($payment, 'BEGATEWAY_MARKETPLACEPS_EXPIRY'));

    if ($timeout > 0) {
      $params['request']['expired_at'] = date("c", $timeout*60 + time());
    }

		$headers = $this->getHeaders($payment);

		$sendResult = $this->send(self::SEND_METHOD_HTTP_POST, $url, $params, $headers);
		if ($sendResult->isSuccess())
		{
			$marketplacepsBillData = $sendResult->getData();
			$verifyResponseResult = $this->verifyResponse($marketplacepsBillData);
			if ($verifyResponseResult->isSuccess())
			{
				$this->saveFormData($marketplacepsBillData);
				$result->setData($marketplacepsBillData);
			}
			else
			{
				$result->addErrors($verifyResponseResult->getErrors());
			}
		}
		else
		{
			$result->addErrors($sendResult->getErrors());
		}

		return $result;
	}

	private function saveFormData($marketplacepsBillData) {
		$data = array(
			'PS_INVOICE_ID' => $marketplacepsBillData['transaction']['uid'],
			'PARAMS' => json_encode(array('form' => $marketplacepsBillData['transaction']['form']))
		);
		\BeGateway\Module\Marketplaceps\OrderDataTable::add($data);
	}

	private function getFormData($marketplacepsBillData) {
		$result = \BeGateway\Module\Marketplaceps\OrderDataTable::getList(array(
			'filter' => array(
				'=PS_INVOICE_ID' => $marketplacepsBillData['transaction']['uid']
			)
		))->fetch();

		AddMessage2Log(json_encode($marketplacepsBillData), 'getFormData');

		if ($result) {
			return json_decode($result['PARAMS'], true);
		}
		AddMessage2Log("Form data for transaction (uid: " . $marketplacepsBillData['transaction']['uid'] . ") not found.", 'begateway.marketplaceps');
		return false;
	}

  /**
	 * @param Payment $payment
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\ArgumentTypeException
	 * @throws Main\ObjectException
	 */
	private function deleteMarketplacepsBill(Payment $payment): ServiceResult {
		$result = new ServiceResult();

		$url = $this->getUrl($payment, 'deleteMarketplacepsBill');
		$headers = $this->getHeaders($payment);

		$sendResult = $this->send(self::SEND_METHOD_HTTP_DELETE, $url, [], $headers);
		if ($sendResult->isSuccess())
		{
			$paymentData = $sendResult->getData();
			$verifyResponseResult = $this->verifyResponse($paymentData);
			if ($verifyResponseResult->isSuccess())
			{
				$result->setData($paymentData);
			}
			else
			{
				$result->addErrors($verifyResponseResult->getErrors());
			}
		}
		else
		{
			$result->addErrors($sendResult->getErrors());
		}

		return $result;
	}

	private function customerId() {
		global $USER;
		if (!$this->userXmlId) {
			$rsUser = \CUser::GetByID($USER->GetID());
			$arUser = $rsUser->Fetch();
			$this->userXmlId = $arUser['XML_ID'];
		}
		return $this->userXmlId;
	}

	private function customerIp(){
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		return $ip;
	}

	private function paymentMethod() {
		global $USER;

		$rsUserApplication = \BeGateway\Module\Marketplace\ApplicationUserTable::getList(array(
      'filter' => array(
        "=USER_ID" => $USER->GetID()
      )
    ));

		if ($arUserApplication = $rsUserApplication->fetch()) {
			$rsApplication = \BeGateway\Module\Marketplace\ApplicationsTable::getList(array(
        'filter' => array(
          "=ID" => $arUserApplication['APPLICATION_ID']
        )
      ));

			if ($arApplication = $rsApplication->fetch()) {
				switch ($arApplication['BANK_TYPE']) {
					case 'BelapbUl':
						return 'bank_transfer';
						break;
					case 'BelapbFl':
						return 'net_banking';
						break;
				}
			}
			return false;
		}
		return false;
	}

	/**
	 * @param Payment $payment
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\ArgumentTypeException
	 * @throws Main\ObjectException
	 */
	private function getBeGatewayMarketplacepsPayment(Payment $payment): ServiceResult {
		$result = new ServiceResult();

		$url = $this->getUrl($payment, 'getMarketplacepsBillStatus');
		$headers = $this->getHeaders($payment);

		$sendResult = $this->send(self::SEND_METHOD_HTTP_GET, $url, [], $headers);
		if ($sendResult->isSuccess())
		{
			$paymentData = $sendResult->getData();
			$verifyResponseResult = $this->verifyResponse($paymentData);
			if ($verifyResponseResult->isSuccess())
			{
				if ($formData = $this->getFormData($paymentData)) {
					$paymentData['transaction']['form'] = $formData['form'];
				}

				$result->setData($paymentData);
			}
			else
			{
				$result->addErrors($verifyResponseResult->getErrors());
			}
		}
		else
		{
			$result->addErrors($sendResult->getErrors());
		}

		return $result;
	}

	/**
	 * @param string $method
	 * @param string $url
	 * @param array $params
	 * @param array $headers
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\ArgumentTypeException
	 * @throws Main\ObjectException
	 */
	private function send(string $method, string $url, array $params = [], array $headers = []): ServiceResult {
		$result = new ServiceResult();

		$httpClient = new HttpClient();
		foreach ($headers as $name => $value)
		{
			$httpClient->setHeader($name, $value);
		}

    PaySystem\Logger::addDebugInfo(__CLASS__.': request url: '.$url);

		if ($method === self::SEND_METHOD_HTTP_GET)
		{
			$response = $httpClient->get($url);
		} else {
			$postData = null;
			if ($params)
			{
				$postData = static::encode($params);
			}

			PaySystem\Logger::addDebugInfo(__CLASS__.': request data: '.$postData);

      $response = $httpClient->query($method, $url, $postData);

      if ($response) {
        $response = $httpClient->getResult();
      }
		}

		if ($response === false)
		{
			$errors = $httpClient->getError();
			foreach ($errors as $code => $message)
			{
				$result->addError(PaySystem\Error::create($message, $code));
			}

			return $result;
		}

		PaySystem\Logger::addDebugInfo(__CLASS__.': response data: '.$response);

		$response = static::decode($response);

		if ($response)
		{
			$result->setData($response);
		}
		else
		{
			$result->addError(PaySystem\Error::create(Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_RESPONSE_DECODE_ERROR')));
		}

		return $result;
	}

	/**
	 * @param array $response
	 * @return ServiceResult
	 */
	private function verifyResponse(array $response): ServiceResult {
		$result = new ServiceResult();

		if (!empty($response['errors']))
		{
			$result->addError(PaySystem\Error::create($response['message']));
		}

		return $result;
	}

	/**
	 * @return array|string[]
	 */
	public function getCurrencyList(): array {
		return ['BYN'];
	}

	/**
	 * @param Payment $payment
	 * @param Request $request
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\ArgumentTypeException
	 * @throws Main\ObjectException
	 */
	public function processRequest(Payment $payment, Request $request): ServiceResult {
		$result = new ServiceResult();

		$inputStream = static::readFromStream();
		$data = static::decode($inputStream);
		$transaction = $data['transaction'];

    if (!$this->isSignatureCorrect($payment, $inputStream)) {
			$result->addError(
				PaySystem\Error::create(
					Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_ERROR_SIGNATURE')
				)
			);
    } else {
      return $this->processPayment($payment);
    }

    return $result;
  }

   /**
	 * @param Payment $payment
	 * @return ServiceResult
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\ArgumentTypeException
	 * @throws Main\ObjectException
	 */
  private function processPayment($payment) : ServiceResult {
    $result = new ServiceResult;

		$beGatewayMarketplacepsPaymentResult = $this->getBeGatewayMarketplacepsPayment($payment);
		if ($beGatewayMarketplacepsPaymentResult->isSuccess())
		{
			$beGatewayMarketplacepsPaymentData = $beGatewayMarketplacepsPaymentResult->getData();

			if ($beGatewayMarketplacepsPaymentData['transaction']['status'] === self::STATUS_SUCCESSFUL_CODE)
			{
        $transaction = $beGatewayMarketplacepsPaymentData['transaction'];

				$description = Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_TRANSACTION', [
					'#ID#' => $transaction['uid'],
				]);

        $money = new \BeGateway\Module\Marketplaceps\Money;
        $money->setCurrency($transaction['currency']);
        $money->setCents($transaction['amount']);

				$fields = [
					'PS_STATUS_CODE' => $transaction['status'],
					'PS_STATUS_DESCRIPTION' => $description,
					'PS_SUM' => $money->getAmount(),
					'PS_STATUS' => 'N',
					'PS_CURRENCY' => $transaction['currency'],
					'PS_RESPONSE_DATE' => new Main\Type\DateTime()
				];

				if ($this->isSumCorrect($payment, $money->getAmount()))
				{
					$fields['PS_STATUS'] = 'Y';

					PaySystem\Logger::addDebugInfo(
						__CLASS__.': PS_CHANGE_STATUS_PAY='.$this->getBusinessValue($payment, 'PS_CHANGE_STATUS_PAY')
					);

					if ($this->getBusinessValue($payment, 'PS_CHANGE_STATUS_PAY') === 'Y')
					{
						$result->setOperationType(PaySystem\ServiceResult::MONEY_COMING);
					}
				}
				else
				{
					$error = Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_ERROR_SUM');
					$fields['PS_STATUS_DESCRIPTION'] .= '. '.$error;
					$result->addError(PaySystem\Error::create($error));
				}

				$result->setPsData($fields);
			}
			else if (in_array($beGatewayMarketplacepsPaymentData['transaction']['status'], array(self::STATUS_FAILED_CODE, self::STATUS_EXPIRED_CODE)))
			{
				AddMessage2Log(json_encode($beGatewayMarketplacepsPaymentData), 'EXPIRED OR FAILED');
				$transaction = $beGatewayMarketplacepsPaymentData['transaction'];
				
				$fields = [
					'PS_STATUS_CODE' => $transaction['status'],
					'PS_STATUS_DESCRIPTION' => $transaction['message'],
					'PS_STATUS' => 'N',
					'PS_CURRENCY' => $transaction['currency'],
					'PS_RESPONSE_DATE' => new Main\Type\DateTime()
				];
				
				AddMessage2Log(json_encode($fields), 'EXPIRED OR FAILED($fields)');

				$result->setPsData($fields);

				$result->addError(PaySystem\Error::create($transaction['message']));
			}
			else if ($beGatewayMarketplacepsPaymentData['transaction']['status'] === self::STATUS_PENDING_CODE) {
				//do nothing
			}
			else
			{
				$result->addError(
					PaySystem\Error::create(
						Loc::getMessage('SALE_HPS_BEGATEWAY_MARKETPLACEPS_ERROR_STATUS',
							[
								'#STATUS#' => $transaction['status'],
							]
						)
					)
				);
			}
		}
		else
		{
			$result->addErrors($beGatewayMarketplacepsPaymentResult->getErrors());
		}

		return $result;
	}

  /*
	 * @param Payment $payment
	 * @param string Request $inputStream
	 * @return bool
  */
  private function isSignatureCorrect(Payment $payment, $inputStream) {
    $signature = $_SERVER['HTTP_CONTENT_SIGNATURE'];

		PaySystem\Logger::addDebugInfo(
			__CLASS__.': Signature: '.$signature."; Webhook: ".$inputStream
		);

    $signature  = base64_decode($_SERVER['HTTP_CONTENT_SIGNATURE']);

    if (!$signature) {
      return false;
    }

    $public_key = $this->getBusinessValue($payment, 'BEGATEWAY_MARKETPLACEPS_PUBLIC_KEY');
    $public_key = str_replace(array("\r\n", "\n"), '', $public_key);
    $public_key = chunk_split($public_key, 64);
    $public_key = "-----BEGIN PUBLIC KEY-----\n" . $public_key . "-----END PUBLIC KEY-----";
    $key = openssl_pkey_get_public($public_key);

    return openssl_verify($inputStream, $signature, $key, OPENSSL_ALGO_SHA256) == 1;
  }

	/**
	 * @param Payment $payment
	 * @param $sum
	 * @return bool
	 * @throws Main\ArgumentNullException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\ArgumentTypeException
	 * @throws Main\ObjectException
	 */
	private function isSumCorrect(Payment $payment, $sum): bool {
		PaySystem\Logger::addDebugInfo(
			__CLASS__.': bePaidSum='.PriceMaths::roundPrecision($sum)."; paymentSum=".PriceMaths::roundPrecision($payment->getSum())
		);

		return PriceMaths::roundPrecision($sum) === PriceMaths::roundPrecision($payment->getSum());
	}

	/**
	 * @param Request $request
	 * @param int $paySystemId
	 * @return bool
	 */
	public static function isMyResponse(Request $request, $paySystemId): bool {
		$inputStream = static::readFromStream();
		if ($inputStream)
		{
			$data = static::decode($inputStream);
			if ($data === false)
			{
				return false;
			}

			if (isset($data['transaction']['tracking_id']))
			{
				[, $trackingPaySystemId] = explode(self::TRACKING_ID_DELIMITER, $data['transaction']['tracking_id']);
				return (int)$trackingPaySystemId === (int)$paySystemId;
			}
		}

		return false;
	}

	/**
	 * @param Request $request
	 * @return bool|int|mixed
	 */
	public function getPaymentIdFromRequest(Request $request) {
		$inputStream = static::readFromStream();
		if ($inputStream)
		{
			$data = static::decode($inputStream);
			if (isset($data['transaction']['tracking_id']))
			{
				[$trackingPaymentId] = explode(self::TRACKING_ID_DELIMITER, $data['transaction']['tracking_id']);
				return (int)$trackingPaymentId;
			}
		}

		return false;
	}

	/**
	 * @param Payment $payment
	 * @return mixed
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	private function getPaymentDescription(Payment $payment) {
		return $this->setDescriptionPlaceholders('BEGATEWAY_MARKETPLACEPS_PAYMENT_DESCRIPTION', $payment);
	}

	/**
	 * @param Payment $payment
	 * @return mixed
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	private function getReceiptDescription(Payment $payment) {
		return $this->setDescriptionPlaceholders('BEGATEWAY_MARKETPLACEPS_RECEIPT_PAYMENT_DESC', $payment);
	}

	/**
	 * @param Payment $payment
	 * @return mixed
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	private function getAccountDescription(Payment $payment) {
		return $this->setDescriptionPlaceholders('BEGATEWAY_MARKETPLACEPS_PAYMENT_ACCOUNT', $payment);
	}

	/**
	 * @param Payment $payment
	 * @return mixed
	 * @throws Main\ArgumentException
	 * @throws Main\ArgumentOutOfRangeException
	 * @throws Main\NotImplementedException
	 * @throws Main\ObjectPropertyException
	 * @throws Main\SystemException
	 */
	private function setDescriptionPlaceholders(string $description, Payment $payment) {
		/** @var PaymentCollection $collection */
		$collection = $payment->getCollection();
		$order = $collection->getOrder();
		$userEmail = $order->getPropertyCollection()->getUserEmail();

		$processed_description =  str_replace(
			[
				'#PAYMENT_NUMBER#',
				'#ORDER_NUMBER#',
				'#PAYMENT_ID#',
				'#ORDER_ID#',
				'#USER_EMAIL#'
			],
			[
				$payment->getField('ACCOUNT_NUMBER'),
				$order->getField('ACCOUNT_NUMBER'),
				$payment->getId(),
				$order->getId(),
				($userEmail) ? $userEmail->getValue() : ''
			],
			$this->getBusinessValue($payment, $description)
		);

		return $processed_description;
	}

	/**
	 * @param Payment $payment
	 * @return array
	 */
	private function getHeaders(Payment $payment): array
	{
		$headers = [
			'Content-Type' => 'application/json',
			'Accept' => 'application/json',
			'Authorization' => 'Basic '.$this->getBasicAuthString($payment),
			'RequestID' => $this->getIdempotenceKey(),
		];

		return $headers;
	}

	/**
	 * @param Payment $payment
	 * @return string
	 */
	private function getBasicAuthString(Payment $payment): string {
		return base64_encode(
			$this->getBusinessValue($payment, 'BEGATEWAY_MARKETPLACEPS_ID')
			. ':'
			. $this->getBusinessValue($payment, 'BEGATEWAY_MARKETPLACEPS_SECRET_KEY')
		);
	}

	/**
	 * @return string
	 */
	private function getIdempotenceKey(): string {
		return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}

	/**
	 * @param Payment $payment
	 * @param string $action
	 * @return string
	 */
	protected function getUrl(Payment $payment = null, $action): string {
		$url = parent::getUrl($payment, $action);
		if ($payment !== null &&
        in_array(
          $action, ['getMarketplacepsBillStatus', 'deleteMarketplacepsBill']
        ))
		{
			$url = str_replace('#uid#', $payment->getField('PS_INVOICE_ID'), $url);
		}

		return $url;
	}

	/**
	 * @return array
	 */
	protected function getUrlList(): array {
		return [
			'sendMarketplacepsBill' => self::API_URL.'/beyag/transactions/payment',
      'getMarketplacepsBillStatus' => self::API_URL.'/beyag/transactions/#uid#',
      'deleteMarketplacepsBill' => self::API_URL.'/beyag/transactions/#uid#'
		];
	}

	/**
	 * @param Payment $payment
	 * @return bool
	 */
	protected function isTestMode(Payment $payment = null): bool {
		return ($this->getBusinessValue($payment, 'PS_IS_TEST') === 'Y');
	}

	/**
	 * @return bool|string
	 */
	private static function readFromStream() {
		return file_get_contents('php://input');
	}

	/**
	 * @param array $data
	 * @return mixed
	 * @throws Main\ArgumentException
	 */
	private static function encode(array $data) {
		return Main\Web\Json::encode($data, JSON_UNESCAPED_UNICODE);
	}

	/**
	 * @param string $data
	 * @return mixed
	 */
	private static function decode($data) {
		try
		{
			return Main\Web\Json::decode($data);
		}
		catch (Main\ArgumentException $exception)
		{
			return false;
		}
	}
}
