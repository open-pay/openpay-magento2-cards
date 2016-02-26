<?php
/**
 * Copyright © 2015 Pay.nl All rights reserved.
 */

namespace OpenpayMagento\Cards\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;


class OpenpayConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string[]
     */
    protected $methodCodes = [
        'openpay_cards',        
    ];

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];

    /**
     * @param PaymentHelper $paymentHelper     
     */
    public function __construct(
        PaymentHelper $paymentHelper       
    ) {        
        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $paymentHelper->getMethodInstance($code);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $config = [];
        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $config['payment']['openpay_credentials'] = array("merchant_id" => "mylel40vq1oduhelfuct", "public_key" => "pk_b05ccb9c1b454794875f46eb975ea13b");                 
                $config['payment']['ccform']["availableTypes"][$code] = array("AE" => "American Express", "VI" => "Visa", "MC" => "MasterCard"); 
                $config['payment']['ccform']["hasVerification"][$code] = true;
                $config['payment']['ccform']["hasSsCardType"][$code] = false;
                $config['payment']['ccform']["months"][$code] = $this->getMonths();
                $config['payment']['ccform']["years"][$code] = $this->getYears();
                $config['payment']['ccform']["cvvImageUrl"][$code] = "http://".$_SERVER['SERVER_NAME']."/pub/static/frontend/Magento/luma/es_MX/Magento_Checkout/cvv.png";
                $config['payment']['ccform']["ssStartYears"][$code] = $this->getStartYears();
            }
        }
                
        return $config;
    }
    
    public function getMonths(){
        return array(
            "1" => "01 - Enero",
            "2" => "02 - Febrero",
            "3" => "03 - Marzo",
            "4" => "04 - Abril",
            "5" => "05 - Mayo",
            "6" => "06 - Junio",
            "7" => "07 - Julio",
            "8" => "08 - Agosto",
            "9" => "09 - Septiembre",
            "10 "=> "10 - Octubre",
            "11"=> "11 - Noviembre",
            "12"=> "12 - Diciembre"
        );
    }
    
    public function getYears(){
        $years = array();
        for($i=1; $i<=10; $i++){
            $year = (string)($i+date('Y'));
            $years[$year] = $year;
        }
        return $years;
    }
    
    public function getStartYears(){
        $years = array();
        for($i=5; $i>=0; $i--){
            $year = (string)(date('Y')-$i);
            $years[$year] = $year;
        }
        return $years;
    }
    
}
