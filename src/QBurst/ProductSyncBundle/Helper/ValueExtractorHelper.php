<?php

namespace QBurst\ProductSyncBundle\Helper;

use Carbon\Carbon;
use Exception;
use Locale;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\ClassDefinition\Data\Relations\AbstractRelations;
use Pimcore\Model\DataObject\ClassDefinition\Data\Objectbricks;

class ValueExtractorHelper
{
    /**
     * Get field details of a Pimcore class object
     *
     * @param AbstractObject $object
     * @param string $fieldKey
     * @param boolean $returnOnlyValue
     *
     * @return string|array
     */
    public static function getFieldDetails(AbstractObject $object, $fieldKey, $returnOnlyValue = false)
    {
        if (isset(ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[$fieldKey])) {
            $fieldValue = ConfigurationHelper::getData(ConfigurationHelper::CONFIGURED_PRODUCT_FIELDS[$fieldKey]);
        } else {
            $fieldValue = $fieldKey;
        }
        if (strpos($fieldValue, '.') !== false) {
            list($attribute, $field) = explode('.', $fieldValue);
        } else {
            $attribute = $fieldValue;
            $field = null;
        }
        $value = $fieldDefinition = $fieldType = null;
        
        $classDefinitions = $object->getClass()->getFieldDefinitions();
        if (!empty($field)) {
            foreach ($classDefinitions as $classField => $definition) {
                $fieldFound = false;
                if ($definition instanceof AbstractRelations && strtolower($classField) == strtolower($attribute)) {
                    if (method_exists($object, 'get'.ucwords($attribute))) {
                        $value = $object->{'get'.ucwords($attribute)}();
                        if (is_object($value)) {
                            if (!method_exists($value, 'get'.ucwords($field))) {
                                throw new Exception('Field `'.$field.'` does not exist in the relation field `'.$attribute.'`. Please adjust the value provided in the configuration');
                            } 
                            $value = $value->{'get'.ucwords($field)}();
                            $fieldDefinition = $definition;
                            $fieldType = $fieldDefinition->getFieldType();
                        }
                    }
                } elseif ($definition instanceof Objectbricks && !is_null($object->{'get'.ucwords($classField)}())) {
                    $itemDefinitions = $object->{'get'.ucwords($classField)}()->getItemDefinitions();//dd($itemDefinitions);
                    foreach ($itemDefinitions as $itemDefinition) {
                        if (strtolower($itemDefinition->getKey()) == strtolower($attribute)) {
                            $value = $object->{'get'.ucwords($classField)}()->{'get'.ucwords($attribute)}();
                            if (is_object($value)) {
                                if (!method_exists($value, 'get'.ucwords($field))) {
                                    throw new Exception('Field `'.$field.'` does not exist in the object brick `'.$attribute.'`. Please adjust the value provided in the configuration');
                                }
                                $value = $value->{'get'.ucwords($field)}();
                                $fieldDefinition = $itemDefinition->getFieldDefinition($field);
                                $fieldFound = true;
                                break;
                            }
                        }
                    }
                }
                if ($fieldFound) {
                    $fieldType = $fieldDefinition->getFieldType();
                    break;
                }
            }
        } elseif (method_exists($object, 'get'.ucwords($attribute))) {
            $value = $object->{'get'.ucwords($attribute)}();
            if (isset($classDefinitions[$attribute])) {
                $fieldDefinition = $classDefinitions[$attribute];
            } else {
                $fieldDefinition = $object->getClass()->getFieldDefinition($attribute);
            }
            if (is_null($fieldDefinition)) {
                //to include the default fields for a class instance such as key, id etc
                $fieldType = gettype($object->{'get'.$attribute}());
            } elseif (is_array($fieldDefinition)) {
                $fieldType = null;
            } elseif (is_object($fieldDefinition)) {
                $fieldType = $fieldDefinition->getFieldType();
            }
        } else {
            throw new Exception('Field `'.$attribute.'` does not exist in this Pimcore Class. Please adjust the value provided in the configuration');
        }
        
        if (is_object($value)) {
            if ($value instanceof Carbon) {
                $value = $value->format('m/d/Y H:i:s');
            } elseif (method_exists($value, 'getValue')) {
                $value = $value->getValue();
            }
        }
        if (!is_null($value)) {
            if ($fieldType == 'country') {
                $value = [$value => Locale::getDisplayRegion('-'.$value)];
            }
            if ($fieldType == 'countrymultiselect' && is_iterable($value)) {
                foreach ($value as $key => $countryCode) {
                    $value[$countryCode] = Locale::getDisplayRegion('-'.$countryCode);
                    unset($value[$key]);
                }
            }
            if ($fieldType == 'language') {
                $value = [$value => Locale::getDisplayName($value)];
            }
            if ($fieldType == 'languagemultiselect' && is_iterable($value)) {
                foreach ($value as $key => $languageCode) {
                    $value[$languageCode] = Locale::getDisplayName($languageCode);
                    unset($value[$key]);
                }
            }
            $value = self::handleCharacters($value);
        }

        if ($returnOnlyValue) {
            return $value;
        }

        if (empty($fieldDefinition)) {
            if (empty($field)) {
                $fieldLabel = ucwords($attribute);
                $fieldName = $attribute;
            } else {
                $fieldLabel = ucwords($field);
                $fieldName = $field;
            }
        } else {
            $fieldLabel = $fieldDefinition->getTitle();
            $fieldName = $fieldDefinition->getName();
        }

        return [
            'value' => $value,
            'type' => $fieldType,
            'label' => $fieldLabel,
            'name' => $fieldName 
        ];
    }

    /**
     * Seperate comma seperated value, to remove empty values and remove white spaces at beginning and end
     *
     * @param string $value
     * 
     * @return array
     */
    public static function seperateCommaSeperatedFieldValue($value)
    {
        return array_filter(
            array_map(
                'trim', 
                explode(
                    ',', 
                    $value
                )
            )
        );
    }

    /**
     * Avoid the special characters that break the json format
     *
     * @param string $value
     * 
     * @return string
     */
    public static function handleCharacters($value)
    {
        if (is_string($value)) {
            $value = htmlspecialchars_decode(preg_replace('/[\x00-\x1F\x7F]/u', '', str_replace('"', '\\"', htmlentities($value))), ENT_NOQUOTES);
        }
        return $value;
    }
}
