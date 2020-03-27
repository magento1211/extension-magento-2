<?php
/**
 * Copyright ©2019 Itegration Ltd., Inc. All rights reserved.
 * See COPYING.txt for license details.
 * @author: Perencz Tamás <tamas.perencz@itegraion.com>
 */

namespace Emartech\Emarsys\Helper;

use DateTime;
use Emartech\Emarsys\Api\AttributesApiInterface;
use Emartech\Emarsys\Api\Data\ConfigInterface;
use Emartech\Emarsys\Api\Data\ConfigInterfaceFactory;
use Emartech\Emarsys\Api\Data\ExtraFieldsInterfaceFactory;
use Emartech\Emarsys\Api\Data\ImagesInterface;
use Emartech\Emarsys\Api\Data\ImagesInterfaceFactory;
use Emartech\Emarsys\Api\Data\ProductInterface;
use Emartech\Emarsys\Api\Data\ProductInterfaceFactory;
use Emartech\Emarsys\Api\Data\ProductStoreDataInterface;
use Emartech\Emarsys\Api\Data\ProductStoreDataInterfaceFactory;
use Emartech\Emarsys\Model\ResourceModel\Api\Category as CategoryResource;
use Emartech\Emarsys\Model\ResourceModel\Api\Product as ProductResource;
use Exception;
use Magento\Catalog\Model\Product as ProductModel;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Stdlib\DateTime\Filter\DateTime as DateTimeFilter;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;

class Product extends AbstractHelper
{
    /**
     * @var string[]
     */
    private $fields = [
        'entity_id',
        'type',
        'children_entity_ids',
        'categories',
        'sku',
        'images',
        'qty',
        'is_in_stock',
        'stores',
        'image',
        'small_image',
        'thumbnail',
    ];

    /**
     * @var null|string[]
     */
    private $extraFields = null;

    /**
     * @var string[]
     */
    private $storeFields = [
        'name',
        'price',
        'url_key',
        'description',
        'status',
        'store_id',
        'currency',
        'display_price',
        'special_price',
        'special_from_date',
        'special_to_date',
    ];

    /**
     * @var null|string[]
     */
    private $extraStoreFields = null;

    /**
     * @var ConfigInterfaceFactory
     */
    private $configFactory;

    /**
     * @var ProductCollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var ProductCollection
     */
    private $productCollection;

    /**
     * @var ProductResource
     */
    private $productResource;

    /**
     * @var CategoryResource
     */
    private $categoryResource;

    /**
     * @var array
     */
    private $productAttributeData;

    /**
     * @var ProductInterfaceFactory
     */
    private $productFactory;

    /**
     * @var array
     */
    private $categoryIds = [];

    /**
     * @var array
     */
    private $childrenProductIds = [];

    /**
     * @var array
     */
    private $stockData = [];

    /**
     * @var array
     */
    private $productUrlSuffix = [];

    /**
     * @var ImagesInterfaceFactory
     */
    private $imagesFactory;

    /**
     * @var ProductStoreDataInterfaceFactory
     */
    private $productStoreDataFactory;

    /**
     * @var ExtraFieldsInterfaceFactory
     */
    private $extraFieldsFactory;

    /**
     * @var array
     */
    private $priceData = [];

    /**
     * @var DateTimeFilter
     */
    private $dateTimeFilter;

    /**
     * Product constructor.
     *
     * @param ConfigInterfaceFactory           $configFactory
     * @param ProductCollectionFactory         $productCollectionFactory
     * @param ProductResource                  $productResource
     * @param CategoryResource                 $categoryResource
     * @param ProductInterfaceFactory          $productFactory
     * @param ImagesInterfaceFactory           $imagesFactory
     * @param ProductStoreDataInterfaceFactory $productStoreDataFactory
     * @param ExtraFieldsInterfaceFactory      $extraFieldsFactory
     * @param DateTimeFilter                   $dateTimeFilter
     * @param Context                          $context
     */
    public function __construct(
        ConfigInterfaceFactory $configFactory,
        ProductCollectionFactory $productCollectionFactory,
        ProductResource $productResource,
        CategoryResource $categoryResource,
        ProductInterfaceFactory $productFactory,
        ImagesInterfaceFactory $imagesFactory,
        ProductStoreDataInterfaceFactory $productStoreDataFactory,
        ExtraFieldsInterfaceFactory $extraFieldsFactory,
        DateTimeFilter $dateTimeFilter,
        Context $context
    ) {
        $this->configFactory = $configFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productResource = $productResource;
        $this->categoryResource = $categoryResource;
        $this->productFactory = $productFactory;
        $this->imagesFactory = $imagesFactory;
        $this->productStoreDataFactory = $productStoreDataFactory;
        $this->extraFieldsFactory = $extraFieldsFactory;
        $this->dateTimeFilter = $dateTimeFilter;

        parent::__construct(
            $context
        );
    }

