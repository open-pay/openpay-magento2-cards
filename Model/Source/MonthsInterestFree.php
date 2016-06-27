<?php
/**
 * Payment CC Types Source Model
 *
 * @category    Inchoo
 * @package     Inchoo_Stripe
 * @author      Ivan Weiler & Stjepan Udovičić
 * @copyright   Inchoo (http://inchoo.net)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
