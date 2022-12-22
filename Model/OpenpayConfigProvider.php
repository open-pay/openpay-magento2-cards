<?php
/**
 * Copyright © 2015 Pay.nl All rights reserved.
 */

namespace Openpay\Cards\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Openpay\Cards\Model\Payment as OpenpayPayment;
use Magento\Checkout\Model\Cart;

class OpenpayConfigProvider implements ConfigProviderInterface
{
    /**
     * @var \Openpay\Cards\Logger\Logger
     */
    protected $logger;

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
     * @var \Openpay\Cards\Model\Payment
     */
    protected $payment ;

    protected $cart;

    /**
     * @param \Openpay\Cards\Logger\Logger $logger
     * @param PaymentHelper $paymentHelper
     * @param OpenpayPayment $payment
     */
    public function __construct(
        \Openpay\Cards\Logger\Logger $logger,
        PaymentHelper $paymentHelper,
        OpenpayPayment $payment,
        Cart $cart) {
        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $paymentHelper->getMethodInstance($code);
        }
        $this->logger = $logger;
        $this->cart = $cart;
        $this->payment = $payment;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $config = [];
        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $protocol = $this->hostSecure() === true ? 'https://' : 'http://';

                $config['payment']['openpay_credentials'] = array("merchant_id" => $this->payment->getMerchantId(), "public_key" => $this->payment->getPublicKey(), "is_sandbox"  => $this->payment->isSandbox());
                $config['payment']['months_interest_free'] = $this->payment->getMonthsInterestFree();
                $config['payment']['installments'] = $this->payment->getInstallments();
                $config['payment']['use_card_points'] = $this->payment->useCardPoints();
                $config['payment']['total'] = $this->cart->getQuote()->getGrandTotal();
                $config['payment']['can_save_cc'] = $this->payment->canSaveCC();
                $config['payment']['exists_one_credit_card'] = $this->payment->existsOneCreditCard();
                $config['payment']['cc_list'] = $this->payment->getCreditCardList();
                $config['payment']['is_logged_in'] = $this->payment->isLoggedIn();
                $config['payment']['url_store'] = $this->payment->getBaseUrlStore();
                $config['payment']['country'] = $this->payment->getCountry();
                $config['payment']['isAvailableInstallments'] = $this->payment->getIsAvailableInstallments();
                $config['payment']['ccform']["availableTypes"][$code] = array("AE" => "American Express", "VI" => "Visa", "MC" => "MasterCard", "CN" => "Carnet");

                $this->logger->info("this->payment->getCountry() - " . $this->payment->getCountry(),['Method' => 'getConfig() | OpenpayConfigProvider']);
                if($this->payment->getCountry() === "PE"){
                    $config['payment']['ccform']["availableTypes"][$code]["DN"] = "Diners";
                }

                $config['payment']['ccform']["hasVerification"][$code] = true;
                $config['payment']['ccform']["hasSsCardType"][$code] = false;
                $config['payment']['ccform']["months"][$code] = $this->getMonths();
                $config['payment']['ccform']["years"][$code] = $this->getYears();
                $config['payment']['ccform']["cvvImageUrl"][$code] = $protocol.$_SERVER['SERVER_NAME']."/pub/static/frontend/Magento/luma/es_MX/Magento_Checkout/cvv.png";
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
            "10"=> "10 - Octubre",
            "11"=> "11 - Noviembre",
            "12"=> "12 - Diciembre"
        );
    }

    public function getYears(){
        $years = array();
        for($i=0; $i<=10; $i++){
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

    public function hostSecure() {
        $is_secure = false;
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            $is_secure = true;
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
            $is_secure = true;
        }

        return $is_secure;
    }

}
