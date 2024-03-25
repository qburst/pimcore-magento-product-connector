<?php

namespace QBurst\ProductSyncBundle\Service;

use Symfony\Contracts\Translation\TranslatorInterface;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields;
use Pimcore\Model\DataObject\ClassDefinition\Data\Objectbricks;
use Pimcore\Model\DataObject\ClassDefinition\Data\Relations\AbstractRelations;
use QBurst\ProductSyncBundle\Helper\ConfigurationHelper;
use QBurst\ProductSyncBundle\Helper\ValueExtractorHelper;
use QBurst\ProductSyncBundle\Model\ProductInterface\MagentoProductInterface;

class TranslationService
{
   /**
    * Translation key used for attribute labels
    */
    private const LABEL_TRANSLATION_KEY = 'general';

    /**
     * Translation key used for attribute option labels
     */
    private const ATTRIBUTE_TRANSLATION_KEY = 'attribute';

    /**
     * Field types for different dropdowns offered in Pimcore
     */
    private const DROPDOWN_FIELD_TYPES = ['select', 'multiselect', 'manyToOneRelation', 'manyToManyObjectRelation', 'country', 'language', 'countrymultiselect', 'languagemultiselect'];

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var array
     */
    protected $localizedfields;

    /**
     * Initialise the class
     *
     * @param TranslatorInterface $translator
     */
    public function __construct(TranslatorInterface $translator) {
        $this->translator = $translator;
        $this->localizedfields = [];
    }
    /**
     * Process the product to return the data including translations
     *
     * @param AbstractObject $object
     *
     * @return array
     */
    public function getProductDataIncludingTranslations(AbstractObject $object)
    {
        $product = [];
        //get product field details as provided in the configuration
        $pimcoreProductFields = ConfigurationHelper::getConfiguredFieldList();
        foreach ($pimcoreProductFields as $configuredField) {
            if (is_array($configuredField)) {
                //comma-seperated field
                foreach ($configuredField as $attribute) {
                    $product[$attribute] = ValueExtractorHelper::getFieldDetails($object, $attribute);
                }
            } else {
                $product[$configuredField] = ValueExtractorHelper::getFieldDetails($object, $configuredField);
            }
        }
        self::getLocalizedFieldList($object, $product);
        foreach ($product as $name => $details) {
            foreach ($this->localizedfields as $languageCode => $field) {
                if (in_array($details['type'], self::DROPDOWN_FIELD_TYPES)) {
                    if (!array_key_exists('translations', $product[$name])) {
                        $product[$name]['translations'] = [];
                        $product[$name]['translations']['values'] = [];
                    }
                    if (in_array($details['type'], ['country', 'language', 'countrymultiselect', 'languagemultiselect']) && is_array($details['value'])) {
                        $product[$name]['translations']['values'][$languageCode] = array_keys($details['value']);
                    } elseif ($details['type'] == 'multiselect') {
                        $product[$name]['translations']['values'][$languageCode] = $details['value'];
                    } elseif ($details['type'] == 'select') {
                        $product[$name]['translations']['values'][$languageCode][] = $details['value'];
                    }
                } elseif (isset($field[$name])) {
                    if (!array_key_exists('translations', $product[$name])) {
                        $product[$name]['translations'] = [];
                        $product[$name]['translations']['values'] = [];
                    }
                    if (is_array($field[$name])) {
                        list($attribute, $fieldName) = explode('.', $name);
                        if (isset($field[$name][$fieldName])) {
                            $product[$name]['translations']['values'][$languageCode] = $field[$name][$fieldName];
                        } elseif (is_array($field[$name])) {
                            foreach ($field[$name] as $translatedDetails) {
                                if (isset($translatedDetails[$fieldName])) {
                                    $product[$name]['translations']['values'][$languageCode][] = $translatedDetails[$fieldName];
                                }
                            }
                        }
                    } else {
                        $product[$name]['translations']['values'][$languageCode] = $field[$name];
                    }
                }
            }
        }
        //load label and value translation for the fields configured
        $product = self::loadTranslations($product);
        return $product;
    }

