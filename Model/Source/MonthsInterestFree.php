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

class MonthsInterestFree
{
    /**
     * @return array
     */
    public function getMonths()
    {
        return array(
            array('value' => '1', 'label' => 'No aceptar pagos a meses'),
            array('value' => '3', 'label' => '3 meses'),
            array('value' => '6', 'label' => '6 meses'),
            array('value' => '9', 'label' => '9 meses'),
            array('value' => '12', 'label' => '12 meses'),
            array('value' => '18', 'label' => '18 meses')            
        );     
    }
}
