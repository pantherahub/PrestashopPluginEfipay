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
 * This Controller receive customer after approval on bank payment page
 */
class EfipayPaymentEmbeddedModuleFrontController extends ModuleFrontController
{
    /**
     * @var PaymentModule
     */
    public $module;

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

        // Obtener el monto y la moneda a pagar
        $totalAmount = (float) $this->context->cart->getOrderTotal();
        $currencyId = (int) $this->context->currency->id;

        // Enviar la solicitud a la pasarela de pagos
        $paymentResponse = $this->generatePayment();
        
        // Manejar la respuesta de la pasarela de pagos
        if ($paymentResponse['status'] == 'Aprobada') {
            $this->module->validateOrder(
                (int) $this->context->cart->id,
                (int) Configuration::get('PS_OS_PAYMENT'),
                $totalAmount,
                $this->module->displayName, // Nombre del método de pago
                null,
                ['transaction_id' => $paymentResponse['paymentId']], // Información adicional
                $currencyId,
                true,
                $this->context->customer->secure_key
            );
    
            Tools::redirect($this->context->link->getPageLink('order-confirmation', true, (int) $this->context->language->id,
                [
                    'id_cart' => (int) $this->context->cart->id,
                    'id_module' => (int) $this->module->id,
                    'id_order' => (int) $this->module->currentOrder,
                    'key' => $this->context->customer->secure_key,
                ]
            ));
        } else {
            $this->context->smarty->assign([
                'errorMessage' => 'No se pudo realizar el pago, intente nuevamente',
                'moduleName' => $this->module->name,
                'transactionsLink' => $this->context->link->getPageLink(
                    'order',
                    true,
                    (int) $this->context->language->id,
                    [
                        'step' => 1,
                    ]
                ),
            ]);
    
            $this->setTemplate('module:efipaypayment/views/templates/front/paymentFail.tpl');
        }
    }


    private function generatePayment()
    {
        // Obtener el monto total a cobrar
        $cart = $this->context->cart;
        $totalAmount = $cart->getOrderTotal();

        // Obtener el tipo de moneda utilizada en el carrito
        $currency = new Currency($cart->id_currency);
        $currencyCode = $currency->iso_code;

        $data = [
            "payment" => [
                "description" => 'Pago Plugin Prestashop',
                "amount" => $totalAmount,
                "currency_type" => $currencyCode,
                "checkout_type" => "api"
            ],
            "advanced_options" => [
                "result_urls" => [
                    "approved" => "https://google.com/",
                    "rejected" => "https://google.com/",
                    "pending" => "https://google.com/",
                ],
                "has_comments" => true,
                "comments_label" => "Aqui tu comentario"
            ],
            "office" => $this->idComercio
        ];

        $headers = [
            'Content-Type' => 'application/json', // Ejemplo de encabezado
            "Authorization" => "Bearer {$this->bearerToken}" // Ejemplo de encabezado con un token de autorización
        ];
        
        $client = new GuzzleHttp\Client();
        
        try {
            // Realizar la solicitud POST utilizando Guzzle
            $response = $client->post($this->urlBase.'payment/generate-payment', [
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
                $responseTransaction = $this->sendPaymentRequest($responseData);
                return $responseTransaction;
            }
        } catch (GuzzleHttp\Exception\RequestException $e) {
            echo 'Error: ' . $e->getCode() . ' ' . $e->getMessage();
        }
    }

    private function sendPaymentRequest($generatePaymentResponse)
    {
        // Obtener el carrito de compra y el cliente
        $cart = $this->context->cart;
        $customer = new Customer($cart->id_customer);
        $customerFullName = $customer->firstname.' '.$customer->lastname;

        $data = [
            "payment" => [
                "id" => $generatePaymentResponse['payment_id'],
                "token" => $generatePaymentResponse['token']
            ],
            "customer_payer" => [
                "name" => $customerFullName,
                "email" => $customer->email
            ],
            "payment_card" => [
                "number" => Tools::getValue('cardNumber'),
                "name" => Tools::getValue('cardHolder'),
                "expiration_date" => Tools::getValue('cardExpiration'),
                "cvv" => Tools::getValue('cardCVC'),
                "identification_type" => Tools::getValue('identificationType'),
                "id_number" => Tools::getValue('idNumber'),
                "installments" => Tools::getValue('installments'),
                "dialling_code" => Tools::getValue('diallingCode'),
                "cellphone" => Tools::getValue('cellphone')
            ] 
        ];
        
        $headers = [
            'Content-Type' => 'application/json', // Ejemplo de encabezado
            "Authorization" => "Bearer {$this->bearerToken}" // Ejemplo de encabezado con un token de autorización
        ];
        
        $client = new GuzzleHttp\Client();
        
        try {
            // Realizar la solicitud POST utilizando Guzzle
            $response = $client->post($this->urlBase.'payment/transaction-checkout', [
                'headers' => $headers,
                'json' => $data,
            ]);
        
            // Obtener el cuerpo de la respuesta
            $body = $response->getBody();
            
            // Verificar si hubo algún error
            if ($response->getStatusCode() != 200) {
                return [
                    'status' => 'rechazada'
                ];
            } else {
                $responseData = json_decode($body, true);
        
                return [
                    'paymentId' => $generatePaymentResponse['payment_id'],
                    'status' => isset($responseData['transaction']['status']) ? $responseData['transaction']['status'] : 'rechazada'
                ];
            }
        } catch (GuzzleHttp\Exception\RequestException $e) {
            echo 'Error: ' . $e->getCode() . ' ' . $e->getMessage();
        }
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
