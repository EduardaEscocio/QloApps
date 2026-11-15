<?php

interface Core_Business_Payment_PaymentStrategy
{
    /**
     * Processa o pagamento para o carrinho fornecido.
     * 
     * @param Cart $cart O objeto do carrinho de compras
     * @param float $amount O valor a ser cobrado
     * @return mixed Resultado do processamento
     */
    public function processPayment($cart, $amount);
}