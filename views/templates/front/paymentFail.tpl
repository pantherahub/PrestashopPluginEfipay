{extends "$layout"}

{block name='content'}
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                {if isset($errorMessage)}
                    <div class="alert alert-danger">
                        {$errorMessage}
                    </div>

                    {if $customer.is_logged && !$customer.is_guest}
                        <div class="w-100 mb-2">
                            <button class="mx-auto btn btn-primary">
                                <a class="text-white" href="{$transactionsLink}">{l s='Ir a carrito de compra.' mod='efipaypayment'}</a>
                            </button>
                        </div>                      
                    {/if}
                {/if}
            </div>
        </div>
    </div>
{/block}
