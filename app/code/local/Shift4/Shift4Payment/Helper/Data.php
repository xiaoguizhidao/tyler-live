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
class Shift4_Shift4Payment_Helper_Data extends Mage_Payment_Helper_Data
{
	// Public variables and functions to define URLs, image paths, global config settings, etc.
	const TRANSACTION_MODE_IMMEDIATE_CHARGE	= 0;
	const TRANSACTION_MODE_BOOK_AND_SHIP	= 1;
	const LOG_MODE_OFF						= 0;
	const LOG_MODE_ERRORS					= 1;
	const LOG_MODE_ALL						= 2;

	public $demoSettingsFileUrl				= 'https://myportal.shift4.com/downloads/certifydemo.xml';
	public $enduserInfoUrl					= 'http://www.shift4.com/magento_enduser';
	public $merchantInfoUrl					= 'http://www.shift4.com/magento_merchant';
	public $imagei4Go						= 'images/shift4payment/i4go.png';
	public $imageShift4						= '';
	public $dotnApiUrlSuffix				= 'api/s4tran_action.cfm';
	public $i4GoServerUrl					= 'https://secure.i4go.com/';

	public function getDotnServerUrl() {
		return 'https://server' . mt_rand(1,12) . '.dollarsonthenet.net/';
	}

	public function isDemoMode() {
		return Mage::getStoreConfig('payment/shift4payment/demo_mode') == 1; // true/false
	}
	public function getEnforceAddress() {
		return Mage::getStoreConfig('payment/shift4payment/enforce_address'); // yes/no
	}
	public function getTransactionMode() {
		return Mage::getStoreConfig('payment/shift4payment/transaction_mode'); // 0=Immediate Charge, 1=Book and Ship (see self::TRANSACTION_MODE)
	}
	public function getLogMode() {
		return Mage::getStoreConfig('payment/shift4payment/log_mode'); // 0=Off, 1=Log Errors, 2=Log All (see self::LOG_MODE)
	}
	public function isValidOrderFormat($orderId) {
		if (!is_numeric($orderId) || strlen($orderId) > 10) {
			return false;
		} else {
			return true;
		}
	}	
	public function getApiSettings() {
		$apiSettings = array();
		if (Mage::getStoreConfig('payment/shift4payment/demo_mode') == 1) {
			try {
				$demoCredentialsFile = file_get_contents($this->demoSettingsFileUrl);	
			} catch (Exception $e) {
				$message = "The file '" . $this->demoSettingsFileUrl . "' could not be loaded. Ensure that this file is accessible from your web server.";
				 Mage::log($message, null, "Shift4-error.log");
			}
			if ($demoCredentialsFile && simplexml_load_string($demoCredentialsFile)) {
				$demoCredentialsXml = simplexml_load_string($demoCredentialsFile);
				$apiSettings['i4GoUrl'] = (string) $demoCredentialsXml->config->i4Go->url;
				$apiSettings['i4GoAccountId'] = (string) $demoCredentialsXml->config->i4Go->accountID;
				$apiSettings['i4GoSiteId'] = (string) $demoCredentialsXml->config->i4Go->siteID;
				$apiSettings['dotnUrl'] = (string) $demoCredentialsXml->config->dotn->url . $this->dotnApiUrlSuffix;
				$apiSettings['accountNumber'] = (string) $demoCredentialsXml->config->dotn->serialNumber;
				$apiSettings['userName'] = (string) $demoCredentialsXml->config->dotn->userName;
				$apiSettings['password'] = (string) $demoCredentialsXml->config->dotn->password;
				$apiSettings['merchantId'] = (string) $demoCredentialsXml->config->dotn->merchantID;
			}
		} else {
			$apiSettings['i4GoUrl'] = $this->i4GoServerUrl;
			$apiSettings['i4GoAccountId'] = Mage::getStoreConfig('payment/shift4payment/i4go_account_id');
			$apiSettings['i4GoSiteId'] = Mage::getStoreConfig('payment/shift4payment/i4go_site_id');
			$apiSettings['dotnUrl'] = $this->getDotnServerUrl() . $this->dotnApiUrlSuffix;
			$apiSettings['accountNumber'] = Mage::getStoreConfig('payment/shift4payment/account_number');
			$apiSettings['userName'] = Mage::getStoreConfig('payment/shift4payment/api_username');
			$apiSettings['password'] = Mage::getStoreConfig('payment/shift4payment/api_password');
			$apiSettings['merchantId'] = Mage::getStoreConfig('payment/shift4payment/merchant_id');
		}
		return $apiSettings;
	}

	public function getTransactionMessage($payment, $requestType, $lastTransactionId, $card, $amount = false, $exception = false)
	{
		$operation = $this->_getOperation($requestType);
		if (!$operation) {
			return false;
		}
		if ($amount) {
			$amount = $this->__('amount %s', $this->_formatPrice($payment, $amount));
		}
		if ($exception) {
			$result = $this->__('failed');
		} else {
			$result = $this->__('successful');
		}
		$card = $this->__('Credit Card: xxxx-%s', $card->getCcLast4());
		$transaction = $this->__('Shift4 Transaction ID %s', $lastTransactionId);
		$message = $this->__('%s %s %s %s. %s. %s', $card, $amount, $operation, $result, $transaction, $exception);
		return $message;
	}

	protected function _getOperation($requestType)
	{
		switch ($requestType) {
			case Shift4_Shift4Payment_Model_PaymentMethod::REQUEST_TYPE_AUTHORIZE:
				return $this->__('authorize');
			case Shift4_Shift4Payment_Model_PaymentMethod::REQUEST_TYPE_CAPTURE:
				return $this->__('capture');
			case Shift4_Shift4Payment_Model_PaymentMethod::REQUEST_TYPE_CREDIT:
				return $this->__('refund');
			case Shift4_Shift4Payment_Model_PaymentMethod::REQUEST_TYPE_VOID:
				return $this->__('void');
			default:
				return false;
		}
	}

	protected function _formatPrice($payment, $amount)
	{
		return $payment->getOrder()->getBaseCurrency()->formatTxt($amount);
	}

	public function convertMessagesToMessage($messages)
	{
		return implode(' | ', $messages);
	}

}
?>