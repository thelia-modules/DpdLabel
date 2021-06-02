<?php

namespace DpdLabel\Controller;


use DpdLabel\DpdLabel;
use DpdLabel\Form\ApiConfigurationForm;
use DpdLabel\Model\DpdlabelLabelsQuery;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
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

        $labelService = $this->getContainer()->get('dpdlabel.generate.label.service');

        $labelName = $labelDir . DS . $order->getRef();
        $labelName = $labelService->setLabelNameExtension($labelName);

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

            $err = $labelService->createLabel($order, $labelName, $data['weight']);

            if (is_string($err)) {
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

        $labelService = $this->getContainer()->get('dpdlabel.generate.label.service');
        $labelDir = DpdLabel::DPD_LABEL_DIR;
        $labelName = $labelDir . DS . $order->getRef();
        $labelName = $labelService->setLabelNameExtension($labelName);

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

        $err = $labelService->createLabel($order, $labelName, $data['weight'], $retour);

        if (is_string($err)) {
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

        $file = $labelDir . DS . $orderRef;
        $labelService = $this->getContainer()->get('dpdlabel.generate.label.service');
        $file = $labelService->setLabelNameExtension($file);

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

        $labelName = $labelDir . DS . $label->getOrder();
        $labelService = $this->getContainer()->get('dpdlabel.generate.label.service');
        $labelName = $labelService->setLabelNameExtension($labelName);

        $fs->remove($labelName);

        $label->delete();

        return $this->generateRedirect(URL::getInstance()->absoluteUrl($this->getRequest()->get("redirect_url")));

    }
}