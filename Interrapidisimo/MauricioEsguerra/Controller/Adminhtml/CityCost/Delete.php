<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Interrapidisimo\MauricioEsguerra\Controller\Adminhtml\CityCost;

class Delete extends \Interrapidisimo\MauricioEsguerra\Controller\Adminhtml\CityCost
{

    /**
     * Delete action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        // check if we know what should be deleted
        $id = $this->getRequest()->getParam('citycost_id');
        if ($id) {
            try {
                // init model and delete
                $model = $this->_objectManager->create(\Interrapidisimo\MauricioEsguerra\Model\CityCost::class);
                $model->load($id);
                $model->delete();
                // display success message
                $this->messageManager->addSuccessMessage(__('Ha borrado el costo.'));
                // go to grid
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                // display error message
                $this->messageManager->addErrorMessage($e->getMessage());
                // go back to edit form
                return $resultRedirect->setPath('*/*/edit', ['citycost_id' => $id]);
            }
        }
        // display error message
        $this->messageManager->addErrorMessage(__('No se encontro costo a borrar.'));
        // go to grid
        return $resultRedirect->setPath('*/*/');
    }
}

