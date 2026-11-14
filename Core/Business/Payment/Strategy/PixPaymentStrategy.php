<?php

class Core_Business_Payment_Strategy_PixPaymentStrategy implements Core_Business_Payment_PaymentStrategy
{
    public function processPayment($cart, $amount)
    {
        // Aqui vai a lógica de geração do Payload e QR Code PIX
        return array(
            'status' => 'pending',
            'message' => 'QR Code PIX gerado',
            'qr_code' => '00020101021126580014br.gov.bcb.pix...'
        );
    }
}