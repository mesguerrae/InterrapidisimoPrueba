<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Interrapidisimo\MauricioEsguerra\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class CityCost extends AbstractDb
{

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('interrapidisimo_mauricioesguerra_citycost', 'citycost_id');
    }
}

