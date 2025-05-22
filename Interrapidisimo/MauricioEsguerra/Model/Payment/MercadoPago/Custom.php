<?php
namespace Interrapidisimo\MauricioEsguerra\Model\Payment\MercadoPago;

use Magento\Payment\Model\Method\Cc;
use Magento\Framework\Exception\LocalizedException;
use MercadoPago; // Alias if SDK is namespaced

class Custom extends Cc
{
    public const CODE = 'mercadopago_custom'; // Public for ConfigProvider

    protected $_code = self::CODE; // Use the constant
    protected $_isGateway = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_canUseCheckout = true;

    protected $_formBlockType = \Interrapidisimo\MauricioEsguerra\Block\Payment\MercadoPago\Form\Cc::class;

    const XML_PATH_PAYMENT_MERCADOPAGO_ACTIVE = 'payment/mercadopago_custom/active';
    const XML_PATH_PAYMENT_MERCADOPAGO_TITLE = 'payment/mercadopago_custom/title';
    const XML_PATH_PAYMENT_MERCADOPAGO_PUBLIC_KEY = 'payment/mercadopago_custom/public_key';
    const XML_PATH_PAYMENT_MERCADOPAGO_ACCESS_TOKEN = 'payment/mercadopago_custom/access_token';
    const XML_PATH_PAYMENT_MERCADOPAGO_COUNTRY = 'payment/mercadopago_custom/country_code';

