<?php

namespace Openpay\Cards\Model\Config;

class OrderStatuses implements \Magento\Framework\Option\ArrayInterface
{
    protected $statusCollectionFactory;
    protected $context;
     
    public function __construct(
		\Magento\Backend\Block\Template\Context $context,
        \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $statusCollectionFactory
    ) {
		$this->context = $context;
        $this->statusCollectionFactory = $statusCollectionFactory;
    }
  
    public function toOptionArray()
    {
        return $this->statusCollectionFactory->create()->toOptionArray();        
    }
}