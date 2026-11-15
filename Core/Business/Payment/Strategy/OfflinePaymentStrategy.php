<?php

class Core_Business_Payment_Strategy_OfflinePaymentStrategy implements Core_Business_Payment_PaymentStrategy
{
    public function processPayment($cart, $amount)
    {
        // Lógica para pagamentos na recepção (dinheiro, transferência bancária padrão)
        return array(
            'status' => 'success',
            'message' => 'Pagamento offline registrado. Aguardando confirmação no balcão.'
        );
    }
}