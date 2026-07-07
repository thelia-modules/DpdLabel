<?php

declare(strict_types=1);

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
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Exception\TokenAuthenticationException;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderQuery;
use Thelia\Tools\URL;

#[Route('/admin/module/DpdLabel', name: 'dpdlabel')]
class LabelController extends BaseAdminController
{
    #[Route('/labels', name: '_labels', methods: ['GET'])]
    public function showAction(Request $request): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::ORDER, [], AccessManager::VIEW)) {
            return $response;
        }

        $form = $this->createForm(LabelGenerationForm::getName());

        return $this->render('DpdLabel/labels.html.twig', [
            'form' => $form->getForm()->createView(),
            'token' => $this->getTokenProvider()->assignToken(),
            'err' => $request->query->get('err'),
            'file' => $request->query->get('file'),
            'orders' => $this->buildOrderList(),
        ]);
    }

    #[Route('/saveLabel', name: '_save_label', methods: ['GET'])]
    public function saveAction(Request $request, LabelService $labelService, Translator $translator): Response
    {
        $orderId = $request->query->get('orderId');

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

            return $this->generateRedirect(URL::getInstance()->absoluteUrl('admin/module/DpdLabel/labels', $params));
        } catch (\Exception $ex) {
            return $this->generateRedirect(URL::getInstance()->absoluteUrl('admin/module/DpdLabel/labels', [
                'err' => $ex->getMessage(),
            ]));
        }
    }

    #[Route('/generateLabel', name: '_generate_label', methods: ['POST'])]
    public function generateLabelAction(Request $request, LabelService $labelService): Response
    {
        $orderId = $request->query->get('orderId');
        $retour = (bool) $request->query->get('retour');
        $returnUrl = $request->request->get('return_url');
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

        if ($this->isSafeInternalUrl($returnUrl)) {
            return new RedirectResponse($returnUrl);
        }

        return $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/order/update/'.$orderId, [
            'err' => $error,
            'tab' => 'bill',
        ]));
    }

    #[Route('/labels-file/{base64EncodedFilename}', name: '_labels_file_download', methods: ['GET'])]
    public function downloadAction(string $base64EncodedFilename, Translator $translator): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::ORDER, [], AccessManager::VIEW)) {
            return $response;
        }

        $fileName = base64_decode($base64EncodedFilename);
        [ 'filename' => $fileNameWithoutExt, 'dirname' => $dirname ] = pathinfo($fileName);
        $files = glob($dirname.'/'.$fileNameWithoutExt.'.*');

        $baseDir = realpath(DpdLabel::DPD_LABEL_DIR);

        // Confine the resolved file to the module label directory (prevents arbitrary file read).
        if (!empty($files) && false !== ($real = realpath($files[0])) && false !== $baseDir
            && str_starts_with($real, $baseDir.\DIRECTORY_SEPARATOR)) {
            $fileName = $real;

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

    #[Route('/getLabel/{orderRef}', name: '_get_label', methods: ['GET'], requirements: ['orderRef' => '[A-Za-z0-9_\-]+'])]
    public function getLabelAction($orderRef, Request $request, Translator $translator, LabelService $labelService): Response
    {
        if (null !== $labelFile = $labelService->getLabelFilePathForOrder($orderRef)) {
            $response = new BinaryFileResponse($labelFile);

            if ($request->query->get('download')) {
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
     */
    #[Route('/deleteLabel', name: '_delete_label', methods: ['GET'])]
    public function deleteLabelAction(Request $request, LabelService $labelService): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::ORDER, [], AccessManager::UPDATE)) {
            return $response;
        }

        try {
            $this->getTokenProvider()->checkToken((string) $request->query->get('_token', ''));
        } catch (TokenAuthenticationException) {
            return new Response('Invalid token', Response::HTTP_FORBIDDEN);
        }

        $orderId = (int) $request->query->get('orderId');
        $returnUrl = $request->query->get('return_url');
        $redirectUrl = $request->query->get('redirect_url');

        $labelService->deleteLabel($orderId);

        if ($this->isSafeInternalUrl($returnUrl)) {
            return new RedirectResponse($returnUrl);
        }

        if ($this->isSafeInternalUrl($redirectUrl)) {
            return $this->generateRedirect(URL::getInstance()->absoluteUrl($redirectUrl));
        }

        return $this->generateRedirect(URL::getInstance()->absoluteUrl('admin/module/DpdLabel/labels'));
    }

    /**
     * Only allow redirects to internal, relative paths (prevents open redirect).
     */
    private function isSafeInternalUrl(?string $url): bool
    {
        return \is_string($url) && '' !== $url && str_starts_with($url, '/') && !str_starts_with($url, '//');
    }

    /**
     * Build the list of DPD orders for the labels management page.
     *
     * Reuses the DpdOrders loop criteria: orders shipped through an active DPD
     * delivery module, in paid or processing status, most recent first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildOrderList(): array
    {
        $deliveryModuleIds = [];
        foreach (DpdLabel::DPD_MODULES as $code) {
            if (null !== $module = ModuleQuery::create()->filterByCode($code)->filterByActivate(1)->findOne()) {
                $deliveryModuleIds[] = $module->getId();
            }
        }

        if ([] === $deliveryModuleIds) {
            return [];
        }

        $locale = $this->getRequest()->getLocale();

        $orders = OrderQuery::create()
            ->filterByDeliveryModuleId($deliveryModuleIds)
            ->filterByStatusId([DpdLabel::STATUS_PAID, DpdLabel::STATUS_PROCESSING])
            ->orderByCreatedAt(Criteria::DESC)
            ->find();

        $rows = [];
        /** @var Order $order */
        foreach ($orders as $order) {
            $status = $order->getOrderStatus();
            $address = $order->getOrderAddressRelatedByDeliveryOrderAddressId();
            $country = $address->getCountry();

            $tax = 0;
            $totalWithTax = $order->getTotalAmount($tax);

            $labelNumber = DpdlabelLabelsQuery::create()
                ->filterByOrderId($order->getId())
                ->findOne()
                ?->getLabelNumber();

            $rows[] = [
                'id' => $order->getId(),
                'ref' => $order->getRef(),
                'status_title' => $status->setLocale($locale)->getTitle(),
                'status_color' => $status->getColor(),
                'created_at' => $order->getCreatedAt(),
                'total_with_tax' => $totalWithTax,
                'currency_symbol' => $order->getCurrency()->getSymbol(),
                'destination' => $this->formatDestination($address, $country->setLocale($locale)->getTitle()),
                'label_number' => $labelNumber,
                'weight' => $order->getWeight(),
            ];
        }

        return $rows;
    }

    private function formatDestination(?OrderAddress $address, ?string $countryTitle): string
    {
        if (null === $address) {
            return '';
        }

        $street = trim(sprintf('%s %s %s', $address->getAddress1(), $address->getAddress2(), $address->getAddress3()));

        return trim(sprintf('%s, %s %s, %s', $street, $address->getCity(), $address->getZipcode(), (string) $countryTitle), ', ');
    }
}
