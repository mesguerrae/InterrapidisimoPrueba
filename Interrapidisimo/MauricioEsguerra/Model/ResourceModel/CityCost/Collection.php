<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Interrapidisimo\MauricioEsguerra\Model\ResourceModel\CityCost;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{

    /**
     * @inheritDoc
     */
    protected $_idFieldName = 'citycost_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(
            \Interrapidisimo\MauricioEsguerra\Model\CityCost::class,
            \Interrapidisimo\MauricioEsguerra\Model\ResourceModel\CityCost::class
        );
    }
}

