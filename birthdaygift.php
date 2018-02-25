<?php
/**
 * Copyright (C) 2018 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @package	  birthdaygift
 * @author	  slick-303 <slick_303@hotmail.com>
 * @copyright 2017-2018 SLiCK-303
 * @license	  http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_TB_VERSION_')) {
	exit;
}

/**
 * Class BirthdayGift
 */
class BirthdayGift extends Module
{
	private $_html = '';

	function __construct()
	{
		$this->name = 'birthdaygift';
		$this->version = '1.0.0';
		$this->author = 'SLiCK-303';
		$this->tab = 'pricing_promotion';
		$this->need_instance = 0;

		$this->bootstrap = true;
		parent::__construct();
		
		$this->displayName = $this->l('Birthday Gift');
		$this->description = $this->l('Offer your clients a birthday gift automatically');

		$this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

		$secure_key = Configuration::get('BDAY_SECURITY_KEY');
		if($secure_key === false)
			Configuration::updateValue('BDAY_SECURITY_KEY', Tools::strtoupper(Tools::passwdGen(16)));
	}
		
	public function install()
	{
		if (!parent::install() ||
			!Configuration::updateValue('BDAY_GIFT_ACTIVE', 1) ||
			!Configuration::updateValue('BDAY_VOUCHER', 0) ||
			!Configuration::updateValue('BDAY_VOUCHER_TYPE', 2) ||
			!Configuration::updateValue('BDAY_VOUCHER_VALUE', 0) ||
			!Configuration::updateValue('BDAY_VOUCHER_PREFIX', 'BDAY') ||
			!Configuration::updateValue('BDAY_MINIMAL_ORDER', 0)
		) {
			return false;
		}
		return true;
	}

	public function uninstall()
	{
		if (!parent::uninstall() ||
			!Configuration::deleteByName('BDAY_SECURITY_KEY') ||
			!Configuration::deleteByName('BDAY_GIFT_ACTIVE') ||
			!Configuration::deleteByName('BDAY_VOUCHER') ||
			!Configuration::deleteByName('BDAY_VOUCHER_TYPE') ||
			!Configuration::deleteByName('BDAY_VOUCHER_VALUE') ||
			!Configuration::deleteByName('BDAY_VOUCHER_PREFIX') ||
			!Configuration::deleteByName('BDAY_MINIMAL_ORDER')
		) {
			return false;
		}

		return true;
	}

	public function getContent()
	{
		$output = '';
		$errors = [];
		if (Tools::isSubmit('submitBirthdayGift'))
		{
			$active = Tools::getValue('BDAY_GIFT_ACTIVE');
			if (!Validate::isInt($active) || $active < 0 || $active > 1)
				$errors[] = $this->l('The birthday gift module active is invalid. Please enter yes or no.');

			$voucher = Tools::getValue('BDAY_VOUCHER');
			if (!Validate::isInt($voucher) || $voucher < 0 || $voucher > 1)
				$errors[] = $this->l('The voucher active is invalid. Please enter yes or no.');

			$type = Tools::getValue('BDAY_VOUCHER_TYPE');
			if (!Validate::isInt($type) || $type < 1 || $type > 2)
				$errors[] = $this->l('The voucher type is invalid. Please choose an existing voucher type.');

			$value = Tools::getValue('BDAY_VOUCHER_VALUE');
			if (!Validate::isFloat($value) || $value < 0)
				$errors[] = $this->l('The voucher value is invalid. Please enter a positive value.');

			$prefix = Tools::getValue('BDAY_VOUCHER_PREFIX');
			if (!Validate::isString($prefix) || empty($prefix))
				$errors[] = $this->l('The voucher prefix is invalid. Please enter a value.');

			$min = Tools::getValue('BDAY_MINIMAL_ORDER');
			if (!Validate::isFloat($min) || $min < 0)
				$errors[] = $this->l('The minimal order amount is invalid. Please enter a positive number.');

			if (isset($errors) && count($errors))
				$output = $this->displayError(implode('<br>', $errors));
			else
			{
				Configuration::updateValue('BDAY_GIFT_ACTIVE', (int)$active);
				Configuration::updateValue('BDAY_VOUCHER', (int)$voucher);
				Configuration::updateValue('BDAY_VOUCHER_TYPE', (int)$type);
				Configuration::updateValue('BDAY_VOUCHER_VALUE', (float)$value);
				Configuration::updateValue('BDAY_VOUCHER_PREFIX', (string)$prefix);
				Configuration::updateValue('BDAY_MINIMAL_ORDER', (float)$min);
				$output = $this->displayConfirmation($this->l('Settings updated.'));
			}
		}

		return $output.$this->renderForm();
	}

