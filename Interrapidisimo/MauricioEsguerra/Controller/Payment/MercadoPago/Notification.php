<?php
namespace Interrapidisimo\MauricioEsguerra\Controller\Payment\MercadoPago;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Service\CreditmemoService;
use MercadoPago; // MP SDK
use Interrapidisimo\MauricioEsguerra\Model\Payment\MercadoPago\Custom as MercadoPagoPaymentMethod; // Your payment model
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

class Notification extends Action implements CsrfAwareActionInterface
{
    protected $_orderFactory;
    protected $_scopeConfig;
    protected $_logger;
    protected $_invoiceService;
    protected $_invoiceSender;
    protected $_transactionFactory;
    protected $_creditmemoFactory;
    protected $_creditmemoService;
    protected $_orderSender;


    public function __construct(
        Context $context,
        OrderFactory $orderFactory,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        OrderSender $orderSender,
        TransactionFactory $transactionFactory,
        CreditmemoFactory $creditmemoFactory,
        CreditmemoService $creditmemoService
    ) {
        parent::__construct($context);
        $this->_orderFactory = $orderFactory;
        $this->_scopeConfig = $scopeConfig;
        $this->_logger = $logger;
        $this->_invoiceService = $invoiceService;
        $this->_invoiceSender = $invoiceSender;
        $this->_orderSender = $orderSender;
        $this->_transactionFactory = $transactionFactory;
        $this->_creditmemoFactory = $creditmemoFactory;
        $this->_creditmemoService = $creditmemoService;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true; // Disable CSRF for webhook endpoint
    }