    /**
     * Get the list of localized fields and respective values for the configured fields of the target class instance
     *
     * @param AbstractObject $object
     * @param array $product
     * @param string $translationIndex
     *
     * @return array
     */
    public function getLocalizedFieldList(AbstractObject $object, $product, $translationIndex = '')
    {
        $fieldDefinitions = $object->getClass()->getFieldDefinitions();
        foreach ($fieldDefinitions as $field => $definition) {
            $configurationField = $translationIndex;
            if ($definition instanceof Localizedfields) {
                $items = $object->{'get'.ucwords($field)}()->getItems();
                foreach ($items as $languageCode => $item) {
                    foreach ($item as $fieldName => $translatedValue) {
                        $productConfiguredField = ltrim($configurationField.'.'.$fieldName, '.');
                        if (isset($product[$productConfiguredField])) {
                            if (!empty($translationIndex)) {
                                $this->localizedfields[$languageCode][$productConfiguredField][] = $item;
                            } else {
                                $this->localizedfields[$languageCode][$productConfiguredField] = ValueExtractorHelper::handleCharacters($translatedValue);
                            }
                        }
                    }
                }
            } elseif ($definition instanceof Objectbricks && !is_null($object->{'get'.ucwords($field)}())) {
                $itemDefinitions = $object->{'get'.ucwords($field)}()->getItemDefinitions();
                foreach ($itemDefinitions as $itemField => $itemDefinition) {
                    $itemFieldDefinitions = $itemDefinition->getFieldDefinitions();
                    foreach ($itemFieldDefinitions as $itemFieldName => $itemFieldDefinition) {
                        if ($itemFieldDefinition instanceof Localizedfields) {
                            $items = $object->{'get'.ucwords($field)}()->{'get'.ucwords($itemField)}()->{'get'.ucwords($itemFieldName)}()->getItems();
                            foreach ($items as $languageCode => $item) {
                                foreach ($item as $fieldName => $translatedValue) {
                                    $productConfiguredField = ltrim($configurationField.'.'.lcfirst($itemField).'.'.lcfirst($fieldName), '.');
                                    if (isset($product[$productConfiguredField])) {
                                        if (!empty($translationIndex)) {
                                            $this->localizedfields[$languageCode][$productConfiguredField][] = ValueExtractorHelper::handleCharacters($item);
                                        } else {
                                            $this->localizedfields[$languageCode][$productConfiguredField] = ValueExtractorHelper::handleCharacters($translatedValue);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } elseif ($definition instanceof AbstractRelations) {
                $fieldDetails = $object->{'get'.ucwords($field)}();
                if (!empty($fieldDetails)) {
                    if (!is_object($fieldDetails) && is_array($fieldDetails)) {
                        foreach ($fieldDetails as $detail) {
                            $this->getLocalizedFieldList($detail, $product, ltrim($configurationField.'.'.$field, '.'));
                        }
                    } else {
                        $this->getLocalizedFieldList($fieldDetails, $product, ltrim($configurationField.'.'.$field, '.'));
                    }
                }
            }
        }
    }

    /**
     * Load the attribute and attribute option label translations
     *
     * @param TranslatorInterface $translator
     * @param array $productValues
     *
     * @return array
     */
    private function loadTranslations($productValues)
    {
        foreach ($productValues as $key => $fieldDetail) {
            if (!array_key_exists('translations', $productValues[$key])) {
                $productValues[$key]['translations'] = [];
                $productValues[$key]['translations']['values'] = [];
            }
            $productValues[$key]['translations']['label'] = self::loadLabelTranslation($fieldDetail['name'], $fieldDetail['label']);
            $productValues[$key]['translations']['values'] = self::loadValueTranslation($productValues[$key]['translations']['values'], $fieldDetail['type']);
        }
        return $productValues;
    }

    /**
     * Load the attribute label translation from Admin translations
     *
     * @param string $name
     * @param string $label
     *
     * @return string|array
     */
    private function loadLabelTranslation($name, $label)
    {
        return self::loadFromAdminTranslationForAllLanguages(self::LABEL_TRANSLATION_KEY, $name, $label);
    }

    /**
     * Load the attribute option label translation from Admin translations
     *
     * @param string|array $values
     * @param string $fieldType
     *
     * @return array
     */
    private function loadValueTranslation($values, $fieldType)
    {
        if (empty($values) || !in_array($fieldType, self::DROPDOWN_FIELD_TYPES)) {
            return $values;
        }
        $localizedfield = [];
        $defaultLanguage = \Pimcore\Tool::getDefaultLanguage();
        foreach ($values as $languageCode => $value) {
            $localizedfield[$languageCode] = [];
            if (is_array($value)) {
                foreach ($value as $index => $multiselectValue) {
                    if ($fieldType == 'select') {
                        $localizedfield[$languageCode][] = [
                            'translate' => self::loadFromAdminTranslation(self::ATTRIBUTE_TRANSLATION_KEY, $languageCode, $multiselectValue),
                            'value' => self::loadFromAdminTranslation(self::ATTRIBUTE_TRANSLATION_KEY, $defaultLanguage, $multiselectValue)
                        ];
                    } elseif ($fieldType == 'manyToOneRelation' || $fieldType == 'manyToManyObjectRelation') {
                        $localizedfield[$languageCode][] = [
                            'translate' => $multiselectValue,
                            'value' => $values[$defaultLanguage][$index]
                        ];
                    } elseif ($fieldType == 'multiselect') {
                        $localizedfield[$languageCode][] = [
                            'translate' => self::loadFromAdminTranslation(self::ATTRIBUTE_TRANSLATION_KEY, $languageCode, $multiselectValue),
                            'value' => self::loadFromAdminTranslation(self::ATTRIBUTE_TRANSLATION_KEY, $defaultLanguage, $multiselectValue),
                        ];
                    } elseif (in_array($fieldType, ['country', 'countrymultiselect'])) {
                        $localizedfield[$languageCode][] = [
                            'translate' => \Locale::getDisplayRegion('-'.$multiselectValue, $languageCode),
                            'value' => \Locale::getDisplayRegion('-'.$multiselectValue, $defaultLanguage)
                        ];
                    } elseif (in_array($fieldType, ['language', 'languagemultiselect'])) {
                        $localizedfield[$languageCode][] = [
                            'translate' => \Locale::getDisplayName($multiselectValue, $languageCode),
                            'value' => \Locale::getDisplayName($multiselectValue, $defaultLanguage)
                        ];
                    }
                }
            }
        }        
        
        return $localizedfield;
    }

    /**
     * Load translations for all valid languages from Admin transltaions
     *
     * @param string $translationKey
     * @param string $value
     * @param string $default
     *
     * @return array
     */
    private function loadFromAdminTranslationForAllLanguages($translationKey, $value, $default = '') {
        $translations = [];
        $validLanguages = \Pimcore\Tool::getValidLanguages();
        foreach ($validLanguages as $languageCode) {
            $translations[$languageCode] = self::loadFromAdminTranslation($translationKey, $languageCode, $value, $default);
        }
        return $translations;
    }

    /**
     * Load translations against a language from Admin transltaions
     *
     * @param string $translationKey
     * @param string $languageCode
     * @param string $value
     * @param string $default
     *
     * @return array
     */
    private function loadFromAdminTranslation($translationKey, $languageCode, $value, $default = '')
    {
        $translated = $this->translator->trans($translationKey.'.'.$value, [], null, $languageCode);
        if ($translated == $translationKey.'.'.$value) {
            return empty($default) ? $value : $default;
        }
            return $translated;
    }

    /**
     * Process and generate the translation values for payload
     *
     * @param array $processedData
     * @param array $storeVieMapConfiguration
     *
     * @return string
     */
    public function formatTranslationsWithStoreCodes($processedData, $storeVieMapConfiguration)
    {
        // Filtered array
        $filteredArray = '';
        $languageStoreviewMap = self::mapLanguageAndStoreViews($storeVieMapConfiguration);

        foreach ($languageStoreviewMap as $language => $storeview) {
            $attributes = '[';
            foreach ($processedData as $data) {
                $translationData = $data['translations'];
                if (empty($data['name']) || empty($data['type'])) {
                    continue;
                }
                $attributes .= '{'.
                    MagentoProductInterface::ATTRIBUTE_CODE.':\"'.strtolower(
                        preg_replace(
                            '/(?<!^)[A-Z]/',
                            '_$0',
                            $data['name']
                        )
                    ).'\",'.
                    MagentoProductInterface::ATTRIBUTE_LABEL.':\"'.$translationData['label'][$language].'\",';
                if (!isset($translationData['values'][$language])) {
                    $attributes = rtrim($attributes, ',').'},';
                    continue;
                }
                if (is_array($translationData['values'][$language])) {
                    //dropdown field
                    $isDropdownField = true;
                    $attributeOptions = '';
                    foreach ($translationData['values'][$language] as $translationDetails) {
                        if (!isset($translationDetails['value']) && is_array($translationDetails)) {
                            //everything other than a select field from dropdowns
                            foreach ($translationDetails as $translatedValues) {
                                $attributeOptions .= '{'.
                                    MagentoProductInterface::ATTRIBUTE_VALUE.':\"'.$translatedValues['value'].'\",'.
                                    MagentoProductInterface::ATTRIBUTE_VALUE_TRANSLATION.':\"'.$translatedValues['translate'].'\"'.
                                '},';
                            }
                        } elseif (isset($translationDetails['value'])) {
                            $attributeOptions .= '{'.
                                MagentoProductInterface::ATTRIBUTE_VALUE.':\"'.$translationDetails['value'].'\",'.
                                MagentoProductInterface::ATTRIBUTE_VALUE_TRANSLATION.':\"'.$translationDetails['translate'].'\"'.
                            '},';
                        } else {
                            $isDropdownField = false;
                            $attributeOptions .= implode(",", $translationData['values'][$language]);
                            break;
                        }
                    }
                    if ($isDropdownField) {
                        $attributes .= MagentoProductInterface::ATTRIBUTE_OPTIONS.':['.rtrim($attributeOptions, ',').']},';
                    } else {
                        $attributes .= MagentoProductInterface::ATTRIBUTE_VALUE.':\"'.$attributeOptions.'\"},';
                    }
                } else {
                    //everything other than a dropdown field
                    $attributes .= MagentoProductInterface::ATTRIBUTE_VALUE.':\"'.$translationData['values'][$language].'\"},';
                }
            }
            $filteredArray .= '{'.
                'storeViewCode:\"'.$storeview.'\",'.
                'attributes:'.rtrim($attributes, ',').']'.
            '},';
        }

        // Output the filtered array
        return rtrim($filteredArray, ',');
    }

    /**
     * Map Language and Magento store views as given in configuration
     *
     * @param string $storeVieMapConfiguration
     *
     * @return array
     */
    private function mapLanguageAndStoreViews($storeVieMapConfiguration)
    {
        // Array mapping languages to storeviews
        $languageStoreviewMap = [];

        // Split the input string into language-storeview pairs
        $languageStoreviewPairs = explode(' ', $storeVieMapConfiguration);
        foreach ($languageStoreviewPairs as $pair) {
            list($language, $storeview) = explode(':', $pair);
            $languageStoreviewMap[$language] = $storeview;
        }
        return $languageStoreviewMap;
    }
}
