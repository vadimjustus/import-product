<?php

/**
 * TechDivision\Import\Product\Subjects\BunchSubject
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @author    Tim Wagner <t.wagner@techdivision.com>
 * @copyright 2016 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/techdivision/import-product
 * @link      http://www.techdivision.com
 */

namespace TechDivision\Import\Product\Subjects;

use TechDivision\Import\Product\Utils\MemberNames;
use TechDivision\Import\Product\Utils\VisibilityKeys;
use TechDivision\Import\Subjects\ExportableTrait;
use TechDivision\Import\Subjects\ExportableSubjectInterface;

/**
 * The subject implementation that handles the business logic to persist products.
 *
 * @author    Tim Wagner <t.wagner@techdivision.com>
 * @copyright 2016 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/techdivision/import-product
 * @link      http://www.techdivision.com
 */
class BunchSubject extends AbstractProductSubject implements ExportableSubjectInterface
{

    /**
     * The trait that implements the export functionality.
     *
     * @var \TechDivision\Import\Subjects\ExportableTrait
     */
    use ExportableTrait;

    /**
     * The mapping for the supported backend types (for the product entity) => persist methods.
     *
     * @var array
     */
    protected $backendTypes = array(
        'datetime' => array('persistProductDatetimeAttribute', 'loadProductDatetimeAttribute'),
        'decimal'  => array('persistProductDecimalAttribute', 'loadProductDecimalAttribute'),
        'int'      => array('persistProductIntAttribute', 'loadProductIntAttribute'),
        'text'     => array('persistProductTextAttribute', 'loadProductTextAttribute'),
        'varchar'  => array('persistProductVarcharAttribute', 'loadProductVarcharAttribute')
    );

    /**
     * Mappings for the table column => CSV column header.
     *
     * @var array
     */
    protected $headerStockMappings = array(
        'qty'                         => array('qty', 'float'),
        'min_qty'                     => array('out_of_stock_qty', 'float'),
        'use_config_min_qty'          => array('use_config_min_qty', 'int'),
        'is_qty_decimal'              => array('is_qty_decimal', 'int'),
        'backorders'                  => array('allow_backorders', 'int'),
        'use_config_backorders'       => array('use_config_backorders', 'int'),
        'min_sale_qty'                => array('min_cart_qty', 'float'),
        'use_config_min_sale_qty'     => array('use_config_min_sale_qty', 'int'),
        'max_sale_qty'                => array('max_cart_qty', 'float'),
        'use_config_max_sale_qty'     => array('use_config_max_sale_qty', 'int'),
        'is_in_stock'                 => array('is_in_stock', 'int'),
        'notify_stock_qty'            => array('notify_on_stock_below', 'float'),
        'use_config_notify_stock_qty' => array('use_config_notify_stock_qty', 'int'),
        'manage_stock'                => array('manage_stock', 'int'),
        'use_config_manage_stock'     => array('use_config_manage_stock', 'int'),
        'use_config_qty_increments'   => array('use_config_qty_increments', 'int'),
        'qty_increments'              => array('qty_increments', 'float'),
        'use_config_enable_qty_inc'   => array('use_config_enable_qty_inc', 'int'),
        'enable_qty_increments'       => array('enable_qty_increments', 'int'),
        'is_decimal_divided'          => array('is_decimal_divided', 'int'),
    );

    /**
     * The array with the available visibility keys.
     *
     * @var array
     */
    protected $availableVisibilities = array(
        'Not Visible Individually' => VisibilityKeys::VISIBILITY_NOT_VISIBLE,
        'Catalog'                  => VisibilityKeys::VISIBILITY_IN_CATALOG,
        'Search'                   => VisibilityKeys::VISIBILITY_IN_SEARCH,
        'Catalog, Search'          => VisibilityKeys::VISIBILITY_BOTH
    );

    /**
     * The attribute set of the product that has to be created.
     *
     * @var array
     */
    protected $attributeSet = array();

    /**
     * The category IDs the product is related with.
     *
     * @var array
     */
    protected $productCategoryIds = array();

    /**
     * The default mappings for the user defined attributes, based on the attributes frontend input type.
     *
     * @var array
     */
    protected $defaultFrontendInputCallbackMapping = array(
        'select' => 'TechDivision\\Import\\Product\\Callbacks\\SelectCallback',
        'multiselect' => 'TechDivision\\Import\\Product\\Callbacks\\MultiselectCallback',
        'boolean' => 'TechDivision\\Import\\Product\\Callbacks\\BooleanCallback'
    );

