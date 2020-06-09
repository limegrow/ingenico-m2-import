<?php

namespace Ingenico\Import\Plugin;

use Ingenico\Import\Model\Import;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;

class MagentoConfigControllerAdminhtmlSystemConfigSave
{
    /**
     * @var Import
     */
    protected $import;

    /**
     * @var \Ingenico\Import\Helper\Encryption
     */
    protected $encryption;

    /**
     * @var \Ingenico\Import\Logger\Logger
     */
    protected $logger;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    protected $_storeManager;
    protected $_resultRedirectFactory;
    protected $_adminSession;
    protected $_messageManager;

    /**
     * @var \Magento\Config\Model\ResourceModel\Config
     */
    private $resourceConfig;

    /**
     * @var \Magento\Framework\HTTP\PhpEnvironment\Request
     */
    protected $request;

    protected $_redirect = true;

    public function __construct(
        \Ingenico\Import\Model\Import $import,
        \Ingenico\Import\Helper\Encryption $encryption,
        \Ingenico\Import\Logger\Logger $logger,
        \Magento\Config\Model\ResourceModel\Config $resourceConfig,
        ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Controller\Result\RedirectFactory $resultRedirectFactory,
        \Magento\Backend\Model\Session $adminSession,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\HTTP\PhpEnvironment\Request $request
    ) {
        $this->import = $import;
        $this->encryption = $encryption;
        $this->logger = $logger;
        $this->resourceConfig = $resourceConfig;
        $this->scopeConfig = $scopeConfig;
        $this->_storeManager = $storeManager;
        $this->_resultRedirectFactory = $resultRedirectFactory;
        $this->_adminSession = $adminSession;
        $this->_messageManager = $messageManager;
        $this->request = $request;
    }

    /**
     * Intercept request, skip config save and execute custom logic
     */
    public function aroundExecute(\Magento\Config\Controller\Adminhtml\System\Config\Save $subject, callable $proceed)
    {
        $section = $subject->getRequest()->getParam('section');
        if (!in_array($section, [
            'ingenico_importer_section',
        ])) {
            return $proceed();
        }

        //$password = $this->scopeConfig->getValue('ingenico_importer_section/import_data/password');
        $post = $this->request->getPost();
        $password = $post['groups']['import_data']['fields']['password']['value'];

        try {
            $files = $this->request->getFiles()->toArray();

            if (!isset($files['groups']['import_data']['fields']['import_data']['value'])) { //@codingStandardsIgnoreLine
                throw new LocalizedException(__('Failed uploading file'));
            }

            $content = file_get_contents($files['groups']['import_data']['fields']['import_data']['value']['tmp_name']); //@codingStandardsIgnoreLine
            $content = $this->encryption->decrypt($content, $password);
            if (!$content) {
                throw new LocalizedException(__('Invalid password.'));
            }

            $this->logger->info('Initialize import');

            $data = json_decode($content, true);
            $storesConfig = $data['stores'];
            $config = $data['config'];
            $aliases = $data['aliases'];

            $this->logger->info('Stores configuration', [$storesConfig]);

            // Import configuration
            $this->import->importConfig($config, $storesConfig);

            // Import Aliases
            $this->import->importAliases($aliases);

            $this->resourceConfig->saveConfig(
                'ingenico_importer_section/import_data/import_data',
                '',
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                0
            );

            $this->logger->info('Data successfully imported!');
            $this->_messageManager->addSuccessMessage('Data successfully imported!');
        } catch (LocalizedException $e) {
            $this->_messageManager->addErrorMessage(__($e->getMessage()));
        }

        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->_resultRedirectFactory->create();
        return $resultRedirect->setPath(
            'adminhtml/system_config/edit',
            [
                '_current' => ['section', 'website', 'store'],
                '_nosid' => true
            ]
        );
    }
}
