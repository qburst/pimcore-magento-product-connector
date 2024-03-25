<?php
/**
 * This file listens for the product events in Pimcore
 */
namespace QBurst\ProductSyncBundle\EventListener;

use Exception;
use GuzzleHttp\Client;
use Pimcore\Extension\Bundle\PimcoreBundleManager;
use Pimcore\Bundle\ApplicationLoggerBundle\FileObject;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Event\Model\ElementEventInterface;
use Pimcore\Extension\Bundle\Exception\BundleNotFoundException;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\Element\Note;
use Pimcore\Model\Element\ValidationException;
use Pimcore\Tool\Admin;
use QBurst\ProductSyncBundle\Helper\ConfigurationHelper;
use QBurst\ProductSyncBundle\Model\MagentoProduct;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ProductUpdateSenderListener
{
    const LOGGER_NAME = 'sync-with-magento-product';

    /**
     * Magento Endpoint to save products
     */
    private const MAGENTO_ENDPOINT = 'SaveProduct';

    /**
     * Magento Endpoint to save products
     */
    protected $applicationLogger;

    /**
     * Initialize the class objects
     * 
     * @param ContainerInterface $container
     * @param TranslatorInterface $translator
     */
    public function __construct(
        protected ContainerInterface $container,
        protected TranslatorInterface $translator
    ) {
        try {
            $bundleManager ??= $this->container->get(PimcoreBundleManager::class);
            $bundle = $bundleManager->getActiveBundle(\Pimcore\Bundle\ApplicationLoggerBundle\PimcoreApplicationLoggerBundle::class, false);
            if ($bundleManager->isInstalled($bundle)) {
                $this->applicationLogger = \Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger::getInstance();
            }
        } catch (BundleNotFoundException $bundleException) {
            $this->applicationLogger = null;
        }
    }

    /**
     * Event function that triggers on a product update
     *
     * @param ElementEventInterface $element
     *
     * @return void
     */
    public function onProductPostUpdate(ElementEventInterface $element): void
    {
        $magentoStoreUrl = ConfigurationHelper::getData(
            ConfigurationHelper::CONFIG_FIELD_MAGENTO_STORE_URL
        );
        $magentoAccessToken = ConfigurationHelper::getData(
            ConfigurationHelper::CONFIG_FIELD_MAGENTO_ACCESS_TOKEN
        );
        $pimcoreClass = ConfigurationHelper::getData(
            ConfigurationHelper::CONFIG_FIELD_PIMCORE_CLASS
        );
        if ($element instanceof DataObjectEvent 
            && !$element->hasArgument('saveVersionOnly')
        ) {
            if (!empty($magentoStoreUrl) 
                && !empty($magentoAccessToken) 
                && !empty($pimcoreClass)
            ) {
                $object = $element->getObject();
                if ($object->getType() != 'folder'
                    && strtolower($object->getClassName()) == strtolower($pimcoreClass)
                ) {
                    $this->callMagentoAPI(
                        $object, 
                        $magentoStoreUrl, 
                        $magentoAccessToken
                    );
                }
            } else {
                throw new ValidationException(
                    'Please fill in the fields in configuration to sync products to Magento'
                );
            }
        }
    }

    /**
     * Call the intended Magento API endpoint
     *
     * @param AbstractObject $object
     * @param string $magentoStoreUrl
     * @param string $magentoAccessToken
     *
     * @return void
     */
    protected function callMagentoAPI(
        AbstractObject $object, 
        $magentoStoreUrl, 
        $magentoAccessToken
    ) {
        $payload = '';
        try {
            $this->createNoteForProduct(
                $object,
                'started',
                'Sync with Magento started'
            );
            $payload = (new MagentoProduct($this->translator))->createPayload($object);
            if (!empty($payload)) {
                $functionName = self::MAGENTO_ENDPOINT;
                $httpClient = new Client();
                $response = $httpClient->request(
                    Request::METHOD_POST,
                    rtrim($magentoStoreUrl, '/') . '/graphql',
                    [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Bearer '.$magentoAccessToken
                        ],
                        'body' => '{'.
                            '"query": "mutation '.$functionName.
                            ' {'.
                                lcfirst($functionName).'('.
                                    'input: { '.$payload.' }'.
                                ')'.
                                ' {status_code message}'.
                            '}"'.
                        '}'
                    ]
                );
                $response = json_decode($response->getBody()->getContents());
                if (isset($response->errors)) {
                    $apiError = '';
                    $messages = array_unique(array_column($response->errors, 'message'));
                    $debugMessages = array_unique(array_column($response->errors, 'debugMessage'));
                    foreach ($messages as $key => $message) {
                        $apiError .= $message;
                        if (!empty($debugMessages[$key])) {
                            $apiError .= '->'.$debugMessages[$key];
                        }
                        $apiError .= ',';
                    }
                    throw new Exception(rtrim($apiError, ','));
                }
                $response = $response->data->{lcfirst($functionName)};
                if (! empty($response)) {
                    if ($response->status_code == '500') {
                        $this->logStatus('error', $response->message, $payload, $object);
                        $this->createNoteForProduct($object, 'failed', $response->message);
                        throw new Exception($response->message);
                    } elseif ($response->status_code != '200') {
                        $this->logStatus('error', $response->message, $payload, $object);
                        $this->createNoteForProduct($object, 'failed', $response->message);
                        throw new Exception($response->message);
                    }
                    $this->logStatus('info', $response->message, $payload, $object);
                    $this->createNoteForProduct($object, 'success', $response->message);
                }
            }
        } catch (ValidationException $validation) {
            $errorMessage =  'A validation error occured while saving the object id:'.$object->getId().', details:'.$validation->getMessage();
            $this->logStatus('warning', $errorMessage, $payload, $object);
            $this->createNoteForProduct($object, 'warning', $errorMessage);
            throw new ValidationException($validation->getMessage());
        } catch (Exception $exception) {
            $errorMessage =  'An error occured while processing Magento API request, details:'.$exception->getMessage();
            $this->logStatus('critical', $errorMessage, $payload, $object);
            $this->createNoteForProduct($object, 'failed', 'Sync failed. Check Application Logger for more info.');
            throw new ValidationException($errorMessage);
        }
    }

    /**
     * Log the workflow status using Application Logger tool
     *
     * @param string $type
     * @param string $message
     * @param array $fileObject
     * @param object $relatedObject
     *
     * @return void
     */
    private function logStatus($type, $message, $fileObject, $relatedObject)
    {
        if (is_null($this->applicationLogger)) {
            return;
        }
        $logMessage = 'Product id:'.$relatedObject->getId().' was processed and Magento sync ';
        if ($type == 'info') {
            $logMessage .= 'completed with the message: ';
        } elseif ($type == 'critical') {
            $logMessage = '';
        } else {
            $logMessage .= 'failed with the error: ';
        }
        $this->applicationLogger->{$type}(
            $logMessage.$message,
            [
                'fileObject' => new FileObject(print_r($fileObject, true)),
                'relatedObject' => $relatedObject,
                'component' => self::LOGGER_NAME,
            ]
        );
    }

    /**
     * Log the workflow status in Object notes
     *
     * @param AbstractObject $object
     * @param string $syncStatus
     * @param string $description
     *
     * @return void
     */
    private function createNoteForProduct(AbstractObject $object, $syncStatus, $description)
    {
        $note = new Note();
        $note->setElement($object);
        $note->setDate(time());
        $note->setDescription($description);
        $note->setType(self::LOGGER_NAME);
        $note->setTitle('Magento Product Sync');
        $note->setUser(Admin::getCurrentUser()->getId());

        // you can add as much additional data to notes & events as you want
        $note->addData('sync status', 'text', $syncStatus);

        $note->save();
    }
}
