<?php

namespace Openpay\Cards\Model\Utils;

use \Magento\Store\Model\StoreManagerInterface;

class Currency 
{
    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    public function __construct(StoreManagerInterface $storeManager) {
        $this->_storeManager = $storeManager;
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
                //$currencies[] = $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
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
        $currentCurrency = $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
        return in_array($currentCurrency, $supportedCurrencies);
    }
}