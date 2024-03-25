<?php

namespace QBurst\ProductSyncBundle\Controller;

use Pimcore\Controller\FrontendController;
use Pimcore\Tool\Authentication;
use Pimcore\Translation\Translator;
use QBurst\ProductSyncBundle\Helper\ConfigurationHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

class AdminConfigurationController extends FrontendController
{
    /**
     * Controller to save and fetch the connector configurations
     *
     * @Route("/productsync_connector_settings")
     *
     * @param Request $request
     * @param Translator $translator
     *
     * @return Response
     */
    public function indexAction(Request $request, Translator $translator): Response
    {
        $user = Authentication::authenticateSession($request);
        if (!$user || !$user->isAllowed('magento_product_connector')) {
            throw new AccessDeniedHttpException();
        }

        $postData = [];
        $configurationSaved = false;
        $responsemessage = '';
        if ($request->isMethod('post')) {
            if ($request->request) {
                $postData = $request->request->all();
                if (count($postData) != count(array_filter($postData, 'strlen'))) {
                    $responsemessage .= '<div class="error-msg">'.$translator
                    ->trans(
                        'qburstMagentoConnector_configuration_validation_fail',
                        [],
                        'admin'
                    ).'</div>';
                } elseif (is_iterable($postData)) {
                    // Split the input string into language-storeview pairs
                    $languageStoreviewPairs = explode(' ', $postData[ConfigurationHelper::CONFIG_FIELD_MAGENTO_STORE_VIEW_TRANSLATIONS]);
                    foreach ($languageStoreviewPairs as $pair) {
                        list($language, $storeview) = explode(':', $pair);
                        $pimcoreLanguages = \Pimcore\Tool\Admin::getLanguages();
                        if (!in_array($language, $pimcoreLanguages)) {
                            $responsemessage .= '<div class="error-msg">'.$language.$translator
                                ->trans(
                                    'qburstMagentoConnector_configuration_language_validation_fail',
                                    [],
                                    'admin'
                                ).'</div>';    
                        }
                    }
                    if (empty($responsemessage)) {
                        $configurationSaved = ConfigurationHelper::setData($postData);
                        if ($configurationSaved) {
                            $responsemessage .= '<div class="success-msg">'.$translator
                                    ->trans(
                                        'qburstMagentoConnector_configuration_save_success',
                                        [],
                                        'admin'
                                    ).'</div>';
                        } else {
                            $responsemessage .= '<div class="error-msg">'.$translator
                                    ->trans(
                                        'qburstMagentoConnector_configuration_save_fail',
                                        [],
                                        'admin'
                                    ).'</div>';
                        }
                    }
                }
            }
        }

        if (!empty($postData) && !$configurationSaved) {
            $configuration = $postData;
        } else {
            $defaultconfig = ConfigurationHelper::getDefaultConfig();
            $configuration = ConfigurationHelper::getData();
            if ($defaultconfig && is_iterable($defaultconfig)) {
                foreach ($defaultconfig as $defaultConfigKey => $defaultConfigEntry) {
                    if (!key_exists($defaultConfigKey, $configuration)) {
                        $configuration[$defaultConfigKey] = $defaultConfigEntry;
                    }
                }
            }
        }

        return $this->render(
            '@QBurstProductSync/admin/index.html.twig',
            [
                'message' => $responsemessage,
                'configuration' => $configuration,
                'currencies' => ConfigurationHelper::getCurrenciesConfig()
            ]
        );
    }
}
