<?php

namespace Openpay\Cards\Model;

class OpenpayCustomer extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface {

    const CACHE_TAG = 'openpay_customers';

    protected $_cacheTag = 'openpay_customers';
    protected $_eventPrefix = 'openpay_customers';
        
    protected function _construct() {
        $this->_init('Openpay\Cards\Model\ResourceModel\OpenpayCustomer');        
    }

    public function getIdentities() {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getDefaultValues() {
        $values = [];

        return $values;
    }
    
    public function fetchOneBy($field, $value) {        
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();                
        $tableName = $connection->getTableName('openpay_customers'); //gives table name with prefix        
        
        $sql = 'Select * FROM '.$tableName.' WHERE '.$field.' = "'.$value.'" limit 1';        
        $result = $connection->fetchAll($sql);
        
        if (count($result)) {
            return json_decode(json_encode($result[0]), false);
        }
        
        return false;
    }


    /**
     * {@inheritDoc}
     */
    public function setOpenpayId($openpayId)
    {
        return $this->setData('openpay_id', $openpayId);
    }

    /**
     * {@inheritDoc}
     */
    public function getOpenpayId()
    {
        return $this->getData('openpay_id');
    }

    /**
     * {@inheritDoc}
     */
    public function setCustomerId($customerId)
    {
        return $this->setData('customer_id', $customerId);
    }

    /**
     * {@inheritDoc}
     */
    public function getCustomerId()
    {
        return $this->getData('customer_id');
    }

}
