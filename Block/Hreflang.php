<?php

declare(strict_types=1);

namespace Santi\Hreflang\Block;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\CmsUrlRewrite\Model\CmsPageUrlRewriteGenerator;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

class Hreflang extends Template
{
    /**
     * Config backend:
     * Stores > Configuration > Santi Extensions > Hreflang > Enable
     */
    private const XML_PATH_ENABLED = 'santi_hreflang/general/enabled';

    /**
     * Stores finales donde queremos generar hreflang.
     *
     * Importante:
     * - No incluir admin.
     * - No incluir stores de pruebas.
     * - No incluir stores espejo que no sean destino SEO final.
     */
    private const STORE_HREFLANG_MAP = [
        'mueblesbonitos_com_es'    => 'es-ES',
        'designameublement_com_fr' => 'fr-FR',
        'lettiemobili_com_it'      => 'it-IT',
        'mbmeubelen_nl_nl'         => 'nl-NL',
        'mbmoebel_de_de'           => 'de-DE',
        'moveisbonitos_pt_pt'      => 'pt-PT',
    ];

    public function __construct(
        Context $context,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlFinderInterface $urlFinder,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly HttpRequest $request,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Hace que la caché del bloque varíe por store y por URL actual.
     */
    public function getCacheKeyInfo(): array
    {
        return array_merge(parent::getCacheKeyInfo(), [
            'santi_hreflang',
            (string) $this->storeManager->getStore()->getId(),
            $this->request->getRequestUri(),
        ]);
    }

    /**
     * Devuelve el array final de etiquetas hreflang.
     *
     * Solo añade una URL si:
     * - el store está en STORE_HREFLANG_MAP,
     * - existe rewrite para ese store,
     * - el producto/categoría/CMS existe en ese store,
     * - el producto está habilitado y visible,
     * - la categoría está activa.
     */
    public function getHreflangLinks(): array
    {
        if (!$this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }

        $pageType = $this->detectPageType();
        $entityId = $this->resolveCurrentEntityId($pageType);
        $links = [];

        foreach ($this->storeManager->getStores() as $store) {
            $storeCode = $store->getCode();

            if (!isset(self::STORE_HREFLANG_MAP[$storeCode])) {
                continue;
            }

            $url = $this->resolveUrlForStore(
                (int) $store->getId(),
                $pageType,
                $entityId
            );

            if ($url === null) {
                continue;
            }

            $links[] = [
                'hreflang' => self::STORE_HREFLANG_MAP[$storeCode],
                'url' => $url,
            ];
        }

        return $this->addXDefault($links);
    }

    /**
     * Detecta el tipo de página actual.
     */
    private function detectPageType(): string
    {
        return match ($this->request->getFullActionName()) {
            'catalog_product_view' => 'product',
            'catalog_category_view' => 'category',
            'cms_page_view' => 'cms',
            'cms_index_index' => 'home',
            default => 'other',
        };
    }

    /**
     * Obtiene el ID de la entidad actual.
     *
     * Producto/categoría:
     * Magento normalmente trae el ID en request param "id".
     *
     * CMS:
     * Se resuelve buscando el rewrite actual.
     */
    private function resolveCurrentEntityId(string $pageType): ?int
    {
        return match ($pageType) {
            'product', 'category' => (int) $this->request->getParam('id') ?: null,
            'cms' => $this->resolveCmsPageId(),
            default => null,
        };
    }

    /**
     * Resuelve el ID de la página CMS actual a partir del request_path.
     */
    private function resolveCmsPageId(): ?int
    {
        $requestPath = ltrim($this->request->getPathInfo(), '/');
        $storeId = (int) $this->storeManager->getStore()->getId();

        $rewrite = $this->urlFinder->findOneByData([
            UrlRewrite::REQUEST_PATH => $requestPath,
            UrlRewrite::STORE_ID => $storeId,
            UrlRewrite::ENTITY_TYPE => CmsPageUrlRewriteGenerator::ENTITY_TYPE,
        ]);

        return $rewrite ? (int) $rewrite->getEntityId() : null;
    }

    /**
     * Resuelve la URL equivalente para un store concreto.
     */
    private function resolveUrlForStore(
        int $storeId,
        string $pageType,
        ?int $entityId
    ): ?string {
        $baseUrl = rtrim(
            $this->storeManager->getStore($storeId)->getBaseUrl(),
            '/'
        );

        return match ($pageType) {
            'home' => $baseUrl . '/',
            'product' => $this->resolveProductUrl($storeId, $entityId, $baseUrl),
            'category' => $this->resolveCategoryUrl($storeId, $entityId, $baseUrl),
            'cms' => $this->resolveCmsUrl($storeId, $entityId, $baseUrl),
            default => null,
        };
    }

    /**
     * Resuelve URL de producto por store.
     *
     * No se añade hreflang si:
     * - el producto no existe en ese store,
     * - está deshabilitado,
     * - no es visible individualmente,
     * - no tiene URL rewrite válido.
     */
    private function resolveProductUrl(
        int $storeId,
        ?int $entityId,
        string $baseUrl
    ): ?string {
        if (!$entityId) {
            return null;
        }

        try {
            $product = $this->productRepository->getById(
                $entityId,
                false,
                $storeId
            );
        } catch (NoSuchEntityException) {
            return null;
        }

        if ((int) $product->getStatus() !== ProductStatus::STATUS_ENABLED) {
            return null;
        }

        if ((int) $product->getVisibility() === Visibility::VISIBILITY_NOT_VISIBLE) {
            return null;
        }

        $rewrite = $this->urlFinder->findOneByData([
            UrlRewrite::ENTITY_TYPE => ProductUrlRewriteGenerator::ENTITY_TYPE,
            UrlRewrite::ENTITY_ID => $entityId,
            UrlRewrite::STORE_ID => $storeId,
            UrlRewrite::REDIRECT_TYPE => 0,
        ]);

        if (!$rewrite) {
            return null;
        }

        return $baseUrl . '/' . ltrim($rewrite->getRequestPath(), '/');
    }

    /**
     * Resuelve URL de categoría por store.
     *
     * No se añade hreflang si:
     * - la categoría no existe en ese store,
     * - está desactivada,
     * - no tiene URL rewrite válido.
     */
    private function resolveCategoryUrl(
        int $storeId,
        ?int $entityId,
        string $baseUrl
    ): ?string {
        if (!$entityId) {
            return null;
        }

        try {
            $category = $this->categoryRepository->get($entityId, $storeId);
        } catch (NoSuchEntityException) {
            return null;
        }

        if (!$category->getIsActive()) {
            return null;
        }

        $rewrite = $this->urlFinder->findOneByData([
            UrlRewrite::ENTITY_TYPE => CategoryUrlRewriteGenerator::ENTITY_TYPE,
            UrlRewrite::ENTITY_ID => $entityId,
            UrlRewrite::STORE_ID => $storeId,
            UrlRewrite::REDIRECT_TYPE => 0,
        ]);

        if (!$rewrite) {
            return null;
        }

        return $baseUrl . '/' . ltrim($rewrite->getRequestPath(), '/');
    }

    /**
     * Resuelve URL de CMS por store.
     *
     * En CMS se usa el mismo entity_id de la página CMS.
     * Si no existe rewrite en el store destino, se omite.
     */
    private function resolveCmsUrl(
        int $storeId,
        ?int $entityId,
        string $baseUrl
    ): ?string {
        if (!$entityId) {
            return null;
        }

        $rewrite = $this->urlFinder->findOneByData([
            UrlRewrite::ENTITY_TYPE => CmsPageUrlRewriteGenerator::ENTITY_TYPE,
            UrlRewrite::ENTITY_ID => $entityId,
            UrlRewrite::STORE_ID => $storeId,
            UrlRewrite::REDIRECT_TYPE => 0,
        ]);

        if (!$rewrite) {
            return null;
        }

        return $baseUrl . '/' . ltrim($rewrite->getRequestPath(), '/');
    }

    /**
     * Añade x-default apuntando al store español.
     */
    private function addXDefault(array $links): array
    {
        foreach ($links as $link) {
            if ($link['hreflang'] === 'es-ES') {
                $links[] = [
                    'hreflang' => 'x-default',
                    'url' => $link['url'],
                ];

                break;
            }
        }

        return $links;
    }
}