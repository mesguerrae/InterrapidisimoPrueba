<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Interrapidisimo\MauricioEsguerra\Model;

use Interrapidisimo\MauricioEsguerra\Api\Data\CityCostInterface;
use Magento\Framework\Model\AbstractModel;

class CityCost extends AbstractModel implements CityCostInterface
{

    /**
     * @inheritDoc
     */
    public function _construct()
    {
        $this->_init(\Interrapidisimo\MauricioEsguerra\Model\ResourceModel\CityCost::class);
    }

    /**
     * @inheritDoc
     */
    public function getCitycostId()
    {
        return $this->getData(self::CITYCOST_ID);
    }

    /**
     * @inheritDoc
     */
    public function setCitycostId($citycostId)
    {
        return $this->setData(self::CITYCOST_ID, $citycostId);
    }

    /**
     * @inheritDoc
     */
    public function getCity()
    {
        return $this->getData(self::CITY);
    }

    /**
     * @inheritDoc
     */
    public function setCity($city)
    {
        return $this->setData(self::CITY, $city);
    }

    /**
     * @inheritDoc
     */
    public function getPrice()
    {
        return $this->getData(self::PRICE);
    }

    /**
     * @inheritDoc
     */
    public function setPrice($price)
    {
        return $this->setData(self::PRICE, $price);
    }

    /**
     * @inheritDoc
     */
    public function getActive()
    {
        return $this->getData(self::ACTIVE);
    }

    /**
     * @inheritDoc
     */
    public function setActive($active)
    {
        return $this->setData(self::ACTIVE, $active);
    }
}

