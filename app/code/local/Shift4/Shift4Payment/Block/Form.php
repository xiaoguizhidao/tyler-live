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
 * Payment method form base block
 */
class Shift4_Shift4Payment_Block_Form extends Mage_Payment_Block_Form
{
	protected function _construct()
	{
		parent::_construct();
		$this->setTemplate('shift4/shift4payment/form.phtml');
	}

	protected function geti4GoLogoUrl()
	{
		return $this->getSkinUrl('images/shift4payment/i4go.png');
	}

	protected function getInfoUrl()
	{
		return 'http://www.shift4.com/magento_enduser';
	}

}