    protected function _initMercadoPagoSDK()
    {
        $accessToken = $this->_scopeConfig->getValue(
            MercadoPagoPaymentMethod::XML_PATH_PAYMENT_MERCADOPAGO_ACCESS_TOKEN,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if (!$accessToken) {
            $this->_logger->error('MercadoPago Webhook: Access Token is not configured.');
            throw new \Exception('Mercado Pago Access Token is not configured.');
        }
        MercadoPago\SDK::setAccessToken($accessToken);
    }

    public function execute()
    {
        $request = $this->getRequest();
        $response = $this->getResponse();
        $rawBody = $request->getContent();
        $this->_logger->info('MercadoPago Webhook Received:', ['body' => $rawBody]);

        try {
            $data = json_decode($rawBody, true);

            if (!$data || !isset($data['type']) || !isset($data['data']['id'])) {
                $this->_logger->warning('MercadoPago Webhook: Invalid payload structure.', ['body' => $rawBody]);
                $response->setStatusCode(400); // Bad Request
                $response->setContent('Invalid payload.');
                return $response;
            }

            $this->_initMercadoPagoSDK();

            // Primary focus on 'payment' notifications for card payments.
            // Merchant Order notifications ('merchant_order') are more for multi-payment scenarios or when using Checkout Pro.
            if ($data['type'] === 'payment') {
                $paymentId = $data['data']['id'];
                $this->_logger->info('MercadoPago Webhook: Processing payment ID: ' . $paymentId);

                $mpPayment = MercadoPago\Payment::find_by_id($paymentId);

                if (!$mpPayment) {
                    $this->_logger->error('MercadoPago Webhook: Payment ID ' . $paymentId . ' not found via API.');
                    $response->setStatusCode(404); // Not found
                    $response->setContent('Payment not found via API.');
                    return $response;
                }

                $this->_logger->info('MercadoPago Webhook: Payment data from API:', $mpPayment->toArray());
                $orderIncrementId = $mpPayment->external_reference;

                if (!$orderIncrementId) {
                    $this->_logger->error('MercadoPago Webhook: External reference (Order ID) missing from MP Payment ' . $paymentId);
                    // Still return 200 to MP to acknowledge, but log error.
                    $response->setStatusCode(200);
                    $response->setContent('Webhook Acknowledged. Error: Missing external reference.');
                    return $response;
                }

                $order = $this->_orderFactory->create()->loadByIncrementId($orderIncrementId);

                if (!$order->getId()) {
                    $this->_logger->error('MercadoPago Webhook: Order not found in Magento. OrderIncrementId: ' . $orderIncrementId);
                    $response->setStatusCode(404);
                    $response->setContent('Order not found in Magento.');
                    return $response;
                }
                
                // Ensure payment method is indeed our MercadoPago method
                if ($order->getPayment()->getMethodInstance()->getCode() !== MercadoPagoPaymentMethod::CODE) {
                    $this->_logger->warning('MercadoPago Webhook: Order ' . $orderIncrementId . ' was not paid with MercadoPago. Skipping.');
                    $response->setStatusCode(200);
                    $response->setContent('Order not paid with this method.');
                    return $response;
                }

                $this->processOrderStatus($order, $mpPayment);

            } else if ($data['type'] === 'test.created') { // For testing webhook from MP panel
                 $this->_logger->info('MercadoPago Webhook: Test notification received successfully.');
            } else {
                $this->_logger->info('MercadoPago Webhook: Received notification type ' . $data['type'] . ', not processed by this handler.');
            }

            $response->setStatusCode(200);
            $response->setContent('Webhook Acknowledged.');

        } catch (\Exception $e) {
            $this->_logger->critical('MercadoPago Webhook Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $response->setStatusCode(500); // Internal Server Error
            $response->setContent('Error processing webhook: ' . $e->getMessage());
        }
        return $response;
    }

    protected function processOrderStatus(Order $order, MercadoPago\Payment $mpPayment)
    {
        $status = $mpPayment->status;
        $statusDetail = $mpPayment->status_detail;
        $paymentId = $mpPayment->id;
        $orderComment = '';

        $this->_logger->info('Processing Order Status for Magento Order: ' . $order->getIncrementId() . ', MP Status: ' . $status . ', MP Status Detail: ' . $statusDetail);

        // Avoid processing if order is already in a final state (canceled/complete) unless it's a refund
        if ($order->isCanceled() && $status !== 'refunded') {
             $this->_logger->info('Order ' . $order->getIncrementId() . ' is already canceled. Skipping status update unless refund.');
             return;
        }
         if ($order->getState() === Order::STATE_COMPLETE && $status !== 'refunded') {
             $this->_logger->info('Order ' . $order->getIncrementId() . ' is already complete. Skipping status update unless refund.');
             return;
        }


        switch ($status) {
            case 'approved':
                if ($order->canInvoice() && !$order->hasInvoices()) {
                    $this->createInvoice($order, $mpPayment);
                    $orderComment = __('Payment approved by Mercado Pago (ID: %1). Invoice created.', $paymentId);
                    $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
                } else {
                    $orderComment = __('Payment approved by MercadoPago (ID: %1). Order already invoiced or cannot be invoiced.', $paymentId);
                    // If already invoiced, ensure it's processing
                    if ($order->getState() !== Order::STATE_PROCESSING) {
                         $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
                    }
                }
                // Ensure order email is sent if not already
                if (!$order->getEmailSent()) {
                    $this->_orderSender->send($order);
                }
                break;

            case 'in_process':
            case 'pending':
                $orderComment = __('Payment is pending or in process with Mercado Pago (ID: %1, Status: %2, Detail: %3).', $paymentId, $status, $statusDetail);
                if ($order->getState() !== Order::STATE_PENDING_PAYMENT) {
                    $order->setState(Order::STATE_PENDING_PAYMENT)->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PENDING_PAYMENT));
                }
                break;

            case 'rejected':
                $orderComment = __('Payment rejected by Mercado Pago (ID: %1, Detail: %2).', $paymentId, $statusDetail);
                if ($order->canCancel()) {
                    $order->cancel();
                    $order->setState(Order::STATE_CANCELED)->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_CANCELED));
                } else {
                    $order->setState(Order::STATE_CANCELED)->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_CANCELED));
                     $this->_logger->warning('Order ' . $order->getIncrementId() . ' could not be programmatically cancelled for rejection, but status set to canceled.');
                }
                break;

            case 'refunded': // Fully refunded
                $orderComment = __('Payment fully refunded by Mercado Pago (ID: %1).', $paymentId);
                 if ($order->canCreditmemo()) {
                     $this->createCreditMemo($order, $mpPayment, true); // true for full refund
                 } else {
                      $this->_logger->info('Order ' . $order->getIncrementId() . ' cannot be creditmemoed for full refund (already refunded or state issue).');
                 }
                // Magento might set to 'closed' or 'canceled' depending on what was refunded.
                // If fully refunded, often it's 'closed'.
                $order->setState(Order::STATE_CLOSED)->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_CLOSED));
                break;
            
            case 'partially_refunded':
                 $orderComment = __('Payment partially refunded by Mercado Pago (ID: %1). Amount refunded: %2', $paymentId, $mpPayment->transaction_details->total_refunded_amount ?? $mpPayment->amount_refunded);
                 // Partial refund logic might need more specifics, e.g., creating a partial credit memo.
                 // For now, just log and add comment. Magento doesn't have a specific "partially_refunded" state.
                 // Order remains in 'processing' or its current state.
                 // $this->createCreditMemo($order, $mpPayment, false, $mpPayment->amount_refunded); // Example for partial
                 break;

            case 'cancelled': // Or 'canceled'
                $orderComment = __('Payment cancelled by Mercado Pago (ID: %1, Detail: %2).', $paymentId, $statusDetail);
                 if ($order->canCancel()) {
                    $order->cancel();
                    $order->setState(Order::STATE_CANCELED)->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_CANCELED));
                } else {
                    $order->setState(Order::STATE_CANCELED)->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_CANCELED));
                }
                break;
            
            default:
                $orderComment = __('Mercado Pago notification received with unhandled status: %1 (ID: %2).', $status, $paymentId);
                $this->_logger->warning($orderComment);
                break;
        }

        if ($orderComment) {
            $order->addStatusHistoryComment($orderComment, true); // true to make it visible to customer depending on status config
            $order->save();
            $this->_logger->info('Order ' . $order->getIncrementId() . ' updated. Comment: ' . $orderComment);
        }
    }

    protected function createInvoice(Order $order, MercadoPago\Payment $mpPayment)
    {
        try {
            $invoice = $this->_invoiceService->prepareInvoice($order);
            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
            $invoice->setTransactionId($mpPayment->id);
            $invoice->register();
            
            $transaction = $this->_transactionFactory->create()
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
            $transaction->save();

            $this->_invoiceSender->send($invoice);
            $order->addStatusHistoryComment(__('Invoice #%1 created.', $invoice->getIncrementId()), true);
            $this->_logger->info('Invoice ' . $invoice->getIncrementId() . ' created for order ' . $order->getIncrementId());
        } catch (\Exception $e) {
            $this->_logger->error('Error creating invoice for order ' . $order->getIncrementId() . ': ' . $e->getMessage());
            $order->addStatusHistoryComment(__('Failed to create invoice for Mercado Pago payment ID %1: %2', $mpPayment->id, $e->getMessage()))->save();
        }
    }

    protected function createCreditMemo(Order $order, MercadoPago\Payment $mpPayment, $isFullRefund = false, $amount = null) {
        try {
            // For full refund, we can let Magento calculate amounts.
            // For partial, we'd need to specify items or adjustment amounts.
            // This example is simplified for full refund based on MP notification.
            // A more robust partial refund would need items_to_refund from MP or admin.
            
            $invoice = null;
            foreach ($order->getInvoiceCollection() as $inv) {
                if ($inv->canRefund()) {
                    $invoice = $inv;
                    break;
                }
            }

            if (!$invoice) {
                $this->_logger->warning('No invoice available to refund for order ' . $order->getIncrementId());
                $order->addStatusHistoryComment(__('Could not create credit memo: No invoice available to refund.'), true)->save();
                return;
            }

            // If specific amount for partial refund, prepare arguments for credit memo service
            $creditMemoData = [];
            if (!$isFullRefund && $amount !== null) {
                // This is complex. For simplicity, we're focusing on full refund triggered by webhook.
                // Partial refund via webhook would require more detailed mapping or assumptions.
                // Let's assume for now that 'refunded' means full.
                // 'partially_refunded' would need a different handling path, potentially creating a partial CM.
                // For now, this function is primarily for 'refunded' status.
                $this->_logger->info('Partial refund via webhook is complex. This function primarily handles full refunds. Amount: ' . $amount);
            }


            $creditmemo = $this->_creditmemoFactory->createByOrder($order, $creditMemoData);
            // $creditmemo->setInvoice($invoice); // Link to invoice
            
            // If it's a specific refund amount (usually from partial refund), set adjustment
            // if ($amount > 0 && !$isFullRefund) {
            //    $creditmemo->setAdjustmentPositive($amount); // Or setBaseAdjustment, etc.
            // }
            
            $this->_creditmemoService->refund($creditmemo, true); // true for online refund
            $order->addStatusHistoryComment(__('Credit Memo #%1 created for Mercado Pago refund ID %2.', $creditmemo->getIncrementId(), $mpPayment->id), true);
            $this->_logger->info('Credit Memo ' . $creditmemo->getIncrementId() . ' created for order ' . $order->getIncrementId());

        } catch (\Exception $e) {
            $this->_logger->error('Error creating credit memo for order ' . $order->getIncrementId() . ': ' . $e->getMessage());
             $order->addStatusHistoryComment(__('Failed to create credit memo for Mercado Pago refund ID %1: %2', $mpPayment->id, $e->getMessage()))->save();
        }
    }
}