    /**
     * The default callback mappings for the Magento standard product attributes.
     *
     * @var array
     */
    protected $defaultCallbackMappings = array(
        'visibility'           => array('TechDivision\\Import\\Product\\Callbacks\\VisibilityCallback'),
        'tax_class_id'         => array('TechDivision\\Import\\Product\\Callbacks\\TaxClassCallback'),
        'bundle_price_type'    => array('TechDivision\\Import\\Product\\Bundle\\Callbacks\\BundleTypeCallback'),
        'bundle_sku_type'      => array('TechDivision\\Import\\Product\\Bundle\\Callbacks\\BundleTypeCallback'),
        'bundle_weight_type'   => array('TechDivision\\Import\\Product\\Bundle\\Callbacks\\BundleTypeCallback'),
        'bundle_price_view'    => array('TechDivision\\Import\\Product\\Bundle\\Callbacks\\BundlePriceViewCallback'),
        'bundle_shipment_type' => array('TechDivision\\Import\\Product\\Bundle\\Callbacks\\BundleShipmentTypeCallback')
    );

    /**
     * Intializes the previously loaded global data for exactly one bunch.
     *
     * @return void
     * @see \Importer\Csv\Actions\ProductImportAction::prepare()
     */
    public function setUp()
    {

        // initialize the callback mappings with the default mappings
        $this->callbackMappings = array_merge($this->callbackMappings, $this->defaultCallbackMappings);

        // load the user defined attributes and add the callback mappings
        foreach ($this->getEavAttributeByIsUserDefined() as $eavAttribute) {
            // load attribute code and frontend input type
            $attributeCode = $eavAttribute[MemberNames::ATTRIBUTE_CODE];
            $frontendInput = $eavAttribute[MemberNames::FRONTEND_INPUT];

            // query whether or not the array for the mappings has been initialized
            if (!isset($this->callbackMappings[$attributeCode])) {
                $this->callbackMappings[$attributeCode] = array();
            }

            // set the appropriate callback mapping for the attributes input type
            if (isset($this->defaultFrontendInputCallbackMapping[$frontendInput])) {
                $this->callbackMappings[$attributeCode][] = $this->defaultFrontendInputCallbackMapping[$frontendInput];
            }
        }

        // merge the callback mappings the the one from the configuration file
        foreach ($this->getConfiguration()->getCallbacks() as $callbackMappings) {
            foreach ($callbackMappings as $attributeCode => $mappings) {
                // write a log message, that default callback configuration will
                // be overwritten with the one from the configuration file
                if (isset($this->callbackMappings[$attributeCode])) {
                    $this->getSystemLogger()->notice(
                        sprintf('Now override callback mappings for attribute %s with values found in configuration file', $attributeCode)
                    );
                }

                // override the attributes callbacks
                $this->callbackMappings[$attributeCode] = $mappings;
            }
        }

        // invoke the parent method
        parent::setUp();
    }

    /**
     * Set's the attribute set of the product that has to be created.
     *
     * @param array $attributeSet The attribute set
     *
     * @return void
     */
    public function setAttributeSet(array $attributeSet)
    {
        $this->attributeSet = $attributeSet;
    }

    /**
     * Return's the attribute set of the product that has to be created.
     *
     * @return array The attribute set
     */
    public function getAttributeSet()
    {
        return $this->attributeSet;
    }

    /**
     * Cast's the passed value based on the backend type information.
     *
     * @param string $backendType The backend type to cast to
     * @param mixed  $value       The value to be casted
     *
     * @return mixed The casted value
     */
    public function castValueByBackendType($backendType, $value)
    {

        // cast the value to a valid timestamp
        if ($backendType === 'datetime') {
            return \DateTime::createFromFormat($this->getSourceDateFormat(), $value)->format('Y-m-d H:i:s');
        }

        // cast the value to a float value
        if ($backendType === 'float') {
            return (float) $value;
        }

        // cast the value to an integer
        if ($backendType === 'int') {
            return (int) $value;
        }

        // we don't need to cast strings
        return $value;
    }

    /**
     * Return's the mappings for the table column => CSV column header.
     *
     * @return array The header stock mappings
     */
    public function getHeaderStockMappings()
    {
        return $this->headerStockMappings;
    }

    /**
     * Return's mapping for the supported backend types (for the product entity) => persist methods.
     *
     * @return array The mapping for the supported backend types
     */
    public function getBackendTypes()
    {
        return $this->backendTypes;
    }

