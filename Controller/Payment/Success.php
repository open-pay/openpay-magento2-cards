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
use Magento\Framework\View\Result\PageFactory;
use Openpay\Cards\Model\Payment as OpenpayPayment;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection as InvoiceCollection;
use Magento\Sales\Model\Order\Invoice;
/**
 * Webhook class  
 */
class Success extends \Magento\Framework\App\Action\Action
{
    protected $resultPageFactory;
    protected $request;
    protected $payment;
    protected $checkoutSession;
    protected $orderRepository;
    protected $logger;
    protected $_invoiceService;
    protected $transactionBuilder;
    
    /**
     * 
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param \Magento\Framework\App\Request\Http $request
     * @param OpenpayPayment $payment
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Psr\Log\LoggerInterface $logger_interface
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     */
    public function __construct(
            Context $context, 
            PageFactory $resultPageFactory, 
            \Magento\Framework\App\Request\Http $request, 
            OpenpayPayment $payment,
            \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
            \Magento\Checkout\Model\Session $checkoutSession,
            \Psr\Log\LoggerInterface $logger_interface,
            \Magento\Sales\Model\Service\InvoiceService $invoiceService,
            \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->request = $request;
        $this->payment = $payment;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger_interface;        
        $this->_invoiceService = $invoiceService;
        $this->transactionBuilder = $transactionBuilder;
    }
    /**
     * Load the page defined in view/frontend/layout/openpay_index_webhook.xml
     * URL /openpay/payment/success
     * 
     * @url https://magento.stackexchange.com/questions/197310/magento-2-redirect-to-final-checkout-page-checkout-success-failed?rq=1
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute() {                
        try {                        
            $order_id = $this->checkoutSession->getLastOrderId();
            $quote_id = $this->checkoutSession->getLastQuoteId();
            
            $this->checkoutSession->setLastSuccessQuoteId($quote_id);
            
            $this->logger->debug('getLastQuoteId: '.$quote_id);
            $this->logger->debug('getLastOrderId: '.$order_id);
            $this->logger->debug('getLastSuccessQuoteId: '.$this->checkoutSession->getLastSuccessQuoteId());
            $this->logger->debug('getLastRealOrderId: '.$this->checkoutSession->getLastRealOrderId());        
            
            $openpay = $this->payment->getOpenpayInstance();                          
            $order = $this->orderRepository->get($order_id);        
            $customer_id = $order->getExtCustomerId();
            if ($customer_id) {
                $customer = $this->payment->getOpenpayCustomer($customer_id);
                $charge = $customer->charges->get($this->request->getParam('id'));
            } else {
                $charge = $openpay->charges->get($this->request->getParam('id'));
            }
            $this->logger->debug('#SUCCESS', array('id' => $this->request->getParam('id'), 'status' => $charge->status));
            if ($order && $charge->status != 'completed') {
                $order->cancel();
                $order->addStatusToHistory(\Magento\Sales\Model\Order::STATE_CANCELED, __('Autenticación de 3D Secure fallida.'));
                $order->save();
                $this->logger->debug('#3D Secure', array('msg' => 'Autenticación de 3D Secure fallida'));
                                
                return $this->resultPageFactory->create();        
            }
            $status = \Magento\Sales\Model\Order::STATE_PROCESSING;
            $order->setState($status)->setStatus($status);
            $order->setTotalPaid($charge->amount);  
            $order->addStatusHistoryComment("Pago recibido exitosamente")->setIsCustomerNotified(true);            
            $order->save();        
            $requiresInvoice = true;
            /** @var InvoiceCollection $invoiceCollection */
            $invoiceCollection = $order->getInvoiceCollection();
            if ( $invoiceCollection->count() > 0 ) {
                /** @var Invoice $invoice */
                foreach ($invoiceCollection as $invoice ) {
                    if ( $invoice->getState() == Invoice::STATE_OPEN) {
                        $invoice->setState(Invoice::STATE_PAID);
                        $invoice->setTransactionId($charge->id);
                        $invoice->pay()->save();
                        $requiresInvoice = false;
                        break;
                    }
                }
            }
            if ( $requiresInvoice ) {
                $invoice = $this->_invoiceService->prepareInvoice($order);
                $invoice->setTransactionId($charge->id);
//            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
//            $invoice->register();
                $invoice->pay()->save();
            }
            $payment = $order->getPayment();                                
            $payment->setAmountPaid($charge->amount);
            $payment->setIsTransactionPending(false);
            $payment->save();
            
            $this->logger->debug('#SUCCESS', array('redirect' => 'checkout/onepage/success'));
            return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
            
        } catch (\Exception $e) {
            $this->logger->error('#SUCCESS', array('message' => $e->getMessage(), 'code' => $e->getCode(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()));
            //throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
        }
        
        return $this->resultRedirectFactory->create()->setPath('checkout/cart'); 
    }
}