<?php

namespace Openpay\Cards\Model\ResourceModel;

class OpenpayCustomer extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb {

    public function __construct(\Magento\Framework\Model\ResourceModel\Db\Context $context) {
        parent::__construct($context);
    }

    protected function _construct() {
        $this->_init('openpay_customers', 'openpay_customer_id');
    }
}
