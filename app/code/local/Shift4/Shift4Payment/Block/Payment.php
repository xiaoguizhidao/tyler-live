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
class Shift4_Shift4Payment_Block_Payment extends Mage_Core_Block_Template
{

	protected $_apiSettings = array();
	protected $_order;

	/**
	 * Init default template for block, also reads in i4Go and DOTN settings based on demo/live mode and saves them in session
	 */
	protected function _construct()
	{
		parent::_construct();
		$this->_apiSettings = Mage::helper('shift4payment')->getApiSettings();

		$session = Mage::getSingleton('checkout/session');
		$session->setShift4PaymentQuoteId($session->getQuoteId());
		$lastRealOrderId = $session->getLastRealOrderId();

		$session->setApiSettings($this->_apiSettings);

		$this->_order = Mage::getModel('sales/order')->loadByIncrementId($lastRealOrderId);

	}

	protected function getOrder() {
		return $this->_order;
	}

	/**
	 * Retrieve API settings
	 *
	 * @return array
	 */
	protected function getApiSetting($key) {
		return $this->_apiSettings[$key];
	}

	/**
	 * Get singleton of Payment Config model
	 *
	 * @return Mage_Payment_Model_Config
	 */
	protected function _getConfig()
	{
		return Mage::getSingleton('payment/config');
	}

	/**
	 * Retrieve credit card expiration months
	 *
	 * @return array
	 */
	public function getCcMonths()
	{
		$months = $this->getData('cc_months');
		if (is_null($months)) {
			$months[0] = $this->__('Month');
			$months = array_merge($months, $this->_getConfig()->getMonths());
			$this->setData('cc_months', $months);
		}
		return $months;
	}

	/**
	 * Retrieve credit card expiration years
	 *
	 * @return array
	 */
	public function getCcYears()
	{
		$years = $this->getData('cc_years');
		if (is_null($years)) {
			$years = $this->_getConfig()->getYears();
			$years = array(0=>$this->__('Year'))+$years;
			$this->setData('cc_years', $years);
		}
		return $years;
	}
}
?>