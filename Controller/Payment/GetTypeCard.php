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
    protected $openpayRequest;
    /**
     *
     * @param Context $context
     * @param OpenpayPayment $payment
     * @param \Openpay\Cards\Logger\Logger $logger_interface
     * @param \Openpay\Cards\Model\Utils\OpenpayRequest $openpayRequest
     */
    public function __construct(
        Context $context,
        OpenpayPayment $payment,
        \Openpay\Cards\Logger\Logger $logger_interface,
        \Openpay\Cards\Model\Utils\OpenpayRequest $openpayRequest
    ) {
        parent::__construct($context);
        $this->payment = $payment;
        $this->logger = $logger_interface;
        $this->openpayRequest = $openpayRequest;
    }
    public function execute() {
        $data = null;
        $post = $this->getRequest()->getPostValue();
        $openpay = $this->payment->getOpenpayInstance();

        try {
            $this->logger->debug('#CardBin', array('cardInfo' => $post['card_bin']));

            $country = $this->payment->getCountry();
            $openpay = $this->payment->getOpenpayInstance();
            $sk = $this->payment->getPrivateKey();

            if($country == 'MX') {
                $path = sprintf('/%s/bines/man/%s', $this->payment->getMerchantId(), $post['card_bin']);
                $cardInfo = $this->openpayRequest->make($path, $country, $this->payment->isSandbox(), "GET", [],['sk' => $sk]);

                $data = array(
                    'status' => 'success',
                    'card_type' => $cardInfo->type,
                    'bin' => $cardInfo->bin,
                );
            }
            if ($country == 'CO') {
                $cardInfo = $this->openpayRequest->make('/cards/validate-bin?bin='.$post['card_bin'], $country, $this->payment->isSandbox(), "GET");
                $data = array(
                    'status' => 'success',
                    'card_type' => $cardInfo->card_type
                );
            }
            if($country == 'PE') {
                $path = sprintf('/%s/bines/%s/promotions', $this->payment->getMerchantId(), $post['card_bin']);
                $cardInfo = $this->openpayRequest->make($path, $country, $this->payment->isSandbox());
                $installments = (count($cardInfo->installments) == 0) ? [] : $cardInfo->installments;
                $data = array(
                    'status' => 'success',
                    'card_type' => $cardInfo->cardType,
                    'installments'  => $installments,
                    'withInterest' => $cardInfo->withInterest
                );
            }
        } catch (\Exception $e) {
            $this->logger->error('#ErrorBin', array('msg' => $e->getMessage()));
            $data = array(
                'status' => 'error',
                'card_type' => "credit card not found"
            );
        }

        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData($data);
        return $resultJson;
    }
}
