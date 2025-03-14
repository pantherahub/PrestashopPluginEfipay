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
    private $limitPayment;
    private $urlBase;
    private $headers;
    private \GuzzleHttp\Client $client;

    public function __construct()
    {
        parent::__construct();

        $this->bearerToken = Configuration::get(EfipayPayment::CONFIG_API_KEY);
        $this->idComercio = Configuration::get(EfipayPayment::CONFIG_ID_COMERCIO);
        $this->limitPayment = Configuration::get(EfipayPayment::CONFIG_LIMIT_PAYMENT);

        $this->urlBase = "https://sag.efipay.co/api/v1/";

        $this->headers = [
            "Content-Type" => "application/json",
            "Accept" => "application/json",
            "Authorization" => "Bearer {$this->bearerToken}"
        ];

        $this->client = new GuzzleHttp\Client();
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
                (int)$this->context->language->id,
                [
                    'step' => 1,
                ]
            ));
        }


        // prueba validación de campos
        $form_error = $this->validateForm();

        if (!empty($form_error)) {
            $this->context->cookie->payment_error = $form_error;
            Tools::redirect('order');
        }
        // ---------------------------------------------------------

        $paymentResponse = $this->generatePayment();
        if ($paymentResponse['status'] == 'Aprobada') {
            $cart = $this->context->cart;
            $totalAmount = $cart->getOrderTotal();

            // $this->module->validateOrder(
            //     (int)$this->context->cart->id,
            //     (int)Configuration::get('PS_OS_BANKWIRE'),
            //     $totalAmount,
            //     $this->module->displayName, // Nombre del método de pago
            //     null,
            //     ['transaction_id' => Tools::passwdGen()], // Información adicional
            //     $cart->id_currency,
            //     true,
            //     $this->context->customer->secure_key
            // );

            $db = Db::getInstance();
            $cartId = (int)$this->context->cart->id;
            // Realiza una consulta SQL para obtener el ID de la orden asociada al carrito
            $sql = "SELECT id_order FROM " . _DB_PREFIX_ . "orders WHERE id_cart = $cartId";
            // Ejecuta la consulta SQL
            $orderId = $db->getValue($sql);
            Tools::redirect($this->context->link->getModuleLink($this->module->name, 'responseSuccess', ['orderId' => $orderId], true));
        } else {
            $this->context->cookie->payment_error = 'La transaccion se rechazo intenta con otro metodo de pago';
            Tools::redirect('order');
        }
    }

    private function validateForm()
    {
        if (!preg_match('/^\d{16}$/', Tools::getValue('cardNumber'))) {
            return "El campo Número de tarjeta es obligatorio.";
        }

        if (empty(Tools::getValue('cardHolder')) || !Validate::isGenericName(Tools::getValue('cardHolder'))) {
            return "El campo nombre es obligatorio.";
        }

        if (empty(Tools::getValue('cardExpiration')) || !preg_match('/^\d{4}-\d{2}$/', Tools::getValue('cardExpiration'))) {
            return "El campo fecha de expiración es obligatorio.";
        }

        if (empty(Tools::getValue('cardCVC')) || !preg_match('/^\d{3}$/', Tools::getValue('cardCVC'))) {
            return "El campo codigo de seguridad es obligatorio y debe contener 3 digitos.";
        }

        if (empty(Tools::getValue('identificationType'))) {
            return "El campo tipo de identificación es obligatorio.";
        }

        if (empty(Tools::getValue('idNumber')) || !Validate::isInt(Tools::getValue('idNumber'))) {
            return "El campo numero de idemtificiación es obligatorio y deber ser un numero entero.";
        }

        if (empty(Tools::getValue('installments')) || !Validate::isInt(Tools::getValue('installments'))) {
            return "El campo cuotas es obligatorio.";
        }

        if (empty(Tools::getValue('diallingCode'))) {
            return "El campo indicativo es obligatorio.";
        }

        if (empty(Tools::getValue('cellphone')) || !Validate::isPhoneNumber(Tools::getValue('cellphone'))) {
            return "El campo numero celular es obligatorio y debe ser un numero valido.";
        }

        if(!Configuration::get(EfipayPayment::CONFIG_API_KEY) || !Configuration::get(EfipayPayment::CONFIG_ID_COMERCIO) || !Configuration::get(EfipayPayment::CONFIG_TOKEN_WEBHOOK)) {
            return "Oops, algo salio mal. Por favor comunicate con el administrador.";     
        }

        return '';
    }


    private function generatePayment()
    {
        // Obtener el monto total a cobrar
        $cart = $this->context->cart;
        $totalAmount = $cart->getOrderTotal();

        // Obtener el tipo de moneda utilizada en el carrito
        $currency = new Currency($cart->id_currency);
        $currencyCode = $currency->iso_code;

        $this->module->validateOrder(
            (int)$this->context->cart->id,
            (int)Configuration::get('PS_OS_BANKWIRE'),
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
                "checkout_type" => "api"
            ],
            "advanced_options" => [
                "references" => [
                    (string)$orderId,
                    $customer->email,
                    "Plugin Prestashop"
                ],
                "has_comments" => false,
            ],
            "office" => $this->idComercio
        ];

        if($this->limitPayment) {
            $data['advanced_options']['limit_date'] = date('Y-m-d', strtotime('+1 day'));
        }

        try {
            $response = $this->client->post($this->urlBase . 'payment/generate-payment', [
                'headers' => $this->headers,
                'json' => $data,
                'http_errors' => false
            ]);

            // Obtener el cuerpo de la respuesta
            $body = $response->getBody()->getContents();

            // Verificar si hubo algún error
            if ($response->getStatusCode() != 200) {
                PrestaShopLogger::addLog('Error: ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase(), 3);
                echo 'Error: ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase();
            } else {
                // Parsear la respuesta JSON para obtener la URL de redirección
                $responseData = json_decode($body, true);
                PrestaShopLogger::addLog('Payment request successful: ' . json_encode($responseData), 1);
                return $this->sendPaymentRequest($responseData);
            }
        } catch (GuzzleHttp\Exception\RequestException $e) {
            PrestaShopLogger::addLog('RequestException: ' . $e->getCode() . ' ' . $e->getMessage(), 3);
            echo 'Error: ' . $e->getCode() . ' ' . $e->getMessage();
        }
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function sendPaymentRequest($generatePaymentResponse)
    {
        // Obtener el carrito de compra y el cliente
        $cart = $this->context->cart;
        $customer = new Customer($cart->id_customer);
        $customerFullName = $customer->firstname . ' ' . $customer->lastname;
        $addresses = $customer->getAddresses($this->context->language->id);
        $address = $addresses[0];

        $country = new Country($address['id_country']);
        $iso_code = $country->iso_code;

        $address['country_iso_code'] = $this->getCountryIso3($iso_code);
        $data = [
            "payment" => [
                "id" => $generatePaymentResponse['payment_id'],
                "token" => $generatePaymentResponse['token']
            ],
            "customer_payer" => [
                "name" => $customerFullName,
                "email" => $customer->email,
                'address_1' => $address['address1'],
                'address_2' => isset($address['address2']) && !empty($address['address2']) ? $address['address2'] : 'calle 43',
                'city' => $address['city'],
                'state' => $address['state'] ?? $address['city'],
                'zip_code'  => $address['postcode'] ?? '00000',
                'country'  => $address['country_iso_code'] ?? 'COL',
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


        try {
            // Realizar la solicitud POST utilizando Guzzle
            $response = $this->client->post($this->urlBase . 'payment/transaction-checkout', [
                'headers' => $this->headers,
                'json' => $data,
            ]);

            // Obtener el cuerpo de la respuesta
            $body = $response->getBody();

            // Verificar si hubo algún error
            if ($response->getStatusCode() != 200) {
                PrestaShopLogger::addLog('Error: ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase(), 3);
                return [
                    'status' => 'rechazada'
                ];
            } else {
                $responseData = json_decode($body, true);
                PrestaShopLogger::addLog('Transaction checkout successful: ' . json_encode($responseData), 1);

                return [
                    'paymentId' => $generatePaymentResponse['payment_id'],
                    'status' => isset($responseData['transaction']['status']) ? $responseData['transaction']['status'] : 'rechazada'
                ];
            }
        } catch (GuzzleHttp\Exception\RequestException $e) {
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 422) {
                $responseBody = $e->getResponse()->getBody()->getContents();
                $errors = json_decode($responseBody, true);
        
                $this->context->cookie->payment_error = $errors["message"];
                Tools::redirect('order');
            }

            echo 'Error: ' . $e->getCode() . ' ' . $e->getMessage();
            PrestaShopLogger::addLog('RequestException: ' . $e->getCode() . ' ' . $e->getMessage(), 3);
        }
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getCountryIso3($code)
    {
        $response = $this->client->get($this->urlBase . 'resources/get-countries', [
            'headers' => $this->headers,
            'http_errors' => false
        ]);

        // Obtener el cuerpo de la respuesta
        $body = json_decode($response->getBody()->getContents(), true);
        return $body[$code]['iso3_code'];
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
    }
}