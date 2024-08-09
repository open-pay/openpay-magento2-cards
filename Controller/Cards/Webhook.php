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
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection as InvoiceCollection;
use Magento\Sales\Model\Order\Invoice;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Exception;

use Openpay\Data\OpenpayApiConnectionError;

/**
 * Webhook class
 */
class Webhook extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    protected $request;
    protected $payment;
    protected $logger;
    protected $invoiceService;
    protected $transactionRepository;
    protected $searchCriteriaBuilder;
    protected $invoiceRepository;

    public function __construct(
        Context $context,
        \Magento\Framework\App\Request\Http $request,
        OpenpayPayment $payment,
        \Openpay\Cards\Logger\Logger $logger_interface,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Sales\Api\InvoiceRepositoryInterface $invoiceRepository
    ) {
        parent::__construct($context);
        $this->request = $request;
        $this->payment = $payment;
        $this->logger = $logger_interface;
        $this->invoiceService = $invoiceService;
        $this->transactionRepository = $transactionRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->invoiceRepository = $invoiceRepository;
    }

    /**
     * Load the page defined in view/frontend/layout/openpay_index_webhook.xml
     * URL /openpay/index/webhook
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute() {
        $this->logger->debug('#Webhook.openpay_cards');
        try {
            $body = file_get_contents('php://input');
            $json = json_decode($body);
            $openpay = $this->payment->getOpenpayInstance();

            $this->logger->debug("#Webhook.openpay_cards.ln:66 json_input - " . json_encode($json));
            if( (isset($json->type) && $json->type == "verification") || empty($json) || json_encode($json) == "{}"){
                header('HTTP/1.1 200 OK');
                return;
            }

            /*JSON Body Validations*/
            if(!isset($json->transaction)) throw new Exception("Transaction object not found in webhook request", 404);
            if(!isset($json->transaction->method)) throw new Exception("Charge method not found in webhook request", 404);
            if(!isset($json->type)) throw new Exception("Charge type not found in webhook request", 404);

            /*Openpay Trx method Validation*/
            if($json->transaction->method != 'card'){
                header('HTTP/1.1 200 OK');
                exit;
            }

            /*Openpay Charge request*/
            $charge = $openpay->charges->get($json->transaction->id);
            $this->logger->debug("#Webhook.openpay_cards.ln:85 Openpay_Charge - " . json_encode($charge));

            $this->logger->debug("#Webhook.openpay_cards.ln:85 Openpay_Charge_Transaction - " . json_encode($charge->transaction));

            /*Openpay Charge Validation*/
            if(!$charge) throw new Exception("Charge not found in Openpay merchant", 404);

            /*Getting Order Data*/
            $order = $this->_objectManager->create('Magento\Sales\Model\Order');
            $order->loadByAttribute('ext_order_id', $charge->id);
            $order_status = $order->getStatus();
            $order_id = $order->getId();
            $status = \Magento\Sales\Model\Order::STATE_PROCESSING;

            /*Logging Webhook data*/
            $this->logger->debug('#Webhook.openpay_cards.ln:98', array('webhook.trx_id' => $json->transaction->id, 'webhook.type' => $json->type , 'charge.status' => $charge->status, 'order.status' => $order_status));

            if(!isset($order_id)) throw new Exception("The requested resource doesn't exist", 404);

            /*Magento Order validation 3DS*/
            if(!isset($json->transaction->payment_method)){
                $this->logger->debug('#webhook.direct.card', array('Notifications' => 'Direct Card Confirm'));
                header('HTTP/1.1 200 OK');
                exit;
            }

            /*IF transaction is only Authorization */
            if ($order && $charge->status == 'in_progress') {
                $order->setState($status)->setStatus($status);
                $order->save();
            }

            /*Update Order Status and Invoice*/
            if($json->type == 'charge.succeeded' && $charge->status == 'completed'){
                $this->logger->debug('#webhook.trx.succeeded.start', array('webhook.type' => 'charge.succeeded', 'openpay.charge.status' => 'completed' ));

                $order->setState($status)->setStatus($status);
                $order->setTotalPaid($charge->amount);
                $order->setBaseTotalPaid($charge->amount);
                $order->addStatusHistoryComment("Pago confirmado vía Webhook")->setIsCustomerNotified(true);

                /*Magento Order validation*/
                if($order_status == 'processing' || $order_status == 'completed'){
                    $this->logger->debug('#webhook.process.cancelled', array('Magento.order.status' => 'processing || completed'));
                    header('HTTP/1.1 200 OK');
                    exit;
                }

                $this->logger->debug('#webhook.save.order', array('Magento.order.status' => $order_status));

                $order->save();

                $this->logger->debug('#webhook.order.save', array('order.state' => $order->getState(), 'order.status' => $order->getStatus(), 'order.base.total.paid' => $order->getBaseTotalPaid()));

                $this->searchCriteriaBuilder->addFilter('order_id', $order_id);
                $list = $this->transactionRepository->getList(
                    $this->searchCriteriaBuilder->create()
                );

                // hotfix transactions not exist
                $this->logger->info('#Webhook.transaction INIT >>>');
                $transactions =  $list->getItems();
                if ($transactions) {
                    $this->logger->info('#Webhook.transactions Exist Transaction');
                    foreach ($transactions as $transaction) {
                        $transaction->setIsClosed(true);
                        $transaction->save();
                    }
                }
                $this->logger->debug('#TransactionsList', array('$transactions', $transactions ));

                $requiresInvoice = true;
                /** @var InvoiceCollection $invoiceCollection */
                $invoiceCollection = $order->getInvoiceCollection();

                $this->logger->debug('#webhook.txn.id', array('$transactions', $transaction->getTxnId() ));
                $this->logger->debug('#webhook.invoiceCollection', array('$invoiceCollection', $invoiceCollection->getData() ));

                if ( $invoiceCollection->count() > 0 ) {
                    /** @var Invoice $invoice */
                    foreach ($invoiceCollection as $invoice ) {
                        $this->logger->debug('#webhook.invoice.id', array('$invoice.id', $invoice->getId(), '$invoice.incrementId' => $invoice->getIncrementId() ));
                        $this->logger->debug('#webhook.invoice.state', array('$invoice', $invoice->getState() ));
                        if ( $invoice->getState() == Invoice::STATE_OPEN) {
                            $invoice->setState(Invoice::STATE_PAID);
                            $invoice->setTransactionId($charge->id);
                            $invoice->pay()->save();
                            $requiresInvoice = false;
                            break;
                        }
                        if ($invoice->getState() == Invoice::STATE_PAID) {
                            $requiresInvoice = false;
                        }
                    }
                }

                if ( $requiresInvoice ) {
                    $invoice = $this->invoiceService->prepareInvoice($order);
                    $this->logger->debug('#webhook.requireInvoice', array('$invoice.id', $invoice->getId(), '$invoice.incrementId' => $invoice->getIncrementId() ));
                    $invoice->setTransactionId($charge->id);
                    $invoice->pay()->save();
                }
                $payment = $order->getPayment();
                $payment->setAmountPaid($charge->amount);
                $payment->setIsTransactionPending(false);
                $payment->save();

                $this->logger->debug('#webhook.trx.succeeded.end', array('Magento.order.invoice' => 'saved' ) );
            }else{
                $this->logger->debug('#webhook.trx.expired.start', array('webhook.type' => 'transaction.expired', 'openpay.charge.status' => 'expired' ));

                $invoiceCollection = $order->getInvoiceCollection();
                if ( $invoiceCollection->count() > 0 ) {
                    /** @var Invoice $invoice */
                    foreach ($invoiceCollection as $invoice ) {
                        $this->logger->debug('#webhook.invoice.id', array('$invoice.id', $invoice->getId(), '$invoice.incrementId' => $invoice->getIncrementId() ));
                        $this->logger->debug('#webhook.invoice.state', array('$invoice', $invoice->getState() ));
                        $invoiceId = $invoice->getId();
                        if ( $invoice->getState() == Invoice::STATE_OPEN || $invoice->getState() != Invoice::STATE_PAID) {
                            $invoice->setState(Invoice::STATE_CANCELED);
                            $this->invoiceRepository->save($invoice);
                            break;
                        }
                    }
                }
                
                $order->cancel();
                $order->addStatusToHistory(\Magento\Sales\Model\Order::STATE_CANCELED, __("La transacción no pudo ser procesada"))->setIsCustomerNotified(true);
                $statusCanceled = $this->payment->getCustomStatus('canceled');
                $order->setState($statusCanceled)->setStatus($statusCanceled);
                $order->save();
                $this->logger->debug('#webhook.trx.expired.end', array('Magento.order.invoice' => 'saved' ) );
            }

            header('HTTP/1.1 200 OK');

        } catch (OpenpayApiConnectionError $e) {
            $this->logger->error('#Webhook.openpay_cards OpenpayApiConnectionError (openpay->charges->get()', array('message' => $e->getMessage()));
            $this->errorException($e->getCode(), $e->getMessage());
        } catch (Exception $e) {
            $this->logger->error('#webhook-cards-exception', array('msg' => $e->getMessage(), 'code' => $e->getCode()));
            $this->errorException($e->getCode(), $e->getMessage());
        }
        exit;
    }

    public function errorException($errorCode, $msg) {
        switch($errorCode) {
            case 404: 
                http_response_code (404);
                break;
            default:
                http_response_code (500);
                break;
        }
        print json_encode (array ('error' => $errorCode, 
            'message' => $msg));
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
