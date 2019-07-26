<?php
/**
 * Copyright (C) 2019 SLiCK-303
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 *
 * @package    birthdaygift
 * @author     SLiCK-303 <slick_303@hotmail.com>
 * @copyright  2019 SLiCK-303
 * @license    Academic Free License (AFL 3.0)
**/

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/birthdaygift.php');

if (Tools::getIsset('secure_key'))
{
	$secure_key = Configuration::get('BDAY_GIFT_SECURE_KEY');
	if (!empty($secure_key) && $secure_key === Tools::getValue('secure_key'))
	{
		$birthdaygift = new BirthdayGift();
		if ($birthdaygift->active)
			$birthdaygift->cronTask();
	}
}
