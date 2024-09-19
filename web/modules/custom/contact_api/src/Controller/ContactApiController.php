<?php

namespace Drupal\contact_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\user\Entity\User;
use Drupal\Core\Mail\MailManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Html;
use Psr\Log\LoggerInterface;

class ContactApiController extends ControllerBase {

  /**
   * The mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a ContactApiController object.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(MailManagerInterface $mail_manager, LoggerInterface $logger) {
    $this->mailManager = $mail_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.mail'),
      $container->get('logger.factory')->get('contact_api')
    );
  }

  /**
   * Handles contact form submission via API.
   */
  public function submitContactForm(Request $request) {
    // Decode the JSON payload.
    $data = json_decode($request->getContent(), TRUE);

    // Validate payload fields.
    if (empty($data['subject']) || empty($data['message']) || empty($data['recipient'])) {
      return new JsonResponse(['error' => 'Missing required fields: subject, message, or recipient.'], 400);
    }

    // Validate recipient exists.
    $recipient = User::load($data['recipient']);
    if (!$recipient) {
      return new JsonResponse(['error' => 'Recipient user not found.'], 404);
    }

    // Get the recipient's email.
    $to = $recipient->getEmail();
    if (empty($to)) {
      $this->logger->error('Failed to send email: Recipient does not have a valid email address.');
      return new JsonResponse(['error' => 'Recipient does not have a valid email address.'], 500);
    }

    // Prepare email variables.
    $subject = Html::escape($data['subject']);
    $message = Html::escape($data['message']);
    $from = $this->currentUser()->getEmail();
    if (empty($from)) {
      $this->logger->error('Failed to send email: Sender does not have a valid email address.');
      return new JsonResponse(['error' => 'Sender does not have a valid email address.'], 500);
    }

    // Log the email details.
    $this->logger->info('Sending email to {to} from {from} with subject: {subject}', [
      'to' => $to,
      'from' => $from,
      'subject' => $subject,
    ]);

    // Send email to the recipient.
    $params = [
      'subject' => $subject,
      'message' => $message,
      'from' => $from,
      'to' => $to,
    ];

    // Custom email template name (avoiding contact_mail).
    $langcode = $this->currentUser()->getPreferredLangcode();
    $send = $this->mailManager->mail('contact_api', 'custom_contact_message', $to, $langcode, $params, $from, TRUE);

    if ($send['result'] !== TRUE) {
      $this->logger->error('Failed to send email to {to}', ['to' => $to]);
      return new JsonResponse(['error' => 'Failed to send the email.'], 500);
    }

    // If send_copy is true, send a copy to the sender.
    if (!empty($data['send_copy']) && $data['send_copy'] === TRUE) {
      $copy_result = $this->mailManager->mail('contact_api', 'custom_contact_message', $from, $langcode, $params, $from, TRUE);
      if ($copy_result['result'] !== TRUE) {
        $this->logger->error('Failed to send copy to {from}', ['from' => $from]);
        return new JsonResponse(['error' => 'Failed to send the copy to the sender.'], 500);
      }
    }

    return new JsonResponse(['message' => 'Contact form submitted and email sent successfully.'], 200);
  }
}
