<?php

namespace DpdLabel\Service;

use DpdLabel\DpdLabel;
use DpdLabel\Form\ApiConfigurationForm;
use DpdLabel\Model\DpdlabelLabels;
use DpdLabel\Model\DpdlabelLabelsQuery;
use DpdPickup\Model\OrderAddressIcirelaisQuery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderQuery;
use Thelia\Tools\URL;

class LabelService
{
    protected $dispatcher;

    /**
     * UpdateDeliveryAddressListener constructor.
     * @param EventDispatcherInterface|null $dispatcher
     */
    public function __construct(EventDispatcherInterface $dispatcher = null)
    {
        $this->dispatcher = $dispatcher;
    }

    public function generateLabel($data)
    {
        $orderId = $data['order_id'];
        $weight = $data['weight'];

        if (!$orderId) {
            return new JsonResponse([
                'error' => "order_id argument not found"
            ]);
        }

        if (!$weight) {
            return new JsonResponse([
                'error' => "weight argument not found"
            ]);
        }

        $order = OrderQuery::create()->filterById($orderId)->findOne();
        $labelName = DpdLabel::DPD_LABEL_DIR . DS . $order->getRef();
        $labelName = $this->setLabelNameExtension($labelName);

        $label = $this->createLabel($order, $labelName, $weight);

        if (is_string($label)) {
            return new JsonResponse([
                'error' => $label
            ]);
        }

        return new JsonResponse([
            'id' => $label->getId(),
            'url' => URL::getInstance()->absoluteUrl('/admin/module/DpdLabel/getLabel/' . $order->getRef()),
            'number' => $order->getRef(),
            'order' => [
                'id' => $order->getId(),
                'status' => [
                    'id' => $order->getOrderStatus()->getId()
                ]
            ]
        ]);
    }

