<?php
/**
 * Product Model namespace
 */
namespace QBurst\ProductSyncBundle\Model;

use Pimcore\Model\Asset\Image as AssetImage;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\ClassDefinition\Data\ExternalImage;
use Pimcore\Model\DataObject\ClassDefinition\Data\Image;
use Pimcore\Model\DataObject\ClassDefinition\Data\ImageGallery;
use Pimcore\Model\DataObject\ClassDefinition\Data\Hotspotimage as HotspotimageClassDefinition;
use Pimcore\Model\DataObject\Data\ExternalImage as DataExternalImage;
use Pimcore\Model\DataObject\Data\Hotspotimage;
use QBurst\ProductSyncBundle\Helper\ConfigurationHelper;
use QBurst\ProductSyncBundle\Service\TranslationService;
use QBurst\ProductSyncBundle\Helper\ValueExtractorHelper;
use QBurst\ProductSyncBundle\Model\ProductInterface\ProductInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class Product
 */
abstract class Product implements ProductInterface
{
    /**
     * @var array
     */
    protected $configuration;

    /**
     * @var object
     */
    protected $translationService;

    /**
     * @var array
     */
    public $productData;

    /**
     * Constructor function
     */
    public function __construct(TranslatorInterface $translator)
    {
        $this->configuration = ConfigurationHelper::getData();
        $this->translationService = new TranslationService($translator);
    }

    /**
     * Create a new payload structure for the target system
     *
     * @param AbstractObject $object Object to handle
     *
     * @return string
     */
    public function createPayload(AbstractObject $object)
    {
        $this->productData = $this->translationService->getProductDataIncludingTranslations($object);
        return $this->transformData($object);
    }

    /**
     * Transform the processed data to create the payload structure in target system
     *
     * @param AbstractObject $object
     *
     * @return string
     */
    protected abstract function transformData(AbstractObject $object);

    /**
     *@inheritDoc
     */
    public function getName()
    {
        return $this->productData[
            $this->configuration[
                ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[self::NAME]
            ]
        ]['value'];
    }

    /**
     *@inheritDoc
     */
    public function generateName(AbstractObject $object, $trailingPart = '')
    {
        return trim(
            ValueExtractorHelper::getFieldDetails(
                $object,
                self::NAME,
                true
            ).' '.$trailingPart
        );
    }

    /**
     *@inheritDoc
     */
    public function getDescription()
    {
        return $this->productData[
            $this->configuration[
                    ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[self::DESCRIPTION]
                ]
            ]['value'];
    }

    /**
     *@inheritDoc
     */
    public function generateDescription(AbstractObject $object, $trailingPart = '')
    {
        return trim(
            ValueExtractorHelper::getFieldDetails(
                $object,
                self::DESCRIPTION,
                true
            ).' '.$trailingPart
        );
    }

    /**
     *@inheritDoc
     */
    public function getSku()
    {
        return $this->productData[
            $this->configuration[
                ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[self::SKU]
            ]
        ]['value'];
    }

    /**
     *@inheritDoc
     */
    public function generateSku(AbstractObject $object, $sku = '')
    {
        if ($object->getParent() && $object->getParent()->getType() != 'folder') {
            $sku = ValueExtractorHelper::getFieldDetails($object->getParent(), self::SKU, true).' '.$sku;
            $sku = $this->generateSku($object->getParent(), $sku);
        }

        return trim(str_replace(' ', '-', $sku), '-');
    }

    /**
     * Generate the sku of the child prducts
     *
     * @param array $children Children product collection
     * @param string $parentSku Sku of parent product
     * @param array $excludedChildSkus List of product sku to exclude
     *
     * @return string
     */
    protected function getChildSkus(array $children, $parentSku, $excludedChildSkus = [])
    {
        $payload = '';
        foreach ($children as $child) {
            if ($child->getType() != 'folder') {
                $childSku = $parentSku.'-'.
                trim(
                    str_replace(
                        ' ',
                        '-',
                        ValueExtractorHelper::getFieldDetails($child, self::SKU, true)
                    ),
                    '-'
                );
                if (!in_array($childSku, $excludedChildSkus)) {
                    $payload .= '{'.self::SKU.': \\"'. $childSku .'\\"},';
                }
            }
        }

        return rtrim($payload, ',');
    }

    /**
     * @inheritDoc
     */
    public function getCategoryList()
    {
        return implode(
            '\\", \\"',
            ValueExtractorHelper::seperateCommaSeperatedFieldValue(
                $this->configuration[
                    ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[
                        self::CATEGORIES
                    ]
                ]
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function getPrice()
    {
        return $this->productData[
            $this->configuration[
                ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[
                    self::PRICE
                ]
            ]
        ]['value'];
    }

    /**
     * @inheritDoc
     */
    public function getQuantity()
    {
        return $this->productData[
            $this->configuration[
                ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[
                    self::STOCK_QUANTITY
                ]
            ]
        ]['value'];
    }

    /**
     * @inheritDoc
     */
    public function setImage(AbstractObject $object)
    {
        $images = [];
        $fieldDefinitions = $object->getClass()->getFieldDefinitions();
        foreach ($fieldDefinitions as $field => $definition) {
            if ($definition instanceof Image || $definition instanceof HotspotimageClassDefinition) {
                $images[] = $object->{'get'.ucwords($field)}();
            } elseif ($definition instanceof ImageGallery) {
                $images = array_merge($images, $object->{'get'.ucwords($field)}()->getItems());
            } elseif ($definition instanceof ExternalImage) {
                $images[] = $object->{'get'.ucwords($field)}();
            }
        }
        return $this->loadGallery($images);
    }

    /**
     * @inheritDoc
     */
    public function loadGallery(array $gallery)
    {
        $images = '';
        foreach ($gallery as $image) {
            if ($image instanceof Hotspotimage && !empty($image->getImage())) {
                $imageData = $image->getImage();
                $images .= '{'.self::IMAGE_URL.': \\"'.\Pimcore\Tool::getHostUrl().$imageData->getFullPath().'\\"';
                $images .= '},';
            } elseif ($image instanceof AssetImage && !empty($image->getFullPath())) {
                $images .= '{'.self::IMAGE_URL.': \\"'.\Pimcore\Tool::getHostUrl().$image->getFullPath().'\\"},';
            } elseif ($image instanceof DataExternalImage && !empty($image->getUrl())) {
                $images .= '{'.self::IMAGE_URL.': \\"'.$image->getUrl().'\\"';
                $images .= '},';
            }
        }

        return rtrim($images, ',');
    }
}
