<?php
/**
 * @copyright Amasty.
 */
$this->startSetup();

$this->run("
ALTER TABLE `{$this->getTable('amshopby/page')}`
ADD `cms_block_id` int(11) DEFAULT NULL");

$this->run("
UPDATE `{$this->getTable('amshopby/page')}` v,`{$this->getTable('cms/block')}` b
SET v.`cms_block_id` = b.`block_id`
WHERE b.`identifier` = v.`cms_block`
");

$this->run("
ALTER TABLE `{$this->getTable('amshopby/page')}`
DROP `cms_block`
");


$this->endSetup();
