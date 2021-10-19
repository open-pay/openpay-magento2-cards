<?php
/**
 * Payment Charge Types Source Model
 *
 * @category    Openpay
 * @package     Openpay_Cards
 * @author      Federico Balderas
 * @copyright   Openpay (http://openpay.mx)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */

namespace Openpay\Cards\Model\Source;

class Countries
{
    /**
     * @return array
     */
    public function getOptions()
    {
        return array(
            array('value' => 'MX', 'label' => 'México'),
            array('value' => 'CO', 'label' => 'Colombia'),
            array('value' => 'PE', 'label' => 'Perú')
        );
    }
}
