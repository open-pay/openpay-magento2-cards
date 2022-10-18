<?php

namespace Openpay\Cards\Model\Source;

use Magento\Framework\App\Config\ScopeConfigInterface;

class SaveCc
{

    /**
     * @return array
     */
    public function getSaveCc()
    {

        return array(
            array('value' => '0', 'label' => 'No Guardar'),
            array('value' => '1', 'label' => 'Guardar y Solicitar CVV para futuras compras'),
            array('value' => '2', 'label' => 'Guardar y no solicitar CVV para futuras compras')             
        );   
         
    }

    public function getSaveCcMxCO(){
        return array(
            array('value' => '0', 'label' => 'No Guardar'),
            array('value' => '1', 'label' => 'Guardar y Solicitar CVV para futuras compras')           
        ); 
    }
}
