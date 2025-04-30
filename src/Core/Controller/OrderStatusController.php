<?php

namespace TfcSwOzi\Core\Controller;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Mail\Service\MailService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Route(defaults: ['_routeScope' => ['api']])]
class OrderStatusController
{
    private EntityRepository $orderRepository;
    private MailService $mailService;
    private LoggerInterface $logger;
    private ContainerInterface $container;

    public function __construct(
        EntityRepository $orderRepository,
        MailService $mailService,
        LoggerInterface $logger,
        ContainerInterface $container
    ) {
        $this->orderRepository = $orderRepository;
        $this->mailService = $mailService;
        $this->logger = $logger;
        $this->container = $container;
    }

    #[Route(path: '/api/_action/order-status/update/{orderNumber}', name: 'api.action.order_status.wws_update', methods: ['POST'])]
    public function updateStatusFromWws(string $orderNumber, Request $request, Context $context): JsonResponse
    {
        $mode = (int)$request->query->get('mode');
        $sendMail = filter_var($request->query->get('sendMail'), FILTER_VALIDATE_BOOLEAN);
        $data = json_decode($request->getContent(), true);

        $criteria = new Criteria();
        $criteria->addFilter(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter('orderNumber', $orderNumber));

        $order = $this->orderRepository->search($criteria, $context)->first();
        if (!$order instanceof OrderEntity) {
            return new JsonResponse(['success' => false, 'message' => 'Order not found'], 404);
        }

        // Determine new status and optional email content
        $statusId = $this->getStatusIdByMode($mode);
        if (!$statusId) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid mode'], 400);
        }

