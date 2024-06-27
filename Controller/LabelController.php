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

namespace DpdLabel\Controller;

use DpdLabel\DpdLabel;
use DpdLabel\Form\LabelGenerationForm;
use DpdLabel\Model\DpdlabelLabelsQuery;
use DpdLabel\Service\LabelService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\OrderQuery;
use Thelia\Tools\URL;

/**
 * @Route("/admin/module/DpdLabel", name="dpdlabel")
 */
class LabelController extends BaseAdminController
{
    /**
     * @Route("/labels", name="_labels", methods="GET")
     */
    public function showAction(Request $request): Response
    {
        return $this->render('dpdlabel-labels', [
            'err' => $request->get('err'),
        ]);
    }

    /**
     * @Route("/saveLabel", name="_save_label", methods="GET")
     */
    public function saveAction(Request $request, LabelService $labelService, Translator $translator): Response
    {
        $orderId = $request->get('orderId');

        try {
            if (null === $order = OrderQuery::create()->filterById($orderId)->findOne()) {
                return new Response(
                    $translator->trans(
                        'Order ID %id not found',
                        ['%id' => $orderId],
                        DpdLabel::DOMAIN_NAME
                    ),
                    Response::HTTP_NOT_FOUND
                );
            }

            $labelPath = $labelService->getLabelPath($order);

            if (null !== DpdlabelLabelsQuery::create()->filterByOrderId($order->getId())->findOne()) {
                return $this->downloadAction(base64_encode($labelPath), $translator);
            }

            $data = $this->validateForm($this->createForm(LabelGenerationForm::getName()))->getData();

            $labelService->createLabel($order, $labelPath, (float) $data['weight']);

            $params = ['file' => base64_encode($labelPath)];

            return $this->generateRedirect(URL::getInstance()?->absoluteUrl('admin/module/DpdLabel/labels', $params));
        } catch (\Exception $ex) {
            return $this->generateRedirect(URL::getInstance()?->absoluteUrl('admin/module/DpdLabel/labels', [
                'err' => $ex->getMessage(),
            ]));
        }
    }

    /**
     * @Route("/generateLabel", name="_generate_label", methods="POST")
     */
    public function generateLabelAction(Request $request, LabelService $labelService): Response
    {
        $orderId = $request->get('orderId');
        $retour = (bool) $request->get('retour');
        $returnUrl = $request->get('return_url');
        $error = null;

        try {
            if (null === $order = OrderQuery::create()->filterById($orderId)->findOne()) {
                throw new TheliaProcessException("Cannot find order ID $orderId");
            }

            $labelPath = $labelService->getLabelPath($order);

            $data = $this->validateForm($this->createForm(LabelGenerationForm::getName()))->getData();

            DpdLabel::setConfigValue('new_status', $data['new_status']);

            $labelService->createLabel($order, $labelPath, (float) $data['weight'], $retour, null, $data['new_status']);
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        if ($returnUrl) {
            return new RedirectResponse($returnUrl);
        }

        return $this->generateRedirect(URL::getInstance()?->absoluteUrl('/admin/order/update/'.$orderId, [
            'err' => $error,
            'tab' => 'bill',
        ]));
    }

    /**
     * @Route("/labels-file/{base64EncodedFilename}", name="_labels-file_download", methods="GET")
     */
    public function downloadAction(string $base64EncodedFilename, Translator $translator): Response
    {
        $fileName = base64_decode($base64EncodedFilename);
        [ 'filename' => $fileNameWithoutExt, 'dirname' => $dirname ] = pathinfo($fileName);
        $fileNameWithoutExt = $dirname.'/'.$fileNameWithoutExt;
        $files = glob($fileNameWithoutExt.'.*');

        if (!empty($files) && file_exists($files[0])) {
            $fileName = $files[0];

            return new Response(
                file_get_contents($fileName),
                200,
                [
                    'Content-Description' => 'File Transfer',
                    'Content-Type' => 'application/octet-stream',
                    'Content-Disposition' => 'attachment; filename="'.basename($fileName).'"',
                    'Expires: 0',
                    'Cache-Control' => 'must-revalidate',
                    'Pragma' => 'public',
                    'Content-Length' => filesize($fileName),
            ]);
        }

        return new Response(
            $translator->trans(
                'Label file %fileName was not found',
                ['%fileName' => $fileName],
                DpdLabel::DOMAIN_NAME
            ),
            Response::HTTP_NOT_FOUND
        );
    }

    /**
     * @Route("/getLabel/{orderRef}", name="_get_label", methods="GET")
     */
    public function getLabelAction($orderRef, Request $request, Translator $translator, LabelService $labelService): Response
    {
        if (null !== $labelFile = $labelService->getLabelFilePathForOrder($orderRef)) {
            $response = new BinaryFileResponse($labelFile);

            if ($request->get('download')) {
                $response->setContentDisposition(
                    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    basename($labelFile)
                );
            }

            return $response;
        }

        return new Response(
            $translator->trans(
                'Label not found for order ref. %ref',
                ['%ref' => $orderRef],
                DpdLabel::DOMAIN_NAME
            ),
            Response::HTTP_NOT_FOUND
        );
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @Route("/deleteLabel", name="_delete_label", methods="GET")
     */
    public function deleteLabelAction(Request $request, LabelService $labelService): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::ORDER, [], AccessManager::UPDATE)) {
            return $response;
        }

        $orderId = $request->get('orderId');
        $returnUrl = $request->get('return_url');

        $labelService->deleteLabel($orderId);

        if ($returnUrl) {
            return new RedirectResponse($returnUrl);
        }

        return $this->generateRedirect(URL::getInstance()?->absoluteUrl($request->get('redirect_url')));
    }
}
