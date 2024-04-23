<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace DpdLabel;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Thelia\Install\Database;
use Thelia\Module\BaseModule;

class DpdLabel extends BaseModule
{
    /** @var string */
    const DOMAIN_NAME = 'dpdlabel';

    const API_USER_ID_DPD_PICKUP = "dpdlabel_userid_dpd_pickup";
    const API_PASSWORD_DPD_PICKUP = "dpdlabel_password_dpd_pickup";
    const API_CENTER_NUMBER_DPD_PICKUP = "dpdlabel_center_number_dpd_pickup";
    const API_CUSTOMER_NUMBER_DPD_PICKUP = "dpdlabel_customer_number_dpd_pickup";
    const API_USER_ID_DPD_CLASSIC = "dpdlabel_userid_dpd_classic";
    const API_PASSWORD_DPD_CLASSIC = "dpdlabel_password_dpd_classic";
    const API_CENTER_NUMBER_DPD_CLASSIC = "dpdlabel_center_number_dpd_classic";
    const API_CUSTOMER_NUMBER_DPD_CLASSIC = "dpdlabel_customer_number_dpd_classic";
    const API_USER_ID_DPD_PREDICT = "dpdlabel_userid_dpd_predict";
    const API_PASSWORD_DPD_PREDICT = "dpdlabel_password_dpd_predict";
    const API_CENTER_NUMBER_DPD_PREDICT = "dpdlabel_center_number_dpd_predict";
    const API_CUSTOMER_NUMBER_DPD_PREDICT = "dpdlabel_customer_number_dpd_predict";
    const API_DPD_ACCOUNT_CONFIGS = [
        'DpdPickup' => [
            'user_id' => self::API_USER_ID_DPD_PICKUP,
            'password' => self::API_PASSWORD_DPD_PICKUP,
            'center_number' => self::API_CENTER_NUMBER_DPD_PICKUP,
            'customer_number' => self::API_CUSTOMER_NUMBER_DPD_PICKUP
        ],
        'DpdClassic' => [
            'user_id' => self::API_USER_ID_DPD_CLASSIC,
            'password' => self::API_PASSWORD_DPD_CLASSIC,
            'center_number' => self::API_CENTER_NUMBER_DPD_CLASSIC,
            'customer_number' => self::API_CUSTOMER_NUMBER_DPD_CLASSIC
        ],
        'Predict' => [
            'user_id' => self::API_USER_ID_DPD_PREDICT,
            'password' => self::API_PASSWORD_DPD_PREDICT,
            'center_number' => self::API_CENTER_NUMBER_DPD_PREDICT,
            'customer_number' => self::API_CUSTOMER_NUMBER_DPD_PREDICT
        ]
    ];
    const API_LABEL_TYPE = "dpdlabel_label_type";
    const API_IS_TEST = "dpdlabel_is_test";

    const API_SHIPPER_NAME = "dpdlabel_shipper_name";
    const API_SHIPPER_ADDRESS1 = "dpdlabel_shipper_address1";
    const API_SHIPPER_ADDRESS2 = "dpdlabel_shipper_address2";
    const API_SHIPPER_COUNTRY = "dpdlabel_shipper_country";
    const API_SHIPPER_CITY = "dpdlabel_shipper_city";
    const API_SHIPPER_ZIP = "dpdlabel_shipper_zip_code";
    const API_SHIPPER_CIV = "dpdlabel_shipper_civ";
    const API_SHIPPER_CONTACT = "dpdlabel_shipper_contact";
    const API_SHIPPER_PHONE = "dpdlabel_shipper_phone";
    const API_SHIPPER_FAX = "dpdlabel_shipper_fax";
    const API_SHIPPER_MAIL = "dpdlabel_shipper_mail";
    const DPD_WSDL_TEST = "https://e-station-testenv.cargonet.software/exa-eprintwebservice/eprintwebservice.asmx?WSDL";
    const DPD_WSDL = "https://e-station.cargonet.software/dpd-eprintwebservice/eprintwebservice.asmx?WSDL";

    const STATUS_PAID = 2;
    const STATUS_PROCESSING = 3;

    const DPD_LABEL_DIR = THELIA_LOCAL_DIR . "DpdLabel";

    const DPD_MODULES = ['DpdPickup', 'DpdClassic', 'Predict'];

    public function postActivation(ConnectionInterface $con = null): void
    {
        $database = new Database($con->getWrappedConnection());

        if ("1" !== self::getConfigValue("is_initialized")){
            $database->insertSql(null, array(__DIR__ . '/Config/thelia.sql'));

            self::setConfigValue("is_initialized", 1);

            // Official DPD data test parameters
            foreach (self::API_DPD_ACCOUNT_CONFIGS as $code => $configs) {
                self::setConfigValue(self::API_DPD_ACCOUNT_CONFIGS[$code]['userId'], 'GeoLabelTestEnv');
                self::setConfigValue(self::API_DPD_ACCOUNT_CONFIGS[$code]['password'], 'Geo-67!L@belT%est');
                self::setConfigValue(self::API_DPD_ACCOUNT_CONFIGS[$code]['center_number'], '77');
                self::setConfigValue(self::API_DPD_ACCOUNT_CONFIGS[$code]['customer_number'], '18028');
            }
            self::setConfigValue(self::API_LABEL_TYPE, 0);
            self::setConfigValue(self::API_IS_TEST, true);
        }
    }

    public static function getApiConfig()
    {
        $data = [];
        foreach (self::API_DPD_ACCOUNT_CONFIGS as $code => $configs) {
            $data['user_id_' . $code] = self::getConfigValue(self::API_DPD_ACCOUNT_CONFIGS[$code]['user_id']);
            $data['password_' . $code] = self::getConfigValue(self::API_DPD_ACCOUNT_CONFIGS[$code]['password']);
            $data['center_number_' . $code] = self::getConfigValue(self::API_DPD_ACCOUNT_CONFIGS[$code]['center_number']);
            $data['customer_number_' . $code] = self::getConfigValue(self::API_DPD_ACCOUNT_CONFIGS[$code]['customer_number']);
        }
        $data['label_type'] = self::getConfigValue(self::API_LABEL_TYPE);
        $data['isTest'] = (int)self::getConfigValue(self::API_IS_TEST);
        $data['shipperName'] = self::getConfigValue(self::API_SHIPPER_NAME);
        $data['shipperAddress1'] = self::getConfigValue(self::API_SHIPPER_ADDRESS1);
        $data['shipperCountry'] = self::getConfigValue(self::API_SHIPPER_COUNTRY);
        $data['shipperCity'] = self::getConfigValue(self::API_SHIPPER_CITY);
        $data['shipperZipCode'] = self::getConfigValue(self::API_SHIPPER_ZIP);
        $data['shipperPhone'] = self::getConfigValue(self::API_SHIPPER_PHONE);
        $data['shipperFax'] = self::getConfigValue(self::API_SHIPPER_FAX);

        return $data;
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', THELIA_MODULE_DIR . ucfirst(self::getModuleCode()))
            ->exclude([THELIA_MODULE_DIR . ucfirst(self::getModuleCode()). "/I18n/*"])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
