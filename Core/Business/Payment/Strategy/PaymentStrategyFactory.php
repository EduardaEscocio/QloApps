<?php

class Core_Business_Payment_PaymentStrategyFactory
{
    public static function createStrategy($moduleName)
    {
        $moduleName = strtolower($moduleName);

        switch ($moduleName) {
            case 'stripe':
                return new Core_Business_Payment_Strategy_StripePaymentStrategy();
            
            case 'pix':
                // Se o nome do seu módulo PIX no QloApps for diferente, altere aqui
                return new Core_Business_Payment_Strategy_PixPaymentStrategy();
            
            case 'bankwire':
            case 'cheque':
            case 'offline':
                return new Core_Business_Payment_Strategy_OfflinePaymentStrategy();
            
            default:
                // Se for um módulo que ainda não tem estratégia, podemos retornar nulo 
                // ou criar uma estratégia padrão genérica.
                return null;
        }
    }
}