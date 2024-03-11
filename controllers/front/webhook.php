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

class EfipayPaymentWebhookModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        // Obtener los datos enviados por la pasarela de pago
        
        // Verificar si la solicitud se realizó utilizando el método POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            var_dump($data['transaction']['status'], $_REQUEST, $_POST);
        }
        

        // Procesar los datos y actualizar la orden en PrestaShop
        if ($this->module->processWebhookData($data)) {
            // Enviar una respuesta exitosa
            http_response_code(200);
            echo "Webhook procesado correctamente";
        } else {
            // Enviar una respuesta de error
            http_response_code(400);
            echo "Error al procesar el webhook";
        }
    }
}
