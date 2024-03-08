<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

/**
 * This Controller simulate an external payment gateway
 */
class EfipayPaymentExternalModuleFrontController extends ModuleFrontController
{
    private $bearerToken;
    private $idComercio;
    private $urlBase;

    public function __construct()
    {
        parent::__construct();

        $this->bearerToken = Configuration::get(EfipayPayment::CONFIG_API_KEY);
        $this->idComercio = Configuration::get(EfipayPayment::CONFIG_ID_COMERCIO);
        $this->urlBase = "https://efipay-sag.redpagos.co/api/v1/";
    }

    /**
     * {@inheritdoc}
     */
    public function postProcess()
    {
        // if (false === $this->checkIfContextIsValid() || false === $this->checkIfPaymentOptionIsAvailable()) {
        //     Tools::redirect($this->context->link->getPageLink(
        //         'order',
        //         true,
        //         (int) $this->context->language->id,
        //         [
        //             'step' => 1,
        //         ]
        //     ));
        // }
    }

    /**
     * {@inheritdoc}
     */
    public function initContent()
    {
        parent::initContent();

        $this->context->smarty->assign([
            // 'action' => $this->context->link->getModuleLink($this->module->name, 'paymentFail', ['error' => 'No se pudo realizar el pago, intente nuevamente'], true),
            'error' => 'No se pudo realizar el pago, intente nuevamente',
        ]);

        $this->setTemplate('module:efipaypayment/views/templates/front/paymentFail.tpl');
    }
}
