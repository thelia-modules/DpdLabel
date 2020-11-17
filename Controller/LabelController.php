<?php

namespace DpdLabel\Controller;


use DpdLabel\DpdLabel;
use DpdLabel\Model\DpdlabelLabels;
use DpdLabel\Model\DpdlabelLabelsQuery;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderQuery;
use Thelia\Tools\URL;

class LabelController extends BaseAdminController
{
    public function showAction()
    {
        $err = $this->getRequest()->get("err");
        return $this->render('dpdlabel-labels', [
            "err" => $err
        ]);
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function saveAction()
    {
        $orderId = $this->getRequest()->get("orderId");

        $labelDir = DpdLabel::DPD_LABEL_DIR;

        $fileSystem = new Filesystem();

        if (!$fileSystem->exists($labelDir)) {
            $fileSystem->mkdir($labelDir, 0777);
        }
        $order = OrderQuery::create()->filterById($orderId)->findOne();

        $labelName = $labelDir . DS . $order->getRef() . ".pdf";

        $err = null;

        if (!$label = dpdlabelLabelsQuery::create()->filterByOrderId($order->getId())->findOne()) {
            $baseForm = $this->createForm("dpdlabel.label.generation.form");
            try {
                $form = $this->validateForm($baseForm);
                $data = $form->getData();
            } catch (\Exception $e) {
                return $this->generateRedirect(URL::getInstance()->absoluteUrl("admin/module/DpdLabel/labels", [
                    "err" => $e->getMessage()
                ]));
            }

            $err = $this->createLabel($order, $labelName, $data['weight']);

            if ($err) {
                return $this->generateRedirect(URL::getInstance()->absoluteUrl("admin/module/DpdLabel/labels", [
                    "err" => $err
                ]));
            }

            $params = ['file' => base64_encode($labelName)];

            return $this->generateRedirect(URL::getInstance()->absoluteUrl('admin/module/DpdLabel/labels', $params));

        }

        return $this->downloadAction(base64_encode($labelName));
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function generateLabelAction()
    {
        $orderId = $this->getRequest()->get("orderId");
        $retour = $this->getRequest()->get("retour");

        $order = OrderQuery::create()->filterById($orderId)->findOne();

        $labelDir = DpdLabel::DPD_LABEL_DIR;
        $labelName = $labelDir . DS . $order->getRef() . ".pdf";

        $fileSystem = new Filesystem();

        if (!$fileSystem->exists($labelDir)) {
            $fileSystem->mkdir($labelDir, 0777);
        }

        $baseForm = $this->createForm("dpdlabel.label.generation.form");
        try {
            $form = $this->validateForm($baseForm);
            $data = $form->getData();
        } catch (\Exception $e) {
            return $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/order/update/' . $orderId, [
                "err" => $e->getMessage()
            ]));
        }

        $err = $this->createLabel($order, $labelName, $data['weight'], $retour);

        if ($err) {
            return $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/order/update/' . $orderId, [
                "err" => $err
            ]));
        }

        return $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/order/update/' . $orderId));
    }


    public function downloadAction($base64EncodedFilename)
    {
        $fileName = base64_decode($base64EncodedFilename);

        if (file_exists($fileName)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($fileName));
            readfile($fileName);
        } else {
            return $this->generateRedirect(URL::getInstance()->absoluteUrl("/admin/module/DpdLabel/labels"));
        }

        return $this->generateRedirect(URL::getInstance()->absoluteUrl("/admin/module/DpdLabel/labels"));
    }

    public function getLabelAction($orderRef)
    {
        if (null !== $response = $this->checkAuth(AdminResources::ORDER, [], AccessManager::UPDATE)) {
            return $response;
        }

        $labelDir = DpdLabel::DPD_LABEL_DIR;

        $file = $labelDir . DS . $orderRef . ".pdf";

        $response = new BinaryFileResponse($file);

        return $response;
    }

    /**
     * @return mixed|\Symfony\Component\HttpFoundation\Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function deleteLabelAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::ORDER, [], AccessManager::UPDATE)) {
            return $response;
        }

        $orderId = $this->getRequest()->get("orderId");

        $labelDir = DpdLabel::DPD_LABEL_DIR;

        $label = DpdlabelLabelsQuery::create()->filterByOrderId($orderId)->findOne();

        $fs = new Filesystem();

        $fs->remove($labelDir . DS . $label->getOrder() . ".pdf");

        $label->delete();

        return $this->generateRedirect(URL::getInstance()->absoluteUrl($this->getRequest()->get("redirect_url")));

    }

    /**
     * @return \Thelia\Core\HttpFoundation\Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function createLabelAction()
    {
        $orderId = $this->getRequest()->get("order_id");
        $weight = $this->getRequest()->get("weight");

        if (!$orderId) {
            return $this->jsonResponse(json_encode(["error" => "order_id argument not found"]), 400);
        }

        if (!$weight) {
            return $this->jsonResponse(json_encode(["error" => "weight argument not found"]), 400);
        }

        $order = OrderQuery::create()->filterById($orderId)->findOne();
        $labelName = DpdLabel::DPD_LABEL_DIR . DS . $order->getRef() . ".pdf";

        $err = $this->createLabel($order, $labelName, $weight);

        if ($err) {
            return $this->jsonResponse(json_encode(["error" => $err]));
        }

        return $this->jsonResponse(json_encode(["file_path" => $labelName]));
    }

    /**
     * @param Order $order
     * @param $labelName
     * @param $weight
     * @param int $retour
     * @return null|string
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected function createLabel(Order $order, $labelName, $weight, $retour = null)
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

        if (false === @file_put_contents($labelName, $labels[0]->label)) {
            return Translator::getInstance()->trans("L'étiquette n'a pas pu être sauvegardée dans $labelName", DpdLabel::DOMAIN_NAME);
        }


        $label = new DpdlabelLabels();
        $label
            ->setOrderId($order->getId())
            ->setLabelNumber($shipments->parcelnumber)
            ->save();


        return null;
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

        $shopCountry = CountryQuery::create()->filterById(ConfigQuery::create()->filterByName("store_country")->findOne()->getValue())->findOne();

        $ApiData["Header"] = [
            "userid" => $data['userId'],
            "password" => $data['password']
        ];

        $deliveryAddress = OrderAddressQuery::create()->filterById($order->getDeliveryOrderAddressId())->findOne();

        $receiveraddress = [
            'name' => $deliveryAddress->getFirstname() . ' ' . $deliveryAddress->getLastname(),
            'countryPrefix' => $deliveryAddress->getCountry()->getIsoalpha2(),
            'city' => $deliveryAddress->getCity(),
            'zipCode' => $deliveryAddress->getZipcode(),
            'street' => $deliveryAddress->getAddress1(),
            'phoneNumber' => $deliveryAddress->getPhone() ?: "x",
            'faxNumber' => '',
            'geoX' => '',
            'geoY' => ''
        ];

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

        $label = array(
            'type' => 'PDF',
        );

        $ApiData["Body"] = [
            "customer_countrycode" => (int)$shopCountry->getIsocode(),
            "customer_centernumber" => (int)$data['center_number'],
            "customer_number" => (int)$data['customer_number'],
            "receiveraddress" => $receiveraddress,
            "shipperaddress" => $shipperaddress,
            "weight" => $weight,
            "referencenumber" => $order->getRef(),
            "labelType" => $label
        ];

        if ($retour) {
            $ApiData["Body"]["expire_offset"] = 30;
            $ApiData["Body"]["refasbarcode"] = false;
        }

        return $ApiData;
    }
}