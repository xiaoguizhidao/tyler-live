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

/**
 * Payment method info base block
 */
class Shift4_Shift4Payment_Block_Info extends Mage_Payment_Block_Info_Cc
{
	/**
	 * Init default template for block
	 */
	protected function _construct()
	{
		parent::_construct();
		$this->setTemplate('shift4/shift4payment/info.phtml');
	}

    /**
     * Get card storage info object
     *
     * @return mixed
     */
    public function getInfo()
    {
        if ($this->hasCardInfoObject()) {
            return $this->getCardInfoObject();
        }
        return parent::getInfo();
    }

    /**
     * Get card storage array
     *
     * @return array
     */
    public function getCards()
    {
        $cardsData = $this->getMethod()->getCardsStorage()->getCards();
        $cards = array();

        if (is_array($cardsData)) {
            foreach ($cardsData as $cardInfo) {
                $data = array();
                if ($cardInfo->getProcessedAmount()) {
                    $amount = Mage::helper('core')->currency($cardInfo->getProcessedAmount(), true, false);
                    $data[Mage::helper('shift4payment')->__('Processed Amount')] = $amount;
                }
                if ($cardInfo->getBalanceOnCard() && is_numeric($cardInfo->getBalanceOnCard())) {
                    $balance = Mage::helper('core')->currency($cardInfo->getBalanceOnCard(), true, false);
                    $data[Mage::helper('shift4payment')->__('Remaining Balance')] = $balance;
                }
                if ($cardInfo->getReceiptText()) {
                    $data[Mage::helper('shift4payment')->__('Receipt Text')] = $cardInfo->getReceiptText();
                }
                if ($cardInfo->getDateTime()) {
                    $data[Mage::helper('shift4payment')->__('Transaction Date')] = $cardInfo->getDateTime();
                }
                $this->setCardInfoObject($cardInfo);
                $cards[] = array_merge($this->getSpecificInformation(), $data);
                $this->unsCardInfoObject();
                $this->_paymentSpecificInformation = null;
            }
        }
        return $cards;
    }

}