<?php
// 1. BLINDAGEM: Carregamento manual para contornar o Autoloader do QloApps
// O dirname(__FILE__) é controllers/front, então ../../ nos leva à raiz do QloApps
$corePath = dirname(__FILE__) . '/../../Core/Business/Payment/';

// Carrega o PaymentOption (que está na pasta Payment/)
if (file_exists($corePath . 'PaymentOption.php')) {
    require_once $corePath . 'PaymentOption.php';
} elseif (file_exists($corePath . 'Core_Business_Payment_PaymentOption.php')) {
    require_once $corePath . 'Core_Business_Payment_PaymentOption.php';
}

// Carrega a Interface e a Factory (que, conforme a imagem, estão dentro de Strategy/)
require_once $corePath . 'Strategy/PaymentStrategy.php';
require_once $corePath . 'Strategy/PaymentStrategyFactory.php';

// Carrega as Estratégias (também dentro de Strategy/)
require_once $corePath . 'Strategy/StripePaymentStrategy.php';
require_once $corePath . 'Strategy/PixPaymentStrategy.php';
require_once $corePath . 'Strategy/OfflinePaymentStrategy.php';

class PaymentProcessorControllerCore extends FrontController
// ... resto do código permanece igual

class PaymentProcessorControllerCore extends FrontController
{
    public $ssl = true;

    public function postProcess()
    {
        // 2. Recebe o nome do módulo selecionado (ex: stripe, pix)
        $moduleName = Tools::getValue('payment_module');

        if (!$moduleName) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => 'Nenhum método de pagamento foi selecionado.'
            ]));
        }

        // 3. Valida o carrinho e o cliente
        $cart = $this->context->cart;
        
        if (!$cart->id || $cart->id_customer == 0 || $cart->id_address_invoice == 0) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => 'Erro na validação do carrinho. Tente recarregar a página.'
            ]));
        }

        $amount = (float)$cart->getOrderTotal(true, Cart::BOTH);

        try {
            // 4. Executa a classe forçada
            $paymentOption = new Core_Business_Payment_PaymentOption();
            $paymentOption->setModuleName($moduleName);

            $result = $paymentOption->processOptionPayment($cart, $amount);

            // 5. Retorna o sucesso à interface!
            $this->ajaxDie(json_encode([
                'success' => true,
                'message' => 'Pagamento processado com sucesso!',
                'data' => $result
            ]));

        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
        }
    }
}