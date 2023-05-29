<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Interrapidisimo\MauricioEsguerra\Api\Data;

interface CityCostInterface
{

    const ACTIVE = 'active';
    const CITYCOST_ID = 'citycost_id';
    const CITY = 'city';
    const PRICE = 'price';

    /**
     * Get citycost_id
     * @return string|null
     */
    public function getCitycostId();

    /**
     * Set citycost_id
     * @param string $citycostId
     * @return \Interrapidisimo\MauricioEsguerra\CityCost\Api\Data\CityCostInterface
     */
    public function setCitycostId($citycostId);

    /**
     * Get city
     * @return string|null
     */
    public function getCity();

    /**
     * Set city
     * @param string $city
     * @return \Interrapidisimo\MauricioEsguerra\CityCost\Api\Data\CityCostInterface
     */
    public function setCity($city);

    /**
     * Get price
     * @return string|null
     */
    public function getPrice();

    /**
     * Set price
     * @param string $price
     * @return \Interrapidisimo\MauricioEsguerra\CityCost\Api\Data\CityCostInterface
     */
    public function setPrice($price);

    /**
     * Get active
     * @return string|null
     */
    public function getActive();

    /**
     * Set active
     * @param string $active
     * @return \Interrapidisimo\MauricioEsguerra\CityCost\Api\Data\CityCostInterface
     */
    public function setActive($active);
}

