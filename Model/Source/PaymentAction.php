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

use Magento\Framework\Option\ArrayInterface;

class PaymentAction implements ArrayInterface {

    /**
     * {@inheritdoc}
     */
    public function toOptionArray() {
        return array(
            array(
                //'value' => \Magento\Authorizenet\Model\Authorizenet::ACTION_AUTHORIZE_CAPTURE,
                'value' => \Openpay\Cards\Model\Payment::ACTION_AUTHORIZE_CAPTURE,
                'label' => 'Cargo inmediato'
            ),
            array(
                'value' => \Openpay\Cards\Model\Payment::ACTION_AUTHORIZE,
                'label' => 'Pre-autorizar únicamente'
            )
        );
    }

}
