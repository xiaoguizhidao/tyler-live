<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category	Shift4
 * @package		Shift4_Shift4Payment
 * @copyright	Copyright (c) 2011 Shift4 Corporation (http://www.shift4.com)
 * @license		http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Shift4_Shift4Payment_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{

	const REQUEST_TYPE_AUTHORIZE			= 'authorize';
	const FRC_AUTHORIZE						= '1B';
	const FRC_AUTHORIZE_SALE_FLAG			= 'S';

	const REQUEST_TYPE_CAPTURE				= 'capture';
	const FRC_CAPTURE						= '1D';
	const FRC_CAPTURE_SALE_FLAG				= 'S';

	const REQUEST_TYPE_CREDIT				= 'credit';
	const FRC_CREDIT						= '1D';
	const FRC_CREDIT_SALE_FLAG				= 'C';

	const REQUEST_TYPE_VOID					= 'void';
	const FRC_VOID							= '08';
	const FRC_VOID_SALE_FLAG				= '';

	const REQUEST_TYPE_GET_INVOICE			= 'get invoice';
	const FRC_GET_INVOICE					= '07';
	const FRC_GET_INVOICE_SALE_FLAG			= '';

	const FRC_RESPONSE_ERROR_INDICATOR		= 'Y';
	const FRC_RESPONSE_APPROVED				= 'A';
	const FRC_RESPONSE_CEILING				= 'C';
	const FRC_RESPONSE_ERROR_CONDITION		= 'E';

	const PARTIAL_AUTH_CARD_LIMIT			= 5;
	
	const RESPONSE_APPROVED					= 'approved';
	const RESPONSE_PARTIAL_AUTH				= 'partial_auth';
	const RESPONSE_DECLINED					= 'declined';
	const RESPONSE_CARD_LIMIT				= 'card_limit';
	const RESPONSE_ERROR					= 'error';
	const RESPONSE_TIMEOUT					= 'timeout';
	const RESPONSE_INVOICE_NOT_FOUND		= 'invoice_not_found';
	const RESPONSE_ERROR_CONDITION			= 'timeout';

	protected $_code						= 'shift4payment';
	protected $_version						= 'S4Magento1.0.2';
	protected $_formBlockType				= 'shift4payment/form';
	protected $_infoBlockType				= 'shift4payment/info';

	protected $_isGateway					= true;
	protected $_canOrder					= true;
	protected $_canAuthorize				= true;
	protected $_canCapture					= true;
	protected $_canCapturePartial			= true;
	protected $_canRefund					= true;
	protected $_canRefundInvoicePartial		= false;
	protected $_canVoid						= true;
	protected $_canUseInternal				= true;
	protected $_canUseCheckout				= true;
	protected $_canUseForMultishipping		= true;
	protected $_canFetchTransactionInfo		= true;
	protected $_canReviewPayment			= true;
	protected $_canCreateBillingAgreement	= false;
	protected $_canManageRecurringProfiles	= false;
	protected $_canCancelInvoice			= true;
	protected $_canSaveCc					= false;

	protected $_debugReplacePrivateDataKeys	= array('username', 'password', 'apiserialnumber', 'merchantid', 'uniqueid', 'expirationmonth', 'expirationyear');
	protected $_partialAuthKey				= 'partial_auth';
	protected $_isGatewayActionsLockedKey	= 'is_gateway_actions_locked';

	/************************************************** PUBLIC IMPLEMENTATIONS **************************************************/

	/**
	 * Checkout order place redirect URL getter
	 *
	 * @return string
	 */
	public function getOrderPlaceRedirectUrl()
	{
		return Mage::getUrl('shift4payment/processing/payment', array('_secure'=>true));
	}

	public function authorize(Varien_Object $payment, $amount)
	{
		$amount = $this->_formatAmount($amount, true);
		if ($amount <= 0) {
			Mage::throwException(Mage::helper('shift4payment')->__('Amount for authorization must be greater than zero.'));
		}
		$this->_initCardsStorage($payment);
		if ($this->isPartialAuthorization($payment)) {
			$this->_partialPlace($payment, $amount, self::REQUEST_TYPE_AUTHORIZE);
		} else {
			$this->_place($payment, $amount, self::REQUEST_TYPE_AUTHORIZE);
		}
		if ($this->getResponseCode() == self::RESPONSE_APPROVED) {
			$order = $payment->getOrder();
			$order->setState('New', 'Pending')->save();
		}
		return $this;
	}

	 /**
	 * Fetch transaction details info. Might not be needed.
	 *
	 * @param Mage_Payment_Model_Info $payment
	 * @param string $transactionId
	 * @return array
	 */
	public function fetchTransactionInfo(Mage_Payment_Model_Info $payment, $transactionId)
	{
		return parent::fetchTransactionInfo($payment, $transactionId);
	}

	 /**
	 * Get invoice
	 *
	 * @param Varien_Object $payment
	 * @return array
	 */
	public function _getInvoice(Varien_Object $payment) {
		$payment->setShift4RequestType(self::REQUEST_TYPE_GET_INVOICE);
		$request = $this->_buildRequest($payment);
		$response = $this->_postRequest($request);
		$this->_logGateway($request, $response);

		return $response;
	}

	public function canCapture()
	{
		if ($this->_isGatewayActionsLocked($this->getInfoInstance())) {
			return false;
		}
		if ($this->_isPreauthorizeCapture($this->getInfoInstance())) {
			return true;
		}
		if ($this->getCardsStorage()->getCardsCount() == 0) {
			return false;
		}
		foreach($this->getCardsStorage()->getCards() as $card) {
			$lastTransaction = $this->getInfoInstance()->getTransaction($card->getLastTransId());
			if ($lastTransaction) {
				// TODO: Verify that this works correctly, maybe we need to check lastTransactionType as well?
				return false;
			}
		}
		return true;
	}

	public function capture(Varien_Object $payment, $amount)
	{
		$amount = $this->_formatAmount($amount, true);
		if ($amount <= 0) {
			Mage::throwException(Mage::helper('shift4payment')->__('Amount for capture must be greater than zero.'));
		}
		$this->_initCardsStorage($payment);
		if ($this->_isPreauthorizeCapture($payment)) {
			$this->_preauthorizeCapture($payment, $amount);
		} else if ($this->isPartialAuthorization($payment)) {
			$this->_partialPlace($payment, $amount, self::REQUEST_TYPE_CAPTURE);
		} else {
			$this->_place($payment, $amount, self::REQUEST_TYPE_CAPTURE);
		}
		if ($this->getResponseCode() == self::RESPONSE_APPROVED) {
			$this->_payInvoice($payment);
		}

		return $this;
	}

	public function cancel(Varien_Object $payment)
	{
		return $this->void($payment);
	}

	public function canVoid(Varien_Object $payment)
	{
		if ($this->_isGatewayActionsLocked($this->getInfoInstance())) {
			return false;
		}
		return true;
	}

	public function void(Varien_Object $payment)
	{
		$cardsStorage = $this->getCardsStorage($payment);
		if ($payment->getVoidLastCardOnly()) {
			// Void only the last card in storage and then remove it
			// This is used for voiding errors, timeouts, declines, insufficient partial auths, etc.
			$cards = $cardsStorage->getCards();
			$lastCard = end($cards);
			$payment->setShift4RequestType(self::REQUEST_TYPE_VOID);
			$request = $this->_buildRequest($payment);
			if (!empty($lastCard)) {
				$request->setData('uniqueid', $lastCard->getCcNumber());
			}
			$response = $this->_postRequest($request);
			$this->_logGateway($request, $response);
			$cardsStorage->removeCard($lastCard[Shift4_Shift4Payment_Model_Cards::CARD_ID_KEY]);
			$payment->save();
		} else {
			$messages = array();
			$isSuccessful = false;
			$isFailed = false;
			foreach($cardsStorage->getCards() as $card) {
				try {
					$newTransaction = $this->_processCardTransaction(self::REQUEST_TYPE_VOID, $payment, false, $card);
					$messages[] = $newTransaction->getMessage();
					$isSuccessful = true;
				} catch (Exception $e) {
					$messages[] = $e->getMessage();
					$isFailed = true;
					continue;
				}
				$cardsStorage->updateCard($card);
			}
			if ($isFailed) {
				$this->_processMultitransactionFailure($payment, $messages, $isSuccessful);
			}
			$payment->setBaseAmountAuthorized(null);
			$payment->setAmountAuthorized(null);
			$payment->unsAdditionalInformation($this->_partialAuthKey);
			$this->getCardsStorage($payment)->flushCards();
		}
		return $this;
	}

	public function canRefund()
	{
		if ($this->_isGatewayActionsLocked($this->getInfoInstance()) || $this->getCardsStorage()->getCardsCount() <= 0) {
			return false;
		}
		foreach($this->getCardsStorage()->getCards() as $card) {
			$lastTransaction = $this->getInfoInstance()->getTransaction($card->getLastTransId());
			if ($lastTransaction
				&& $lastTransaction->getTxnType() == Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE
				&& !$lastTransaction->getIsClosed()
			) {
				return true;
			}
		}
		return false;
	}

	public function refund(Varien_Object $payment, $requestedAmount)
	{
		$requestedAmount = $this->_formatAmount($requestedAmount, true);
		$cardsStorage = $this->getCardsStorage($payment);
		if ($this->_formatAmount($cardsStorage->getCapturedAmount() - $cardsStorage->getRefundedAmount()) < $requestedAmount) {
			Mage::throwException(Mage::helper('shift4payment')->__('Invalid amount for refund.'));
		}
		$messages = array();
		$isSuccessful = false;
		$isFailed = false;
		foreach($cardsStorage->getCards() as $card) {
			if ($requestedAmount > 0) {
				$cardAmountForRefund = $this->_formatAmount($card->getCapturedAmount() - $card->getRefundedAmount());
				if ($cardAmountForRefund <= 0) {
					continue;
				}
				if ($cardAmountForRefund > $requestedAmount) {
					$cardAmountForRefund = $requestedAmount;
				}
				try {
					$newTransaction = $this->_processCardTransaction(self::REQUEST_TYPE_CREDIT, $payment, $cardAmountForRefund, $card);
					$messages[] = $newTransaction->getMessage();
					$isSuccessful = true;
				} catch (Exception $e) {
					$messages[] = $e->getMessage();
					$isFailed = true;
					continue;
				}
				$card->setRefundedAmount($this->_formatAmount($card->getRefundedAmount() + $cardAmountForRefund));
				$cardsStorage->updateCard($card);
				$requestedAmount = $this->_formatAmount($requestedAmount - $cardAmountForRefund);
			} else {
				return $this;
			}
		}
		if ($isFailed) {
			$this->_processMultitransactionFailure($payment, $messages, $isSuccessful);
		}
		return $this;
	}

	protected function _preauthorizeCapture($payment, $requestedAmount)
	{
		$requestedAmount = $this->_formatAmount($requestedAmount, true);
		$cardsStorage = $this->getCardsStorage($payment);
		if ($this->_formatAmount($cardsStorage->getProcessedAmount() - $cardsStorage->getCapturedAmount()) < $requestedAmount) {
			Mage::throwException(Mage::helper('shift4payment')->__('Invalid amount for capture.'));
		}
		$messages = array();
		$isSuccessful = false;
		$isFailed = false;
		foreach($cardsStorage->getCards() as $card) {
			if ($requestedAmount > 0) {
				$cardAmountForCapture = $card->getProcessedAmount();
				if ($cardAmountForCapture > $requestedAmount) {
					$cardAmountForCapture = $requestedAmount;
				}
				try {
					$newTransaction = $this->_processCardTransaction(self::REQUEST_TYPE_CAPTURE, $payment, $cardAmountForCapture, $card);
					$messages[] = $newTransaction->getMessage();
					$isSuccessful = true;
				} catch (Exception $e) {
					$messages[] = $e->getMessage();
					$isFailed = true;
					continue;
				}
				$card->setCapturedAmount($cardAmountForCapture);
				$cardsStorage->updateCard($card);
				$requestedAmount = $this->_formatAmount($requestedAmount - $cardAmountForCapture, true);
			}
		}
		if ($isFailed) {
			$this->_processMultitransactionFailure($payment, $messages, $isSuccessful);
		}
		return $this;
	}

	protected function _processCardTransaction($requestType, $payment, $amount = false, $card)
	{
		if ($amount) {
			$amount = $this->_formatAmount($amount, true);
		}
		$lastTransactionId = $card->getLastTransId();
		$lastTransaction = $payment->getTransaction($lastTransactionId);
		$payment->setShift4RequestType($requestType);

		if($card->getCcNumber()) {
			$payment->setCcNumberEnc($card->getCcNumber());
		}
		if ($requestType == self::REQUEST_TYPE_CAPTURE) {
			$transactionType = Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE;
			$payment->setAmount($amount);
			$transactionDetails = array(
				'is_transaction_closed' => 0,
				'parent_transaction_id' => $lastTransactionId
			);
		} else if ($requestType == self::REQUEST_TYPE_VOID) {
			$amount = false;
			$transactionType = Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID;
			$transactionDetails = array(
				'is_transaction_closed' => 1,
				'should_close_parent_transaction' => 1,
				'parent_transaction_id' => $lastTransactionId
			);
		} else if ($requestType == self::REQUEST_TYPE_CREDIT) {
			$transactionType = Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND;
			$payment->setAmount($amount);
			$transactionDetails = array(
				'is_transaction_closed' => 1,
				'should_close_parent_transaction' => 1,
				'parent_transaction_id' => $lastTransactionId
			);
		}
		$request = $this->_buildRequest($payment);
		$response = $this->_postRequest($request);
		$this->_logGateway($request, $response);

		switch ($response->getResponseCode()) {
			case self::RESPONSE_APPROVED:
				if ($requestType == self::REQUEST_TYPE_VOID) {
					// Void does not create a new transaction in Shift4, so we reuse the transaction id and append "-void" to it
					$transactionId = $lastTransactionId . '-' . Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID;
				} else {
					$transactionId = $response->getData('tranid');
				}
				$card->setLastTransId($transactionId);
				return $this->_addTransaction(
					$payment,
					$transactionId,
					$transactionType,
					$transactionDetails,
					Mage::helper('shift4payment')->getTransactionMessage($payment, $requestType, $transactionId, $card, $amount)
				);
				$exceptionMessage = $this->_formatGatewayError($response->getResponseReasonText());
				break;
			case self::RESPONSE_INVOICE_NOT_FOUND:
				// If void comes back not found, we need to do a credit
				// Amount to refund is the processed amount minus any refunds already done
				$cardAmountForRefund = $this->_formatAmount($card->getProcessedAmount() - $card->getRefundedAmount());
				if ($cardAmountForRefund <= 0) {
					// Sanity check
					$exceptionMessage = Mage::helper('shift4payment')->__('Invalid amount for refund.');
				}
				// Call _processCardTransaction recursively as a credit with the amount to credit
				$creditResponse = $this->_processCardTransaction(self::REQUEST_TYPE_CREDIT, $payment, $cardAmountForRefund, $card);
				// Increment the amount refunded so far, and save it in the database
				$card->setRefundedAmount($this->_formatAmount($card->getRefundedAmount() + $cardAmountForRefund));
				$this->getCardsStorage($payment)->updateCard($card);
				$payment->setCcNumberEnc(null);
				return $creditResponse;
				break;
			case self::RESPONSE_DECLINED:
			case self::RESPONSE_ERROR:
				$exceptionMessage = $this->_formatGatewayError($response->getResponseReasonText());
				break;
			default:
				$exceptionMessage = Mage::helper('shift4payment')->__('Payment ' . $transactionType . ' error.');
				break;
		}
		$exceptionMessage = Mage::helper('shift4payment')->getTransactionMessage(
			$payment, $requestType, $lastTransactionId, $card, $amount, $exceptionMessage
		);
		Mage::throwException($exceptionMessage);
	}

	protected function _place($payment, $amount, $requestType)
	{
		$payment->setShift4RequestType($requestType);
		$payment->setAmount($amount);
		$request = $this->_buildRequest($payment);
		$response = $this->_postRequest($request);
		$this->_logGateway($request, $response);

		switch ($requestType) {
			case self::REQUEST_TYPE_AUTHORIZE:
				$newTransactionType = Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH;
				$defaultExceptionMessage = Mage::helper('shift4payment')->__('Payment authorization error.');
				break;
			case self::REQUEST_TYPE_CAPTURE:
				$newTransactionType = Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE;
				$defaultExceptionMessage = Mage::helper('shift4payment')->__('Payment capturing error.');
				break;
		}

		switch ($response->getResponseCode()) {
			case self::RESPONSE_APPROVED:
				$card = $this->_registerCard($response, $payment, $amount);
				$this->_addTransaction(
					$payment,
					$card->getLastTransId(),
					$newTransactionType,
					array('is_transaction_closed' => 0),
					Mage::helper('shift4payment')->getTransactionMessage(
						$payment, $requestType, $card->getLastTransId(), $card, $response->getData('primaryamount')
					)
				);
				if ($requestType == self::REQUEST_TYPE_CAPTURE) {
					$card->setCapturedAmount($card->getProcessedAmount());
					$this->getCardsStorage($payment)->updateCard($card);
				}
				$this->_processAmount($payment, $amount, $requestType);
				$this->setResponseCode(self::RESPONSE_APPROVED);
				return $this;
			case self::RESPONSE_PARTIAL_AUTH:
				$this->_processPartialAuthorizationResponse($response, $payment, $amount, $requestType);
				return $this;
			case self::RESPONSE_DECLINED:
				$card = $this->_registerCard($response, $payment, $amount);
				$this->setDeclinedCcType($response->getData('cardtype'));
				$this->setDeclinedCcLast4(substr($response->getData('uniqueid'), 0, 4));
				$this->setResponseCode(self::RESPONSE_DECLINED);
				return $this;
			case self::RESPONSE_ERROR:
				$this->setResponseCode(self::RESPONSE_ERROR);
				$this->setResponseMessage($response->getResponseMessage());
				return $this;
			default:
				Mage::throwException($defaultExceptionMessage);
		}
		return $this;
	}

	protected function _partialPlace($payment, $amount, $requestType)
	{
		$payment->setShift4RequestType($requestType);
		$amount = $amount - $this->getCardsStorage($payment)->getProcessedAmount();
		if ($amount <= 0) {
			Mage::throwException(Mage::helper('shift4payment')->__('Amount for partial authorization must be greater than zero.'));
		}
		$payment->setAmount($amount);
		$request = $this->_buildRequest($payment);
		$response = $this->_postRequest($request);
		$this->_logGateway($request, $response);

		$this->_processPartialAuthorizationResponse($response, $payment, $amount, $requestType);
		return $this;
	}

	protected function _processMultitransactionFailure($payment, $messages, $isSuccessfulTransactions)
	{
		if ($isSuccessfulTransactions) {
			$messages[] = Mage::helper('shift4payment')->__('Gateway actions are locked because the gateway cannot complete one or more of the transactions. Please log in to your Dollars On The Net account to manually resolve the issues.');
			$currentOrderId = $payment->getOrder()->getId();
			$copyOrder = Mage::getModel('sales/order')->load($currentOrderId);
			$copyOrder->getPayment()->setAdditionalInformation($this->_isGatewayActionsLockedKey, 1);
			foreach($messages as $message) {
				$copyOrder->addStatusHistoryComment($message);
			}
			$copyOrder->save();
		}
		Mage::throwException(Mage::helper('shift4payment')->convertMessagesToMessage($messages));
	}

	public function isPartialAuthorization($payment = null)
	{
		if (is_null($payment)) {
			$payment = $this->getInfoInstance();
		}
		return $payment->getAdditionalInformation($this->_partialAuthKey);
	}

	public function processInvoice($invoice, $payment)
	{
		$invoice->setTransactionId(1);
		return $this;
	}

	public function processCreditmemo($creditmemo, $payment)
	{
		$creditmemo->setTransactionId(1);
		return $this;
	}

	/************************************************** CARDS STORAGE **************************************************/

	protected function _initCardsStorage($payment)
	{
		$this->_cardsStorage = Mage::getModel('shift4payment/cards')->setPayment($payment);
	}

	public function getCardsStorage($payment = null)
	{
		if (is_null($payment)) {
			$payment = $this->getInfoInstance();
		}
		if (is_null($this->_cardsStorage)) {
			$this->_initCardsStorage($payment);
		}
		return $this->_cardsStorage;
	}

	protected function _registerCard(Varien_Object $response, Mage_Sales_Model_Order_Payment $payment, $amount)
	{
		$cardsStorage = $this->getCardsStorage($payment);
		$card = $cardsStorage->registerCard();
		$tempReceipt = $response->getData('receipttext');
		$formattedReceiptText = str_replace('%0D%0A', ' ', $tempReceipt);

		$card
			->setLastTransId($response->getData('tranid'))
			->setRequestedAmount($this->_formatAmount($amount))
			->setProcessedAmount($this->_formatAmount($response->getData('primaryamount')))
			->setCcNumber($response->getData('uniqueid'))
			->setCcLast4(substr($response->getData('uniqueid'), 0, 4))
			->setCcType($response->getData('cardtype'))
			->setReceiptText($formattedReceiptText)
			->setDateTime($response->getData('date') . ' ' . $response->getData('time'))
			;
		$cardsStorage->updateCard($card);
		$payment
			->setCcNumber(null)
			->setCcNumberEnc(null)
			->setCcLast4(null)
			->setCcType(null)
			->setCcExpMonth(null)
			->setCcExpYear(null)
			->setCcSsStartMonth(null)
			->setCcSsStartYear(null)
			;
		$payment->save();
		return $card;
	}

	/************************************************** BUILD/POST/GET REQUEST **************************************************/

	/**
	 * Get transaction request skeleton
	 *
	 * @return	Shift4_Shift4Payment_Model_ApiRequest
	 */
	protected function _getRequest()
	{
		$session = $this->_getSession();
		$apiSettings = $session->getApiSettings();
		if (!$apiSettings) {
			$apiSettings = Mage::helper('shift4payment')->getApiSettings();
			$session->setApiSettings($apiSettings);
		}
		$request = Mage::getModel('shift4payment/ApiRequest')
			->setData('url', $apiSettings['dotnUrl'])
			->setData('username', $apiSettings['userName'])
			->setData('password', $apiSettings['password'])
			->setData('apiserialnumber', $apiSettings['accountNumber'])
			->setData('merchantid', $apiSettings['merchantId'])
			->setData('cardpresent', 'N')
			->setData('apioptions', 'ALLDATA')
			->setData('apiformat', 0)
			->setData('apisignature', '$')
			->setData('cvv2indicator', 0)
			->setData('contenttype', 'xml')
			->setData('verbose', 'YES')
			->setData('vendor', $this->_version);
		return $request;
	}

	/**
	 * Build the transaction request object
	 *
	 * @param	Varien_Object $payment
	 * @return	Shift4_Shift4Payment_Model_ApiRequest
	 */
	protected function _buildRequest(Varien_Object $payment)
	{
		$order = $payment->getOrder();
		$this->setStore($order->getStoreId());

		$frcArray = $this->_requestTypeToFrc($payment->getShift4RequestType());
		$request = $this->_getRequest()
			->setData('functionrequestcode', $frcArray['frc'])
			->setData('saleflag', $frcArray['saleFlag']);

		if ($order && $order->getIncrementId()) {
			$request->setData('invoice',$order->getIncrementId());
		}
		if ($payment->getAmount()) {
			$request->setData('primaryamount',$payment->getAmount(),2);
		}
		$request->setData('apioptions',$request->getData('apioptions') . ',ALLOWPARTIALAUTH');

		if (!empty($order)) {
			$billingAddress = $order->getBillingAddress();

			$items = $order->getAllItems();
			$products=array();
			$qty=array();
			foreach ($items as $itemId => $item)
			{
				$qty = $item->getQtyToInvoice();
				$products[$item->getName()] = $qty;
			}
			$productNotes = '';
			$productDescriptor = array();
			foreach ($products as $name => $quantity) {
				$msg = $quantity . ' x ' . $name;
				$productNotes .= $msg . '<br />';
				$productDescriptor[] = $msg;
			}
			for ($i = 0; $i < 4; $i++) {$productDescriptor[] = '';}

			$shippingPostCode = $order->getShippingAddress()->getPostcode();

			if (!empty($billingAddress)) {
				$request
					->setData('customername',$billingAddress->getFirstname() . ' ' . $billingAddress->getLastname())
					->setData('customerreference',$billingAddress->getCustomerId())
					->setData('streetaddress',$billingAddress->getStreet(1))
					->setData('zipcode',$billingAddress->getPostcode())
					->setData('destinationzipcode', $shippingPostCode);
			}

			$request
				->setData('notes', $productNotes)
				->setData('productdescriptor1', $productDescriptor[0])
				->setData('productdescriptor2', $productDescriptor[1])
				->setData('productdescriptor3', $productDescriptor[2])
				->setData('productdescriptor4', $productDescriptor[3]);

			if ($order->getBaseTaxAmount() > 0) {
				$request->setData('taxindicator','Y');
				$request->setData('taxamount',$order->getBaseTaxAmount());
			}
		}
		if($payment->getCcNumberEnc()) {
			$request
				->setData('uniqueid',$payment->getCcNumberEnc())
				->setData('cardtype',$payment->getCcType())
				->setData('expirationmonth',$payment->getCcExpMonth())
				->setData('expirationyear',$payment->getCcExpYear());
		}

		return $request;
	}

	/**
	 * Post the transaction request
	 *
	 * @param	Shift4_Shift4Payment_Model_ApiRequest $request
	 * @return	Shift4_Shift4Payment_Model_ApiResponse
	 */
	protected function _postRequest(Shift4_Shift4Payment_Model_ApiRequest $request)
	{
		$client = new Varien_Http_Client();
		$client->setUri($request->getData('url'));
		$client->setConfig(array(
			'maxredirects' => 0,
			'timeout' => 30,
		));
		$params = array_merge(array('STX'=>'YES'), $request->getData(), array('ETX'=>'YES'));

		$client->setParameterPost($params);
		$client->setMethod(Zend_Http_Client::POST);

		try {
			$response = $client->request();
		} catch (Exception $e) {
			$response = Mage::getModel('shift4payment/ApiResponse');
			$response->setResponseCode(self::RESPONSE_ERROR);
			$response->setResponseMessage('There was a problem communicating with the payment gateway. Please try again.');
		}

		$request->setParams($params);
		return $this->_processResponse($request, $response->getBody());
	}

	/**
	 * Process the payment gateway response
	 *
	 * @param	Shift4_Shift4Payment_Model_ApiRequest $request
	 * @param	Shift4_Shift4Payment_Model_ApiResponse $response
	 * @return	Shift4_Shift4Payment_Model_ApiResponse
	 */
	protected function _processResponse($request, $response)
	{
		$error = false;

		// Read in the XML response
		$responseXml = simplexml_load_string($response);
		$response = Mage::getModel('shift4payment/ApiResponse');

		if ($responseXml) {
			foreach($responseXml->children() as $elementName => $child) {
				$response->setData($elementName,(string) $child);
			}
		} else {
			$response->setResponseCode(self::RESPONSE_ERROR);
			$error = true;
		}

		if (!$error) {
			// Process different response codes and set them in $response->setResponseCode()
			$frc				= strtoupper($request->getData('functionrequestcode'));
			$saleFlag			= strtoupper($request->getData('saleflag'));
			$requestType		= $this->_frcToRequestType($frc, $saleFlag);
			$errorIndicator		= strtoupper($response->getData('errorindicator'));
			$errorCode			= strtoupper($response->getData('errorcode'));
			$validAvs			= strtoupper($response->getData('validavs'));
			$responseCode		= strtoupper($response->getData('responsecode'));
			$authorization 		= strtoupper($response->getData('authorization'));
			$primaryErrorCode	= strtoupper($response->getData('primaryerrorcode'));
			$longError			= strtoupper($response->getData('longerror'));
			$primaryAmount		= $this->_formatAmount($response->getData('primaryamount'), true);
			$cvv2Valid			= strtoupper($response->getData('cvv2valid'));
			
			if ($requestType == self::REQUEST_TYPE_AUTHORIZE || $requestType == self::REQUEST_TYPE_CAPTURE) {
				if ($errorIndicator == 'N') {
					if (strtoupper($response->getData('responsecode')) == self::FRC_RESPONSE_APPROVED || strtoupper($response->getData('responsecode')) == self::FRC_RESPONSE_CEILING) {
						if ($cvv2Valid == 'N') {
							$response->setResponseCode(self::RESPONSE_DECLINED);
						} else if ($validAvs == 'N' &&	Mage::helper('shift4payment')->getEnforceAddress() == 1) {
							$response->setResponseCode(self::RESPONSE_DECLINED);
						} else {
							$response->setResponseCode(self::RESPONSE_APPROVED);
						if ($this->_formatAmount($request->getData('primaryamount')) > $primaryAmount) {
								$response->setResponseCode(self::RESPONSE_PARTIAL_AUTH);
							}
						}
					} else if ($responseCode == self::FRC_RESPONSE_ERROR_CONDITION) {				
						if (!$this->_checkErrorCode($authorization)) {
							$response->setResponseCode(self::RESPONSE_DECLINED);
						} else {
							$response->setResponseMessage('There was a problem communicating with the payment gateway. Please try again.');
							$response->setResponseCode(self::RESPONSE_ERROR_CONDITION);
						}	
					} else {
						$response->setResponseCode(self::RESPONSE_DECLINED);
					}
				} elseif ($errorIndicator == 'Y') {
					if (!$this->_checkErrorCode($errorCode)) {
						$response->setResponseCode(self::RESPONSE_DECLINED);
					} else {
						$response->setResponseCode(self::RESPONSE_ERROR);
					}
				}
			} else if ($requestType == self::REQUEST_TYPE_CREDIT) {
				if ($errorIndicator == self::FRC_RESPONSE_ERROR_INDICATOR) {
					$response->setResponseCode(self::RESPONSE_ERROR);
				} else {
					$response->setResponseCode(self::RESPONSE_APPROVED);
				}
			} else if ($requestType == self::REQUEST_TYPE_VOID) {
				if ($errorIndicator != self::FRC_RESPONSE_ERROR_INDICATOR) {
					$response->setResponseCode(self::RESPONSE_APPROVED);
				} else if ($primaryErrorCode == 9815 || stripos($longError, 'invoice not found') !== false) {
					$response->setResponseCode(self::RESPONSE_INVOICE_NOT_FOUND);
				} else {
					$response->setResponseCode(self::RESPONSE_ERROR);
				}
			} else if ($requestType == self::REQUEST_TYPE_GET_INVOICE) {
				if (empty($responseCode)) {
					$this->_logTimeout($request);
					$response->setResponseMessage('There was a problem communicating with the payment gateway. Please try again.');
					$response->setResponseCode(self::RESPONSE_TIMEOUT);
				} else if ($responseCode == self::FRC_RESPONSE_APPROVED || $responseCode == self::FRC_RESPONSE_CEILING) {
					$response->setResponseCode(self::RESPONSE_APPROVED);
				}  else if ($responseCode == self::FRC_RESPONSE_ERROR_CONDITION) {
						if (!$this->_checkErrorCode($authorization)) {
							$response->setResponseCode(self::RESPONSE_DECLINED);
						} else {
							$response->setResponseMessage('There was a problem communicating with the payment gateway. Please try again.');
							$response->setResponseCode(self::RESPONSE_ERROR_CONDITION);
						}
				} else {
					$response->setResponseCode(self::RESPONSE_DECLINED);
				}
			}

			// Other conversions and formatting
			$response->setData('cardtype',$this->_formatCardType($response->getData('cardtype')));
			$response->setData('primaryamount', $primaryAmount);
			$response->setData('originalexpirationmonth', $request->getData('expirationmonth'));
			$response->setData('originalexpirationyear', substr($request->getData('expirationyear'), -2, 2)); // Get last 2 digits of year
		}

		return $response;
	}

	protected function _processPartialAuthorizationResponse($response, $payment, $amount, $requestType)
	{
		$amount = $this->_formatAmount($amount, true);
		$responseAmount = $this->_formatAmount($response->getData('primaryamount'), true);
		$exceptionMessage = null;

		switch ($requestType) {
			case self::REQUEST_TYPE_AUTHORIZE:
				$newTransactionType = Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH;
				break;
			case self::REQUEST_TYPE_CAPTURE:
				$newTransactionType = Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE;
				break;
		}
		try {
			switch ($response->getResponseCode()) {
				case self::RESPONSE_APPROVED:
				case self::RESPONSE_PARTIAL_AUTH:
					$card = $this->_registerCard($response, $payment, $amount);
					$this->_addTransaction(
						$payment,
						$card->getLastTransId(),
						$newTransactionType,
						array('is_transaction_closed' => 0),
						Mage::helper('shift4payment')->getTransactionMessage(
							$payment, $requestType, $card->getLastTransId(), $card, $responseAmount
						)
					);
					if ($requestType == self::REQUEST_TYPE_CAPTURE) {
						$card->setCapturedAmount($card->getProcessedAmount());
						$this->getCardsStorage($payment)->updateCard($card);
					}

					if ($response->getResponseCode() == self::RESPONSE_PARTIAL_AUTH &&
						$this->getCardsStorage($payment)->getCardsCount() >= self::PARTIAL_AUTH_CARD_LIMIT) {
						$this->setResponseCode(self::RESPONSE_CARD_LIMIT);
						return true;
					}

					$this->setPartialAuthCcType($response->getData('cardtype'));
					$this->setPartialAuthCcLast4(substr($response->getData('uniqueid'), 0, 4));
					$this->setPartialAuthCcExpMonth($response->getData('originalexpirationmonth'));
					$this->setPartialAuthCcExpYear($response->getData('originalexpirationyear'));
					$this->setPartialAuthProcessed($responseAmount);
					$amountRemaining = $this->_formatAmount($payment->getAmount() - $responseAmount, true);
					if ($amountRemaining > 0) {
						$payment->setAdditionalInformation($this->_partialAuthKey, 'true');
						$this->setPartialAuthRemaining($amountRemaining);
						$this->setResponseCode(self::RESPONSE_PARTIAL_AUTH);
					} else {
						$payment->unsAdditionalInformation($this->_partialAuthKey);
						$this->setResponseCode(self::RESPONSE_APPROVED);
					}

					$this->_processAmount($payment, $responseAmount, $requestType);

					return true;
				case self::RESPONSE_DECLINED:
					$card = $this->_registerCard($response, $payment, $amount);
					$this->setDeclinedCcType($response->getData('cardtype'));
					$this->setDeclinedCcLast4(substr($response->getData('uniqueid'), 0, 4));
					$this->setResponseCode(self::RESPONSE_DECLINED);
					return true;
				case self::RESPONSE_ERROR:
					$this->setResponseCode(self::RESPONSE_ERROR);
					$this->setResponseMessage($response->getResponseMessage());
					return true;
				default:
					$exceptionMessage = $this->_formatGatewayError(Mage::helper('shift4payment')->__('Payment partial authorization error.'));
			}
		} catch (Exception $e) {
			$exceptionMessage = $e->getMessage();
		}

		throw new Mage_Payment_Model_Info_Exception($exceptionMessage);
	}

	/**
	 * Determines if error code is in a certain list of codes, including any code that begins with "2"
	 *
	 * @param string $errorCode
	 * @return void
	*/
	protected function _checkErrorCode($errorCode) {
		$errorCode = ltrim($errorCode, '0'); // some error codes have extra leading zeros 
		$codeList = array('1001','9033','9489','9901','9902','9951','9960','9961','9962','9964','9978','4003','9012','9018','9020','9023','9033','9957');

		if (in_array($errorCode, $codeList)) {
			return true;
		} elseif (substr($errorCode, 0, 1) == '2') {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Sanitize an array
	 *
	 * @param array $inputArray
	 * @return $sanitizedArray
	 */
	function _sanitizeArray($inputArray = '[no data was provided]') {
		$keywordList = array(
						'password',
						'apipassword',
						'cc_number',
						'cardnumber',
						'cvv2',
						'cvv',
						'cvvcode',
						'shift4_cc_number',
						'shift4_cc_expires_month',
						'shift4_cc_expires_year',
						'shift4_cc_cvv',
						'cvv2code',
						'trackinformation',
						'cckey',
						'secretanswer',
						'notes',
						'text',
						'creditcard',
						'verification',
						'cardtype',
						'expiration',
						'expirationmonth',
						'expirationyear',
						);
		$inputArray = array_change_key_case($inputArray, CASE_LOWER);

		foreach ($inputArray as $key => $value) {
			foreach ($keywordList as $filterWord) {
				if (strpos($key, $filterWord) !== FALSE) {
					$inputArray[$key] = '(filtered)';
				}
			}
		}
		$sanitizedArray = &$inputArray;
		return $sanitizedArray;
	}

	/************************************************** MODIFYING ORDER/PAYMENT/TRANSACTIONS **************************************************/

	protected function _addTransaction(Mage_Sales_Model_Order_Payment $payment, $transactionId, $transactionType,
		array $transactionDetails = array(), $message = false
	) {
		$payment->setTransactionId($transactionId);
		$payment->resetTransactionAdditionalInfo();
		foreach ($transactionDetails as $key => $value) {
			$payment->setData($key, $value);
		}
		$transaction = $payment->addTransaction($transactionType, null, false, $message);
		foreach ($transactionDetails as $key => $value) {
			$payment->unsetData($key);
		}
		$payment->unsLastTransId();
		$transaction->setMessage($message);
		$transaction->save();
		return $transaction;
	}

	protected function _payInvoice($payment) {
		$order = $payment->getOrder();
		try {
			if(!$order->canInvoice()) {
				Mage::throwException(Mage::helper('core')->__('Cannot create an invoice.'));
			}
			$invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
			if (!$invoice->getTotalQty()) {
				Mage::throwException(Mage::helper('core')->__('Cannot create an invoice without products.'));
			}
			$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::NOT_CAPTURE);
			$invoice->register();
			$transactionSave = Mage::getModel('core/resource_transaction')
				->addObject($invoice)
				->addObject($invoice->getOrder());
			$transactionSave->save();
			$invoice->pay();
			//$invoice->setTransactionId(1)->save(); // Needed to allow online refunds
			$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true)->save();
			$order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING, true)->save();
			$payment->getOrder()->save();
			$payment->save();			
		}
		catch (Mage_Core_Exception $e) {
			Mage::throwException($e->getMessage());
		}
	}

	protected function _processAmount($payment, $amount, $requestType) {
		if ($requestType == self::REQUEST_TYPE_AUTHORIZE) {
			$payment->setBaseAmountAuthorized($payment->getBaseAmountAuthorized() + $amount);
			$payment->setAmountAuthorized($payment->getAmountAuthorized() + $amount);
		} else if ($requestType == self::REQUEST_TYPE_CAPTURE) {
			$payment->setBaseAmountAuthorized($payment->getBaseAmountAuthorized() + $amount);
			$payment->setAmountAuthorized($payment->getAmountAuthorized() + $amount);
		}
		// TODO: Set to partial_payment when in middle of partial auths, set to complete/processing when final approval is done
		$payment->getOrder()->save();
		$payment->save();
	}

	/************************************************** HELPERS AND STATUS CHECKING **************************************************/

	/**
	 * Get session object
	 *
	 * @return Mage_Core_Model_Session_Abstract
	 */
	protected function _getSession()
	{
		if (Mage::app()->getStore()->isAdmin()) {
			return Mage::getSingleton('adminhtml/session_quote');
		} else {
			return Mage::getSingleton('checkout/session');
		}
	}

	/**
	 * Round up and cast specified amount to float or string
	 *
	 * @param	string|float $amount
	 * @param	bool $asFloat
	 * @return	string|float
	 */
	protected function _formatAmount($amount, $asFloat = false)
	{
		 $amount = Mage::app()->getStore()->roundPrice($amount);
		 return !$asFloat ? (string)$amount : $amount;
	}

	protected function _formatGatewayError($message)
	{
		return Mage::helper('shift4payment')->__('Gateway communication error: %s', $message);
	}

	protected function _isPreauthorizeCapture($payment)
	{
		if ($this->getCardsStorage($payment)->getCardsCount() <= 0) {
			return false;
		}
		foreach($this->getCardsStorage($payment)->getCards() as $card) {
			$lastTransaction = $payment->getTransaction($card->getLastTransId());
			if (!$lastTransaction
				|| $lastTransaction->getTxnType() != Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH
			) {
				return false;
			}
		}
		return true;
	}

	protected function _isGatewayActionsLocked($payment)
	{
		return $payment->getAdditionalInformation($this->_isGatewayActionsLockedKey);
	}

	protected function _log($message, $file = '')
	{
		if (!empty($file)) {
			Mage::log($message, $file);
		} else {
			Mage::log($message);
		}
	}

	protected function _frcToRequestType($frc, $saleFlag) {
		if ($frc == self::FRC_AUTHORIZE  && $saleFlag == self::FRC_AUTHORIZE_SALE_FLAG) {
			return self::REQUEST_TYPE_AUTHORIZE;
		} else if ($frc == self::FRC_CAPTURE && $saleFlag == self::FRC_CAPTURE_SALE_FLAG) {
			return self::REQUEST_TYPE_CAPTURE;
		} else if ($frc == self::FRC_CREDIT && $saleFlag == self::FRC_CREDIT_SALE_FLAG) {
			return self::REQUEST_TYPE_CREDIT;
		} else if ($frc == self::FRC_VOID && $saleFlag == self::FRC_VOID_SALE_FLAG) {
			return self::REQUEST_TYPE_VOID;
		} else if ($frc == self::FRC_GET_INVOICE && $saleFlag == self::FRC_GET_INVOICE_SALE_FLAG) {
			return self::REQUEST_TYPE_GET_INVOICE;
		} else {
			return false;
		}
	}

	protected function _requestTypeToFrc($requestType) {
		if ($requestType == self::REQUEST_TYPE_AUTHORIZE) {
			return array('frc' => self::FRC_AUTHORIZE, 'saleFlag' => self::FRC_AUTHORIZE_SALE_FLAG);
		} else if ($requestType == self::REQUEST_TYPE_CAPTURE) {
			return array('frc' => self::FRC_CAPTURE, 'saleFlag' => self::FRC_CAPTURE_SALE_FLAG);
		} else if ($requestType == self::REQUEST_TYPE_CREDIT) {
			return array('frc' => self::FRC_CREDIT, 'saleFlag' => self::FRC_CREDIT_SALE_FLAG);
		} else if ($requestType == self::REQUEST_TYPE_VOID) {
			return array('frc' => self::FRC_VOID, 'saleFlag' => self::FRC_VOID_SALE_FLAG);
		} else if ($requestType == self::REQUEST_TYPE_GET_INVOICE) {
			return array('frc' => self::FRC_GET_INVOICE, 'saleFlag' => self::FRC_GET_INVOICE_SALE_FLAG);
		} else {
			return false;
		}
	}

	protected function _formatCardType($cardType) {
		switch (strtoupper($cardType)) {
			case 'VS':
				return 'Visa';
			case 'MC':
				return 'Mastercard';
			case 'NS':
				return 'Discover';
			case 'AX':
			case 'AMEX':
				return 'American Express';
			case 'YC':
				return "It's Your Card";
			case 'GC':
				return "Gift Card";
			case 'DC':
				return 'Diners Club';
			case 'JC':
			case 'JCB':
				return 'JCB';
			default:
				return $cardType;
		}
	}

	/**
	 * Log gateway communications
	 *
	 * @param $request
	 * @param $response
	 */
	protected function _logGateway($request, $response) {
		if (Mage::helper('shift4payment')->getLogMode() != Shift4_Shift4Payment_Helper_Data::LOG_MODE_OFF) {
			if (Mage::helper('shift4payment')->getLogMode() == Shift4_Shift4Payment_Helper_Data::LOG_MODE_ALL && is_callable(array($response, 'getData'))) {
				$requestSanitized = $this->_sanitizeArray($request->getParams());
				$tempResponseFormatted = print_r($response->getData(), true);
				$responseFormatted = str_replace('%0D%0A', ' ', $tempResponseFormatted);
				$logData = PHP_EOL . 'Sent: ' . PHP_EOL . print_r($requestSanitized, true) . 'Received: ' . PHP_EOL . $responseFormatted . PHP_EOL . PHP_EOL;
				Mage::log($logData, null, 'Shift4.log');
			} else if ((Mage::helper('shift4payment')->getLogMode() == Shift4_Shift4Payment_Helper_Data::LOG_MODE_ERRORS) && ($response->getResponseCode() == self::RESPONSE_DECLINED) && is_callable(array($response, 'getData'))) {
				$requestSanitized = $this->_sanitizeArray($request->getParams());
				$tempResponseFormatted = print_r($response->getData(), true);
				$responseFormatted = str_replace('%0D%0A', ' ', $tempResponseFormatted);
				$logData = PHP_EOL . 'Sent: ' . PHP_EOL . print_r($requestSanitized, true) . 'Received: ' . PHP_EOL . $responseFormatted . PHP_EOL . PHP_EOL;
				Mage::log($logData, null, 'Shift4-error.log');
			}
		}
	}

	/**
	 * Log timeout
	 *
	 * @param	$payment
	 * @return boolean
	 */
	protected function _logTimeout(Varien_Object $request) {
		$sessionId = $this->_getSession()->getSessionId();
		$sql = "
				INSERT INTO shift4_timeout_log
				(
					time,
					customer_id,
					customer_name,
					invoice,
					request_code,
					session_id,
					notes,
					amount,
					cardtype,
					unique_id
				)
				VALUES
				(
					'" . now() . "',
					'" . $request->getData('customerreference') . "',
					'" . $request->getData('customername') . "',
					'" . $request->getData('invoice') . "',
					'" . $request->getData('functionrequestcode') . "',
					'" . $sessionId . "',
					'" . $request->getData('notes') . "',
					'" . $request->getData('primaryamount') . "',
					'" . $this->_formatCardType($request->getData('cardtype')) . "',
					'" . $request->getData('uniqueid') . "'
				)
				";
		$db = Mage::getSingleton('core/resource')->getConnection('core_write');
		$queryResult = $db->query($sql);
		return true;
	}

}
?>