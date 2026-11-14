<?php
/**
 * 2007-2017 PrestaShop
 * (Cabeçalho de licença mantido)
 */

class Core_Business_Payment_PaymentOption
{
    private $callToActionText;
    private $logo;
    private $action;
    private $method;
    private $inputs;
    private $form;
    private $moduleName;

    /** 
     * NOVA PROPRIEDADE: Armazena a estratégia de pagamento 
     * @var Core_Business_Payment_PaymentStrategy|null 
     */
    private $paymentStrategy;

    public function getCallToActionText()
    {
        return $this->callToActionText;
    }

    public function setCallToActionText($callToActionText)
    {
        $this->callToActionText = $callToActionText;
        return $this;
    }

    public function getLogo()
    {
        return $this->logo;
    }

    public function setLogo($logo)
    {
        $this->logo = $logo;
        return $this;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function setAction($action)
    {
        $this->action = $action;
        return $this;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
    }

    public function getInputs()
    {
        return $this->inputs;
    }

    public function setInputs($inputs)
    {
        $this->inputs = $inputs;
        return $this;
    }

    public function getForm()
    {
        return $this->form;
    }

    public function setForm($form)
    {
        $this->form = $form;
        return $this;
    }

    public function getModuleName()
    {
        return $this->moduleName;
    }

    /**
     * MODIFICADO: Agora também carrega a estratégia (a "cápsula")
     */
    public function setModuleName($moduleName)
    {
        $this->moduleName = $moduleName;
        
        // A Factory avalia o nome do módulo e injeta a classe correspondente
        $this->paymentStrategy = Core_Business_Payment_PaymentStrategyFactory::createStrategy($moduleName);
        
        return $this;
    }

    /**
     * NOVO MÉTODO: O "botão" que executa o pagamento da estratégia isolada
     */
    public function processOptionPayment($cart, $amount)
    {
        if (!$this->paymentStrategy instanceof Core_Business_Payment_PaymentStrategy) {
            throw new Exception("Estratégia de pagamento não implementada para o módulo: " . $this->moduleName);
        }
        
        return $this->paymentStrategy->processPayment($cart, $amount);
    }

    /**
     * Método legado mantido intacto para não quebrar módulos antigos
     */
    public static function convertLegacyOption(array $legacyOption)
    {
        if (!$legacyOption) {
            return;
        }

        if (array_key_exists('cta_text', $legacyOption)) {
            $legacyOption = array($legacyOption);
        }

        $newOptions = array();

        $defaults = array(
            'action' => null,
            'form' => null,
            'method' => null,
            'inputs' => array(),
            'logo' => null
        );

        foreach ($legacyOption as $option) {
            $option = array_merge($defaults, $option);

            $newOption = new Core_Business_Payment_PaymentOption();
            $newOption->setCallToActionText($option['cta_text'])
                      ->setAction($option['action'])
                      ->setForm($option['form'])
                      ->setInputs($option['inputs'])
                      ->setLogo($option['logo'])
                      ->setMethod($option['method']);
                      
            // Garante que se o legacyOption tiver o nome do módulo, ele já carrega a estratégia
            if (isset($option['module_name'])) {
                $newOption->setModuleName($option['module_name']);
            }

            $newOptions[] = $newOption;
        }

        return $newOptions;
    }
}