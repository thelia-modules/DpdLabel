<?php

namespace DpdLabel\Controller;

use DpdLabel\DpdLabel;
use DpdLabel\Form\ApiConfigurationForm;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Exception\FormValidationException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/module/DpdLabel/api-config", name="dpdlabel_api")
 * Class ConfigurationController
 * @package DpdLabel\Controller
 * @author Etienne Perriere <eperriere@openstudio.fr>
 */
class ConfigurationController extends BaseAdminController
{
    /**
     * @Route("", name="_config", methods="POST")
     */
    public function configureApiAction()
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], ['DpdLabel'], [AccessManager::CREATE, AccessManager::UPDATE])) {
            return $response;
        }

        $baseForm = $this->createForm(ApiConfigurationForm::getName());

        $errorMessage = null;

        try {
            $form = $this->validateForm($baseForm);
            $data = $form->getData();

            foreach (DpdLabel::API_DPD_ACCOUNT_CONFIGS as $code => $configs) {
                DpdLabel::setConfigValue(DpdLabel::API_DPD_ACCOUNT_CONFIGS[$code]['user_id'], $data["user_id_$code"]);
                DpdLabel::setConfigValue(DpdLabel::API_DPD_ACCOUNT_CONFIGS[$code]['password'], $data["password_$code"]);
                DpdLabel::setConfigValue(DpdLabel::API_DPD_ACCOUNT_CONFIGS[$code]['center_number'], $data["center_number_$code"]);
                DpdLabel::setConfigValue(DpdLabel::API_DPD_ACCOUNT_CONFIGS[$code]['customer_number'], $data["customer_number_$code"]);
            }
            DpdLabel::setConfigValue(DpdLabel::API_LABEL_TYPE, $data["label_type"]);
            DpdLabel::setConfigValue(DpdLabel::API_IS_TEST, $data["isTest"]);
            DpdLabel::setConfigValue(DpdLabel::API_SHIPPER_NAME, $data["shipper_name"]);
            DpdLabel::setConfigValue(DpdLabel::API_SHIPPER_ADDRESS1, $data["shipper_address1"]);
            DpdLabel::setConfigValue(DpdLabel::API_SHIPPER_COUNTRY, $data["shipper_country"]);
            DpdLabel::setConfigValue(DpdLabel::API_SHIPPER_CITY, $data["shipper_city"]);
            DpdLabel::setConfigValue(DpdLabel::API_SHIPPER_ZIP, $data["shipper_zip_code"]);
            DpdLabel::setConfigValue(DpdLabel::API_SHIPPER_PHONE, $data["shipper_phone"]);
            DpdLabel::setConfigValue(DpdLabel::API_SHIPPER_FAX, $data["shipper_fax"]);

        } catch (FormValidationException $ex) {
            $errorMessage = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            $errorMessage = $this->getTranslator()->trans('Sorry, an error occurred: %err', ['%err' => $ex->getMessage()], DpdLabel::DOMAIN_NAME);
        }


        if ($errorMessage !== null) {

            $this->setupFormErrorContext(
                Translator::getInstance()->trans(
                    "Error while updating api configurations",
                    [],
                    DpdLabel::DOMAIN_NAME
                ),
                $errorMessage,
                $baseForm
            );
        }

        return $this->generateRedirectFromRoute(
            "admin.module.configure",
            [],
            [
                'module_code' => "DpdLabel",
                'current_tab' => "api_config",
                '_controller' => 'Thelia\\Controller\\Admin\\ModuleController::configureApiAction'
            ]
        );
    }
}
