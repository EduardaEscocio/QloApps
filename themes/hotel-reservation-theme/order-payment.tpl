{* ... (Cabeçalho de licença original mantido) ... *}

{if !$opc}
	{addJsDefL name=txtProduct}{l s='product' js=1}{/addJsDefL}
	{addJsDefL name=txtProducts}{l s='products' js=1}{/addJsDefL}
	{capture name=path}{l s='Your payment method'}{/capture}
	<h1 class="page-heading">{l s='Please choose your payment method'}
		{if !isset($empty) && !$PS_CATALOG_MODE}
			<span class="heading-counter">{l s='Your shopping cart contains:'}
				<span id="summary_products_quantity">{$productNumber} {if $productNumber == 1}{l s='product'}{else}{l s='products'}{/if}</span>
			</span>
		{/if}
	</h1>
{/if}

{if !$opc}
	{assign var='current_step' value='payment'}
	{block name='order_steps'}
		{include file="$tpl_dir./order-steps.tpl"}
	{/block}
	{block name='errors'}
		{include file="$tpl_dir./errors.tpl"}
	{/block}
{else}
	<div id="opc_payment_methods" class="opc-main-block">
		<div id="opc_payment_methods-overlay" class="opc-overlay" style="display: none;"></div>
{/if}

{if $advanced_payment_api}
	{block name='order_payment_advanced'}
		{include file="$tpl_dir./order-payment-advanced.tpl"}
	{/block}
{else}
	{block name='order_payment_classic'}
		<!-- NOSSO NOVO HTML DE PAGAMENTO COMEÇA AQUI -->
		<div class="box">
			<h3 class="page-subheading">Escolha a forma de pagamento:</h3>
			
			<div style="margin: 20px 0; font-size: 16px; line-height: 2;">
				<label style="cursor: pointer;">
					<input type="radio" name="payment_method" value="stripe" id="pay-stripe"> Cartão de Crédito
				</label><br>
				<label style="cursor: pointer;">
					<input type="radio" name="payment_method" value="pix" id="pay-pix"> PIX
				</label><br>
				<label style="cursor: pointer;">
					<input type="radio" name="payment_method" value="offline" id="pay-offline"> Pagar na Recepção
				</label>
			</div>

			<p class="cart_navigation clearfix">
				<button id="btn-confirm-payment" class="button btn btn-default button-medium">
					<span>Confirmar Pedido <i class="icon-chevron-right right"></i></span>
				</button>
			</p>
		</div>
		<!-- NOSSO NOVO HTML DE PAGAMENTO TERMINA AQUI -->
	{/block}
{/if}

<script>
document.addEventListener('DOMContentLoaded', function() {
    const confirmButton = document.querySelector('#btn-confirm-payment');

    if (confirmButton) {
        confirmButton.addEventListener('click', function(e) {
            e.preventDefault(); 

            const selectedPayment = document.querySelector('input[name="payment_method"]:checked');

            if (!selectedPayment) {
                alert('Por favor, selecione um método de pagamento.');
                return;
            }

            const moduleName = selectedPayment.value; 
            
            // CORREÇÃO: Forçamos o index.php e passamos o módulo diretamente na URL para evitar perda de dados em redirecionamentos
            const paymentUrl = baseUri + 'index.php?controller=PaymentProcessor&ajax=true&payment_module=' + moduleName;

            confirmButton.innerText = 'Processando...';
            confirmButton.disabled = true;

            fetch(paymentUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json'
                }
                // O body foi removido porque 'payment_module' já vai de forma segura na URL
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Resposta da Estratégia:', data.data);
                    
                    if (moduleName === 'pix') {
                        alert('Pagamento PIX gerado! QR Code: ' + data.data.qr_code);
                    } else {
                        alert('Sucesso: ' + data.message);
                        window.location.href = baseUri + 'index.php?controller=order-confirmation';
                    }
                } else {
                    alert('Erro: ' + data.message);
                    confirmButton.innerText = 'Tentar Novamente';
                    confirmButton.disabled = false;
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                alert('Ocorreu um erro de rede ao processar o pagamento.');
                confirmButton.innerText = 'Confirmar Pedido';
                confirmButton.disabled = false;
            });
        });
    }
});
</script>