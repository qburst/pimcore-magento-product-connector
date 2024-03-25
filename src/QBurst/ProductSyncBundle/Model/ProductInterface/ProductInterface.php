<?php

namespace QBurst\ProductSyncBundle\Model\ProductInterface;

use Pimcore\Model\DataObject\AbstractObject;

interface ProductInterface
{
    public const WEBSITE_URL = 'website_url';
    public const NAME = 'name';
    public const DESCRIPTION = 'description';
    public const SHORT_DESCRIPTION = 'shortDescription';
    public const SKU = 'sku';
    public const CATEGORIES = 'categories';
    public const IMAGES = 'images';
    public const IMAGE_URL = 'url';
    public const PRICE = 'price';
    public const STOCK = 'stock';
    public const STOCK_QUANTITY = 'quantity';

    /**
     * Get name from the processed data
     *
     * @return string
     */
    public function getName();

    /**
     * Fetch and process the name from class object
     *
     * @param AbstractObject $object
     * @param string $trailingPart
     *
     * @return string
     */
    public function generateName(AbstractObject $object, $trailingPart);

    /**
     * Get description from the processed data
     *
     * @return string
     */
    public function getDescription();

    /**
     * Fetch and process the description from class object
     *
     * @param AbstractObject $object
     * @param string $trailingPart
     *
     * @return string
     */
    public function generateDescription(AbstractObject $object, $trailingPart);

    /**
     * Get SKU from the processed data
     *
     * @return string
     */
    public function getSku();

    /**
     * Fetch and process the name from class object
     *
     * @param AbstractObject $object
     * @param string $sku
     *
     * @return string
     */
    public function generateSku(AbstractObject $object, $sku = '');

    /**
     * Get the list of categories assigned
     *
     * @return string
     */
    public function getCategoryList();

    /**
     * Get price from the processed data
     *
     * @return string
     */
    public function getPrice();

    /**
     * Get quantity from the processed data
     *
     * @return string
     */
    public function getQuantity();

    /**
     * Generate the product image gallery list
     *
     * @param AbstractObject $object
     *
     * @return string
     */
    public function setImage(AbstractObject $object);

    /**
     * Format the image gallery details
     *
     * @param array $gallery
     *
     * @return string
     */
    public function loadGallery(array $gallery);
}
