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

use Openpay\Data\OpenpayApiConnectionError;

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
    protected $orderSender;
    protected $invoiceSender;
    protected $transactionRepository;
    protected $searchCriteriaBuilder;
    protected $coreRegistry;
    protected $quoteRepository;
    protected $messageError;

    /**
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param \Magento\Framework\App\Request\Http $request
     * @param OpenpayPayment $payment
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Openpay\Cards\Logger\Logger $logger_interface
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     * @param \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Framework\Registry $coreRegistry
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Framework\Message\ManagerInterface
     *
     */
    public function __construct(
            Context $context,
            PageFactory $resultPageFactory,
            \Magento\Framework\App\Request\Http $request,
            OpenpayPayment $payment,
            \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
            \Magento\Checkout\Model\Session $checkoutSession,
            \Openpay\Cards\Logger\Logger $logger_interface,
            \Magento\Sales\Model\Service\InvoiceService $invoiceService,
            \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
            \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
            \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
            \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
            \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
            \Magento\Framework\Registry $coreRegistry,
            \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
            \Magento\Framework\Message\ManagerInterface $messageManager
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
        $this->orderSender = $orderSender;
        $this->invoiceSender = $invoiceSender;
        $this->transactionRepository = $transactionRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->coreRegistry = $coreRegistry;
        $this->quoteRepository = $quoteRepository;
        $this->messageManager = $messageManager;
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

            $status = $this->payment->getCustomStatus('processing');

            $openpay = $this->payment->getOpenpayInstance();
            $order = $this->orderRepository->get($order_id);

            $customer_id = $order->getExtCustomerId();
            if ($customer_id) {
                $customer = $this->payment->getOpenpayCustomer($customer_id);
                $charge = $customer->charges->get($this->request->getParam('id'));
            } else {
                $charge = $openpay->charges->get($this->request->getParam('id'));
            }
            $this->logger->debug('#SUCCESS', array('id' => $this->request->getParam('id'), 'status' => $charge->status, 'order_status' => $order->getStatus()));

            if ($order && $charge->status == 'in_progress') {
                $order->setState($status)->setStatus($status);
                $order->addStatusHistoryComment("Preautorización realizada exitosamente");
                $order->save();
                return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
            }

            if ($order && $charge->status != 'completed') {
                $order->cancel();
                $messageError = 'La transacción no pudo ser procesada, ' . $charge->error_message;
                $order->addStatusToHistory(\Magento\Sales\Model\Order::STATE_CANCELED, __($messageError));
                $statusCanceled = $this->payment->getCustomStatus('canceled');
                $order->setState($statusCanceled)->setStatus($statusCanceled);
                $order->save();
                $quote = $this->quoteRepository->get($quote_id);
                $quote->setIsActive(true)->save();
                $this->checkoutSession->replaceQuote($quote);
                $this->logger->debug('#3D Secure', array('msg' => 'Autenticación de 3D Secure fallida'));
                $code = $charge->error_code;
                $this->messageManager->addErrorMessage($this->getMessageError($code). ' .Intente con otra tarjeta.');
                return $this->resultRedirectFactory->create()->setPath('checkout/cart');
            }

            $this->checkoutSession->setForceOrderMailSentOnSuccess(true);
            $this->orderSender->send($order, true);

            $order->setState($status)->setStatus($status);
            $order->setTotalPaid($charge->amount);
            $order->setBaseTotalPaid($charge->amount);
            $order->addStatusHistoryComment("Pago recibido exitosamente")->setIsCustomerNotified(true);

            // Validation for 3DS
            if ($order && ($order->getStatus() == "processing" || $order->getStatus() == 'completed')) {
                $this->logger->debug('#Confimation Success', array('Notifications' => 'Success'));
                return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
            }

            $order->save();

            $this->logger->debug('#success.order.save', array('order.state' => $order->getState(), 'order.status' => $order->getStatus(), 'order.base.total.paid' => $order->getBaseTotalPaid()));

            $this->searchCriteriaBuilder->addFilter('order_id', $order_id);
            $list = $this->transactionRepository->getList(
                $this->searchCriteriaBuilder->create()
            );

            $this->logger->info('#Init Success Transactions >>>');
            $transactions =  $list->getItems();
            if ($transactions) {
                $this->logger->info('#Exist Transactions');
                foreach ($transactions as $transaction) {
                    $transaction->setIsClosed(true);
                    $transaction->save();
                }
            }
            $this->logger->debug('#TransactionsList', array('$transactions', $transactions ));

            $requiresInvoice = true;
            /** @var InvoiceCollection $invoiceCollection */
            $invoiceCollection = $order->getInvoiceCollection();

            $this->logger->debug('#success.txn.id', array('$transactions', $transaction->getTxnId() ));
            $this->logger->debug('#success.invoiceCollection', array('$invoiceCollection', $invoiceCollection->getData() ));

            if ( $invoiceCollection->count() > 0 ) {
                /** @var Invoice $invoice */
                foreach ($invoiceCollection as $invoice ) {
                    $this->logger->debug('#success.invoice.id', array('$invoice.id', $invoice->getId(), '$invoice.incrementId' => $invoice->getIncrementId() ));
                    $this->logger->debug('#success.invoice.state', array('$invoice', $invoice->getState() ));
                    if ( $invoice->getState() == Invoice::STATE_OPEN) {
                        $invoice->setState(Invoice::STATE_PAID);
                        $invoice->setTransactionId($charge->id);
                        $invoice->pay()->save();
                        $this->invoiceSender->send($invoice, true);
                        $requiresInvoice = false;
                        break;
                    }
                    if ($invoice->getState() == Invoice::STATE_PAID) {
                        $requiresInvoice = false;
                    }
                }
            }
            if ( $requiresInvoice ) {
                $invoice = $this->_invoiceService->prepareInvoice($order);
                $this->logger->debug('#success.requireInvoice', array('$invoice.id', $invoice->getId(), '$invoice.incrementId' => $invoice->getIncrementId() ));
                $invoice->setTransactionId($charge->id);
//            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
//            $invoice->register();
                $invoice->pay()->save();
                $this->invoiceSender->send($invoice, true);
            }
            $payment = $order->getPayment();
            $payment->setAmountPaid($charge->amount);
            $payment->setIsTransactionPending(false);
            $payment->save();

            $this->logger->debug('#SUCCESS', array('redirect' => 'checkout/onepage/success'));
            return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');

        } catch (OpenpayApiConnectionError $e) {
            $this->logger->error('#SUCCESS OpenpayApiConnectionError (openpay->charges->get()', array('message' => $e->getMessage()));
        } catch (\Exception $e) {
            $this->logger->error('#SUCCESS', array('message' => $e->getMessage(), 'code' => $e->getCode(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()));
            //throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
        }

        return $this->resultRedirectFactory->create()->setPath('checkout/cart');
    }

    private function getMessageError($code) {

        switch($code){
            /* ERRORES GENERALES */
            case "2010":
                return "Autenticación de 3D Secure fallida, favor de contactar a tu Banco emisor. La transacción no pudo completarse y la orden de compra fue cancelada.";
            case '1000':
            case '1004':
            case '1005':
                return 'el servicio no está disponible';

            /* ERRORES TARJETA */
            case '3001':
            case '3004':
            case '3005':
            case '3007':
                return 'La tarjeta fue rechazada';

            case '3002':
                return 'La tarjeta ha expirado';

            case '3003':
                return 'La tarjeta no tiene fondos suficientes';

            case '3006':
                return 'La operación no esta permitida para este cliente o esta transacción';

            case '3008':
                return 'La tarjeta no es soportada en transacciones en línea';

            case '3009':
                return 'La tarjeta fue reportada como perdida';

            case '3010':
                return 'El banco ha restringido la tarjeta';

            case '3011':
                return 'El banco ha solicitado que la tarjeta sea retenida. Contacte al banco';

            case '3012':
                return 'Se requiere solicitar al banco autorización para realizar este pago';

            default: /* Demás errores 400 */
                return 'La petición no pudo ser procesada';
        }
    }
}
