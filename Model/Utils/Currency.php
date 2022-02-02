<?php

namespace Openpay\Cards\Model\Utils;

use \Magento\Store\Model\StoreManagerInterface;

class Currency 
{
    /**
     * @var string
     */
    protected $currentCurrency;
    
    public function __construct(StoreManagerInterface $storeManager) {
        $this->currentCurrency = $storeManager->getStore()->getCurrentCurrency()->getCode();
    }


     /**
     * Return an array of currencies supported by country code
     *
     * @param string $countryCode
     * @return array
     */
    public function getSupportedCurrenciesByCountryCode(string $countryCode) : array {
        $currencies = ['USD'];
        $countryCode = strtoupper($countryCode);
        switch ($countryCode) {
            case 'MX':
                $currencies[] = 'MXN';
                return $currencies;
            case 'CO':
                $currencies[] = 'COP';
                return $currencies;
            case 'PE':
                $currencies[] = 'PEN';
                return $currencies;
            default:
                break;
        }
    }

     /**
     * Return a true when the current currency configurated is supported, false otherwise
     *
     * @param array $countryCode
     * @return array
     */
    public function isSupportedCurrentCurrency (array $supportedCurrencies) : bool {
        return in_array($this->currentCurrency, $supportedCurrencies);
    }

    /**
     * Return the current currency
     * 
     * @return string
     */
    public function getCurrentCurrency () : string {
        return $this->currentCurrency;
    }
}