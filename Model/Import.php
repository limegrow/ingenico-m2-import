<?php

namespace Ingenico\Import\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;

class Import
{
    const PSPID_SUFFIX = 'M2';

    /**
     * @var \Ingenico\Import\Logger\Logger
     */
    protected $logger;

    /**
     * @var \Ingenico\Payment\Model\AliasFactory
     */
    private $aliasFactory;

    /**
     * @var \Ingenico\Payment\Model\ResourceModel\Alias\CollectionFactory
     */
    private $aliasCollectionFactory;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \Magento\Config\Model\ResourceModel\Config
     */
    private $resourceConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Store\Api\StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @var \Magento\Framework\App\Cache\Manager
     */
    private $cacheManager;

    /**
     * @var array
     */
    private $storesConfig = [];

    public function __construct(
        \Ingenico\Import\Logger\Logger $logger,
        \Ingenico\Payment\Model\AliasFactory $aliasFactory,
        \Ingenico\Payment\Model\ResourceModel\Alias\CollectionFactory $aliasCollectionFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        ScopeConfigInterface $scopeConfig,
        \Magento\Config\Model\ResourceModel\Config $resourceConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Store\Api\StoreRepositoryInterface $storeRepository,
        \Magento\Framework\App\Cache\Manager $cacheManager
    ) {
        $this->logger = $logger;
        $this->aliasFactory = $aliasFactory;
        $this->aliasCollectionFactory = $aliasCollectionFactory;
        $this->customerRepository = $customerRepository;
        $this->scopeConfig = $scopeConfig;
        $this->resourceConfig = $resourceConfig;
        $this->storeManager = $storeManager;
        $this->storeRepository = $storeRepository;
        $this->cacheManager = $cacheManager;
    }

    /**
     * Get Store Code by ID.
     *
     * @param string $storeId
     *
     * @return bool|mixed
     */
    private function getStoreCode($storeId)
    {
        foreach ($this->storesConfig as $item) {
            if ($item['id'] == $storeId) {
                return $item['code'];
            }
        }

        return false;
    }

    /**
     * Write config.
     *
     * @param     $path
     * @param     $value
     * @param     $scope
     * @param int $scopeId
     */
    private function writeConfig($path, $value, $scope, $scopeId = 0)
    {
        if ($scope === 'stores') {
            $storeCode = $this->getStoreCode($scopeId);

            try {
                $storeId = $this->storeRepository->getActiveStoreByCode($storeCode);
            } catch (NoSuchEntityException $exception) {
                $this->logger->warn($exception->getMessage());
                return;
            }

            $this->resourceConfig->saveConfig(
                $path,
                $value,
                ScopeInterface::SCOPE_STORES,
                $storeId
            );

        } elseif ($scope === 'default') {
            $this->resourceConfig->saveConfig(
                $path,
                $value,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                0
            );
        }
    }

