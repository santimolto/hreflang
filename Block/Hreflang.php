<?php
declare(strict_types=1);

namespace Santi\Hreflang\Block;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Hreflang extends Template
{
    private const XML_PATH_ENABLED = 'santi_hreflang/general/enabled';

    private const STORE_HREFLANG_MAP = [
        'designameublement_com_fr' => 'fr-FR',
        'moveisbonitos_pt_pt'      => 'pt-PT',
        'mueblesbonitos_com_es'    => 'es-ES',
    ];

    private StoreManagerInterface $storeManager;
    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
    }

    public function getHreflangLinks(): array
    {
        if (!$this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }

        $links = [];

        $currentStore = $this->storeManager->getStore();
        $currentUrl = $currentStore->getCurrentUrl(false);

        foreach ($this->storeManager->getStores() as $store) {
            $storeCode = $store->getCode();

            if (!isset(self::STORE_HREFLANG_MAP[$storeCode])) {
                continue;
            }

            $storeBaseUrl = rtrim($store->getBaseUrl(), '/');
            $currentBaseUrl = rtrim($currentStore->getBaseUrl(), '/');

            $url = str_replace($currentBaseUrl, $storeBaseUrl, $currentUrl);

            $links[] = [
                'hreflang' => self::STORE_HREFLANG_MAP[$storeCode],
                'url' => strtok($url, '?')
            ];
        }

        return $links;
    }
}
