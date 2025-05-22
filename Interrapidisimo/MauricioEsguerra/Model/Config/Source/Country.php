<?php
namespace Interrapidisimo\MauricioEsguerra\Model\Config\Source;

class Country implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'AR', 'label' => __('Argentina (AR)')],
            ['value' => 'BR', 'label' => __('Brazil (BR)')],
            ['value' => 'CL', 'label' => __('Chile (CL)')],
            ['value' => 'CO', 'label' => __('Colombia (CO)')],
            ['value' => 'MX', 'label' => __('Mexico (MX)')],
            ['value' => 'PE', 'label' => __('Peru (PE)')],
            ['value' => 'UY', 'label' => __('Uruguay (UY)')]
            // Add other countries as needed, e.g., 'VE', 'EC', 'PA', 'PY', 'BO'
            // Check Mercado Pago documentation for currently supported countries for their API.
        ];
    }
}
