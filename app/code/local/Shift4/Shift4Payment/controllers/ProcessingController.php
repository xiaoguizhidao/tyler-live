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
class Shift4_Shift4Payment_ProcessingController extends Mage_Core_Controller_Front_Action
{

	protected function _expireAjax()
	{
		try {
			if (!Mage::getSingleton('checkout/session')->getQuote()->hasItems()) {
				$this->getResponse()->setHeader('HTTP/1.1','403 Session Expired');
				exit;
			}
		}
		catch (Exception $e) {
			Mage::log('_expireAjax Error: ' . $e->getMessage(), null, 'Shift4-error.log');
		}
	}

	/**
	 * Payment processing page (redirected after Place Order button)
	 */
	public function paymentAction()
	{
		try {
			$session = $this->_getCurrentSession();
			$lastRealOrderId = $session->getLastRealOrderId();
			
			/*			
			if (!Mage::helper('shift4payment')->isValidOrderFormat($lastRealOrderId)) {
				Mage::log('Error: invoice contains non-numbers, or is greater than 10 digits: ' . $lastRealOrderId, null, 'Shift4-error.log');
			}
			*/
			$url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);		
			if (empty($lastRealOrderId)) {
				$this->_redirectUrl($url);	
			} else {
				$order = $this->_getCurrentOrder($lastRealOrderId);
				
				//Redirect if order payment is already fullfilled.
				if(!$order->canInvoice()) {
					$this->_redirectUrl($url);
					return;
				}
				
				
				$order->addStatusHistoryComment(Mage::helper('shift4payment')->__('Customer was redirected to Shift4 payment page. Awaiting payment.'))->save();
				$order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true)->save();
				$order->setStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true)->save();
				$this->loadLayout();
				$this->renderLayout();
				$session->unsQuoteId();
			}
		}
		catch (Exception $e) {
			Mage::log('paymentAction Error: ' . $e->getMessage(), null, 'Shift4-error.log');
		}
	}

	/**
	 * Ajax call after user clicks Make Payment, inserts the token into the order info
	 */
	public function tokenizeAction()
	{
		try {
			$session = $this->_getCurrentSession();
			$post = $this->getRequest()->getPost();
			if (empty($post)) {
				$url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);
				$this->_redirectUrl($url);
			} else {
				$lastRealOrderId = $session->getLastRealOrderId();
				$ccNumber = $post['uniqueId'];
				$ccType = $post['cardType'];
				$ccExpMonth = $post['expirationMonth'];
				$ccExpYear = $post['expirationYear'];
				if (!empty($ccNumber) && !empty($lastRealOrderId)) {
					$this->_getCurrentOrder($lastRealOrderId)->getPayment()
						->setCcNumber($ccNumber)
						->setCcNumberEnc($ccNumber)
						->setCcLast4(substr($ccNumber, 0, 4))
						->setCcType($ccType)
						->setCcExpMonth($ccExpMonth)
						->setCcExpYear($ccExpYear)
						->save();
					$this->_setJsonResponse(array('responseCode' => 0));
				}
			}
		}
		catch (Exception $e) {
			Mage::log('tokenizeAction Error: ' . $e->getMessage(), null, 'Shift4-error.log');
		}
	}

	/**
	 * Ajax call after user clicks submit with CC info, sends the authorize/capture call to Shift4
	 */
	public function processPaymentAction()
	{
		try {
			$session = $this->_getCurrentSession();
			if ($session->getLastRealOrderId()) {
				$order = $this->_getCurrentOrder($session->getLastRealOrderId());
				$payment = $order->getPayment();
				$paymentMethod = $this->_getPaymentMethod();
				if (Mage::helper('shift4payment')->getTransactionMode() == Shift4_Shift4Payment_Helper_Data::TRANSACTION_MODE_BOOK_AND_SHIP) {
					$response = $paymentMethod->authorize($payment,$order->getGrandTotal()); // Book and Ship
				} else {
					$response = $paymentMethod->capture($payment,$order->getGrandTotal()); // Immediate Charge
				}
				$this->processPaymentResponse($order, $response);
			} else {
				$response = Mage::getModel('shift4payment/ApiResponse');
				$response->setResponseCode(self::RESPONSE_ERROR)->setResponseMessage('processPaymentAction error - no lastRealOrderId');
				$this->processPaymentResponse($order, $response);
			}
		}
		catch (Exception $e) {
			Mage::log('processPaymentAction Error: ' . $e->getMessage(), null, 'Shift4-error.log');
		}
	}

	/**
	 * Response handler to convert the paymentMethod response to JSON values
	 */
	protected function processPaymentResponse($order, $response) {
		try {
			$responseCode = $response->getResponseCode();
			switch ($responseCode) {
				case Shift4_Shift4Payment_Model_PaymentMethod::RESPONSE_APPROVED:
					try {
						$order->sendNewOrderEmail()->setEmailSent(true)->save();
					}
					catch (Exception $e) { }
					$this->_setJsonResponse(array(
						'responseCode' => $responseCode
					));
					break;
				case Shift4_Shift4Payment_Model_PaymentMethod::RESPONSE_PARTIAL_AUTH:
					$this->_setJsonResponse(array(
						'responseCode' => $responseCode,
						'ccType' => $response->getPartialAuthCcType(),
						'ccLast4' => $response->getPartialAuthCcLast4(),
						'ccExpMonth' => $response->getPartialAuthCcExpMonth(),
						'ccExpYear' => $response->getPartialAuthCcExpYear(),
						'processedAmount' => $order->formatPrice($response->getPartialAuthProcessed()),
						'remainingAmount' => $order->formatPrice($response->getPartialAuthRemaining())
					));
					break;
				case Shift4_Shift4Payment_Model_PaymentMethod::RESPONSE_DECLINED:
				case Shift4_Shift4Payment_Model_PaymentMethod::RESPONSE_CARD_LIMIT:
					$paymentMethod = $this->_getPaymentMethod();
					$payment = $order->getPayment();
					$payment->setVoidLastCardOnly(true);
					$paymentMethod->void($payment);
					$this->_setJsonResponse(array(
						'responseCode' => $responseCode,
						'ccType' => $response->getDeclinedCcType(),
						'ccLast4' => $response->getDeclinedCcLast4(),
					));
					break;
				case Shift4_Shift4Payment_Model_PaymentMethod::RESPONSE_ERROR:
					$paymentMethod = $this->_getPaymentMethod();
					$payment = $order->getPayment();
					sleep(5);
					$response = $paymentMethod->_getInvoice($payment);
					$this->processPaymentResponse($order, $response);
					break;
				case Shift4_Shift4Payment_Model_PaymentMethod::RESPONSE_ERROR_CONDITION:
				case Shift4_Shift4Payment_Model_PaymentMethod::RESPONSE_TIMEOUT:
					$this->_setJsonResponse(array(
						'responseCode' => $responseCode,
						'responseMessage' => $response->getResponseMessage()
					));
					break;
				default:
					$this->_setJsonResponse(array(
						'responseCode' => Shift4_Shift4Payment_Model_PaymentMethod::RESPONSE_ERROR,
						'responseMessage' => $response->getResponseMessage()
					));
					break;
			}
		}
		catch (Exception $e) {
			Mage::log('processPaymentResponse Error: ' . $e->getMessage(), null, 'Shift4-error.log');
		}
	}

	/**
	 * Payment cancel page (redirected after Cancel button)
	 */
	public function cancelAction()
	{
		try {
			$session = $this->_getCurrentSession();
			if ($session->getLastRealOrderId()) {
				$order = $this->_getCurrentOrder($session->getLastRealOrderId());
				if ($order->getId()) {
					if (Mage::helper('shift4payment')->getTransactionMode() == Shift4_Shift4Payment_Helper_Data::TRANSACTION_MODE_IMMEDIATE_CHARGE) {
						$order->getPayment()->void($order->getPayment());
					}
					$order->cancel()->save();
				}
			}
			$this->_redirect('checkout/cart');
		}
		catch (Exception $e) {
			Mage::log('processPaymentResponse Error: ' . $e->getMessage(), null, 'Shift4-error.log');
		}
	}

	/**
	 * Payment success page (redirected after processPaymentAction completes)
	 */
	public function successAction()
	{
		try {
			// TODO: Send an email to the store owner if an order was made in demo mode
			$session = $this->_getCurrentSession();
			$session->getQuote()->setIsActive(false)->save();
			$this->_redirect('checkout/onepage/success', array('_secure'=>true));
		}
		catch (Exception $e) {
			Mage::log('successAction Error: ' . $e->getMessage(), null, 'Shift4-error.log');
		}
	}

	/********** Helper functions **********/

	/**
	 * Get the current session with Shift4PaymentQuoteId set to QuoteId
	 *
	 * @return Mage_Checkout_Model_Session
	 */
	protected function _getCurrentSession() {
		try {
			$session = Mage::getSingleton('checkout/session');
			$session->setShift4PaymentQuoteId($session->getQuoteId());
			return $session;
		}
		catch (Exception $e) {
			Mage::log('_getCurrentSession Error: ' . $e->getMessage(), null, 'Shift4-error.log');
		}
	}

	/**
	 * Get the current order loaded by IncrementId (LastRealOrderId)
	 *
	 * @param	int $incrementId
	 * @return	Mage_Sales_Model_Order
	 */
	protected function _getCurrentOrder($incrementId) {
		try {
			return Mage::getModel('sales/order')->loadByIncrementId($incrementId);
		}
		catch (Exception $e) {
			Mage::log('_getCurrentOrder Error: ' . $e->getMessage(), null, 'Shift4-error.log');
		}
	}

	/**
	 * Get the Shift4 Payment Method
	 *
	 * @return Shift4_Shift4Payment_Model_PaymentMethod
	 */
	protected function _getPaymentMethod() {
		try {
			return Mage::getModel('shift4payment/PaymentMethod');
		}
		catch (Exception $e) {
			Mage::log('_getPaymentMethod Error: ' . $e->getMessage(), null, 'Shift4-error.log');
		}
	}

	/**
	 * Set the response body to a simple HTML message.
	 *
	 * @param	string $message
	 * @return	string
	 */
	protected function _setHtmlResponse($message)
	{
		try {
			$this->getResponse()->setBody('<html><body>' . $message . '</body></html>');
		}
		catch (Exception $e) {
			Mage::log('_setHtmlResponse Error: ' . $e->getMessage(), null, 'Shift4-error.log');
		}
	}

	/**
	 * Set the response body to a simple JSON message.
	 *
	 * @param	string $message
	 * @return	string
	 */
	protected function _setJsonResponse($message)
	{
		try {
			$this->getResponse()->setBody('{ "result": ' . json_encode($message) . ' }');
		}
		catch (Exception $e) {
			Mage::log('_setJsonResponse Error: ' . $e->getMessage(), null, 'Shift4-error.log');
		}
	}
}