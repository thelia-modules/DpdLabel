<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DpdLabel\Service;

use DpdLabel\DpdLabel;
use DpdLabel\enum\AuthorizedModuleEnum;
use DpdLabel\Form\ApiConfigurationForm;
use DpdLabel\Model\DpdlabelLabels;
use DpdLabel\Model\DpdlabelLabelsQuery;
use DpdPickup\Model\OrderAddressIcirelaisQuery;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Tools\URL;

class LabelService
{
    /**
     * UpdateDeliveryAddressListener constructor.
     */
    public function __construct(protected EventDispatcherInterface $dispatcher)
    {
    }

    public function getLabelPath(Order $order)
    {
        $fileSystem = new Filesystem();

        if (!$fileSystem->exists(DpdLabel::DPD_LABEL_DIR)) {
            $fileSystem->mkdir(DpdLabel::DPD_LABEL_DIR);
        }

        return $this->setLabelNameExtension(DpdLabel::DPD_LABEL_DIR.$order->getRef());
    }

    /**
     * @throws PropelException
     * @throws \SoapFault
     */
    public function createLabel(Order $order, string $labelName, float $weight, bool $retour = false, string $forceTypeLabel = null, string $newStatusCode = null): DpdlabelLabels
    {
        $data = $this->writeData($order, $weight, $retour, $forceTypeLabel);

        $DpdWSD = DpdLabel::DPD_WSDL;

        /** Check if status needs to be changed after processing */
        $newStatus = OrderStatusQuery::create()->findOneByCode($newStatusCode);

        if (1 === (int) DpdLabel::getConfigValue(DpdLabel::API_IS_TEST)) {
            $DpdWSD = DpdLabel::DPD_WSDL_TEST;
        }

        $client = new \SoapClient($DpdWSD, ['trace' => 1, 'exception' => 1]);

        $header = new \SoapHeader('http://www.cargonet.software', 'UserCredentials', $data['Header']);

        $client->__setSoapHeaders($header);

        $response = $retour ?
            $client->CreateReverseInverseShipmentWithLabelsBc(['request' => $data['Body']]) :
            $client->CreateShipmentWithLabelsBc(['request' => $data['Body']]);

        $shipments = $retour ?
            $response->CreateReverseInverseShipmentWithLabelsBcResult->Shipment :
            $response->CreateShipmentWithLabelsBcResult->shipments->ShipmentBc;

        $labelData = $retour ?
            $response->CreateReverseInverseShipmentWithLabelsBcResult->Labels->Label[0]?->label :
            $response->CreateShipmentWithLabelsBcResult->labels->Label->label;

        // if no labelName we don't create the file
        if (false === @file_put_contents($labelName, $labelData)) {
            throw new TheliaProcessException(
                Translator::getInstance()->trans(
                    'The label data cannot be saved in file %file',
                    ['%file' => $labelName],
                    DpdLabel::DOMAIN_NAME
                )
            );
        }

        /* Change the order status if it was requested by the user */
        if (null !== $newStatus) {
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

        $order->setDeliveryRef($shipments->Shipment->BarcodeId)->save();

        return $label;
    }

    /**
     * @throws PropelException
     *
     * @return array[]
     */
    protected function writeData(Order $order, float $weight, bool $retour, ?string $forceTypeLabel): array
    {
        $data = DpdLabel::getApiConfig();
        $deliveryModuleCode = $order->getDeliveryModuleInstance()->getCode();

        $shopCountry = CountryQuery::create()->findPk(ConfigQuery::getStoreCountry());

        $ApiData = [
            'Header' => [
                'userid' => $data['user_id_'.$deliveryModuleCode],
                'password' => $data['password_'.$deliveryModuleCode],
            ],
        ];

        $deliveryAddress = $order->getOrderAddressRelatedByDeliveryOrderAddressId();

        $phone = $deliveryAddress->getCellphone() ?: $deliveryAddress->getPhone() ?: 'x';
        $name = $deliveryAddress->getFirstname().' '.$deliveryAddress->getLastname();

        $receiveraddress = [
            'name' => $name,
            'countryPrefix' => $deliveryAddress->getCountry()->getIsoalpha2(),
            'city' => $deliveryAddress->getCity(),
            'zipCode' => $deliveryAddress->getZipcode(),
            'street' => $deliveryAddress->getAddress1(),
            'phoneNumber' => $phone,
            'faxNumber' => '',
            'geoX' => '',
            'geoY' => '',
        ];

        $receiverinfo =
            empty($deliveryAddress->getCompany()) ? [] : [
                'contact' => $name,
                'name2' => $deliveryAddress->getCompany(),
            ];

        $services = [];

        if ($order->getModuleRelatedByDeliveryModuleId()->getCode() === AuthorizedModuleEnum::DpdPickup->value) {
            $orderAddressIciRelais = OrderAddressIcirelaisQuery::create()->findPk($deliveryAddress->getId());

            $services = [
                'contact' => [
                    'sms' => $phone,
                    'email' => $order->getCustomer()->getEmail(),
                    'autotext' => '',
                    'type' => 'No',
                ],
                'parcelshop' => [
                    'shopaddress' => [
                        'shopid' => $orderAddressIciRelais->getCode(),
                    ],
                ],
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
            'geoY' => '',
        ];

        $ApiData['Body'] = [
            'customer_countrycode' => (int) $shopCountry?->getIsocode(),
            'customer_centernumber' => (int) $data['center_number_'.$deliveryModuleCode],
            'customer_number' => (int) $data['customer_number_'.$deliveryModuleCode],
            'receiveraddress' => $receiveraddress,
            'receiverinfo' => $receiverinfo,
            'services' => $services,
            'shipperaddress' => $shipperaddress,
            'weight' => $weight,
            'referencenumber' => $order->getRef(),
            'labelType' => [
                'type' => $forceTypeLabel ?: ApiConfigurationForm::LABEL_TYPE_CHOICES[(int) $data['label_type']],
            ],
        ];

        if ($retour) {
            $ApiData['Body']['expire_offset'] = 30;
            $ApiData['Body']['refasbarcode'] = false;
        }

        return $ApiData;
    }

    public function setLabelNameExtension($labelName, string $forceTypeLabel = null)
    {
        $label = strtoupper(
            $forceTypeLabel ?: ApiConfigurationForm::LABEL_TYPE_CHOICES[DpdLabel::getConfigValue(DpdLabel::API_LABEL_TYPE)]
        );

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

    /**
     * Get a label file path for an order.
     *
     * @return mixed|null
     */
    public function getLabelFilePathForOrder($orderRef)
    {
        $file = DpdLabel::DPD_LABEL_DIR.$orderRef;

        $files = glob($file.'.*');

        if (!empty($files) && file_exists($files[0])) {
            return $files[0];
        }

        return null;
    }

    /**
     * Delete a label.
     *
     * @throws PropelException
     *
     * @return void
     */
    public function deleteLabel(int $orderId): void
    {
        if (null !== $label = DpdlabelLabelsQuery::create()->filterByOrderId($orderId)->findOne()) {
            $fs = new Filesystem();

            $labelName = DpdLabel::DPD_LABEL_DIR.$label->getOrder()->getRef();

            foreach (glob($labelName.'.*') as $filename) {
                $fs->remove($filename);
            }

            $label->delete();
        }
    }
}
