<?php

/**
 * Copyright (C) 2023 Emanuel Schiendorfer
 *
 * @author    Emanuel Schiendorfer <https://github.com/eschiendorfer>
 * @copyright 2023 Emanuel Schiendorfer
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_'))
	exit;

class Genzo_Turnstile extends Module
{
    public $errors;

    private $turnstile_site_key;
    private $turnstile_secret_key;
    private $turnstile_if_logged;

	function __construct() {
		$this->name = 'genzo_turnstile';
		$this->tab = 'front_office_features';
		$this->version = '1.0.0';
		$this->author = 'Emanuel Schiendorfer';
		$this->need_instance = 0;

		$this->bootstrap = true;

	 	parent::__construct();

		$this->displayName = $this->l('Cloudflare Turnstile');
		$this->description = $this->l('Secure your forms from spam');
		$this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        // Set global values
        $this->turnstile_site_key = Configuration::get('GENZO_TURNSTILE_SITE_KEY');
        $this->turnstile_secret_key = Configuration::get('GENZO_TURNSTILE_SECRET_KEY');
        $this->turnstile_if_logged = Configuration::get('GENZO_TURNSTILE_IF_LOGGED');
	}

	public function install() {
		if (!parent::install() OR
            !$this->registerHook('actionFrontControllerSetMedia') OR
            !$this->registerHook('displayFooter')
        ) {
            return false;
        }

		return true;
	}


	// Backoffice
    public function getContent() {

        if (Tools::isSubmit('submitTurnstileSettings')) {
            if ($this->saveTurnstileSettings()) {
                $url = $this->context->link->getAdminLink('AdminModules', true, ['module_name' => $this->name, 'configure' => $this->name, 'conf' => 4]);
                Tools::redirectAdmin($url);
            }
        }

        return $this->renderSettingsForm();

    }

    private function renderSettingsForm() {
        $fieldsForm[0]['form'] = [
            'legend' => [
                'title' => $this->l('Cloudflare Turnstile Settings'),
                'icon'  => 'icon-cogs',
            ],
            'input'  => [
                [
                    'type'     => 'text',
                    'label'    => $this->l('Site key'),
                    'name'     => 'turnstile_site_key',
                    'size'     => 15,
                    'required' => true,
                ],
                [
                    'type'     => 'text',
                    'label'    => $this->l('Secret key'),
                    'name'     => 'turnstile_secret_key',
                    'size'     => 15,
                    'required' => true,
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Logged Customer'),
                    'desc'  => $this->l('Do you want to use the captcha also for logged in customers?'),
                    'name' => 'turnstile_if_logged',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                ]
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'name' => 'submitTurnstileSettings'
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->fields_value = [
            'turnstile_site_key' => Configuration::get('GENZO_TURNSTILE_SITE_KEY'),
            'turnstile_secret_key' => Configuration::get('GENZO_TURNSTILE_SECRET_KEY'),
            'turnstile_if_logged' => Configuration::get('GENZO_TURNSTILE_IF_LOGGED'),
        ];

        return $helper->generateForm($fieldsForm);
    }

    private function saveTurnstileSettings() {
        return Configuration::updateValue('GENZO_TURNSTILE_SITE_KEY', Tools::getValue('turnstile_site_key')) &&
            Configuration::updateValue('GENZO_TURNSTILE_SECRET_KEY', Tools::getValue('turnstile_secret_key')) &&
            Configuration::updateValue('GENZO_TURNSTILE_IF_LOGGED', Tools::getValue('turnstile_if_logged'));
    }

    //Hooks
    public function hookActionFrontControllerSetMedia() {

        if ($submitsToCheck = $this->checkIfControllerNeedsValidation()) {

            Media::addJsDef(['turnstileSiteKey' => $this->turnstile_site_key, 'submitsToCheck' => $submitsToCheck]);
            $this->context->controller->addJS($this->_path.'/views/js/genzo_turnstile.js');

            foreach ($submitsToCheck as $submitToCheck => $action) {
                if (Tools::isSubmit($submitToCheck)) {
                    if (!$this->validateFormSubmitToken()) {
                        unset($_POST[$submitToCheck]);
                        $this->context->controller->errors[] = $this->l('Captcha Validation failed');
                    }
                }
            }
        }

    }

    public function hookDisplayFooter() {
        return '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer nonce="strict-dynamic"></script>';
    }

    private function checkIfControllerNeedsValidation() {

        // Check if the customer is logged
        if (!$this->turnstile_if_logged && $this->context->customer->isLogged()) {
            return false;
        }

        // Check any controller
        if ($this->context->controller instanceof ContactController) {
            return ['submitMessage' => 'ContactForm'];
        }
        elseif ($this->context->controller instanceof AuthController) {
            return ['SubmitLogin' => 'Login'];
        }

        return false;
    }

    private function validateFormSubmitToken() {
        $data = [
            'secret'   => $this->turnstile_secret_key,
            'response' => Tools::getValue('cf-turnstile-response'),
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);

        $result = json_decode(curl_exec($ch), true);

        if (isset($result['success'])) {
            return (bool)$result['success'];
        }

        return false;
    }

}