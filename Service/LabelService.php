<?php

namespace DpdLabel\Service;

use DpdLabel\DpdLabel;
use DpdLabel\enum\AuthorizedModuleEnum;
use DpdLabel\Form\ApiConfigurationForm;
use DpdLabel\Model\DpdlabelLabels;
use DpdPickup\Model\OrderAddressIcirelaisQuery;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
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
        $forceTypeLabel = $data['force_type_label'] ?? null;
        $newStatus = $data['new_status'] ?? null;

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
        $labelName = $this->setLabelNameExtension($labelName, $forceTypeLabel);

        $label = $this->createLabel(
            $order,
            $labelName,
            $weight,
            null,
            $forceTypeLabel,
            $newStatus
        );

        if (is_string($label)) {
            return new JsonResponse([
                'error' => Translator::getInstance()->trans("Sorry, an unexpected error occurred: %err", ['%err' => $label ], DpdLabel::DOMAIN_NAME)
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
     * @param null $retour
     * @param null $forceTypeLabel
     * @param null $newStatus
     * @return DpdlabelLabels|string
     * @throws PropelException
     * @throws \SoapFault
     */
    public function createLabel(Order $order, $labelName, $weight, $retour = null, $forceTypeLabel = null, $newStatus = null)
    {

        $data = $this->writeData($order, $weight, $retour, $forceTypeLabel);

        $DpdWSD = DpdLabel::DPD_WSDL;

        /** Check if status needs to be changed after processing */
        $newStatus = OrderStatusQuery::create()->findOneByCode($newStatus);

        if (1 === (int)DpdLabel::getConfigValue(DpdLabel::API_IS_TEST)) {
            $DpdWSD = DpdLabel::DPD_WSDL_TEST;
        }

        $client = new \SoapClient($DpdWSD, ["trace" => 1, "exception" => 1]);

        try {
            $header = new \SoapHeader('http://www.cargonet.software', 'UserCredentials', $data["Header"]);
            $client->__setSoapHeaders($header);
            if ($retour) {
                $response = $client->CreateReverseInverseShipmentWithLabelsBc(["request" => $data["Body"]]);
            } else {
                $response = $client->CreateShipmentWithLabelsBc(["request" => $data["Body"]]);
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        if ($retour) {
            $shipments = $response->CreateReverseInverseShipmentWithLabelsBcResult->shipment;
            $labels = $response->CreateReverseInverseShipmentWithLabelsBcResult->labels->Label;
        } else {
            $shipments = $response->CreateShipmentWithLabelsBcResult->shipments->ShipmentBc;
            $labels = $response->CreateShipmentWithLabelsBcResult->labels->Label;
        }

        // if no labelName we don't create the file
        if (false === @file_put_contents($labelName, $labels->label)) {
            return Translator::getInstance()->trans("The label data cannot be saved in file %file", ['%file' => $labelName], DpdLabel::DOMAIN_NAME);
        }

        /* Change the order status if it was requested by the user */
        if ($newStatus) {
            $newStatusId = $newStatus->getId();

            if ($order->getOrderStatus()->getId() !== $newStatusId) {
                $order->setOrderStatus($newStatus);
                $this->dispatcher->dispatch(
                    (new OrderEvent($order))->setStatus($newStatusId),
                    TheliaEvents::ORDER_UPDATE_STATUS
                );
            }
        }

        $label = new DpdlabelLabels();
        $label
            ->setOrderId($order->getId())
            ->setLabelNumber($shipments->Shipment->BarcodeId)
            ->save();

        $order->setDeliveryRef($shipments->Shipment->BarcodeId)
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
    protected function writeData(Order $order, $weight, $retour = null, $forceTypeLabel = null)
    {
        $data = DpdLabel::getApiConfig();
        $deliveryModuleCode = $order->getDeliveryModuleInstance()->getCode();

        $shopCountry = CountryQuery::create()->filterById(ConfigQuery::create()->filterByName("store_country")->findOne()->getValue())->findOne();

        $ApiData["Header"] = [
            "userid" => $data['user_id_' . $deliveryModuleCode],
            "password" => $data['password_' . $deliveryModuleCode]
        ];

        $deliveryAddress = OrderAddressQuery::create()->filterById($order->getDeliveryOrderAddressId())->findOne();
        $phone = $deliveryAddress->getCellphone() ?: $deliveryAddress->getPhone() ?: "x";

        $receiveraddress = [
            'name' => $deliveryAddress->getFirstname() . ' ' . $deliveryAddress->getLastname(),
            'countryPrefix' => $deliveryAddress->getCountry()->getIsoalpha2(),
            'city' => $deliveryAddress->getCity(),
            'zipCode' => $deliveryAddress->getZipcode(),
            'street' => $deliveryAddress->getAddress1(),
            'phoneNumber' => $phone,
            'faxNumber' => '',
            'geoX' => '',
            'geoY' => ''
        ];

        $services = [];
        if (ModuleQuery::create()->filterById($order->getDeliveryModuleId())->findOne()->getCode() === AuthorizedModuleEnum::DpdPickup->value){
            $orderAddressIciRelais = OrderAddressIcirelaisQuery::create()->filterById($deliveryAddress->getId())->findOne();
            $services = [
                "contact" => [
                    "sms" => $phone,
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
            'name' => $data['shipperName'],
            'countryPrefix' => $data['shipperCountry'],
            'city' => $data['shipperCity'],
            'zipCode' => $data['shipperZipCode'],
            'street' => $data['shipperAddress1'],
            'phoneNumber' => $data['shipperPhone'],
            'faxNumber' => $data['shipperFax'],
            'geoX' => '',
            'geoY' => ''
        ];

        $ApiData["Body"] = [
            "customer_countrycode" => (int)$shopCountry->getIsocode(),
            "customer_centernumber" => (int)$data['center_number_' . $deliveryModuleCode],
            "customer_number" => (int)$data['customer_number_' . $deliveryModuleCode],
            "receiveraddress" => $receiveraddress,
            "services" => $services,
            "shipperaddress" => $shipperaddress,
            "weight" => $weight,
            "referencenumber" => $order->getRef(),
            "labelType" => [
                "type" => $forceTypeLabel ?: ApiConfigurationForm::LABEL_TYPE_CHOICES[(int) $data['label_type']]
            ]
        ];

        if ($retour) {
            $ApiData["Body"]["expire_offset"] = 30;
            $ApiData["Body"]["refasbarcode"] = false;
        }

        return $ApiData;
    }

    public function setLabelNameExtension($labelName, $forceTypeLabel = null)
    {
        $label = strtoupper($forceTypeLabel) ?: ApiConfigurationForm::LABEL_TYPE_CHOICES[DpdLabel::getConfigValue(DpdLabel::API_LABEL_TYPE)];

        switch ($label) {
            case 'PDF':
            case 'PDF_A6':
                $labelName .= '.pdf';
                break;
            case 'PNG':
            case 'BIC3':
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
