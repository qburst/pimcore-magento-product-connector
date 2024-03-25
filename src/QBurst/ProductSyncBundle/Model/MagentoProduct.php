<?php
/**
 * Product Model Class
 */
namespace QBurst\ProductSyncBundle\Model;

use Exception;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\Element\ValidationException;
use QBurst\ProductSyncBundle\Helper\ConfigurationHelper;
use QBurst\ProductSyncBundle\Helper\ValueExtractorHelper;
use QBurst\ProductSyncBundle\Model\ProductInterface\MagentoProductInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Pimcore\Model\Asset\Image as AssetImage;
use Pimcore\Model\DataObject\Data\ExternalImage as DataExternalImage;
use Pimcore\Model\DataObject\Data\Hotspotimage;
use Pimcore\Model\DataObject\ClassDefinition\Data\Video;

/**
 * Class Product
 */
class MagentoProduct extends Product implements MagentoProductInterface
{
    /**
     * Constructor function
     */
    public function __construct(TranslatorInterface $translator)
    {
        parent::__construct($translator);
    }

    /**
     * Transform the processed data to create the payload structure in target system
     *
     * @param AbstractObject $object
     *
     * @return string
     */
    protected function transformData(AbstractObject $object)
    {
        $this->fixMagentoCoreAttributeCodes();
        $parentSku = '';
        $attributeSetName = $this->getAttributeSetName();
        $type = SELF::CONFIGURABLE_TYPE_ID;
        $isConfigurable = $isVariant = false;
        $visibility = 4; //Visibile in Both
        $name = $this->getName();
        $description = $this->getDescription();
        $sku = $this->getSku();
        if (empty($sku)) {
            throw new ValidationException(
                ucfirst(
                    $this->configuration[
                        ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[self::SKU]
                    ]
                ).' cannot be empty'
            );
        }
        $sku = $this->generateSku($object, $sku);
        $objectType = $this->getProductType();
        $configurableAttributes = $this->getConfigurableAttributeList();

        $payload = [];
        $payload[] = self::ATTRIBUTE_SET_NAME.': \\"'. $attributeSetName .'\\"';
        $payload[] = self::WEBSITE_URL.': \\"'.
            rtrim(
                $this->configuration[
                    ConfigurationHelper::CONFIG_FIELD_MAGENTO_STORE_URL
                ],
                '/'
            ).
            '\\"';
        $payload[] = self::CURRENCY.': \\"'.
            $this->configuration[
                ConfigurationHelper::CONFIG_FIELD_MAGENTO_CURRENCY
            ].'\\"';
        //Set the type and visibility of product
        if ($objectType == $this->configuration[ConfigurationHelper::CONFIG_FIELD_SIMPLE_PRODUCT_TYPE_VALUE]) {
            $type = self::SIMPLE_TYPE_ID;
            if ($this->checkIfParentProductValid($object)) {
                $isConfigurable = $isVariant = true;
                $visibility = 1; //Not visible individually
                $parentSku = $this->generateSku($object);
                if (!empty($parentSku)) {
                    $payload[] = self::SKU.': \\"'.$parentSku.'\\"';
                    $parentType = $this->generateProductType($object->getParent()) == $this->configuration[ConfigurationHelper::CONFIG_FIELD_SIMPLE_PRODUCT_TYPE_VALUE] ? self::SIMPLE_TYPE_ID : self::CONFIGURABLE_TYPE_ID;
                    $payload[] = self::TYPE_ID.': '.$parentType;
                    $payload[] = self::STATUS.': '.$this->generateStatus($object->getParent());
                    $payload[] = self::CONFIGURABLE_ATTRIBUTES.': [\\"'.$configurableAttributes.'\\"]';
                    $payload[] = self::PRODUCT_VARIANTS.': ['.
                        $this->getChildSkus(
                            $object->getParent()->getChildren()->getData(),
                            $parentSku,
                            [$sku]
                        );
                    $payload[] = '{'.self::ATTRIBUTE_SET_NAME.': \\"'.$attributeSetName.'\\"';
                }
                $name = empty($name) ? $sku : $name;
            }
        } elseif ($objectType == $this->configuration[ConfigurationHelper::CONFIG_FIELD_CONFIGURABLE_PRODUCT_TYPE_VALUE]) {
            $isConfigurable = true;
            if ($this->checkIfParentProductValid($object)) {
                $name = $this->generateName($object->getParent(), $name);
                $description = $this->generateDescription($object->getParent(), $description);
            }
            if ($object->hasChildren()) {
                $payload[] = self::PRODUCT_VARIANTS.': ['.$this->getChildSkus(
                    $object->getChildren()->getData(),
                    $sku
                ).']';
            }
        } else {
            throw new ValidationException('Cannot determine the type of product!!! Please make sure that the values given in configuration for field "'.ucfirst($this->configuration[ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[self::PRODUCT_TYPE_CONFIG]]).'" matches the value given here.');     
        }
        if (empty($name)) {
            throw new ValidationException(
                ucfirst(
                    $this->configuration[
                        ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[self::NAME]
                    ]
                ).' cannot be empty'
            );
        }
        $payload[] = self::NAME.': \\"'.$name.'\\"';
        if (empty(strip_tags($description)) && !$isVariant) {
            throw new ValidationException(
                ucfirst(
                    $this->configuration[
                        ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[
                            self::DESCRIPTION
                        ]
                    ]
                ).' cannot be empty'
            );
        }
        $payload[] = self::DESCRIPTION.': \\"'.$description.'\\"';
        $payload[] = self::SKU.': \\"'.$sku.'\\"';
        $payload[] = self::STATUS.': '.$this->getStatus();
        $payload[] = self::TYPE_ID.': '.$type;
        $payload[] = self::VISIBILITY.': '.$visibility;
        $categories = $this->getCategoryList();
        if (!empty($categories)) {
            $payload[] = self::CATEGORIES.': [\\"'.$categories.'\\"]';
        }
        $customAttributes = $this->setCustomAttributes(
            $isConfigurable,
            $isVariant
        );
        $payload[] = self::CUSTOM_ATTRIBUTES.': ['. $customAttributes .']';
        $payload[] = self::IMAGES.': ['.$this->setImage($object).']';
        $video = $this->setVideo($object);
        if (!empty($video)) {
            $payload[] = self::VIDEO.': '.$video;
        }
        $price = 0;
        if ($type == self::SIMPLE_TYPE_ID) {
            $price = $this->getPrice();
            if (empty($price)) {
                throw new ValidationException(
                    ucfirst(
                        $this->configuration[
                            ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[
                                self::PRICE
                            ]
                        ]
                    ).' cannot be empty'
                );
            }
            $payload[] = self::PRICE.': '.$price;
            $quantity = $this->getQuantity();
            if (!empty($quantity)) {
                $payload[] = self::STOCK.': {'.self::STOCK_QUANTITY.': '.$quantity.'}';
            }
        }
        $payload[] = 'translations:['.$this->translationService->formatTranslationsWithStoreCodes(
            array_diff_key(
                $this->productData,
                array_flip(
                    [
                        $this->configuration[   
                            ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[self::STATUS]
                        ],
                        $this->configuration[
                            ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[self::PRODUCT_TYPE_CONFIG]
                        ],
                        $this->configuration[
                            ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[self::STOCK_QUANTITY]
                        ],
                    ]
                )
            ),
            $this->configuration[
                ConfigurationHelper::CONFIG_FIELD_MAGENTO_STORE_VIEW_TRANSLATIONS
            ]
        ).']';
        if (!$isVariant) {
            $payload[] = self::CONFIGURABLE_ATTRIBUTES.': [\\"'.$configurableAttributes.'\\"]';
        } else {
            $payload[] = '}]';
        }
        $payload = implode(',', $payload);
        return $payload;
    }