    /**
     * @return string[]
     */
    public function getProductGlobalFields()
    {
        return $this->fields;
    }

    /**
     * @return string[]
     */
    public function getProductStoreFields()
    {
        return $this->storeFields;
    }

    /**
     * @return string[]
     */
    public function getProductFields()
    {
        return array_merge(
            $this->getProductGlobalFields(),
            $this->getProductStoreFields()
        );
    }

    /**
     * @return string[]
     */
    public function getProductExtraFields()
    {
        if (null == $this->extraFields) {
            $this->extraFields = [];

            /** @var ConfigInterface $config */
            $config = $this->configFactory->create();

            $productAttributes = $config->getConfigValue(
                AttributesApiInterface::TYPE_PRODUCT . ConfigInterface::ATTRIBUTE_CONFIG_POST_TAG,
                0
            );

            if (is_array($productAttributes)) {
                $this->extraFields = array_diff(
                    $productAttributes,
                    $this->getProductFields()
                );
            }
        }

        return $this->extraFields;
    }

    /**
     * @return $this
     */
    public function initCollection()
    {
        /** @var ProductCollection customerCollection */
        $this->productCollection = $this->productCollectionFactory->create();

        return $this;
    }

    /**
     * @param int         $page
     * @param int         $pageSize
     * @param string|null $table
     * @param string|null $primaryKey
     * @param array       $wheres
     * @param string|null $countField
     *
     * @return array
     */
    public function handleIds(
        $page,
        $pageSize,
        $table = null,
        $primaryKey = null,
        $wheres = [],
        $countField = null
    ) {
        return $this->productResource->handleIds(
            $page,
            $pageSize,
            $table,
            $primaryKey,
            $wheres,
            $countField
        );
    }

    /**
     * @param array      $wheres
     * @param array|null $joinInner
     *
     * @return $this
     */
    public function getCategoryIds($wheres, $joinInner = null)
    {
        $this->categoryIds = $this->categoryResource->getCategoryIds(
            $wheres,
            $joinInner
        );

        return $this;
    }

    /**
     * @param array      $wheres
     * @param array|null $joinInner
     *
     * @return $this
     */
    public function getChildrenProductIds($wheres, $joinInner = null)
    {
        $this->childrenProductIds = $this->productResource->getChildrenProductIds(
            $wheres,
            $joinInner
        );

        return $this;
    }

    /**
     * @param array      $wheres
     * @param array|null $joinInner
     *
     * @return $this
     */
    public function getStockData($wheres, $joinInner = null)
    {
        $this->stockData = $this->productResource->getStockData(
            $wheres,
            $joinInner
        );

        return $this;
    }

    /**
     * @param array         $wheres
     * @param int[]         $storeIds
     * @param array|null    $joinInner
     * @param null|string[] $fields
     */
    public function getAttributeData(
        $wheres,
        $storeIds,
        $joinInner = null,
        $fields = null
    ) {
        if (!$fields) {
            $fields = array_merge(
                $this->getProductFields(),
                $this->getProductExtraFields()
            );
        }

        $this->productAttributeData = $this->productResource->getAttributeData(
            $wheres,
            $storeIds,
            $fields,
            $joinInner
        );
    }

    /**
     * @param string $linkField
     * @param int    $min
     * @param int    $max
     *
     * @return $this
     */
    public function setWhere($linkField, $min, $max)
    {
        $this->productCollection
            ->addFieldToFilter($linkField, ['from' => $min])
            ->addFieldToFilter($linkField, ['to' => $max]);

        return $this;
    }