    public function importConfig(array $config, $storesConfig)
    {
        $this->logger->info('Import configuration...');
        $this->storesConfig = $storesConfig;

        foreach ($config as $item) {
            $scope = $item['scope'];
            $scope_id = $item['scope_id'];
            $path = $item['path'];
            $value = $item['value'];

            // Settings mapping
            switch ($path) {
                case 'payment_services/ops/title':
                    $this->logger->info('Title has been imported', [$value]);
                    $this->writeConfig(
                        'payment/ingenico_e_payments/title',
                        $value,
                        $scope_id
                    );
                    break;
                case 'payment_services/ops/pspid':
                    $this->logger->info('pspid has been imported', [$value]);
                    $this->writeConfig(
                        'ingenico_connection/test/pspid',
                        $value . self::PSPID_SUFFIX,
                        'default'
                    );
                    $this->writeConfig(
                        'ingenico_connection/live/pspid',
                        $value . self::PSPID_SUFFIX,
                        'default'
                    );
                    break;
                case 'payment_services/ops/secret_key_in':
                    $this->logger->info('signature has been imported', [$value]);
                    $this->writeConfig(
                        'ingenico_connection/test/signature',
                        $value,
                        'default'
                    );
                    $this->writeConfig(
                        'ingenico_connection/live/signature',
                        $value,
                        'default'
                    );
                    break;
                case 'payment_services/ops/api_userid':
                    $this->logger->info('dluser has been imported', [$value]);
                    $this->writeConfig(
                        'ingenico_connection/test/user',
                        $value,
                        'default'
                    );
                    $this->writeConfig(
                        'ingenico_connection/live/user',
                        $value,
                        'default'
                    );
                    break;
                case 'payment_services/ops/api_pswd':
                    $this->logger->info('dlpassword has been imported', [$value]);
                    $this->writeConfig(
                        'ingenico_connection/test/password',
                        $value,
                        'default'
                    );
                    $this->writeConfig(
                        'ingenico_connection/live/password',
                        $value,
                        'default'
                    );
                    break;
                case 'payment_services/ops/payment_action':
                    // authorize or sale
                    $this->logger->info('payment_action has been imported', [$value]);
                    $this->writeConfig(
                        'ingenico_settings/tokenization/direct_sales',
                        $value !== 'authorize' ? 1 : 0,
                        'default'
                    );
                    break;
                case 'payment_services_ops_paymentReminder':
                    $this->logger->info('reminder flag has been imported', [$value]);
                    $this->writeConfig(
                        'ingenico_settings/orders/payment_reminder_email_send',
                        $value,
                        'default'
                    );
                    break;
                case 'payment/ops_flex/active':
                    // Activate Inline payment page if FlexCheckout was active
                    if ($value) {
                        $this->writeConfig(
                            'ingenico_payment_page/presentation/mode',
                            'INLINE',
                            'default'
                        );
                    }

                    break;
                case 'payment/ops_cc/enabled_3dsecure':
                case 'payment/ops_cc_redirect/enabled_3dsecure':
                case 'payment/ops_dc/enabled_3dsecure':
                case 'payment/ops_dc_redirect/enabled_3dsecure':
                    // Allow 3DSecure
                    if ($value) {
                        $this->writeConfig(
                            'ingenico_settings/tokenization/skip_security_check',
                            '0',
                            'default'
                        );
                    }

                    break;
                case 'payment/ops_cc/active_alias':
                case 'payment/ops_cc_redirect/active_alias':
                    if ($value) {
                        $this->writeConfig(
                            'ingenico_settings/tokenization/enabled',
                            '1',
                            'default'
                        );
                        $this->writeConfig(
                            'ingenico_settings/tokenization/one_click_payment_enabled',
                            '1',
                            'default'
                        );
                    }

                    break;
            }

            // Payment methods mapping
            switch ($path) {
                case 'payment/ops_cc/active':
                    if ($value) {
                        $this->writeConfig(
                            'ingenico_payment_methods/methods/card/visa/enabled',
                            '1',
                            'default'
                        );
                        $this->writeConfig(
                            'ingenico_payment_methods/methods/card/mastercard/enabled',
                            '1',
                            'default'
                        );
                        $this->writeConfig(
                            'ingenico_payment_methods/methods/card/maestro/enabled',
                            '1',
                            'default'
                        );
                        $this->writeConfig(
                            'ingenico_payment_methods/methods/card/jcb/enabled',
                            '1',
                            'default'
                        );
                        $this->writeConfig(
                            'ingenico_payment_methods/methods/card/discover/enabled',
                            '1',
                            'default'
                        );
                        $this->writeConfig(
                            'ingenico_payment_methods/methods/card/diners_club/enabled',
                            '1',
                            'default'
                        );
                        $this->writeConfig(
                            'ingenico_payment_methods/methods/card/cb/enabled',
                            '1',
                            'default'
                        );
                        $this->writeConfig(
                            'ingenico_payment_methods/methods/card/amex/enabled',
                            '1',
                            'default'
                        );
                    }

                    break;
                case 'payment/ops_BCMC/active':
                    if ($value) {
                        $this->writeConfig(
                            'ingenico_payment_methods/methods/card/bancontact/enabled',
                            '1',
                            'default'
                        );
                    }

                    break;
                case 'payment/ops_iDeal/active':
                    if ($value) {
                        $this->writeConfig(
                            'ingenico_payment_methods/methods/real_time_banking/ideal/enabled',
                            '1',
                            'default'
                        );
                    }

                    break;
                case 'payment/ops_kbcOnline/active':
                    if ($value) {
                        $this->writeConfig(
                            'ingenico_payment_methods/methods/real_time_banking/kbc/enabled',
                            '1',
                            'default'
                        );
                    }

                    break;
                case 'payment/ops_belfiusDirectNet/active':
                    if ($value) {
                        $this->writeConfig(
                            'ingenico_payment_methods/methods/real_time_banking/belfius/enabled',
                            '1',
                            'default'
                        );
                    }

                    break;
                case 'payment/ops_bankTransfer/active':
                    if ($value) {
                        $this->writeConfig(
                            'ingenico_payment_methods/methods/real_time_banking/bank_transfer/enabled',
                            '1',
                            'default'
                        );
                    }

                    break;
                case 'payment/ops_cbcOnline/active':
                    if ($value) {
                        $this->writeConfig(
                            'ingenico_payment_methods/methods/real_time_banking/cbc/enabled',
                            '1',
                            'default'
                        );
                    }

                    break;
                case 'payment/ops_giroPay/active':
                    if ($value) {
                        $this->writeConfig(
                            'ingenico_payment_methods/methods/real_time_banking/giropay/enabled',
                            '1',
                            'default'
                        );
                    }

                    break;
                case 'payment/ops_ingHomePay/active':
                    if ($value) {
                        $this->writeConfig(
                            'ingenico_payment_methods/methods/real_time_banking/ing/enabled',
                            '1',
                            'default'
                        );
                    }

                    break;
                case 'payment/ops_paysafecard/active':
                    if ($value) {
                        $this->writeConfig(
                            'ingenico_payment_methods/methods/prepaid_vouchers/paysafecard/enabled',
                            '1',
                            'default'
                        );
                    }

                    break;
                case 'payment/ops_paypal/active':
                    if ($value) {
                        $this->writeConfig(
                            'ingenico_payment_methods/methods/e_wallet/pay_pal/enabled',
                            '1',
                            'default'
                        );
                    }

                    break;
            }
        }
    }