	private function createDiscount($id_customer)
	{
		$voucher_prefix = (string) Configuration::get('BDAY_VOUCHER_PREFIX');
		$voucher_type = (int) Configuration::get('BDAY_VOUCHER_TYPE');
		$voucher_amount = (float) Configuration::get('BDAY_VOUCHER_VALUE');
		$min_order = (float) Configuration::get('BDAY_MINIMAL_ORDER');

		$cart_rule = new CartRule();

		if ($voucher_type == 1)
			$cart_rule->reduction_percent = $voucher_amount;
		else
			$cart_rule->reduction_amount = $voucher_amount;

		$cart_rule->id_customer = (int)$id_customer;
		$cart_rule->date_to = date('Y-m-d H:i:s', time() + 31536000); // + 1 year
		$cart_rule->date_from = date('Y-m-d H:i:s');
		$cart_rule->quantity = 1;
		$cart_rule->quantity_per_user = 1;
		$cart_rule->highlight = 1;
		$cart_rule->minimum_amount = $min_order;

		$languages = Language::getLanguages(true);
		foreach ($languages as $language)
			$cart_rule->name[(int)$language['id_lang']] = $this->l('Birthday present');

		$cart_rule->code = $voucher_prefix.'-'.Tools::strtoupper(Tools::passwdGen(6));
		$cart_rule->active = 1;
		if (!$cart_rule->add())
			return false;

		return $cart_rule;
	}

	public function createTodaysVouchers($count = false)
	{
		$emailsSent = 0;
		$shop_email = (string) Configuration::get('PS_SHOP_EMAIL');
		$shop_name = (string) Configuration::get('PS_SHOP_NAME');
		$voucher_active = (int) Configuration::get('BDAY_VOUCHER');
		$voucher_prefix = (string) Configuration::get('BDAY_VOUCHER_PREFIX');
		$voucher_type = (int) Configuration::get('BDAY_VOUCHER_TYPE');
		$voucher_amount = (float) Configuration::get('BDAY_VOUCHER_VALUE');
		$min_order = (float) Configuration::get('BDAY_MINIMAL_ORDER');

		$users = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('
		SELECT DISTINCT c.id_customer, firstname, lastname, email
		FROM '._DB_PREFIX_.'customer c
		LEFT JOIN '._DB_PREFIX_.'orders o ON (c.id_customer = o.id_customer)
		WHERE o.valid = 1
		AND c.birthday LIKE \'%'.date('-m-d').'\'');

		if ($count || !count($users))
			return count($users);

		foreach ($users as $user)
		{
			if ($voucher_active == 1)
				$this->createDiscount($user['id_customer']);

			$template_vars = [
				'{firstname}' => $user['firstname'],
				'{lastname}'  => $user['lastname']
			];

			if (Validate::isEmail($user['email'])) {
				Mail::Send(
						(int)Configuration::get('PS_LANG_DEFAULT'),
						'birthday',
						sprintf(Mail::l('Happy Birthday', (int)Configuration::get('PS_LANG_DEFAULT'))),
						$template_vars,
						($user['email'] ? $user['email'] : null),
						($user['firstname'] ? $user['firstname'].' '.$user['lastname'] : null),
						$shop_email,
						$shop_name,
						null,
						null,
						dirname(__FILE__).'/mails/');

				++$emailsSent;

			} else
				echo Db::getInstance()->getMsgError();

			// log emailing results : 
			if( $emailsSent > 0 ) {
				PrestaShopLogger::addLog($emailsSent . ' emails sent from birthdaygift module');
			}
		}
	}