    /**
     * @param string $linkField
     * @param string $direction
     *
     * @return $this
     */
    public function setOrder($linkField, $direction)
    {
        $this->productCollection
            ->groupByAttribute($linkField)
            ->setOrder($linkField, $direction);

        return $this;
    }

    /**
     * @return ProductCollection
     */
    public function getProductCollection()
    {
        return $this->productCollection;
    }

    /**
     * @param ProductModel $product
     * @param array        $storeIds
     * @param string       $linkField
     * @param bool         $toArray
     *
     * @return ProductInterface
     */
    public function buildProductObject(
        $product,
        $storeIds,
        $linkField,
        $toArray = false
    ) {
        $productEntityId = $product->getEntityId();
        $productId = $product->getData($linkField);

        /** @var ProductInterface $productItem */
        $productItem = $this->productFactory->create()
            ->setType($product->getTypeId())
            ->setCategories($this->handleCategories($productEntityId))
            ->setChildrenEntityIds($this->handleChildrenEntityIds($productId))
            ->setEntityId($productEntityId)
            ->setIsInStock($this->handleStock($productEntityId))
            ->setQty($this->handleQty($productEntityId))
            ->setSku($product->getSku())
            ->setImages($this->handleImages($product, $storeIds, $productId))
            ->setStoreData(
                $this->handleProductStoreData(
                    $product,
                    $storeIds,
                    $productId,
                    $toArray
                )
            );

        if ($toArray) {
            $productItem = $productItem->getData();
        }

        return $productItem;
    }

    /**
     * @param int $productId
     *
     * @return array
     */
    // @codingStandardsIgnoreLine
    protected function handleCategories($productId)
    {
        if (array_key_exists($productId, $this->categoryIds)) {
            return $this->categoryIds[$productId];
        }

        return [];
    }

    /**
     * @param int $productId
     *
     * @return array
     */
    // @codingStandardsIgnoreLine
    protected function handleChildrenEntityIds($productId)
    {
        if (array_key_exists($productId, $this->childrenProductIds)) {
            return $this->childrenProductIds[$productId];
        }

        return [];
    }

    /**
     * @param int $productId
     *
     * @return int
     */
    // @codingStandardsIgnoreLine
    protected function handleStock($productId)
    {
        if (array_key_exists($productId, $this->stockData)) {
            return $this->stockData[$productId]['is_in_stock'];
        }

        return 0;
    }

    /**
     * @param int $productId
     *
     * @return int
     */
    // @codingStandardsIgnoreLine
    protected function handleQty($productId)
    {
        if (array_key_exists($productId, $this->stockData)) {
            return $this->stockData[$productId]['qty'];
        }

        return 0;
    }

    /**
     * @param int    $productId
     * @param int    $storeId
     * @param string $attributeCode
     *
     * @return string|null
     */
    private function getStoreData($productId, $storeId, $attributeCode)
    {
        if (array_key_exists($productId, $this->productAttributeData)
            && array_key_exists(
                $storeId,
                $this->productAttributeData[$productId]
            )
            && array_key_exists(
                $attributeCode,
                $this->productAttributeData[$productId][$storeId]
            )
        ) {
            return $this->productAttributeData[$productId][$storeId][$attributeCode];
        }

        if ($storeId != 0) {
            return $this->getStoreData($productId, 0, $attributeCode);
        }

        return null;
    }

    /**
     * @param ProductModel $product
     * @param array        $storeIds
     * @param int          $id
     *
     * @return ImagesInterface
     */
    // @codingStandardsIgnoreLine
    protected function handleImages($product, $storeIds, $id)
    {
        $imagePreUrl = $storeIds[0]->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . 'catalog/product';

        try {
            $image = $this->getStoreData($id, 0, 'image');
        } catch (Exception $e) {
            $image = null;
        }

        if ($image) {
            $image = $imagePreUrl . $image;
        }

        try {
            $smallImage = $this->getStoreData($id, 0, 'small_image');
        } catch (Exception $e) {
            $smallImage = null;
        }

        if ($smallImage) {
            $smallImage = $imagePreUrl . $smallImage;
        }

        try {
            $thumbnail = $this->getStoreData($id, 0, 'thumbnail');
        } catch (Exception $e) {
            $thumbnail = null;
        }

        if ($thumbnail) {
            $thumbnail = $imagePreUrl . $thumbnail;
        }

        return $this->imagesFactory->create()
            ->setImage($image)
            ->setSmallImage($smallImage)
            ->setThumbnail($thumbnail);
    }