    /**
     * Return's the visibility key for the passed visibility string.
     *
     * @param string $visibility The visibility string to return the key for
     *
     * @return integer The requested visibility key
     * @throws \Exception Is thrown, if the requested visibility is not available
     */
    public function getVisibilityIdByValue($visibility)
    {

        // query whether or not, the requested visibility is available
        if (isset($this->availableVisibilities[$visibility])) {
            return $this->availableVisibilities[$visibility];
        }

        // throw an exception, if not
        throw new \Exception(
            sprintf(
                'Found invalid visibility %s in file %s on line %d',
                $visibility,
                $this->getFilename(),
                $this->getLineNumber()
            )
        );
    }

    /**
     * Add the passed category ID to the product's category list.
     *
     * @param integer $categoryId The category ID to add
     *
     * @return void
     */
    public function addProductCategoryId($categoryId)
    {
        $this->productCategoryIds[$this->getLastEntityId()][$categoryId] = $this->getLastEntityId();
    }

    /**
     * Return's the list with category IDs the product is related with.
     *
     * @return array The product's category IDs
     */
    public function getProductCategoryIds()
    {

        // initialize the array with the product's category IDs
        $categoryIds = array();

        // query whether or not category IDs are available for the actual product entity
        if (isset($this->productCategoryIds[$lastEntityId = $this->getLastEntityId()])) {
            $categoryIds = $this->productCategoryIds[$lastEntityId];
        }

        // return the array with the product's category IDs
        return $categoryIds;
    }

    /**
     * Return's the attribute option value with the passed value and store ID.
     *
     * @param mixed   $value   The option value
     * @param integer $storeId The ID of the store
     *
     * @return array|boolean The attribute option value instance
     */
    public function getEavAttributeOptionValueByOptionValueAndStoreId($value, $storeId)
    {
        return $this->getProductProcessor()->getEavAttributeOptionValueByOptionValueAndStoreId($value, $storeId);
    }

    /**
     * Return's an array with the available EAV attributes for the passed is user defined flag.
     *
     * @param integer $isUserDefined The flag itself
     *
     * @return array The array with the EAV attributes matching the passed flag
     */
    public function getEavAttributeByIsUserDefined($isUserDefined = 1)
    {
        return $this->getProductProcessor()->getEavAttributeByIsUserDefined($isUserDefined);
    }

    /**
     * Return's the URL rewrites for the passed URL entity type and ID.
     *
     * @param string  $entityType The entity type to load the URL rewrites for
     * @param integer $entityId   The entity ID to laod the rewrites for
     *
     * @return array The URL rewrites
     */
    public function getUrlRewritesByEntityTypeAndEntityId($entityType, $entityId)
    {
        return $this->getProductProcessor()->getUrlRewritesByEntityTypeAndEntityId($entityType, $entityId);
    }

    /**
     * Load's and return's the product with the passed SKU.
     *
     * @param string $sku The SKU of the product to load
     *
     * @return array The product
     */
    public function loadProduct($sku)
    {
        return $this->getProductProcessor()->loadProduct($sku);
    }

    /**
     * Load's and return's the product website relation with the passed product and website ID.
     *
     * @param string $productId The product ID of the relation
     * @param string $websiteId The website ID of the relation
     *
     * @return array The product website
     */
    public function loadProductWebsite($productId, $websiteId)
    {
        return $this->getProductProcessor()->loadProductWebsite($productId, $websiteId);
    }

    /**
     * Return's the category product relation with the passed category/product ID.
     *
     * @param integer $categoryId The category ID of the category product relation to return
     * @param integer $productId  The product ID of the category product relation to return
     *
     * @return array The category product relation
     */
    public function loadCategoryProduct($categoryId, $productId)
    {
        return $this->getProductProcessor()->loadCategoryProduct($categoryId, $productId);
    }

    /**
     * Load's and return's the stock status with the passed product/website/stock ID.
     *
     * @param integer $productId The product ID of the stock status to load
     * @param integer $websiteId The website ID of the stock status to load
     * @param integer $stockId   The stock ID of the stock status to load
     *
     * @return array The stock status
     */
    public function loadStockStatus($productId, $websiteId, $stockId)
    {
        return $this->getProductProcessor()->loadStockStatus($productId, $websiteId, $stockId);
    }

