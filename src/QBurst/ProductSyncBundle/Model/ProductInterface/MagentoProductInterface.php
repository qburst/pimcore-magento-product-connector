<?php

namespace QBurst\ProductSyncBundle\Model\ProductInterface;

use Pimcore\Model\DataObject\AbstractObject;

interface MagentoProductInterface
{
    public const ATTRIBUTE_SET_NAME = 'attribute_set_name';
    public const CURRENCY = 'currency';
    public const STATUS = 'status';
    public const STATUS_ENABLED = '1';
    public const STATUS_DISABLED = '2';
    public const TYPE_ID = 'type_id';
    public const PRODUCT_TYPE_CONFIG = 'productType';
    public const SIMPLE_TYPE_ID = 'SIMPLE';
    public const CONFIGURABLE_TYPE_ID = 'CONFIGURABLE';
    public const VISIBILITY = 'visibility';
    public const CONFIGURABLE_ATTRIBUTES = 'configurable_attributes';
    public const PRODUCT_VARIANTS = 'product_variants';
    public const CUSTOM_ATTRIBUTES = 'custom_attributes';
    public const ATTRIBUTE_CODE = 'attribute_code';
    public const ATTRIBUTE_LABEL = 'label';
    public const ATTRIBUTE_OPTIONS = 'options';
    public const ATTRIBUTE_VALUE = 'value';
    public const ATTRIBUTE_VALUE_TRANSLATION = 'translate';
    public const ATTRIBUTE_INPUT = 'input';
    public const ATTRIBUTE_GROUP_NAME = 'attribute_group_name';
    public const IMAGE_TYPES = '[THUMBNAIL, IMAGE, SMALL_IMAGE]';
    public const VIDEO = 'video';
    public const VIDEO_ID = 'id';
    public const VIDEO_URL = 'url';

    /**
     * Get attribute from the processed data
     *
     * @return string
     */
    public function getAttributeSetName();


    /**
     * Format custom attributes for the payload
     *
     * @param boolean $isConfigurable,
     * @param boolean $isVariant
     *
     * @return string
     */
    public function setCustomAttributes( $isConfigurable, $isVariant);

    /**
     * Map the input type in Magento
     *
     * @param string $type Field type in Pimcore
     *
     * @return string
     */
    public function mapInputType($type);

    /**
     * Get product type from the processed data
     *
     * @return string
     */
    public function getProductType();

    /**
     * Fetch and process the product type from class object
     *
     * @param AbstractObject $object
     *
     * @return string
     */
    public function generateProductType(AbstractObject $object);

    /**
     * Get status from the processed data
     *
     * @return string
     */
    public function getStatus();

    /**
     * Fetch and process the status from class object
     *
     * @param AbstractObject $object
     *
     * @return string
     */
    public function generateStatus(AbstractObject $object);

    /**
     * Fetch the list of fields configured for custom attributes
     *
     * @return array
     */
    public function getCustomAttributeFields();

    /**
     * Fetch the list of fields configured for configurable attributes
     *
     * @return array
     */
    public function getConfigurableAttributeFields();

    /**
     * Format the configurable attribute fields from configuration, to match the attributes in Magento
     *
     * @return array
     */
    public function getConfigurableAttributeList();
}
