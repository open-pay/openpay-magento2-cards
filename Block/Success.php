<?php

namespace Openpay\Cards\Block;

class Success extends \Magento\Framework\View\Element\Template
{
    protected $context;
    protected $coreRegistry;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $coreRegistry
    ) {
        $this->coreRegistry = $coreRegistry;
        parent::__construct($context);
    }


    public function getMessageError(){
        return $this->coreRegistry->registry('messageError');
    }

}