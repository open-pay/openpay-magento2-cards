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

class Installments {

    /**
     * @return array
     */
    public function getOptions() {
        $installments = array();
        for ($i=1; $i <= 36; $i++) {
            $label = $i == 1 ? 'No aceptar pagos en cuotas' : $i.' cuotas';
            $installments[] = array('value' => $i, 'label' => $label);
        }
        
        return $installments;        
    }

}
