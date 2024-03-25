<?php

namespace QBurst\ProductSyncBundle\Helper;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class ConfigurationHelper
{
    /**
     * @var string
     */
    private static $configurationPath = PIMCORE_PROJECT_ROOT.'/config/qburst_productsync';

    /**
     * @var string
     */
    public const CONFIG_FIELD_PIMCORE_CLASS = 'pimcoreClass';

    /**
     * @var string
     */
    public const CONFIG_FIELD_MAGENTO_STORE_URL = 'magentoStoreUrl';

    /**
     * @var string
     */
    public const CONFIG_FIELD_MAGENTO_ACCESS_TOKEN = 'magentoAccessToken';

    /**
     * @var string
     */
    public const CONFIG_FIELD_MAGENTO_CURRENCY = 'magentoCurrency';

    /**
     * @var string
     */
    public const CONFIG_FIELD_MAGENTO_STORE_VIEW_TRANSLATIONS = 'magentoStoreViewTranslations';

    /**
     * @var string
     */
    public const CONFIG_FIELD_CONFIGURABLE_PRODUCT_TYPE_VALUE = 'configurableProductTypeValue';

    /**
     * @var string
     */
    public const CONFIG_FIELD_SIMPLE_PRODUCT_TYPE_VALUE = 'simpleProductTypeValue';

    /**
     * Mapping between Magento product fields and configuration fields
     */
    public const CONFIGURED_PRODUCT_FIELDS = [
        'name' => 'productName',
        'description' => 'productDescription',
        'shortDescription' => 'productShortDescription',
        'sku' => 'productSku',
        'price' => 'productPrice',
        'quantity' => 'productQuantity',
        'status' => 'productStatus',
        'categories' => 'MagentoProductCategory',
        'productType' => 'parentProductType',
        'configurable_attributes' => 'magentoConfigurableAttributes',
        'custom_attributes' => 'magentoCustomAttributes',
    ];

    /**
     * Get the product configured fields and its saved values as a key-value pair
     *
     * @return array
     */
    public static function getConfiguredFieldList()
    {
        $configuredFieldList = self::getData();
        $removeKeys = [
            self::CONFIGURED_PRODUCT_FIELDS['categories'],
        ];

        $remainingConfiguredFieldList = array_diff_key($configuredFieldList, array_flip($removeKeys));
        $remainingConfiguredFieldList = array_intersect_key($remainingConfiguredFieldList, array_flip(self::CONFIGURED_PRODUCT_FIELDS));
        foreach ($remainingConfiguredFieldList as $key => $field) {
            if (in_array($key, ['magentoConfigurableAttributes', 'magentoCustomAttributes'])) {
                if (strpos($field, ',') !== false) {
                    $remainingConfiguredFieldList[$key] = array_filter(explode(',', $field));
                }
            }
        }

        return $remainingConfiguredFieldList;
    }

    /**
     * Get the field value, if key provided or all values saved in configuration
     *
     * @param string $key
     *
     * @return array|string
     */
    public static function getData($key = '')
    {
        $configuration = self::getConfiguration();
        if (!empty($key)) {
            if (isset($configuration[$key])) {
                return $configuration[$key];
            } else {
                return null;
            }
        }

        return $configuration;
    }

    /**
     * Get the configuration details from file
     *
     * @return array
     */
    private static function getConfiguration(): array
    {

        $filename = self::getConfigurationFile();

        try {
            $ymlarray = Yaml::parseFile($filename);
        } catch (ParseException $exception) {
            return [];
        }

        return $ymlarray;

    }

    /**
     * Save the configuration details in file
     *
     * @param array $configuration
     *
     * @return bool
     */
    public static function setData(array $configuration): bool
    {
        $filename = self::getConfigurationFile();

        try {
            $yaml = Yaml::dump($configuration);
            file_put_contents($filename, $yaml);
        } catch (\Exception $exception) {
            return false;
        }

        return true;
    }

    /**
     * Get the default configuration details from file
     *
     * @return array
     */
    public static function getDefaultConfig(): array
    {
        $filename = self::getDefaultConfigFile();

        try {
            $ymlarray = Yaml::parseFile($filename);
        } catch (ParseException $exception) {
            return [];
        }

        return $ymlarray;
    }

    /**
     * Get the configuration file path
     *
     * @return string
     */
    private static function getConfigurationFile(): string
    {
        $file = self::$configurationPath.'/connector_configuration.yml';

        if (!is_file($file)) {
            if (!is_dir(self::$configurationPath)) {
                mkdir(self::$configurationPath);
            }
            $defaultcontent = self::getDefaultConfig();
            $yaml = Yaml::dump($defaultcontent);
            file_put_contents($file, $yaml);
        }

        return $file;
    }

    /**
     * Get the default configuration filepath
     *
     * @return string
     */
    private static function getDefaultConfigFile(): string
    {
        try {
            return __DIR__.'/../Resources/var/defaultconfiguration.yml';
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Get the default configuration details from file
     *
     * @return array
     */
    public static function getCurrenciesConfig(): array
    {
        $filename = self::getCurrenciesConfigFile();

        try {
            $ymlarray = Yaml::parseFile($filename);
        } catch (ParseException $exception) {
            return [];
        }

        return $ymlarray;
    }

    /**
     * Get the currencies configuration filepath
     *
     * @return string
     */
    private static function getCurrenciesConfigFile(): string
    {
        try {
            return __DIR__.'/../Resources/var/currencies.yml';
        } catch (\Exception $e) {
            return '';
        }
    }
}
