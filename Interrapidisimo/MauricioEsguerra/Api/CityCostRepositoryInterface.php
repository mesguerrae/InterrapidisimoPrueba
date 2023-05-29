<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Interrapidisimo\MauricioEsguerra\Api;

use Magento\Framework\Api\SearchCriteriaInterface;

interface CityCostRepositoryInterface
{

    /**
     * Save CityCost
     * @param \Interrapidisimo\MauricioEsguerra\Api\Data\CityCostInterface $cityCost
     * @return \Interrapidisimo\MauricioEsguerra\Api\Data\CityCostInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(
        \Interrapidisimo\MauricioEsguerra\Api\Data\CityCostInterface $cityCost
    );

    /**
     * Retrieve CityCost
     * @param string $citycostId
     * @return \Interrapidisimo\MauricioEsguerra\Api\Data\CityCostInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function get($citycostId);

    /**
     * Retrieve CityCost matching the specified criteria.
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Interrapidisimo\MauricioEsguerra\Api\Data\CityCostSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    );

    /**
     * Delete CityCost
     * @param \Interrapidisimo\MauricioEsguerra\Api\Data\CityCostInterface $cityCost
     * @return bool true on success
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(
        \Interrapidisimo\MauricioEsguerra\Api\Data\CityCostInterface $cityCost
    );

    /**
     * Delete CityCost by ID
     * @param string $citycostId
     * @return bool true on success
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($citycostId);
}