    /**
     * Preserve the attributes codes of Magento standard attributes
     *
     * @return void
     */
    private function fixMagentoCoreAttributeCodes()
    {
        $magentoCoreFields = [
            self::NAME,
            self::DESCRIPTION,
            self::SHORT_DESCRIPTION,
            self::SKU,
            self::PRICE
        ];
        foreach ($magentoCoreFields as $field) {
            if (isset($this->productData[$this->configuration[ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[$field]]])) {
                $this->productData[$this->configuration[ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[$field]]]['name'] =  $field;
            }
        }
    }

    /**
     *@inheritDoc
     */
    public function getAttributeSetName()
    {
        return ucfirst(
            $this->configuration[ConfigurationHelper::CONFIG_FIELD_PIMCORE_CLASS]
        );
    }

    /**
     *@inheritDoc
     */
    public function setCustomAttributes(
        $isConfigurable,
        $isVariant
    ) {
        $configurableAttributeFields = $this->getConfigurableAttributeFields();
        $customAttributeFields = $this->getCustomAttributeFields();
        $customAttributes = $attributeGroup = '';
        $userInputs = array_unique(
            array_merge(
                $configurableAttributeFields,
                $customAttributeFields,
                [
                    $this->configuration[
                        ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[
                            self::SHORT_DESCRIPTION
                        ]
                    ]
                ]
            )
        );
        $defaultLanguage = \Pimcore\Tool::getDefaultLanguage();
        foreach ($userInputs as $input) {
            $value = $this->productData[$input]['value'];
            if (!empty($this->productData[$input]['translations']['values']) && isset($this->productData[$input]['translations']['values'][$defaultLanguage])) {
                $value = $this->productData[$input]['translations']['values'][$defaultLanguage];
                if (is_array($value) && isset($value[0]) && is_array($value[0])) {
                    $value = implode(',', array_column($value, 'value'));
                } elseif (is_array($value)) {
                    $value = implode(',', $value);
                }
            }
            if (empty($value)) {
                if ($isConfigurable
                    && in_array($input, $configurableAttributeFields)
                ) {
                    throw new ValidationException(
                        'Field `'.
                        $this->productData[$input]['label'].
                        '` cannot be empty as it is mapped as configurable attribute in Magento'
                    );
                }
                if (!$isVariant
                    && $input == $this->configuration[ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[self::SHORT_DESCRIPTION]]
                ) {
                    throw new ValidationException(
                        'Field `'.
                        $this->configuration[
                            ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[
                                self::SHORT_DESCRIPTION
                            ]
                        ].
                        '` cannot be empty'
                    );
                }
            }
            if (is_array($value)) {
                $value = implode(',', $value);
            }
            $fieldType = $this->productData[$input]['type'];
            if (!is_null($fieldType)) {
                $mappedFieldInput = $this->mapInputType($fieldType);
                if (in_array($input, $configurableAttributeFields)) {
                    if (in_array($mappedFieldInput, ['textarea', 'texteditor', 'multiselect'])) {
                        throw new ValidationException(
                            'Field `'.
                            $this->productData[$input]['label'].
                            '` should only be a select field and not a `'.
                            $fieldType.
                            '` as it is mapped as configurable attribute in Magento'
                        );
                    }
                    $mappedFieldInput = 'select';
                }
                $attributeGroup = '';
                if (strpos($input, '.') !== false && stripos($fieldType, 'relation') === false) {
                    list($attributeGroup, $field) = explode('.', $input);
                    if (empty($field)) {
                        $attributeGroup = '';
                    }
                }
                $customAttributes .= '{'.
                    self::ATTRIBUTE_CODE.': \\"'.$this->createMagentoAttributeCode($this->productData[$input]['name']).'\\", '.
                    self::ATTRIBUTE_VALUE.': \\"'.$value.'\\", '.
                    self::ATTRIBUTE_GROUP_NAME.': \\"'.ucwords($attributeGroup).'\\", '.
                    self::ATTRIBUTE_INPUT.': \\"'.$mappedFieldInput.'\\"'.
                '},';
            }
        }

        return rtrim($customAttributes, ',');
    }

