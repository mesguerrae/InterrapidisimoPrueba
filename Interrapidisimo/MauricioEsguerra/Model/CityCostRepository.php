<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Interrapidisimo\MauricioEsguerra\Model;

use Interrapidisimo\MauricioEsguerra\Api\CityCostRepositoryInterface;
use Interrapidisimo\MauricioEsguerra\Api\Data\CityCostInterface;
use Interrapidisimo\MauricioEsguerra\Api\Data\CityCostInterfaceFactory;
use Interrapidisimo\MauricioEsguerra\Api\Data\CityCostSearchResultsInterfaceFactory;
use Interrapidisimo\MauricioEsguerra\Model\ResourceModel\CityCost as ResourceCityCost;
use Interrapidisimo\MauricioEsguerra\Model\ResourceModel\CityCost\CollectionFactory as CityCostCollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class CityCostRepository implements CityCostRepositoryInterface
{

    /**
     * @var CityCostInterfaceFactory
     */
    protected $cityCostFactory;

    /**
     * @var ResourceCityCost
     */
    protected $resource;

    /**
     * @var CityCostCollectionFactory
     */
    protected $cityCostCollectionFactory;

    /**
     * @var CityCost
     */
    protected $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    protected $collectionProcessor;


    /**
     * @param ResourceCityCost $resource
     * @param CityCostInterfaceFactory $cityCostFactory
     * @param CityCostCollectionFactory $cityCostCollectionFactory
     * @param CityCostSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        ResourceCityCost $resource,
        CityCostInterfaceFactory $cityCostFactory,
        CityCostCollectionFactory $cityCostCollectionFactory,
        CityCostSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->cityCostFactory = $cityCostFactory;
        $this->cityCostCollectionFactory = $cityCostCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @inheritDoc
     */
    public function save(CityCostInterface $cityCost)
    {
        try {
            $this->resource->save($cityCost);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__(
                'Could not save the cityCost: %1',
                $exception->getMessage()
            ));
        }
        return $cityCost;
    }

    /**
     * @inheritDoc
     */
    public function get($cityCostId)
    {
        $cityCost = $this->cityCostFactory->create();
        $this->resource->load($cityCost, $cityCostId);
        if (!$cityCost->getId()) {
            throw new NoSuchEntityException(__('CityCost with id "%1" does not exist.', $cityCostId));
        }
        return $cityCost;
    }

    /**
     * @inheritDoc
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $criteria
    ) {
        $collection = $this->cityCostCollectionFactory->create();
        
        $this->collectionProcessor->process($criteria, $collection);
        
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);
        
        $items = [];
        foreach ($collection as $model) {
            $items[] = $model;
        }
        
        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(CityCostInterface $cityCost)
    {
        try {
            $cityCostModel = $this->cityCostFactory->create();
            $this->resource->load($cityCostModel, $cityCost->getCitycostId());
            $this->resource->delete($cityCostModel);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__(
                'Could not delete the CityCost: %1',
                $exception->getMessage()
            ));
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById($cityCostId)
    {
        return $this->delete($this->get($cityCostId));
    }
}