    /**
     * @param Order $order
     * @param $labelName
     * @param $weight
     * @param int $retour
     * @return DpdlabelLabels|string
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function createLabel(Order $order, $labelName, $weight, $retour = null)
    {
        $data = $this->writeData($order, $weight, $retour);

        $DpdWSD = DpdLabel::DPD_WSDL;

        if (1 === (int)DpdLabel::getConfigValue(DpdLabel::API_IS_TEST)) {
            $DpdWSD = DpdLabel::DPD_WSDL_TEST;
        }

        $client = new \SoapClient($DpdWSD, array("trace" => 1, "exception" => 1, 'encoding' => 'ISO-8859-1'));

        try {
            $header = new \SoapHeader('http://www.cargonet.software', 'UserCredentials', $data["Header"]);
            $client->__setSoapHeaders($header);
            if ($retour) {
                $response = $client->CreateReverseInverseShipmentWithLabels(["request" => $data["Body"]]);
            } else {
                $response = $client->CreateShipmentWithLabels(["request" => $data["Body"]]);
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        if ($retour) {
            $shipments = $response->CreateReverseInverseShipmentWithLabelsResult->shipment;
            $labels = $response->CreateReverseInverseShipmentWithLabelsResult->labels->Label;
        } else {
            $shipments = $response->CreateShipmentWithLabelsResult->shipments->Shipment;
            $labels = $response->CreateShipmentWithLabelsResult->labels->Label;
        }

        $labelDir = DpdLabel::DPD_LABEL_DIR;
        $allOrderLabels = $labelDir . DS . $order->getRef();
        $fs = new Filesystem();

        foreach (glob($allOrderLabels.'.*') as $filename) {
            $fs->remove($filename);
        }
        if (false === @file_put_contents($labelName, $labels[0]->label)) {
            return Translator::getInstance()->trans("L'étiquette n'a pas pu être sauvegardée dans $labelName",[], DpdLabel::DOMAIN_NAME);
        }


        $label = DpdlabelLabelsQuery::create()
            ->filterByOrderId($order->getId())
            ->findOneOrCreate();

        $label->setOrderId($order->getId())
            ->setLabelNumber($shipments->barcode)
            ->save();

        $order->setDeliveryRef($shipments->barcode)
            ->save();

        return $label;
    }

    /**
     * @param Order $order
     * @param $weight
     * @param null $retour
     * @return mixed
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected function writeData(Order $order, $weight, $retour = null)
    {

        $data = DpdLabel::getApiConfig();

        $storeCountry = ConfigQuery::create()->filterByName("store_country")->findOne();
        $countryId = ($storeCountry) ? $storeCountry->getValue() : 64;
        $shopCountry = CountryQuery::create()->filterById($countryId)->findOne();

        $deliveryAddress = $order->getOrderAddressRelatedByDeliveryOrderAddressId();

        $ApiData["Header"] = [
            "userid" => $data['userId'],
            "password" => $data['password']
        ];

        $receiveraddress = [
            'name' => utf8_decode($deliveryAddress->getFirstname() . ' ' . $deliveryAddress->getLastname()),
            'countryPrefix' => $deliveryAddress->getCountry()->getIsoalpha2(),
            'city' => utf8_decode($deliveryAddress->getCity()),
            'zipCode' => $deliveryAddress->getZipcode(),
            'street' => utf8_decode($deliveryAddress->getAddress1()),
            'phoneNumber' => $deliveryAddress->getCellphone() ?: "x",
            'faxNumber' => '',
            'geoX' => '',
            'geoY' => ''
        ];

        $services = [];
        if (ModuleQuery::create()->filterById($order->getDeliveryModuleId())->findOne()->getCode() === "DpdPickup"){
            $orderAddressIciRelais = OrderAddressIcirelaisQuery::create()->filterById($deliveryAddress->getId())->findOne();
            $services = [
                "contact" => [
                    "sms" => $deliveryAddress->getCellphone() ?: "x",
                    "email" => $order->getCustomer()->getEmail(),
                    "autotext" => "",
                    "type" => "No"
                ],
                "parcelshop" => [
                    "shopaddress" => [
                        "shopid" => $orderAddressIciRelais->getCode(),
                    ]
                ]
            ];
        }

        $shipperaddress = [
            'name' => utf8_decode($data['shipperName']),
            'countryPrefix' => $data['shipperCountry'],
            'city' => utf8_decode($data['shipperCity']),
            'zipCode' => $data['shipperZipCode'],
            'street' => utf8_decode($data['shipperAddress1']),
            'phoneNumber' => $data['shipperPhone'],
            'faxNumber' => $data['shipperFax'],
            'geoX' => '',
            'geoY' => ''
        ];

        $ApiData["Body"] = [
            "customer_countrycode" => (int)$shopCountry->getIsocode(),
            "customer_centernumber" => (int)$data['center_number'],
            "customer_number" => (int)$data['customer_number'],
            "receiveraddress" => $receiveraddress,
            "shipperaddress" => $shipperaddress,
            "weight" => $weight,
            "services" => $services,
            "referencenumber" => $order->getRef(),
            "labelType" => ["type" => ApiConfigurationForm::LABEL_TYPE_CHOICES[$data['label_type']]]
        ];

        if ($retour) {
            $ApiData["Body"]["expire_offset"] = 30;
            $ApiData["Body"]["refasbarcode"] = false;
        }

        return $ApiData;
    }

    public function setLabelNameExtension($labelName)
    {
        switch (ApiConfigurationForm::LABEL_TYPE_CHOICES[DpdLabel::getConfigValue(DpdLabel::API_LABEL_TYPE)]) {
            case 'PDF':
            case 'PDF_A6':
                $labelName .= '.pdf';
                break;
            case 'Default':
                $labelName .= '.png';
                break;
            case 'EPL':
                $labelName .= '.epl';
                break;
            case 'ZPL':
            case 'ZPL300':
                $labelName .= '.zpl';
                break;
            default:
                break;
        }
        return $labelName;
    }
}