    /**
     *@inheritDoc
     */
    public function mapInputType($type)
    {
        switch ($type) {
            case 'date':
                $input = 'date';
                break;
            case 'datetime':
                $input = 'datetime';
                break;
            case 'textarea':
                $input = 'textarea';
                break;
            case 'checkbox':
                $input = 'boolean';
                break;
            case 'select':
            case 'country':
            case 'language':
            case 'user':
            case 'manyToOneRelation':
                $input = 'select';
                break;
            case 'multiselect':
            case 'countrymultiselect':
            case 'languagemultiselect':
            case 'manyToManyObjectRelation':
                $input = 'multiselect';
                break;
            case 'wysiwyg':
                $input = 'wysiwyg';
                break;
            case 'input':
            case 'numeric':
            default:
                $input = 'text';
                break;
        }

        return $input;
    }

    /**
     *@inheritDoc
     */
    public function generateSku(AbstractObject $object, $sku = '')
    {
        if ($this->checkIfParentProductValid($object)) {
            $sku = ValueExtractorHelper::getFieldDetails($object->getParent(), self::SKU, true).' '.$sku;
            $sku = $this->generateSku($object->getParent(), $sku);
        }

        return trim(str_replace(' ', '-', $sku), '-');
    }

    /**
     *@inheritDoc
     */
    public function getProductType()
    {
        return $this->productData[
            $this->configuration[
                    ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[self::PRODUCT_TYPE_CONFIG]
                ]
            ]['value'];
    }

    /**
     *@inheritDoc
     */
    public function generateProductType(AbstractObject $object)
    {
        return trim(ValueExtractorHelper::getFieldDetails($object, self::PRODUCT_TYPE_CONFIG, true));
    }

    /**
     * Check if the parent is a valid product
     *
     * @param AbstractObject $object Object to handle
     *
     * @return bool
     */
    protected function checkIfParentProductValid(AbstractObject $object)
    {
        return
            $object->getParent() &&
            $object->getParent()->getType() != 'folder' &&
            $object->getParent()->getClassId() == $object->getClassId() &&
            ValueExtractorHelper::getFieldDetails($object->getParent(), self::PRODUCT_TYPE_CONFIG, true) == $this->configuration[ConfigurationHelper::CONFIG_FIELD_CONFIGURABLE_PRODUCT_TYPE_VALUE];
    }

