<?php
/** 
 * @category    Payments
 * @package     Openpay_Cards
 * @author      Federico Balderas
 * @copyright   Openpay (http://openpay.mx)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */

namespace Openpay\Cards\Controller\Payment;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\ResultFactory;

use Openpay\Cards\Model\Payment as OpenpayPayment;

class GetTypeCard extends \Magento\Framework\App\Action\Action{
    
    protected $payment;
    protected $logger;
    /**
     * 
     * @param Context $context
     * @param OpenpayPayment $payment
     * @param \Psr\Log\LoggerInterface $logger_interface
     */
    public function __construct(
        Context $context,
        OpenpayPayment $payment,
        \Psr\Log\LoggerInterface $logger_interface
    ) {
        parent::__construct($context);
        $this->payment = $payment;
        $this->logger = $logger_interface;
    }
    public function execute() {
        $data = null;
        $post = $this->getRequest()->getPostValue();
        $openpay = $this->payment->getOpenpayInstance();

        try {
            $this->logger->debug('#CardBin', array('cardInfo' => $post['card_bin']));
            $cardInfo = $openpay->bines->get($post['card_bin']);
            
            $data = $cardInfo->type;

        } catch (\Exception $e) {
            $this->logger->error('#ErrorBin', array('msg' => $e->getMessage()));                    
        }

        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData($data);
        return $resultJson;
    }

}