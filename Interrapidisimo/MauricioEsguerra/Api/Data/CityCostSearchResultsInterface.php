<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Interrapidisimo\MauricioEsguerra\Api\Data;

interface CityCostSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{

    /**
     * Get CityCost list.
     * @return \Interrapidisimo\MauricioEsguerra\Api\Data\CityCostInterface[]
     */
    public function getItems();

    /**
     * Set city list.
     * @param \Interrapidisimo\MauricioEsguerra\Api\Data\CityCostInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}

