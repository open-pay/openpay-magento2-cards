<?php
/**
 * Payment CC Types Source Model
 *
 * @category    Openpay
 * @package     Openpay_Cards
 * @author      Federico Balderas
 * @copyright   Openpay (http://openpay.mx)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */

namespace Openpay\Cards\Model\Source;

class Cctype extends \Magento\Payment\Model\Source\Cctype
{
    /**
     * @return array
     */
    public function getAllowedTypesMx()
    {
        return array(
            array('value' => 'VI', 'label' => 'Visa'),
            array('value' => 'MC', 'label' => 'MasterCard'),
            array('value' => 'AE', 'label' => 'American Express'),
            array('value' => 'CN', 'label' => 'Carnet')              
        );     
    }

    /**
     * @return array
     */
    public function getAllowedTypesCo()
    {
        return array(
            array('value' => 'VI', 'label' => 'Visa'),
            array('value' => 'MC', 'label' => 'MasterCard'),
            array('value' => 'AE', 'label' => 'American Express')            
        );     
    }

    /**
     * @return array
     */
    public function getAllowedTypesPe()
    {
        return array(
            array('value' => 'VI', 'label' => 'Visa'),
            array('value' => 'MC', 'label' => 'MasterCard'),
            array('value' => 'AE', 'label' => 'American Express'),
            array('value' => 'DN', 'label' => 'Diners')             
        );     
    }
}
