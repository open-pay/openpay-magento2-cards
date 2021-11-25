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

            $country = $this->payment->getCountry();
            if($country == 'MX'){
                $openpay = $this->payment->getOpenpayInstance();
                $cardInfo = $openpay->bines->get($post['card_bin']);
                $data = array(
                    'status' => 'success',
                    'card_type' => $cardInfo->type
                );
            } else if($country == 'CO') {
                $cardInfo = $this->requestOpenpay('/cards/validate-bin?bin='.$post['card_bin'], $this->payment->isSandbox(), "GET");
                $data = array(
                    'status' => 'success',
                    'card_type' => $cardInfo->card_type
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

    public function requestOpenpay($api, $is_sandbox, $method = 'GET') {
        $url = 'https://api.openpay.co/v1';
        $sandbox_url = 'https://sandbox-api.openpay.co/v1';
    
        $absUrl = $is_sandbox ? $sandbox_url : $url;
        $absUrl .= $api;
    
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $absUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        $result = curl_exec($ch);
    
        if (curl_exec($ch) === false) {
            $this->logger->error("Curl error", array("curl_errno" => curl_errno($ch), "curl_error" => curl_error($ch)));
        } else {
            $info = curl_getinfo($ch);
            $this->logger->debug("requestOpenpay", array("HTTP code " => $info['http_code'], "on request to" => $info['url']));
        }
    
        curl_close($ch);
    
        return json_decode($result);
    }

}