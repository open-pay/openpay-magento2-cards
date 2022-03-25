<?php
/** 
 * @category    Payments
 * @package     Openpay_Stores
 * @author      Federico Balderas
 * @copyright   Openpay (http://openpay.mx)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */

namespace Openpay\Cards\Controller\Cards;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Openpay\Cards\Model\Payment as OpenpayPayment;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Exception;

/**
 * Webhook class  
 */
class Webhook extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    
    protected $request;
    protected $payment;
    protected $logger;
    protected $invoiceService;
    
    public function __construct(
            Context $context,             
            \Magento\Framework\App\Request\Http $request, 
            OpenpayPayment $payment, 
            \Psr\Log\LoggerInterface $logger_interface,
            \Magento\Sales\Model\Service\InvoiceService $invoiceService
    ) {
        parent::__construct($context);        
        $this->request = $request;
        $this->payment = $payment;
        $this->logger = $logger_interface;     
        $this->invoiceService = $invoiceService;
    }

    /**
     * Load the page defined in view/frontend/layout/openpay_index_webhook.xml
     * URL /openpay/index/webhook
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute() {        
        $this->logger->debug('#webhook-cards');        
        try {
            $body = file_get_contents('php://input');        
            $json = json_decode($body);                    

            $openpay = $this->payment->getOpenpayInstance();

            if(!isset($json->type)){
                header('HTTP/1.1 200 OK');
                exit;
            }

            if(!isset($json->transaction)) throw new Exception("Transaction object is empty", -1);

            if(isset($json->transaction->customer_id)){
                $customer = $openpay->customers->get($json->transaction->customer_id);
                $charge = $customer->charges->get($json->transaction->id);
                if(!$charge) throw new Exception("Charge object is empty", -15);

            }else{
                $charge = $openpay->charges->get($json->transaction->id);
                if(!$charge) throw new Exception("Charge object is empty", -15);
            }

            $this->logger->debug('#webhook-cards', array('trx_id' => $json->transaction->id, 'status' => $charge->status));        

            if (isset($json->type) && $json->transaction->method == 'card') {

                $order = $this->_objectManager->create('Magento\Sales\Model\Order');            
                $order->loadByAttribute('ext_order_id', $charge->id);

                $status_order = $order->getStatus();
                $this->logger->debug('#webhook-cards-status', array('status' => $status_order));


                if($status_order == 'processing' || $status_order == 'completed'){
                    header('HTTP/1.1 200 OK');
                    exit;
                }

                if($json->type == 'charge.succeeded' && $charge->status == 'completed'){
                    $status = \Magento\Sales\Model\Order::STATE_PROCESSING;
                    $order->setState($status)->setStatus($status);
                    $order->setTotalPaid($charge->amount);  
                    $order->addStatusHistoryComment("Pago recibido exitosamente")->setIsCustomerNotified(true);            
                    $order->save();
                    
                    $invoice = $this->invoiceService->prepareInvoice($order);        
                    $invoice->setTransactionId($charge->id);
                    $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                    $invoice->register();
                    $invoice->save();
                }else if($json->type == 'transaction.expired' && $charge->status == 'expired'){
                    $status = \Magento\Sales\Model\Order::STATE_CANCELED;
                    $order->setState($status)->setStatus($status);
                    $order->addStatusHistoryComment("Pago vencido")->setIsCustomerNotified(true);            
                    $order->save();
                }

                header('HTTP/1.1 200 OK');

            } else if ($json->transaction->method != 'card') throw new Exception("Transaction method is not card", -30);

        } catch (\Exception $e) {

            $this->logger->error('#webhook-cards', array('msg' => $e->getMessage(), 'code' => $e->getCode()));                    
            header("HTTP/1.0 500 Server Error");

        }
        exit;
    }       
    
    /**
     * Create exception in case CSRF validation failed.
     * Return null if default exception will suffice.
     *
     * @param RequestInterface $request
     * @link https://magento.stackexchange.com/questions/253414/magento-2-3-upgrade-breaks-http-post-requests-to-custom-module-endpoint
     *
     * @return InvalidRequestException|null
     * @SuppressWarnings(PMD.UnusedFormalParameter)
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Perform custom request validation.
     * Return null if default validation is needed.
     *
     * @param RequestInterface $request
     *
     * @return bool|null
     * @SuppressWarnings(PMD.UnusedFormalParameter)
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

}