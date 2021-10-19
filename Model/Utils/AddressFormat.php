<?php

namespace Openpay\Cards\Model\Utils;

class AddressFormat
{
    /**
     * @param \Magento\Sales\Model\Order\Address $address
     */
    public static function formatAddress($address, $country = 'MX', $complete = true) {
        switch ($country) {
            case 'MX':
            case 'PE':
                $adressFormated = [
                    'city' => $address->getCity(),
                    'state' => $address->getRegion(),
                    'postal_code' => $address->getPostcode(),
                ];
                if ($complete) {
                    $adressFormated['line1'] = $address->getStreetLine(1);
                    $adressFormated['line2'] = $address->getStreetLine(2);
                    $adressFormated['country_code'] = $address->getCountryId();
                }
                return $adressFormated;
            case 'CO':
                return [
                    'department' => $address->getRegion(),
                    'city' => $address->getCity(),
                    'additional' => $address->getStreetLine(1).' '.$address->getStreetLine(2)
                ];
        }
    }

    public static function getNameAddressFieldByCountry($country) {
        switch ($country) {
            case 'MX':
            case 'PE':
                return 'address';
            case 'CO':
                return 'customer_address';
        }
    }
}