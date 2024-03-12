{**
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
 *}

<form action="{$action}" method="POST" id="payment-form" class="form-horizontal">
    <input type="hidden" name="option" value="embedded">

    <div class="form-group">
        <label class="form-control-label" for="cardNumber">{l s='Número de la tarjeta' mod='efipaypayment'}</label>
        <input value="5249314023340339" type="number" name="cardNumber" id="cardNumber" class="form-control" autocomplete="cc-number" required>
    </div>

    <div class="form-group">
        <label class="form-control-label" for="cardHolder">{l s='Titular de la tarjeta' mod='efipaypayment'}</label>
        <input value="Stiven" type="text" name="cardHolder" id="cardHolder" class="form-control" placeholder="{l s='Full name' mod='efipaypayment'}" autocomplete="cc-name" required>
    </div>

    <div class="row">
        <div class="form-group col-xs-6">
            <label class="form-control-label" for="cardCVC">{l s='CVV' mod='efipaypayment'}</label>
            <input value="478" type="number" name="cardCVC" id="cardCVC" class="form-control" autocomplete="cc-csc" required>
        </div>

        <div class="form-group col-xs-6">
            <label class="form-control-label" for="cardExpiration">{l s='Fecha expiración' mod='efipaypayment'}</label>
            <input  type="month" name="cardExpiration" id="cardExpiration" class="form-control" placeholder="YYYY-MM" autocomplete="cc-exp" required>
        </div>
    </div>

    <div class="row">
        <div class="form-group col-xs-6">
            <label class="form-control-label" for="identificationType">{l s='Tipo de identificación' mod='efipaypayment'}</label>
            <select name="identificationType" id="identificationType" class="form-control" required>
                <option disabled selected>{l s='Seleccionar una opción' mod='efipaypayment'}</option>
                {foreach $identificationTypes as $type}
                    <option value="{$type.value}">{$type.label}</option>
                {/foreach}
            </select>
        </div>
        <div class="form-group col-xs-6">
            <label class="form-control-label" for="idNumber">{l s='Número de documento' mod='efipaypayment'}</label>
            <input value="1102934454" type="number" name="idNumber" id="idNumber" class="form-control" required>
        </div>
    </div>

    <div class="row">
        <div class="form-group col-xs-4">
            <label class="form-control-label" for="installments">{l s='Cantidad de cuotas' mod='efipaypayment'}</label>
            <select name="installments" id="installments" class="form-control" required>
                <option disabled selected>{l s='Selecciona una opción' mod='efipaypayment'}</option>
                {for $installments = 1 to 10 step 1}
                    <option value="{$installments}">{$installments}</option>
                {/for}
            </select>
        </div>

        <div class="form-group col-xs-4">
            <label class="form-control-label" for="diallingCode">{l s='Indicativo' mod='efipaypayment'}</label>
            <select name="diallingCode" id="diallingCode" class="form-control" required>
                <option disabled selected>{l s='Selecciona una opción' mod='efipaypayment'}</option>
                {foreach $countries as $country}
                    <option value="{$country.callingCode}">{$country.name}</option>
                {/foreach} 
            </select>
        </div>

        <div class="form-group col-xs-4">
            <label class="form-control-label" for="cellphone">{l s='Número de celular' mod='efipaypayment'}</label>
            <input value="3137994567" type="number" name="cellphone" id="cellphone" class="form-control" required>
        </div>
    </div>
</form>
