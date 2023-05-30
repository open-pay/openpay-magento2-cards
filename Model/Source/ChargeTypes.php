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

class ChargeTypes
{
    /**
     * @return array
     */
    public function getTypes()
    {
        return array(
            array('value' => 'direct', 'label' => 'Directo'),
            array('value' => 'auth', 'label' => 'Autenticación selectiva'),
            array('value' => '3d', 'label' => '3D Secure')            
        );     
    }
    public function getTypesCoPe()
    {
        return array(
            array('value' => 'direct', 'label' => 'Directo'),
            array('value' => '3d', 'label' => '3D Secure')
        );
    }
}