    protected $_mercadopagoSdk;
    protected $_scopeConfig;
    protected $_logger; // Ensure logger is assigned in constructor

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger, // Correctly typed logger
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        array $data = []
    ) {
        parent::__construct(
            $context, $registry, $extensionFactory, $customAttributeFactory,
            $paymentData, $scopeConfig, $logger, $moduleList, $localeDate, null, null, $data
        );
        $this->_scopeConfig = $scopeConfig;
        $this->_logger = $logger; // Assign logger
    }

    protected function _initMercadoPagoSDK()
    {
        $accessToken = $this->_getAccessToken();
        if (!$accessToken) {
            $this->_logger->error('MercadoPago: Access Token is not configured.');
            throw new LocalizedException(__('Mercado Pago Access Token is not configured.'));
        }
        MercadoPago\SDK::setAccessToken($accessToken);
    }

    protected function _getAccessToken()
    {
        return $this->_scopeConfig->getValue(
            self::XML_PATH_PAYMENT_MERCADOPAGO_ACCESS_TOKEN,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    
    protected function _getPublicKey()
    {
        return $this->_scopeConfig->getValue(
            self::XML_PATH_PAYMENT_MERCADOPAGO_PUBLIC_KEY,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    protected function _getCountryCode()
    {
        return $this->_scopeConfig->getValue(
            self::XML_PATH_PAYMENT_MERCADOPAGO_COUNTRY,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    
    public function getTitle()
    {
        return $this->_scopeConfig->getValue(
            self::XML_PATH_PAYMENT_MERCADOPAGO_TITLE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function isActive($storeId = null)
    {
        return (bool)(int)$this->_scopeConfig->getValue(
            self::XML_PATH_PAYMENT_MERCADOPAGO_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if (!$this->isActive($quote ? $quote->getStoreId() : null)) {
            return false;
        }
        if (!$this->_getAccessToken() || !$this->_getPublicKey()) {
            $this->_logger->debug('MercadoPago: Not available due to missing API keys.');
            return false;
        }
        if (!$this->_getCountryCode()){
             $this->_logger->debug('MercadoPago: Country code not configured.');
             return false; // Essential for API calls
        }
        return parent::isAvailable($quote);
    }

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->_logger->debug('MercadoPago Authorize:', [$payment->getOrder()->getIncrementId(), $amount]);
        if (!$this->canAuthorize()) {
            throw new LocalizedException(__('Authorize action is not available.'));
        }
        // For MercadoPago, actual authorization usually happens with token creation or during capture.
        // This method might be used if a pre-authorization step is explicitly configured.
        // For now, mark as pending and expect capture to do the work.
        $payment->setTransactionId($payment->getOrder()->getIncrementId() . '-auth-' . time()); 
        $payment->setIsTransactionClosed(false); 
        return $this;
    }

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->_logger->debug('MercadoPago Capture:', ['order_id' => $payment->getOrder()->getIncrementId(), 'amount' => $amount]);

        if (!$this->canCapture()) {
            throw new LocalizedException(__('The capture action is unavailable.'));
        }

        $this->_initMercadoPagoSDK();
        $order = $payment->getOrder();
        $billingAddress = $order->getBillingAddress();

        $token = $payment->getAdditionalInformation('mercadopago_card_token');
        $paymentMethodId = $payment->getAdditionalInformation('mercadopago_payment_method_id');
        $issuerId = $payment->getAdditionalInformation('mercadopago_issuer_id');
        $installments = $payment->getAdditionalInformation('mercadopago_installments');

        if (!$token || !$paymentMethodId || $installments === null) { // Installments can be 0 or 1 for some regions, so check for null
            $this->_logger->error('MercadoPago Capture Error: Missing payment information from frontend.', [
                'has_token' => !empty($token),
                'has_payment_method_id' => !empty($paymentMethodId),
                'has_installments' => $installments !== null
            ]);
            throw new LocalizedException(__('Mercado Pago payment information (token, payment method ID, or installments) is missing. Ensure frontend is correctly passing data.'));
        }
        
        $payment_data = new MercadoPago\Payment();
        $payment_data->transaction_amount = (float)$amount;
        $payment_data->token = $token;
        $payment_data->description = "Order #" . $order->getIncrementId() . " for " . $order->getStore()->getFrontendName();
        $payment_data->installments = (int)$installments;
        $payment_data->payment_method_id = $paymentMethodId;
        if ($issuerId) {
            $payment_data->issuer_id = (string)$issuerId;
        }
        $payment_data->external_reference = $order->getIncrementId();

        $payer = new MercadoPago\Payer();
        $payer->email = $billingAddress->getEmail();
        // It's highly recommended to send as much payer data as possible for fraud prevention
        $payer->first_name = $billingAddress->getFirstname();
        $payer->last_name = $billingAddress->getLastname();
        
        // Payer address (Optional but recommended)
        // $addressData = new MercadoPago\Address();
        // $addressData->zip_code = $billingAddress->getPostcode();
        // $addressData->street_name = $billingAddress->getStreetLine(1);
        // $addressData->street_number = $billingAddress->getStreetLine(2); // Or parse from line 1 if needed
        // $payer->address = $addressData;

        $payment_data->payer = $payer;

        // Add notification URL once the controller is created
        // $payment_data->notification_url = $order->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB, true) . 'rest/V1/mercadopago/notification';
        // $this->_logger->debug('Notification URL set to: ' . $payment_data->notification_url);


        $this->_logger->debug('MercadoPago Payment Request:', [json_encode($payment_data->toArray())]);

        try {
            $payment_data->save(); 
            $this->_logger->debug('MercadoPago Payment Response:', [$payment_data->status, $payment_data->status_detail, $payment_data->id, json_encode($payment_data->toArray())]);

            if ($payment_data->id == null) {
                $api_error = $payment_data->last_error;
                $errorMessage = 'Payment processing failed: No Payment ID received.';
                if ($api_error && $api_error->message) {
                    $errorMessage .= " API Error: " . $api_error->message;
                } else if (is_array($payment_data->error) && isset($payment_data->error['message'])) {
                    $errorMessage .= " API Error: " . $payment_data->error['message'];
                }
                $this->_logger->error('MercadoPago Capture Error: ' . $errorMessage, ['response_array' => $payment_data->toArray()]);
                throw new LocalizedException(__($errorMessage));
            }

            $payment->setLastTransId($payment_data->id);
            $payment->setTransactionId($payment_data->id);
            $payment->setTransactionAdditionalInfo(
                \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS,
                [
                    'status' => $payment_data->status,
                    'status_detail' => $payment_data->status_detail,
                    'id' => $payment_data->id,
                    'payment_method_id' => $payment_data->payment_method_id,
                    'transaction_amount' => $payment_data->transaction_amount
                ]
            );

            if ($payment_data->status == 'approved') {
                $payment->setIsTransactionClosed(true); 
            } elseif ($payment_data->status == 'in_process' || $payment_data->status == 'pending') {
                $payment->setIsTransactionClosed(false);
                $payment->setIsTransactionPending(true);
            } else {
                $errorMsg = 'Payment was not approved by Mercado Pago.';
                if (isset($payment_data->status_detail)) {
                    $errorMsg .= ' Detail: ' . $payment_data->status_detail;
                }
                // Check if error is an object with a message property
                if (is_object($payment_data->error) && property_exists($payment_data->error, 'message')) {
                     $errorMsg .= ' API Error: ' . $payment_data->error->message;
                } elseif (is_string($payment_data->error)) { // Fallback if error is a simple string
                     $errorMsg .= ' API Error: ' . $payment_data->error;
                }

                $this->_logger->error('MercadoPago Payment Failed:', ['status' => $payment_data->status, 'status_detail' => $payment_data->status_detail, 'response_array' => $payment_data->toArray()]);
                throw new LocalizedException(__($errorMsg));
            }

        } catch (LocalizedException $e) {
            throw $e; // Re-throw Magento specific exceptions
        } catch (MercadoPago\Exception\MPException $e) { // Catch MercadoPago SDK specific exceptions
            $this->_logger->critical('MercadoPago SDK Exception during Capture: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw new LocalizedException(__('Mercado Pago API Error: %1', $e->getMessage()));
        }
        catch (\Exception $e) {
            $this->_logger->critical('Generic Exception during MercadoPago Capture: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw new LocalizedException(__('An error occurred while processing payment with Mercado Pago: %1', $e->getMessage()));
        }

        return $this;
    }

    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->_logger->debug('MercadoPago Refund:', ['order_id' => $payment->getOrder()->getIncrementId(), 'amount' => $amount]);
        if (!$this->canRefund()) {
            throw new LocalizedException(__('The refund action is unavailable.'));
        }

        $this->_initMercadoPagoSDK();
        $paymentId = $payment->getParentTransactionId() ?: $payment->getLastTransId();

        if (!$paymentId) {
            throw new LocalizedException(__('Cannot refund without a valid Mercado Pago Payment ID.'));
        }

        try {
            $mpPayment = MercadoPago\Payment::find_by_id($paymentId);
            if (!$mpPayment) {
                throw new LocalizedException(__('Mercado Pago Payment ID %1 not found.', $paymentId));
            }
            
            // Check if payment can be refunded
            if ($mpPayment->status == 'refunded' || $mpPayment->status == 'cancelled') {
                 throw new LocalizedException(__('Payment %1 has already been refunded or cancelled.', $paymentId));
            }
            if ($mpPayment->amount_refunded >= $mpPayment->transaction_amount_refunded + $amount) {
                 // This logic might be off, should be total transaction amount vs already refunded + current amount
            }


            $this->_logger->debug('Attempting to refund ' . $amount . ' for MP Payment ID ' . $paymentId);
            
            // For dx-php v3, the refund is typically done by creating a refund object on the payment
            // or by making a POST request to the refunds endpoint.
            // $mpPayment->refund($amount); // This might be for full refund or specific SDK versions
            
            // Let's use the POST request approach as it's more explicit for partial/full based on amount
            $refund_payload = ['amount' => (float)$amount];
            $refund_response_raw = MercadoPago\SDK::post(
                "/v1/payments/" . $paymentId . "/refunds",
                json_encode($refund_payload) // SDK might handle json_encode, but being explicit is safer
            );
            
            $this->_logger->debug('MercadoPago Refund API Response:', [$refund_response_raw]);

            // The dx-php SDK's post method usually returns an array with 'status' and 'response' keys
            if (isset($refund_response_raw['status']) && $refund_response_raw['status'] >= 200 && $refund_response_raw['status'] < 300) {
                $refund_data = $refund_response_raw['response'];
                $payment->setTransactionId($refund_data['id'] ?? $paymentId . '-refund-' . time());
                $payment->setParentTransactionId($paymentId);
                $payment->setIsTransactionClosed(true); 
                $payment->setShouldCloseParentTransaction(true); // Or partially closed if partial refund
                $this->_logger->info('MercadoPago Refund successful for Magento Order: ' . $payment->getOrder()->getIncrementId() . ', MP Payment ID: ' . $paymentId . ', Refund ID: ' . ($refund_data['id'] ?? 'N/A'));
            } else {
                $errorMsg = 'Mercado Pago refund failed.';
                if(isset($refund_response_raw['response']['message'])) {
                    $errorMsg .= ' ' . $refund_response_raw['response']['message'];
                } else if (isset($refund_response_raw['message'])) {
                     $errorMsg .= ' ' . $refund_response_raw['message'];
                }
                 $this->_logger->error('MercadoPago Refund Error:', ['payment_id' => $paymentId, 'response' => $refund_response_raw]);
                throw new LocalizedException(__($errorMsg));
            }

        } catch (LocalizedException $e) {
            throw $e; 
        } catch (MercadoPago\Exception\MPException $e) {
            $this->_logger->critical('MercadoPago SDK Exception during Refund: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw new LocalizedException(__('Mercado Pago API Error during refund: %1', $e->getMessage()));
        } catch (\Exception $e) {
            $this->_logger->critical('Generic Exception during MercadoPago Refund: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw new LocalizedException(__('An error occurred while processing refund with Mercado Pago: %1', $e->getMessage()));
        }
        return $this;
    }

    public function void(\Magento\Payment\Model\InfoInterface $payment)
    {
        $this->_logger->debug('MercadoPago Void:', ['order_id' => $payment->getOrder()->getIncrementId()]);
        if (!$this->canVoid()) {
            throw new LocalizedException(__('The void action is unavailable.'));
        }

        $this->_initMercadoPagoSDK();
        // Void typically applies to an authorized transaction that hasn't been captured.
        // Or cancelling a payment that is 'pending' or 'in_process'.
        $paymentId = $payment->getParentTransactionId() ?: $payment->getLastTransId();

        if (!$paymentId) {
            throw new LocalizedException(__('Cannot void without a valid Mercado Pago Payment ID or transaction to void.'));
        }
        
        try {
            $mpPayment = MercadoPago\Payment::find_by_id($paymentId);
            if (!$mpPayment) {
                throw new LocalizedException(__('Mercado Pago Payment ID %1 not found for void.', $paymentId));
            }

            // Check if payment can be cancelled (e.g., 'pending', 'in_process')
            // 'authorized' status would also be cancellable if pre-auth was done.
            if (!in_array($mpPayment->status, ['pending', 'in_process', 'authorized'])) {
                 throw new LocalizedException(__('Payment cannot be voided. Status is %1.', $mpPayment->status));
            }
            
            $mpPayment->status = "cancelled"; 
            $update_response = $mpPayment->update(); 

            $this->_logger->debug('MercadoPago Void/Cancel API Response:', [$update_response, $mpPayment->status, $mpPayment->toArray()]);

            if ($mpPayment->status == 'cancelled') {
                $payment->setTransactionId($paymentId . '-void-' . time()); 
                $payment->setParentTransactionId($paymentId);
                $payment->setIsTransactionClosed(true);
                $payment->setShouldCloseParentTransaction(true); 
                $this->_logger->info('MercadoPago Void successful for Magento Order: ' . $payment->getOrder()->getIncrementId() . ', MP Payment ID: ' . $paymentId);
            } else {
                $errorMsg = 'Mercado Pago void/cancellation failed.';
                 if (isset($mpPayment->last_error) && $mpPayment->last_error->message) { // dx-php v2 style error
                    $errorMsg .= ' API Error: ' . $mpPayment->last_error->message;
                } elseif (is_array($update_response) && isset($update_response['response']['message'])) { // dx-php v3 style error
                     $errorMsg .= ' API Error: ' . $update_response['response']['message'];
                }
                $this->_logger->error('MercadoPago Void/Cancel Error:', ['payment_id' => $paymentId, 'current_status' => $mpPayment->status, 'response' => $mpPayment->toArray()]);
                throw new LocalizedException(__($errorMsg));
            }

        } catch (LocalizedException $e) {
            throw $e;
        } catch (MercadoPago\Exception\MPException $e) {
            $this->_logger->critical('MercadoPago SDK Exception during Void: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw new LocalizedException(__('Mercado Pago API Error during void: %1', $e->getMessage()));
        }
        catch (\Exception $e) {
            $this->_logger->critical('Generic Exception during MercadoPago Void: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw new LocalizedException(__('An error occurred while voiding/cancelling with Mercado Pago: %1', $e->getMessage()));
        }
        return $this;
    }

    // Implement assignData, validate, and other necessary methods from Cc or AbstractMethod if needed.
    // public function assignData(\Magento\Framework\DataObject $data)
    // {
    //     parent::assignData($data);
    //     $additionalData = $data->getData(\Magento\Quote\Api\Data\PaymentInterface::KEY_ADDITIONAL_DATA);
    //     if (is_array($additionalData)) {
    //        // $this->getInfoInstance()->setAdditionalInformation('mercadopago_card_token', $additionalData['mercadopago_card_token'] ?? null);
    //        // ... and so on for other fields like payment_method_id, issuer_id, installments
    //     }
    //     return $this;
    // }
}