    /**
     * @param ProductModel $product
     * @param array        $storeIds
     * @param int          $productId
     * @param bool         $toArray
     *
     * @return ProductStoreDataInterface[]
     */
    // @codingStandardsIgnoreLine
    protected function handleProductStoreData(
        $product,
        $storeIds,
        $productId,
        $toArray = false
    ) {
        $product->setPriceCalculation(false);

        $returnArray = [];

        foreach ($storeIds as $storeId => $storeObject) {
            $price = $this->getPrice($productId, $storeId);
            $displayPrice = (float)$this->getDisplayPrice($price, $storeObject);
            $originalPrice = (float)$this->getStoreData($productId, $storeId, 'price');
            $originalDisplayPrice = (float)$this->getDisplayPrice(
                $originalPrice,
                $storeObject
            );
            $webShopPrice = (float)$this->getWebShopPrice(
                $productId,
                $storeId,
                0
            );
            if (!$webShopPrice) {
                $webShopPrice = $price;
            } elseif ($webShopPrice > $price) {
                $webShopPrice = $price;
            } elseif ($price > $webShopPrice) {
                $price = $webShopPrice;
            }

            $displayWebShopPrice = (float)$this->getDisplayPrice(
                $webShopPrice,
                $storeObject
            );
            $originalWebShopPrice = (float)$this->getOriginalWebShopPrice(
                $productId,
                $storeId,
                0
            );
            if (!$originalWebShopPrice) {
                $originalWebShopPrice = $originalPrice;
            } elseif ($originalWebShopPrice > $originalPrice) {
                $originalWebShopPrice = $originalPrice;
            } elseif ($originalPrice > $originalWebShopPrice) {
                $originalPrice = $originalWebShopPrice;
            }

            $originalDisplayWebShopPrice = (float)$this->getDisplayPrice(
                $originalWebShopPrice,
                $storeObject
            );

            /** @var ProductStoreDataInterface $productStoreData */
            $productStoreData = $this->productStoreDataFactory->create()
                ->setStoreId($storeId)
                ->setStatus($this->getStoreData($productId, $storeId, 'status'))
                ->setDescription(
                    $this->getStoreData(
                        $productId,
                        $storeId,
                        'description'
                    )
                )
                ->setLink($this->handleLink($storeObject, $productId))
                ->setName($this->getStoreData($productId, $storeId, 'name'))
                ->setPrice($price)
                ->setDisplayPrice($displayPrice)
                ->setOriginalPrice($originalPrice)
                ->setOriginalDisplayPrice($originalDisplayPrice)
                ->setWebshopPrice($webShopPrice)
                ->setDisplayWebshopPrice($displayWebShopPrice)
                ->setOriginalWebshopPrice($originalWebShopPrice)
                ->setOriginalDisplayWebshopPrice($originalDisplayWebShopPrice)
                ->setCurrencyCode($this->getCurrencyCode($storeObject));

            if ($this->getProductExtraFields()) {
                $extraFields = [];
                foreach ($this->getProductExtraFields() as $field) {
                    $extraField = $this->extraFieldsFactory->create()
                        ->setKey($field)
                        ->setValue(
                            $this->getStoreData(
                                $productId,
                                $storeId,
                                $field
                            )
                        );

                    if ($toArray) {
                        $extraField = $extraField->getData();
                    }

                    $extraFields[] = $extraField;
                }
                $productStoreData->setExtraFields($extraFields);
            }

            $returnArray[] = $productStoreData;
        }

        return $returnArray;
    }