    /**
     * Load's and return's the stock status with the passed product/website/stock ID.
     *
     * @param integer $productId The product ID of the stock item to load
     * @param integer $websiteId The website ID of the stock item to load
     * @param integer $stockId   The stock ID of the stock item to load
     *
     * @return array The stock item
     */
    public function loadStockItem($productId, $websiteId, $stockId)
    {
        return $this->getProductProcessor()->loadStockItem($productId, $websiteId, $stockId);
    }

    /**
     * Load's and return's the datetime attribute with the passed entity/attribute/store ID.
     *
     * @param integer $entityId    The entity ID of the attribute
     * @param integer $attributeId The attribute ID of the attribute
     * @param integer $storeId     The store ID of the attribute
     *
     * @return array|null The datetime attribute
     */
    public function loadProductDatetimeAttribute($entityId, $attributeId, $storeId)
    {
        return $this->getProductProcessor()->loadProductDatetimeAttribute($entityId, $attributeId, $storeId);
    }

    /**
     * Load's and return's the decimal attribute with the passed entity/attribute/store ID.
     *
     * @param integer $entityId    The entity ID of the attribute
     * @param integer $attributeId The attribute ID of the attribute
     * @param integer $storeId     The store ID of the attribute
     *
     * @return array|null The decimal attribute
     */
    public function loadProductDecimalAttribute($entityId, $attributeId, $storeId)
    {
        return $this->getProductProcessor()->loadProductDecimalAttribute($entityId, $attributeId, $storeId);
    }

    /**
     * Load's and return's the integer attribute with the passed entity/attribute/store ID.
     *
     * @param integer $entityId    The entity ID of the attribute
     * @param integer $attributeId The attribute ID of the attribute
     * @param integer $storeId     The store ID of the attribute
     *
     * @return array|null The integer attribute
     */
    public function loadProductIntAttribute($entityId, $attributeId, $storeId)
    {
        return $this->getProductProcessor()->loadProductIntAttribute($entityId, $attributeId, $storeId);
    }

    /**
     * Load's and return's the text attribute with the passed entity/attribute/store ID.
     *
     * @param integer $entityId    The entity ID of the attribute
     * @param integer $attributeId The attribute ID of the attribute
     * @param integer $storeId     The store ID of the attribute
     *
     * @return array|null The text attribute
     */
    public function loadProductTextAttribute($entityId, $attributeId, $storeId)
    {
        return $this->getProductProcessor()->loadProductTextAttribute($entityId, $attributeId, $storeId);
    }

    /**
     * Load's and return's the varchar attribute with the passed entity/attribute/store ID.
     *
     * @param integer $entityId    The entity ID of the attribute
     * @param integer $attributeId The attribute ID of the attribute
     * @param integer $storeId     The store ID of the attribute
     *
     * @return array|null The varchar attribute
     */
    public function loadProductVarcharAttribute($entityId, $attributeId, $storeId)
    {
        return $this->getProductProcessor()->loadProductVarcharAttribute($entityId, $attributeId, $storeId);
    }

    /**
     * Return's the URL rewrite product category relation for the passed
     * product and category ID.
     *
     * @param integer $productId  The product ID to load the URL rewrite product category relation for
     * @param integer $categoryId The category ID to load the URL rewrite product category relation for
     *
     * @return array|false The URL rewrite product category relations
     */
    public function loadUrlRewriteProductCategory($productId, $categoryId)
    {
        return $this->getProductProcessor()->loadUrlRewriteProductCategory($productId, $categoryId);
    }

    /**
     * Persist's the passed product data and return's the ID.
     *
     * @param array $product The product data to persist
     *
     * @return string The ID of the persisted entity
     */
    public function persistProduct($product)
    {
        return $this->getProductProcessor()->persistProduct($product);
    }

    /**
     * Persist's the passed product varchar attribute.
     *
     * @param array $attribute The attribute to persist
     *
     * @return void
     */
    public function persistProductVarcharAttribute($attribute)
    {
        $this->getProductProcessor()->persistProductVarcharAttribute($attribute);
    }

    /**
     * Persist's the passed product integer attribute.
     *
     * @param array $attribute The attribute to persist
     *
     * @return void
     */
    public function persistProductIntAttribute($attribute)
    {
        $this->getProductProcessor()->persistProductIntAttribute($attribute);
    }

    /**
     * Persist's the passed product decimal attribute.
     *
     * @param array $attribute The attribute to persist
     *
     * @return void
     */
    public function persistProductDecimalAttribute($attribute)
    {
        $this->getProductProcessor()->persistProductDecimalAttribute($attribute);
    }

