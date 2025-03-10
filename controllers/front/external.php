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
    private $limitPayment;

    public function __construct()
    {
        parent::__construct();

        $this->bearerToken = Configuration::get(EfipayPayment::CONFIG_API_KEY);
        $this->idComercio = Configuration::get(EfipayPayment::CONFIG_ID_COMERCIO);
        $this->limitPayment = Configuration::get(EfipayPayment::CONFIG_LIMIT_PAYMENT);
    
        $this->urlBase = "https://sag.efipay.co/api/v1/";
    }

    /**
     * {@inheritdoc}
     */
    public function postProcess()
    {
        if (false === $this->checkIfContextIsValid() || false === $this->checkIfPaymentOptionIsAvailable()) {
            Tools::redirect($this->context->link->getPageLink(
                'order',
                true,
                (int) $this->context->language->id,
                [
                    'step' => 1,
                ]
            ));
        }

        // Obtener el monto total a cobrar
        $cart = $this->context->cart;
        $totalAmount = $cart->getOrderTotal();

        // Obtener el tipo de moneda utilizada en el carrito
        $currency = new Currency($cart->id_currency);
        $currencyCode = $currency->iso_code;

        if(!Configuration::get(EfipayPayment::CONFIG_API_KEY) || !Configuration::get(EfipayPayment::CONFIG_ID_COMERCIO) || !Configuration::get(EfipayPayment::CONFIG_TOKEN_WEBHOOK)) {
            $this->context->cookie->payment_error = "Oops, algo salio mal. Por favor comunicate con el administrador.";
            Tools::redirect('order');   
        }

        // crear la orden con el estado pendiente de pago
        $this->module->validateOrder(
            (int) $this->context->cart->id,
            (int) Configuration::get('PS_OS_BANKWIRE'),
            $totalAmount,
            $this->module->displayName, // Nombre del método de pago
            null,
            ['transaction_id' => Tools::passwdGen()], // Información adicional
            $cart->id_currency,
            true,
            $this->context->customer->secure_key
        );

        $db = Db::getInstance();
        $cartId = (int)$this->context->cart->id;
        // Realiza una consulta SQL para obtener el ID de la orden asociada al carrito
        $sql = "SELECT id_order FROM " . _DB_PREFIX_ . "orders WHERE id_cart = $cartId";
        // Ejecuta la consulta SQL
        $orderId = $db->getValue($sql);

        $customer = new Customer($cart->id_customer);
       
        $data = [
            "payment" => [
                "description" => 'Pago del pedido Prestashop: '.$orderId,
                "amount" => $totalAmount,
                "currency_type" => $currencyCode,
                "checkout_type" => "redirect"
            ],
            "advanced_options" => [
                "references" => [
                    (string)$orderId,
                    $customer->email,
                    "Plugin Prestashop"
                ],
                "result_urls" => [
                    "approved" => $this->context->link->getModuleLink($this->module->name, 'responseSuccess', ['orderId' => $orderId], true),
                    "rejected" => $this->context->link->getModuleLink($this->module->name, 'responseError', ['orderId' => $orderId], true),
                    "pending" => $this->context->link->getModuleLink($this->module->name, 'responsePending', ['orderId' => $orderId], true),
                    "webhook" => $this->context->link->getModuleLink($this->module->name, 'webhook', [], true),
                ],
                "has_comments" => false,
            ],
            "office" => $this->idComercio
        ];

        if($this->limitPayment) {
            $data['advanced_options']['limit_date'] = date('Y-m-d', strtotime('+1 day'));
        }
            
        $headers = [
            "Accept" => "application/json",
            "Authorization" => "Bearer {$this->bearerToken}"
        ];
        
        $client = new GuzzleHttp\Client();
        
        try {
            // Realizar la solicitud POST utilizando Guzzle
            $response = $client->post($this->urlBase. 'payment/generate-payment', [
                'headers' => $headers,
                'json' => $data,
                'http_errors' => false // Para manejar manualmente los errores HTTP
            ]);
        
            // Obtener el cuerpo de la respuesta
            $body = $response->getBody()->getContents();
        
            // Verificar si hubo algún error
            if ($response->getStatusCode() != 200) {
                echo 'Error: ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase();
            } else {
                // Parsear la respuesta JSON para obtener la URL de redirección
                $responseData = json_decode($body, true);
                $redirectUrl = $responseData['url'] ?? null;
                
                if ($redirectUrl) {
                    Tools::redirect($redirectUrl);
                } else {
                    echo "No se encontró la URL de redirección en la respuesta.";
                }
            }
        } catch (GuzzleHttp\Exception\RequestException $e) {
            echo 'Error: ' . $e->getCode() . ' ' . $e->getMessage();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function initContent()
    {
        parent::initContent();
    }

    /**
     * Check if the context is valid
     *
     * @return bool
     */
    private function checkIfContextIsValid()
    {
        return true === Validate::isLoadedObject($this->context->cart)
            && true === Validate::isUnsignedInt($this->context->cart->id_customer)
            && true === Validate::isUnsignedInt($this->context->cart->id_address_delivery)
            && true === Validate::isUnsignedInt($this->context->cart->id_address_invoice);
    }

    /**
     * Check that this payment option is still available in case the customer changed
     * his address just before the end of the checkout process
     *
     * @return bool
     */
    private function checkIfPaymentOptionIsAvailable()
    {
        if (!Configuration::get(EfipayPayment::CONFIG_PO_EXTERNAL_ENABLED)) {
            return false;
        }

        $modules = Module::getPaymentModules();

        if (empty($modules)) {
            return false;
        }

        foreach ($modules as $module) {
            if (isset($module['name']) && $this->module->name === $module['name']) {
                return true;
            }
        }

        return false;
    }
}
