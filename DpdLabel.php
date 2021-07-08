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

    const API_USER_ID = "dpdlabel_userid";
    const API_PASSWORD = "dpdlabel_password";
    const API_CENTER_NUMBER = "dpdlabel_center_number";
    const API_CUSTOMER_NUMBER = "dpdlabel_customer_number";
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

    const DPD_WSDL_TEST = "http://92.103.148.116/exa-eprintwebservice/eprintwebservice.asmx?WSDL";
    const DPD_WSDL = "https://e-station.cargonet.software/dpd-eprintwebservice/eprintwebservice.asmx?WSDL";

    const STATUS_PAID = 2;
    const STATUS_PROCESSING = 3;

    const DPD_LABEL_DIR = THELIA_LOCAL_DIR . "DpdLabel";

    public function postActivation(ConnectionInterface $con = null): void
    {
        $database = new Database($con->getWrappedConnection());

        if ("1" !== self::getConfigValue("is_initialized")){
            $database->insertSql(null, array(__DIR__ . '/Config/thelia.sql'));

            self::setConfigValue("is_initialized", 1);
        }
    }

    public static function getApiConfig()
    {
        $data = [];
        $data['userId'] = self::getConfigValue(self::API_USER_ID);
        $data['password'] = self::getConfigValue(self::API_PASSWORD);
        $data['center_number'] = self::getConfigValue(self::API_CENTER_NUMBER);
        $data['customer_number'] = self::getConfigValue(self::API_CUSTOMER_NUMBER);
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
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([THELIA_MODULE_DIR . ucfirst(self::getModuleCode()). "/I18n/*"])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
