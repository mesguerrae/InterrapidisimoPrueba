<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Interrapidisimo\MauricioEsguerra\Ui\Component\Listing\Column;

class CityCostActions extends \Magento\Ui\Component\Listing\Columns\Column
{

    const URL_PATH_EDIT = 'interrapidisimo_mauricioesguerra/citycost/edit';
    const URL_PATH_DETAILS = 'interrapidisimo_mauricioesguerra/citycost/details';
    protected $urlBuilder;
    const URL_PATH_DELETE = 'interrapidisimo_mauricioesguerra/citycost/delete';

    /**
     * @param \Magento\Framework\View\Element\UiComponent\ContextInterface $context
     * @param \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
        \Magento\Framework\UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if (isset($item['citycost_id'])) {
                    $item[$this->getData('name')] = [
                        'edit' => [
                            'href' => $this->urlBuilder->getUrl(
                                static::URL_PATH_EDIT,
                                [
                                    'citycost_id' => $item['citycost_id']
                                ]
                            ),
                            'label' => __('Editar')
                        ],
                        'delete' => [
                            'href' => $this->urlBuilder->getUrl(
                                static::URL_PATH_DELETE,
                                [
                                    'citycost_id' => $item['citycost_id']
                                ]
                            ),
                            'label' => __('Borrar'),
                            'confirm' => [
                                'title' => __('Delete "${ $.$data.title }"'),
                                'message' => __('¿Estás segura de que no quieres eliminar  "${ $.$data.title }" registro?')
                            ]
                        ]
                    ];
                }
            }
        }
        
        return $dataSource;
    }
}

