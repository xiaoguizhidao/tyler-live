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

class Shift4_Shift4SalesReport_Block_Index extends Mage_Adminhtml_Block_Widget_Container
{

	protected $_salesGridData = array();
	protected $_statsGridData = array();
	protected $_timeoutsGridData = array();
	protected $_currencySymbol;
	protected $_salesTotal = 0;
	protected $_timeoutsTotal = 0;

	/*
	 * Constructor method
	 */
	public function _construct()
	{
		$this->setTemplate('shift4/shift4salesreport/index.phtml');
		$this->_currencySymbol = Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())->getSymbol();
		parent::_construct();
	}

	/*
	 *
	 */
	public function getModuleResult($module)
	{
		if(extension_loaded($module)) {
			return $this->__('Loaded');
		} else {
			return $this->__('Not available');
		}
	}

	protected function runReports($startDate, $endDate)
	{
		$salesArray = array();
		$ccTypes = array();
		$statsArray = array();
		$timeoutsArray = array();

		$db = Mage::getSingleton('core/resource')->getConnection('core_read');

		$salesReportQuery = "
			SELECT		o.increment_id,
						o.created_at,
						p.additional_information,
						o.grand_total,
						o.customer_firstname,
						o.customer_lastname
			FROM		sales_flat_order o
			LEFT JOIN	sales_flat_order_payment p ON o.entity_id = p.parent_id
			WHERE		o.created_at between '" . $startDate . "' AND '" . $endDate . "' AND p.method = 'shift4payment'
			";
		$timeoutsReportQuery = "
			SELECT	invoice,
					time AS datetime,
					cardtype AS cc_type,
					unique_id,
					amount,
					customer_name,
					customer_id
			FROM	shift4_timeout_log
			WHERE	time between '" . $startDate . "' AND '" . $endDate . "'
			";
		$salesResults = $db->fetchAll($salesReportQuery);
		$timeoutsResults = $db->fetchAll($timeoutsReportQuery);

		// Build sales report by unserializing partial auth payment data into separate rows
		foreach ($salesResults as $key => $value) {
			$additionalInfo = unserialize($salesResults[$key]['additional_information']);
			$cards = $additionalInfo[Shift4_Shift4Payment_Model_Cards::CARDS_NAMESPACE];
			if (count($cards) > 0) {
				foreach ($cards as $cardInfo) {
					if (isset($cardInfo['cc_type']) && isset($cardInfo['cc_last4'])) {
						$ccType = $cardInfo['cc_type'];
						$amountProcessed = $cardInfo[Shift4_Shift4Payment_Model_Cards::CARD_PROCESSED_AMOUNT_KEY];
						$salesArray[] = array(
							"invoice" => $salesResults[$key]['increment_id'],
							"datetime" => $salesResults[$key]['created_at'],
							"cc_type" => $ccType,
							"cc_last4" => 'xxxx-' . $cardInfo['cc_last4'],
							"amount" => $this->_formatAmount($amountProcessed),
							"total" => $this->_formatAmount($salesResults[$key]['grand_total']),
							"customer_name" => $salesResults[$key]['customer_firstname'] . ' ' . $salesResults[$key]['customer_lastname']
						);
						$this->_salesTotal += $amountProcessed;
						if (array_key_exists($ccType, $ccTypes)) {
							$ccTypes[$ccType]['count']++;
							$ccTypes[$ccType]['total'] += $amountProcessed;
						} else {
							$ccTypes[$ccType] = array('cc_type' => $ccType, 'count' => 1, 'total' => $amountProcessed);
						}
					}
				}
			}
		}
		// Build statistics by reindexing the array to numeric keys
		foreach ($ccTypes as $key => $value) {
			$value['total'] = $this->_formatAmount($value['total']);
			$statsArray[] = $value;
		}

		foreach ($timeoutsResults as $key => $value) {
			$this->_timeoutsTotal += $value['amount'];
			$value['amount'] = $this->_formatAmount($value['amount']);
			$value['cc_last4'] = 'xxxx-' . substr($value['unique_id'], 0, 4);
			$timeoutsArray[] = $value;
		}

		$this->_salesGridData["rows"] = $salesArray;
		$this->_salesTotal = $this->_formatAmount($this->_salesTotal);
		$this->_statsGridData["rows"] = $statsArray;
		$this->_timeoutsGridData["rows"] = $timeoutsArray;
		$this->_timeoutsTotal = $this->_formatAmount($this->_timeoutsTotal);

	}

	protected function _formatAmount($amount) {
		return $this->_currencySymbol . number_format($amount, 2);
	}
}
