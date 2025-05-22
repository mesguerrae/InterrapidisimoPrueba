<?php
namespace Interrapidisimo\MauricioEsguerra\Model\Payment\MercadoPago;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Interrapidisimo\MauricioEsguerra\Model\Payment\MercadoPago\Custom as MercadoPagoPaymentMethod; // Alias for clarity

class ConfigProvider implements ConfigProviderInterface
{
    protected $_methodCode = MercadoPagoPaymentMethod::CODE; // Use constant from Custom model
    protected $_methodInstance;
    protected $_scopeConfig;

    public function __construct(
        PaymentHelper $paymentHelper,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->_methodInstance = $paymentHelper->getMethodInstance($this->_methodCode);
        $this->_scopeConfig = $scopeConfig;
    }

    public function getConfig()
    {
        if (!$this->_methodInstance->isAvailable()) {
            return [];
        }
        
        return [
            'payment' => [
                $this->_methodCode => [ // Use $this->_methodCode which is 'mercadopago_custom'
                    'publicKey' => $this->_scopeConfig->getValue(
                        MercadoPagoPaymentMethod::XML_PATH_PAYMENT_MERCADOPAGO_PUBLIC_KEY,
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                    ),
                    'countryCode' => $this->_scopeConfig->getValue(
                        MercadoPagoPaymentMethod::XML_PATH_PAYMENT_MERCADOPAGO_COUNTRY,
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                    ),
                    'title' => $this->_methodInstance->getTitle(),
                    'active' => $this->_methodInstance->isActive(),
                    // Add any other config needed by the frontend JS (e.g., base_url for API calls if needed by JS)
                ]
            ]
        ];
    }
}