    /**
     * Persist's the passed product datetime attribute.
     *
     * @param array $attribute The attribute to persist
     *
     * @return void
     */
    public function persistProductDatetimeAttribute($attribute)
    {
        $this->getProductProcessor()->persistProductDatetimeAttribute($attribute);
    }

    /**
     * Persist's the passed product text attribute.
     *
     * @param array $attribute The attribute to persist
     *
     * @return void
     */
    public function persistProductTextAttribute($attribute)
    {
        $this->getProductProcessor()->persistProductTextAttribute($attribute);
    }

    /**
     * Persist's the passed product website data and return's the ID.
     *
     * @param array $productWebsite The product website data to persist
     *
     * @return void
     */
    public function persistProductWebsite($productWebsite)
    {
        $this->getProductProcessor()->persistProductWebsite($productWebsite);
    }

    /**
     * Persist's the passed category product relation.
     *
     * @param array $categoryProduct The category product relation to persist
     *
     * @return void
     */
    public function persistCategoryProduct($categoryProduct)
    {
        $this->getProductProcessor()->persistCategoryProduct($categoryProduct);
    }

    /**
     * Persist's the passed stock item data and return's the ID.
     *
     * @param array $stockItem The stock item data to persist
     *
     * @return void
     */
    public function persistStockItem($stockItem)
    {
        $this->getProductProcessor()->persistStockItem($stockItem);
    }

    /**
     * Persist's the passed stock status data and return's the ID.
     *
     * @param array $stockStatus The stock status data to persist
     *
     * @return void
     */
    public function persistStockStatus($stockStatus)
    {
        $this->getProductProcessor()->persistStockStatus($stockStatus);
    }

    /**
     * Persist's the URL rewrite with the passed data.
     *
     * @param array $row The URL rewrite to persist
     *
     * @return string The ID of the persisted entity
     */
    public function persistUrlRewrite($row)
    {
        return $this->getProductProcessor()->persistUrlRewrite($row);
    }

    /**
     * Persist's the URL rewrite product => category relation with the passed data.
     *
     * @param array $row The URL rewrite product => category relation to persist
     *
     * @return void
     */
    public function persistUrlRewriteProductCategory($row)
    {
        $this->getProductProcessor()->persistUrlRewriteProductCategory($row);
    }

    /**
     * Delete's the entity with the passed attributes.
     *
     * @param array       $row  The attributes of the entity to delete
     * @param string|null $name The name of the prepared statement that has to be executed
     *
     * @return void
     */
    public function deleteProduct($row, $name = null)
    {
        $this->getProductProcessor()->deleteProduct($row, $name);
    }

    /**
     * Delete's the URL rewrite(s) with the passed attributes.
     *
     * @param array       $row  The attributes of the entity to delete
     * @param string|null $name The name of the prepared statement that has to be executed
     *
     * @return void
     */
    public function deleteUrlRewrite($row, $name = null)
    {
        $this->getProductProcessor()->deleteUrlRewrite($row, $name);
    }

    /**
     * Delete's the stock item(s) with the passed attributes.
     *
     * @param array       $row  The attributes of the entity to delete
     * @param string|null $name The name of the prepared statement that has to be executed
     *
     * @return void
     */
    public function deleteStockItem($row, $name = null)
    {
        $this->getProductProcessor()->deleteStockItem($row, $name);
    }

    /**
     * Delete's the stock status with the passed attributes.
     *
     * @param array       $row  The attributes of the entity to delete
     * @param string|null $name The name of the prepared statement that has to be executed
     *
     * @return void
     */
    public function deleteStockStatus($row, $name = null)
    {
        $this->getProductProcessor()->deleteStockStatus($row, $name);
    }

    /**
     * Delete's the product website relations with the passed attributes.
     *
     * @param array       $row  The attributes of the entity to delete
     * @param string|null $name The name of the prepared statement that has to be executed
     *
     * @return void
     */
    public function deleteProductWebsite($row, $name = null)
    {
        $this->getProductProcessor()->deleteProductWebsite($row, $name);
    }

    /**
     * Delete's the category product relations with the passed attributes.
     *
     * @param array       $row  The attributes of the entity to delete
     * @param string|null $name The name of the prepared statement that has to be executed
     *
     * @return void
     */
    public function deleteCategoryProduct($row, $name = null)
    {
        $this->getProductProcessor()->deleteCategoryProduct($row, $name);
    }
}