    /**
     * @param int $productId
     * @param int $storeId
     *
     * @return float
     */
    protected function getPrice($productId, $storeId)
    {
        $price = $this->getStoreData($productId, $storeId, 'price');
        $specialPrice = $this->getStoreData(
            $productId,
            $storeId,
            'special_price'
        );
        if (null !== $specialPrice) {
            try {
                $specialFromDate = $this->getStoreData(
                    $productId,
                    $storeId,
                    'special_from_date'
                );
                $specialFromDate = $this->dateTimeFilter->filter(
                    $specialFromDate
                );
                $specialFromDate = new DateTime($specialFromDate);

                $now = $this->dateTimeFilter->filter(null);
                $now = new DateTime($now);

                $specialToDate = $this->getStoreData(
                    $productId,
                    $storeId,
                    'special_to_date'
                );
                $specialToDate = $this->dateTimeFilter->filter($specialToDate);
                $specialToDate = new DateTime($specialToDate);

                if ($specialFromDate <= $now && $now <= $specialToDate) {
                    $price = $specialPrice;
                }
            } catch (Exception $e) {
                $specialPrice = null;
            }
        }
        return (float)$price;
    }

    /**
     * @param int $productId
     * @param int $storeId
     * @param int $customerGroupId
     *
     * @return float
     */
    protected function getWebShopPrice(
        $productId,
        $storeId,
        $customerGroupId = 0
    ) {
        if (array_key_exists($productId, $this->priceData)
            && array_key_exists($storeId, $this->priceData[$productId])
            && array_key_exists(
                $customerGroupId,
                $this->priceData[$productId][$storeId]
            )
            && array_key_exists(
                'final_price',
                $this->priceData[$productId][$storeId][$customerGroupId]
            )

        ) {
            return $this->priceData[$productId][$storeId][$customerGroupId]['final_price'];
        }

        return 0;
    }

    /**
     * @param int $productId
     * @param int $storeId
     * @param int $customerGroupId
     *
     * @return float
     */
    protected function getOriginalWebShopPrice(
        $productId,
        $storeId,
        $customerGroupId = 0
    ) {
        if (array_key_exists($productId, $this->priceData)
            && array_key_exists($storeId, $this->priceData[$productId])
            && array_key_exists(
                $customerGroupId,
                $this->priceData[$productId][$storeId]
            )
            && array_key_exists(
                'price',
                $this->priceData[$productId][$storeId][$customerGroupId]
            )

        ) {
            return $this->priceData[$productId][$storeId][$customerGroupId]['price'];
        }

        return 0;
    }

    /**
     * @param Store $store
     *
     * @return string
     */
    // @codingStandardsIgnoreLine
    protected function getCurrencyCode($store)
    {
        if ($store->getId() === '0') {
            return $store->getBaseCurrencyCode();
        }
        return $store->getCurrentCurrencyCode();
    }

    /**
     * @param float $price
     * @param Store $store
     *
     * @return float
     */
    protected function getDisplayPrice($price, $store)
    {
        if ($this->getCurrencyCode($store) !== $store->getBaseCurrencyCode()) {
            try {
                $tmp = $store->getBaseCurrency()->convert(
                    $price,
                    $store->getCurrentCurrencyCode()
                );
                $price = $tmp;
            } catch (\Exception $e) {
                $this->_logger->error($e->getMessage());
            }
        }

        return $price;
    }

    /**
     * @param Store $store
     * @param int   $productId
     *
     * @return string
     */
    // @codingStandardsIgnoreLine
    protected function handleLink($store, $productId)
    {
        $link = $this->getStoreData($productId, $store->getId(), 'url_key');

        if ($link) {
            return $store->getBaseUrl() . $link . $this->getProductUrlSuffix($store->getId());
        }

        return '';
    }

    /**
     * @param int $storeId
     *
     * @return string
     */
    // @codingStandardsIgnoreLine
    protected function getProductUrlSuffix($storeId)
    {
        if (!isset($this->productUrlSuffix[$storeId])) {
            $this->productUrlSuffix[$storeId] = $this->scopeConfig->getValue(
                ProductUrlPathGenerator::XML_PATH_PRODUCT_URL_SUFFIX,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        return $this->productUrlSuffix[$storeId];
    }

    /**
     * @param array      $websiteIds
     * @param int[]      $customerGroupIds
     * @param array      $wheres
     * @param array|null $joinInner
     *
     * @return $this
     */
    public function getPrices(
        $websiteIds,
        $customerGroupIds,
        $wheres,
        $joinInner = null
    ) {
        $this->priceData = $this->productResource->getPrices(
            $websiteIds,
            $customerGroupIds,
            $wheres,
            $joinInner
        );

        return $this;
    }
}