    public function importAliases(array $aliases)
    {
        $this->logger->info('Import aliases...');

        foreach ($aliases as $alias) {
            try {
                $this->importAlias($alias);
            } catch (\Exception $e) {
                $this->logger->warn($e->getMessage(), [$alias]);
                continue;
            }

            $this->logger->info(sprintf('Alias %s has been imported.', $alias['alias']));
        }

        $this->cacheManager->flush(['config']);
    }

    /**
     * Import Alias
     *
     * @param array $alias
     *
     * @throws LocalizedException
     */
    public function importAlias(array $alias)
    {
        $aliasName = $alias['alias'];

        // Try to load by Alias
        $collection = $this->aliasCollectionFactory
            ->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter('alias', $aliasName)
            ->setPageSize(1)
            ->setCurPage(1);

        if ($collection->getSize() > 0) {
            // Skip alias if exists
            throw new LocalizedException(__('Alias %1 already exists.  Skipping.', $aliasName));
        }

        // Get Customer by email
        try {
            $customer = $this->customerRepository->get($alias['customer_email']);
        } catch (\Exception $e) {
            // Customer can't be found
            throw new LocalizedException(__('Customer %1 isn\'t exists....', $alias['customer_email']));
        }

        // Create alias
        $aliasObj = $this->aliasFactory->create();
        $aliasObj->setCreatedAt(strtotime($alias['created_at']))
                 ->setUpdatedAt(strtotime($alias['created_at']))
                 ->setAlias($aliasName)
                 ->setCustomerId($customer->getId())
                 ->setBrand($alias['brand'])
                 ->setCardno($alias['masked'])
                 ->setPm('CreditCard')
                 ->setEd($alias['expiration_date'])
                 ->setBin($alias['masked'])
                 ->save();
    }
}
