<?php

namespace Ingenico\Import\Plugin;

class MagentoConfigBlockSystemConfigEdit
{
    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $request;

    public function __construct(
        \Magento\Framework\App\Request\Http $request
    ) {
        $this->request = $request;
    }

    /**
     * Rename button from "Save" to "Submit" on Registration Page
     */
    public function afterSetLayout(\Magento\Config\Block\System\Config\Edit $subject, $result)
    {
        // rename button on registration page
        if ($this->request->getParam('section') === 'ingenico_importer_section') {
            $subject->getToolbar()->getChildBlock('save_button')->setLabel(__('Import'));
        }
        return $result;
    }
}
