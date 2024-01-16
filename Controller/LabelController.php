<?php

namespace DpdLabel\Controller;


use DpdLabel\DpdLabel;
use DpdLabel\Form\ApiConfigurationForm;
use DpdLabel\Form\LabelGenerationForm;
use DpdLabel\Model\DpdlabelLabelsQuery;
use DpdLabel\Service\LabelService;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\OrderQuery;
use Thelia\Tools\URL;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/module/DpdLabel", name="dpdlabel")
 */
class LabelController extends BaseAdminController
{
    /**
     * @Route("/labels", name="_labels", methods="GET")
     */
    public function showAction(RequestStack $requestStack)
    {
        $err =  $requestStack->getCurrentRequest()->get("err");
        return $this->render('dpdlabel-labels', [
            "err" => $err
        ]);
    }

    /**
     * @Route("/saveLabel", name="_save_label", methods="GET")
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function saveAction(RequestStack $requestStack, LabelService $labelService)
    {
        $request = $requestStack->getCurrentRequest();
        $orderId = $request->get("orderId");

        $labelDir = DpdLabel::DPD_LABEL_DIR;

        $fileSystem = new Filesystem();

        if (!$fileSystem->exists($labelDir)) {
            $fileSystem->mkdir($labelDir, 0777);
        }
        $order = OrderQuery::create()->filterById($orderId)->findOne();

        $labelName = $labelDir . DS . $order->getRef();
        $labelName = $labelService->setLabelNameExtension($labelName);

        if (!$label = dpdlabelLabelsQuery::create()->filterByOrderId($order->getId())->findOne()) {
            $baseForm = $this->createForm(LabelGenerationForm::getName());
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
     * @Route("/generateLabel", name="_generate_label", methods="POST")
     */
    public function generateLabelAction(RequestStack $requestStack, LabelService $labelService)
    {
        $request = $requestStack->getCurrentRequest();
        $orderId = $request->get("orderId");
        $retour = $request->get("retour");

        $order = OrderQuery::create()->filterById($orderId)->findOne();

        $labelDir = DpdLabel::DPD_LABEL_DIR;
        $labelName = $labelDir . DS . $order->getRef();
        $labelName = $labelService->setLabelNameExtension($labelName);

        $fileSystem = new Filesystem();

        if (!$fileSystem->exists($labelDir)) {
            $fileSystem->mkdir($labelDir, 0777);
        }

        $baseForm = $this->createForm(LabelGenerationForm::getName());
        try {
            $form = $this->validateForm($baseForm);
            $data = $form->getData();
        } catch (\Exception $e) {
            return $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/order/update/' . $orderId, [
                "err" => $e->getMessage(),
                "tab" => 'bill'
            ]));
        }

        $err = $labelService->createLabel($order, $labelName, $data['weight'], $retour);

        if (is_string($err)) {
            return $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/order/update/' . $orderId, [
                "err" => $err,
                "tab" => 'bill'
            ]));
        }

        return $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/order/update/' . $orderId, [ "tab" => 'bill' ]));
    }

    /**
     * @Route("/labels-file/{base64EncodedFilename}", name="_labels-file_download", methods="GET")
     */
    public function downloadAction($base64EncodedFilename)
    {
        $fileName = base64_decode($base64EncodedFilename);
        [ 'filename' => $fileNameWithoutExt, 'dirname' => $dirname ] = pathinfo($fileName);
        $fileNameWithoutExt = $dirname.'/'.$fileNameWithoutExt;
        $files = glob($fileNameWithoutExt.'.*');

        if (!empty($files) && file_exists($files[0])) {
            $fileName = $files[0];
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

    /**
     * @Route("/getLabel/{orderRef}", name="_get_label", methods="GET")
     */
    public function getLabelAction($orderRef)
    {
        if (null !== $response = $this->checkAuth(AdminResources::ORDER, [], AccessManager::UPDATE)) {
            return $response;
        }

        $labelDir = DpdLabel::DPD_LABEL_DIR;

        $file = $labelDir . DS . $orderRef;
        $files = glob($file.'.*');

        if (!empty($files) && file_exists($files[0])) {
            return new BinaryFileResponse($files[0]);
        }

        return '';
    }

    /**
     * @return mixed|\Symfony\Component\HttpFoundation\Response
     * @throws \Propel\Runtime\Exception\PropelException
     * @Route("/deleteLabel", name="_delete_label", methods="GET")
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

        $labelName = $labelDir . DS . $label->getOrder()->getRef();

        foreach (glob($labelName.'.*') as $filename) {
            $fs->remove($filename);
        }

        $label->delete();

        return $this->generateRedirect(URL::getInstance()->absoluteUrl($this->getRequest()->get("redirect_url")));
    }
}
