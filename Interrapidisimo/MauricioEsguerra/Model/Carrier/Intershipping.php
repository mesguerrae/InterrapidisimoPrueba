<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Interrapidisimo\MauricioEsguerra\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;
use Interrapidisimo\MauricioEsguerra\Api\CityCostRepositoryInterface;
use Interrapidisimo\MauricioEsguerra\Api\Data\CityCostInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;


class Intershipping extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements
    \Magento\Shipping\Model\Carrier\CarrierInterface
{

    protected $_code = 'intershipping';

    protected $_isFixed = true;

    protected $_rateResultFactory;

    protected $_rateMethodFactory;

    protected $_cityCostRepository;

    protected $_cityCostModel;

    protected $_searchCriteriaBuilder;



    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param Interrapidisimo\MauricioEsguerra\Api\CityCostRepositoryInterface;
     * @param use Interrapidisimo\MauricioEsguerra\Api\Data\CityCostInterface;
     * @param use Magento\Framework\Api\SearchCriteriaBuilder;
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        CityCostRepositoryInterface $cityCostRepository,
        CityCostInterface $cityCostModel,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        array $data = []
    ) {
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_cityCostRepository = $cityCostRepository;      
        $this->_cityCostModel = $cityCostModel;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $shippingPrice = $this->getConfigData('price');

        $result = $this->_rateResultFactory->create();

        if ($shippingPrice !== false) {
            $method = $this->_rateMethodFactory->create();

            $method->setCarrier($this->_code);
            $method->setCarrierTitle($this->getConfigData('title'));

            $method->setMethod($this->_code);
            $method->setMethodTitle($this->getConfigData('name'));

            if ($request->getFreeShipping() === true) {
                $shippingPrice = '0.00';
            }


            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/intershipping.log');
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);

            $logger->info("Ciudad destino: ". $request->getDestCity() );
            $city = $request->getDestCity();     
            

            try {
                $filters = $this->_searchCriteriaBuilder
                        ->addFilter("city", $request->getDestCity(), 'eq')
                        ->addFilter("active", true, 'eq');

                $cityCost = $this->_cityCostRepository->getList($filters->create());
                $logger->info("cityCost ". $cityCost->getTotalCount() );
                foreach($cityCost->getItems() as $item){
                    $logger->info("Precio: ". $item->getPrice() );
                    $shippingPrice = $item->getPrice();
                }
            } catch (\Throwable $th) {
                $logger->info("Exception ". $th->__toString() );
            }

            $method->setPrice($shippingPrice);
            $method->setCost($shippingPrice);

            $result->append($method);
        }

        return $result;
    }

    /**
     * getAllowedMethods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData('name')];
    }
}