        try {
            $this->orderRepository->update([
                [
                    'id' => $order->getId(),
                    'stateId' => $statusId,
                ]
            ], $context);

            if ($sendMail) {
                $this->sendStatusEmail($order, $mode, $data, $context);
            }

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('Order status update failed', ['exception' => $e]);
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    #[Route(path: '/api/Orderstatus/{orderNumber}', name: 'api.order_status.confirmation', methods: ['GET'])]
    public function confirmOrderFromWws(string $orderNumber, Request $request, Context $context): JsonResponse
    {
        $mode = $request->query->get('mode');
        $sendMail = filter_var($request->query->get('sendMail', false), FILTER_VALIDATE_BOOLEAN);

        if ($mode !== '0') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid mode for confirmation'], 400);
        }

        // Fetch order by order number
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));
        $criteria->addAssociation('stateMachineState');
        $order = $this->orderRepository->search($criteria, $context)->first();

        if (!$order instanceof OrderEntity) {
            return new JsonResponse(['success' => false, 'message' => 'Order not found'], 404);
        }

        try {
            // Status ID 1 = In Bearbeitung (wartet) for confirmation mode
            $this->orderRepository->update([[
                'id' => $order->getId(),
                'stateId' => '1',
            ]], $context);

            if ($sendMail) {
                $this->sendStatusEmail($order, 'In Bearbeitung (Auftrag erhalten)', $context);
            }

            return new JsonResponse(['success' => true, 'message' => 'Order confirmation processed']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    #[Route(path: '/api/Orderstatus/{orderNumber}', name: 'api.order_status.delivery_delay', methods: ['POST'])]
    public function handleDeliveryDelay(string $orderNumber, Request $request, Context $context): JsonResponse
    {
        $mode = $request->query->get('mode');
        $sendMail = filter_var($request->query->get('sendMail', false), FILTER_VALIDATE_BOOLEAN);

        if ($mode !== '1') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid mode for delivery delay'], 400);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['delay'])) {
            return new JsonResponse(['success' => false, 'message' => 'Missing delay information'], 400);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));
        $criteria->addAssociation('stateMachineState');

        $order = $this->orderRepository->search($criteria, $context)->first();

        if (!$order instanceof OrderEntity) {
            return new JsonResponse(['success' => false, 'message' => 'Order not found'], 404);
        }

        try {
            // Status ID 5 = 'Zur Lieferung bereit' (Delivery delay)
            $this->orderRepository->update([[
                'id' => $order->getId(),
                'stateId' => '5',
            ]], $context);

            if ($sendMail) {
                $delayMessage = 'Lieferverzögerung: ' . $data['delay'];
                $this->sendStatusEmail($order, $delayMessage, $context);
            }

            return new JsonResponse(['success' => true, 'message' => 'Delivery delay recorded']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    #[Route(path: '/api/Orderstatus/{orderNumber}', name: 'api.order_status.shipping_information"', methods: ['POST'])]
    public function handleShippingInformation(string $orderNumber, Request $request, Context $context): JsonResponse
    {
        $mode = $request->query->get('mode');
        $sendMail = filter_var($request->query->get('sendMail', false), FILTER_VALIDATE_BOOLEAN);

        if ($mode !== '2') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid mode for shipping information'], 400);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['informations'], $data['trackingId'], $data['shipperId'])) {
            return new JsonResponse(['success' => false, 'message' => 'Missing required shipping information'], 400);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));
        $criteria->addAssociation('stateMachineState');

        $order = $this->orderRepository->search($criteria, $context)->first();

        if (!$order instanceof OrderEntity) {
            return new JsonResponse(['success' => false, 'message' => 'Order not found'], 404);
        }

        try {
            // Status ID 7 = 'Komplett ausgeliefert' (shipment sent)
            $this->orderRepository->update([[
                'id' => $order->getId(),
                'stateId' => '7',
            ]], $context);

            // Optional: Send email with tracking information
            if ($sendMail) {
                $trackingMessage = 'Your shipment is on the way via ' . $data['informations'] . '. Tracking ID: ' . $data['trackingId'];
                $this->sendStatusEmail($order, $trackingMessage, $context);
            }

            return new JsonResponse(['success' => true, 'message' => 'Shipping information recorded']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    #[Route(path: '/api/Orderstatus/{orderNumber}', name: 'api.order_status.invoice_transmission', methods: ['POST'])]
    public function handleInvoiceTransmission(string $orderNumber, Request $request, Context $context): JsonResponse
    {
        $mode = $request->query->get('mode');
        $sendMail = filter_var($request->query->get('sendMail', false), FILTER_VALIDATE_BOOLEAN);

        if ($mode !== '3') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid mode for invoice transmission'], 400);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['invoiceNumber'], $data['deliveryDate'], $data['displayDate'])) {
            return new JsonResponse(['success' => false, 'message' => 'Missing required invoice information'], 400);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));
        $criteria->addAssociation('stateMachineState');

        $order = $this->orderRepository->search($criteria, $context)->first();

        if (!$order instanceof OrderEntity) {
            return new JsonResponse(['success' => false, 'message' => 'Order not found'], 404);
        }

        try {
            // Status ID 2 = 'Komplett abgeschlossen' (Invoice sent)
            $this->orderRepository->update([[
                'id' => $order->getId(),
                'stateId' => '2',
            ]], $context);

            // Generate invoice document
            $invoiceDocument = $this->createInvoiceDocument($order, $data, $context);

            // Optionally send email with invoice attached
            if ($sendMail) {
                $this->sendInvoiceEmail($order, $data['invoiceNumber'], $invoiceDocument, $context);
            }

            return new JsonResponse(['success' => true, 'message' => 'Invoice transmitted successfully']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function createInvoiceDocument(OrderEntity $order, array $data, Context $context): string
    {
        $documentGenerator = $this->container->get('document.generator.service');

        // Generate the invoice document
        $document = $documentGenerator->generate(
            'invoice',
            $order->getId(),
            $context,
            ['deliveryDate' => $data['deliveryDate'], 'displayDate' => $data['displayDate']]
        );

        $documentPath = $this->container->getParameter('kernel.project_dir') . '/var/tmp/' . $document->getFilename();

        if (!file_exists($documentPath)) {
            throw new \Exception('Failed to generate invoice document.');
        }

        return $documentPath;
    }

    private function sendInvoiceEmail(OrderEntity $order, string $invoiceNumber, string $documentPath, Context $context): void
    {
        $emailTemplateRepository = $this->container->get('email_template.repository');

        // Fetch email template (replace `invoice_template_id` with actual template ID)
        $templateCriteria = new Criteria();
        $templateCriteria->setIds(['invoice_template_id']);
        $template = $emailTemplateRepository->search($templateCriteria, $context)->first();

        if (!$template) {
            throw new \Exception('Email template not found.');
        }

        // Generate email content
        $emailContent = str_replace(
            ['{{orderNumber}}', '{{invoiceNumber}}'],
            [$order->getOrderNumber(), $invoiceNumber],
            $template->getContent()
        );

        // Use Shopware's Mail Service
        $mailService = $this->container->get('mail.service');
        $mailService->send([
            'recipients' => [$order->getOrderCustomer()->getEmail() => $order->getOrderCustomer()->getFirstName()],
            'subject' => 'Invoice for Your Order ' . $order->getOrderNumber(),
            'contentHtml' => $emailContent,
            'contentPlain' => strip_tags($emailContent),
            'attachments' => [
                'invoice.pdf' => file_get_contents($documentPath)
            ],
        ], $context);
    }

    #[Route(path: '/api/order-status/{orderNumber}', name: 'aapi.order_status.return_transmission', methods: ['GET'])]
    public function handleReturnTransmission(string $orderNumber, Request $request, Context $context): JsonResponse
    {
        $mode = $request->query->get('mode');
        $sendMail = filter_var($request->query->get('sendMail', false), FILTER_VALIDATE_BOOLEAN);

        // Check if mode is 4 for return transmission
        if ($mode !== '4') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid mode for return transmission'], 400);
        }

        // Fetch order by order number
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));
        $order = $this->orderRepository->search($criteria, $context)->first();

        // Check if order is found
        if (!$order instanceof OrderEntity) {
            return new JsonResponse(['success' => false, 'message' => 'Order not found'], 404);
        }

        try {
            // Update order status to 'Return Received' (Status ID 5 - Retoure eingegangen)
            $this->orderRepository->update([[
                'id' => $order->getId(),
                'stateId' => '5', // Assuming status ID 5 is 'Return Received'
            ]], $context);

            // Optionally send email if 'sendMail' is true
            if ($sendMail) {
                $this->sendReturnEmail($order, $context);
            }

            return new JsonResponse(['success' => true, 'message' => 'Return information transmitted successfully']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }


    private function sendReturnEmail(OrderEntity $order, Context $context): void
    {
        // Assuming you have an email template for return notifications
        $emailTemplateRepository = $this->container->get('email_template.repository');

        // Fetch email template (replace 'return_template_id' with actual template ID)
        $templateCriteria = new Criteria();
        $templateCriteria->setIds(['return_template_id']);
        $template = $emailTemplateRepository->search($templateCriteria, $context)->first();

        if (!$template) {
            throw new \Exception('Email template not found.');
        }

        // Generate email content (replace placeholders as needed)
        $emailContent = str_replace(
            ['{{orderNumber}}'],
            [$order->getOrderNumber()],
            $template->getContent()
        );

        // Use Shopware's Mail Service
        $mailService = $this->container->get('mail.service');
        $mailService->send([
            'recipients' => [$order->getOrderCustomer()->getEmail() => $order->getOrderCustomer()->getFirstName()],
            'subject' => 'Return Confirmation for Order ' . $order->getOrderNumber(),
            'contentHtml' => $emailContent,
            'contentPlain' => strip_tags($emailContent),
        ], $context);
    }

    #[Route(path: '/api/order-status/{orderNumber}', name: 'api.order_status.credit_note_transmission', methods: ['POST'])]
    public function handleCreditNoteTransmission(string $orderNumber, Request $request, Context $context): JsonResponse
    {
        $mode = $request->query->get('mode');
        $sendMail = filter_var($request->query->get('sendMail', false), FILTER_VALIDATE_BOOLEAN);

        // Check if mode is 5 for credit note transmission
        if ($mode !== '5') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid mode for credit note transmission'], 400);
        }

        // Fetch order by order number
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));
        $order = $this->orderRepository->search($criteria, $context)->first();

        // Check if order is found
        if (!$order instanceof OrderEntity) {
            return new JsonResponse(['success' => false, 'message' => 'Order not found'], 404);
        }

        // Get the credit note data from the request body
        $creditData = json_decode($request->getContent(), true);

        if (empty($creditData['retoureValue']) || empty($creditData['retoureComment']) || empty($creditData['creditNumber']) || empty($creditData['creditDate'])) {
            return new JsonResponse(['success' => false, 'message' => 'Missing credit note data'], 400);
        }

        try {
            // Update order with credit note information (optional, based on your business logic)
            // This is just an example of updating the order with the credit amount.
            // Adjust according to how you want to record the credit note in the order.

            $this->orderRepository->update([[
                'id' => $order->getId(),
                'creditAmount' => $creditData['retoureValue'],  // You could also store this in a custom field
            ]], $context);

            // Optionally generate the credit note document
            $documentPath = $this->createCreditNoteDocument($order, $creditData, $context);

            // Optionally send email if 'sendMail' is true
            if ($sendMail) {
                $this->sendCreditNoteEmail($order, $creditData, $documentPath, $context);
            }

            return new JsonResponse(['success' => true, 'message' => 'Credit note transmitted successfully']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function createCreditNoteDocument(OrderEntity $order, array $creditData, Context $context): string
    {
        $documentGenerator = $this->container->get('document.generator.service');

        // Generate the credit note PDF (replace `credit_note` with the required document type)
        $document = $documentGenerator->generate(
            'credit_note',
            $order->getId(),
            $context,
            ['creditData' => $creditData]
        );

        $documentPath = $this->container->getParameter('kernel.project_dir') . '/var/tmp/' . $document->getFilename();

        if (!file_exists($documentPath)) {
            throw new \Exception('Failed to generate credit note document.');
        }

        return $documentPath;
    }

    private function sendCreditNoteEmail(OrderEntity $order, array $creditData, string $documentPath, Context $context): void
    {
        $emailTemplateRepository = $this->container->get('email_template.repository');

        // Fetch email template (replace 'credit_note_template_id' with actual template ID)
        $templateCriteria = new Criteria();
        $templateCriteria->setIds(['credit_note_template_id']);
        $template = $emailTemplateRepository->search($templateCriteria, $context)->first();

        if (!$template) {
            throw new \Exception('Email template not found.');
        }

        // Generate email content (replace placeholders as needed)
        $emailContent = str_replace(
            ['{{orderNumber}}', '{{creditValue}}', '{{creditNumber}}'],
            [$order->getOrderNumber(), $creditData['retoureValue'], $creditData['creditNumber']],
            $template->getContent()
        );

        // Use Shopware's Mail Service
        $mailService = $this->container->get('mail.service');
        $mailService->send([
            'recipients' => [$order->getOrderCustomer()->getEmail() => $order->getOrderCustomer()->getFirstName()],
            'subject' => 'Credit Note for Order ' . $order->getOrderNumber(),
            'contentHtml' => $emailContent,
            'contentPlain' => strip_tags($emailContent),
        ], $context);

        // Add attachment (the credit note PDF document)
        $this->addAttachmentToEmail($documentPath, $mailService);
    }

    private function addAttachmentToEmail(string $documentPath, \Shopware\Core\Content\Mail\Service\MailService $mailService): void
    {
        $attachment = file_get_contents($documentPath);

        if (!$attachment) {
            throw new \Exception('Failed to read document file for attachment.');
        }

        $mailService->addAttachment(
            $attachment,
            'credit_note.pdf',
            'application/pdf'
        );
    }

    private function getStatusIdByMode(int $mode): ?string
    {
        return match($mode) {
            0 => 'in_progress',         // Original ID 1
            1 => 'ready_for_shipping',  // Original ID 5
            2 => 'completely_delivered',// Original ID 7
            3 => 'completely_finished', // Original ID 2
            4 => 'partially_finished',  // Original ID 3
            5 => 'partially_delivered', // Original ID 6
            default => null
        };
    }

    private function sendStatusEmail(OrderEntity $order, int $mode, array $data, Context $context): void
    {
        $templateContent = match($mode) {
            1 => 'Lieferverzögerung: ' . ($data['delay'] ?? 'Keine Angabe'),
            2 => 'Sendung über ' . ($data['informations'] ?? 'Unbekannt') . '. Tracking-ID: ' . ($data['trackingId'] ?? 'N/A'),
            3 => 'Rechnung Nr. ' . ($data['invoiceNumber'] ?? 'N/A') . ' vom ' . ($data['deliveryDate'] ?? 'N/A') . '. ' . ($data['docComment'] ?? ''),
            5 => 'Retoure-Wert: ' . ($data['retoureValue'] ?? '0') . '€. Kommentar: ' . ($data['retoureComment'] ?? ''),
            default => 'Statusupdate zu Ihrer Bestellung'
        };

        $this->mailService->send([
            'recipients' => [$order->getOrderCustomer()->getEmail() => $order->getOrderCustomer()->getFirstName()],
            'subject' => 'Bestellstatus-Aktualisierung',
            'contentHtml' => '<p>' . nl2br($templateContent) . '</p>',
            'contentPlain' => $templateContent
        ], $context);
    }
}
