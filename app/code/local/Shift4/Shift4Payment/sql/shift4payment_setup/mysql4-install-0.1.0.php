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

$installer = $this;
/* @var $installer Shift4_Shift4Payment_Model_Mysql4_Setup */

$installer->startSetup();

$installer->run("
	DROP TABLE IF EXISTS `{$this->getTable('shift4_timeout_log')}`;
	CREATE TABLE `{$this->getTable('shift4_timeout_log')}` (
		`id` INT NOT NULL AUTO_INCREMENT,
		`time` DATETIME NOT NULL,
		`customer_id` INT NOT NULL,
		`customer_name` VARCHAR(50) NOT NULL,
		`invoice` VARCHAR(11) NOT NULL,
		`request_code` VARCHAR(2) NOT NULL,
		`session_id` VARCHAR(128) NOT NULL,
		`notes` TEXT NOT NULL,
		`amount` DECIMAL(14, 2) NOT NULL,
		`cardtype` VARCHAR(32) NOT NULL,
		`unique_id` VARCHAR(32) NOT NULL,
		PRIMARY KEY (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

$installer->endSetup();

?>