	public function renderForm()
	{
		$nu = $this->createTodaysVouchers(true);

		$cron_info = '';
		if (Shop::getContext() === Shop::CONTEXT_SHOP)
			$cron_info = $this->l('Define the settings and paste the following URL in the crontab, or call it manually on a daily basis:').						'<br><b>'.$this->context->shop->getBaseURL().'modules/birthdaygift/cron.php?secure_key='.Configuration::get('BDAY_SECURE_KEY').'</b>';

		$fields_form_1 = [
			'form' => [
				'legend' => [
					'title' => $this->l('Information'),
					'icon' => 'icon-cogs',
				],
				'description' => $cron_info,
			]
		];

		$fields_form_2 = [
			'form' => [
				'legend' => [
					'title' => $this->l('E-Mails to send'),
					'icon' => 'icon-cogs',
				],
				'description' => sprintf($this->l('Todays process will send %d e-mail(s).'), $nu),
			]
		];

		$fields_form_3 = [
			'form' => [
				'legend' => [
					'title' => $this->l('Settings'),
					'icon' => 'icon-cogs'
				],
				'input' => [
                    [
                        'type'    => 'switch',
                        'label'   => $this->l('Send birthday message'),
                        'name'    => 'BDAY_GIFT_ACTIVE',
                        'desc'    => $this->l('Activate sending of birthday message'),
                        'is_bool' => true,
                        'values'  => [
                            [
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
					[
						'type' => 'radio',
						'label' => $this->l('Include voucher'),
						'name' => 'BDAY_VOUCHER',
						'is_bool' => true,
						'values' => [
							[
								'id' => 'include_voucher_enable',
								'value' => 1,
								'checked' => 'checked',
								'label' => $this->l('Enabled')
							],
							[
								'id' => 'include_voucher_disable',
								'value' => 0,
								'label' => $this->l('Disabled')
							],
						],
					],
					[
						'type' => 'text',
						'label' => $this->l('Voucher prefix: '),
						'name' => 'BDAY_VOUCHER_PREFIX',
						'desc' => $this->l('Prefix for the voucher')
					],
                    [
                        'type'   => 'radio',
                        'label'  => $this->l('Voucher type: '),
                        'name'   => 'BDAY_VOUCHER_TYPE',
                        'values' => [
                            [
                                'id'    => 'discount_type1',
                                'value' => 1,
                                'label' => $this->l('Voucher offering a percentage'),
                            ],
                            [
                                'id'    => 'discount_type2',
                                'value' => 2,
                                'label' => $this->l('Voucher offering a fixed amount (by currency)'),
                            ],
                        ],
                    ],
					[
						'type' => 'text',
						'label' => $this->l('Voucher value: '),
						'name' => 'BDAY_VOUCHER_VALUE',
						'desc' => $this->l('Fixed or Percentage value')
					],
					[
						'type' => 'text',
						'label' => $this->l('Minimal Order: '),
						'name' => 'BDAY_MINIMAL_ORDER',
						'desc' => $this->l('The minimum order amount needed to use the voucher')
					],
				],
				'submit' => [
					'title' => $this->l('Save'),
				],
			],
		];

		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$this->fields_form = [];
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'submitBirthdayGift';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = [
			'fields_value' => $this->getConfigFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		];

		return $helper->generateForm([$fields_form_1, $fields_form_2, $fields_form_3]);
	}

	public function getConfigFieldsValues()
	{
		return [
			'BDAY_GIFT_ACTIVE' => Tools::getValue('BDAY_GIFT_ACTIVE', (int)Configuration::get('BDAY_GIFT_ACTIVE')),
			'BDAY_VOUCHER' => Tools::getValue('BDAY_VOUCHER', (int)Configuration::get('BDAY_VOUCHER')),
			'BDAY_VOUCHER_TYPE' => Tools::getValue('BDAY_VOUCHER_TYPE', (int)Configuration::get('BDAY_VOUCHER_TYPE')),
			'BDAY_VOUCHER_VALUE' => Tools::getValue('BDAY_VOUCHER_VALUE', (float)Configuration::get('BDAY_VOUCHER_VALUE')),
			'BDAY_VOUCHER_PREFIX' => Tools::getValue('BDAY_VOUCHER_PREFIX', (string)Configuration::get('BDAY_VOUCHER_PREFIX')),
			'BDAY_MINIMAL_ORDER' => Tools::getValue('BDAY_MINIMAL_ORDER', (float)Configuration::get('BDAY_MINIMAL_ORDER')),
		];
	}}