    /**
     *@inheritDoc
     */
    public function getStatus()
    {
        return (empty(
            $this->productData[
                $this->configuration[
                    ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[self::STATUS]
                ]
            ]['value']
        ) ? self::STATUS_DISABLED : self::STATUS_ENABLED);
    }

    /**
     *@inheritDoc
     */
    public function generateStatus(AbstractObject $object)
    {
        return (empty(
            ValueExtractorHelper::getFieldDetails(
                $object,
                self::STATUS,
                true
            )
        ) ? self::STATUS_DISABLED : self::STATUS_ENABLED);
    }

    /**
     *@inheritDoc
     */
    public function getCustomAttributeFields()
    {
        return ValueExtractorHelper::seperateCommaSeperatedFieldValue(
            $this->configuration[
                ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[
                    self::CUSTOM_ATTRIBUTES
                ]
            ]
        );
    }

    /**
     *@inheritDoc
     */
    public function getConfigurableAttributeFields()
    {
        return ValueExtractorHelper::seperateCommaSeperatedFieldValue(
            $this->configuration[
                ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[
                    self::CONFIGURABLE_ATTRIBUTES
                ]
            ]
        );
    }

    /**
     * Generate the Magento attribute code
     *
     * @param string $code
     *
     * @return string
     */
    private function createMagentoAttributeCode($code)
    {
        return strtolower(
            preg_replace(
                '/(?<!^)[A-Z]/',
                '_$0',
                $code
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function getConfigurableAttributeList()
    {
        $configurableAttributes = $this->getConfigurableAttributeFields();
        foreach ($configurableAttributes as $key => $attribute) {
            if (strpos($attribute, '.') !== false) {
                $value = explode('.', $attribute);
                $configurableAttributes[$key] = $this->createMagentoAttributeCode(array_pop($value));
            } else {
                $configurableAttributes[$key] = $this->createMagentoAttributeCode($attribute);
            }
        }

        return implode('\\", \\"', $configurableAttributes);
    }

    /**
     * @inheritDoc
     */
    public function loadGallery(array $gallery)
    {
        $images = '';
        foreach ($gallery as $image) {
            $addTypes = false;
            if (empty($images)) {
                $addTypes = true;
            }
            if ($image instanceof Hotspotimage && !empty($image->getImage())) {
                $imageData = $image->getImage();
                $images .= '{'.self::IMAGE_URL.': \\"'.\Pimcore\Tool::getHostUrl().$imageData->getFrontendFullPath().'\\"';
                if ($addTypes) {
                    $images .= ', types:'.self::IMAGE_TYPES;
                }
                $images .= '},';
            } elseif ($image instanceof AssetImage && !empty($image->getFrontendFullPath())) {
                $images .= '{'.self::IMAGE_URL.': \\"'.\Pimcore\Tool::getHostUrl().$image->getFrontendFullPath().'\\"';
                if ($addTypes) {
                    $images .= ', types:'.self::IMAGE_TYPES;
                }
                $images .= '},';
            } elseif ($image instanceof DataExternalImage && !empty($image->getUrl())) {
                $images .= '{'.self::IMAGE_URL.': \\"'.$image->getUrl().'\\"';
                if ($addTypes) {
                    $images .= ', types:'.self::IMAGE_TYPES;
                }
                $images .= '},';
            }
        }

        return rtrim($images, ',');
    }

    /**
     * @inheritDoc
     */
    public function setVideo(AbstractObject $object)
    {
        $videos = '';
        $fieldDefinitions = $object->getClass()->getFieldDefinitions();
        foreach ($fieldDefinitions as $field => $definition) {
            if ($definition instanceof Video) {
                $fieldDetails = $object->{'get'.ucwords($field)}();
                if (empty($fieldDetails)) {
                    return null;
                }
                $type = $fieldDetails->getType();
                if ($type != Video::TYPE_YOUTUBE && $type != Video::TYPE_VIMEO) {
                    throw new Exception("$type type of video is not supoorted in Magento. Please remove this type of video to continue.");
                }
                $videoId = $fieldDetails->getData();
                switch ($type) {
                    case Video::TYPE_YOUTUBE:
                        $url = "https://www.youtube.com/watch?v=".$videoId;
                        break;
                    case Video::TYPE_VIMEO:
                        $url = "https://vimeo.com/".$videoId;
                        break;
                }
                $videos = '{'.self::VIDEO_ID.': \\"'.$videoId.'\\",'.self::VIDEO_URL.': \\"'.$url.'\\"},';
            }
        }
        return rtrim($videos, ',');
    }
}
