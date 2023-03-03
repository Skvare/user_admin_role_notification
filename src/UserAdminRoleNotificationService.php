<?php

namespace Drupal\user_admin_role_notification;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Url;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * UserAdminRoleNotificationService implement helper service class.
 */
class UserAdminRoleNotificationService {

  use StringTranslationTrait;
  use LoggerChannelTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The mail manager instance.
   *
   * @var \Drupal\Core\Mail\MailManager
   */
  protected $mailManager;

  /**
   * The link generator instance.
   *
   * @var \Drupal\Core\Utility\LinkGenerator
   */
  protected $linkGenerator;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Creates a verbose messenger.
   */
  public function __construct(ConfigFactoryInterface $config_factory, MailManagerInterface $mailManager, LinkGeneratorInterface $linkGenerator, EntityTypeManagerInterface $entityTypeManager) {
    $this->configFactory = $config_factory;
    $this->mailManager = $mailManager;
    $this->linkGenerator = $linkGenerator;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Get settings of admin content notification.
   */
  public function getConfigs() {
    return $this->configFactory->get('user_admin_role_notification.settings');
  }

  /**
   * Get users of roles.
   *
   * @param string $role
   *   Machine name of role.
   *
   * @return array
   *   Array of User Uids.
   */
  public function getUsersOfRole($role) {
    $query = $this->entityTypeManager->getStorage('user')->getQuery();
    $ids = $query->condition('status', 1)
      ->condition('roles', [$role], 'IN')
      ->accessCheck(FALSE)
      ->execute();

    return $ids;
  }

  /**
   * Send Eamil.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account that was created or updated.
   * @param int $operation
   *   Operation that was performed.
   */
  public function sendMail(AccountInterface $account, $operation = 1) {
    global $base_url;
    $config = $this->getConfigs();
    $enabled = $config->get('user_admin_role_notification_enabled');

    if ($enabled) {
      $user_name = $account->getDisplayName();
      $url = Url::fromUri($base_url . '/user/' . $account->id());
      $internal_link = $this->linkGenerator->generate($this->t('@title', ['@title' => $user_name]), $url);
      switch ($operation) {
        case 1:
          $action = $this->t('created');
          break;

        case 2:
          $action = $this->t('added');
          break;

        case 3:
          $action = $this->t('removed');
          break;

        case 4:
          $action = $this->t('deleted');
          $internal_link = $user_name;
          break;

        default:
          $action = '';
          break;
      }
      $variables = [
        '@user_link' => $internal_link,
        '@action' => $action,
      ];
      // @codingStandardsIgnoreStart
      $subject = $this->t($config->get('user_admin_role_notification_email_subject'), $variables);
      $body = $this->t($config->get('user_admin_role_notification_email_body'), $variables);
      // @codingStandardsIgnoreEnd
      $other_emails = $config->get('user_admin_role_notification_email');
      $send_to_role = $config->get('user_admin_role_notification_send_to_role');
      if (!empty($send_to_role)) {
        $ids = $this->getUsersOfRole($send_to_role);
        $emails = [];
        if (count($ids)) {
          $users = $this->entityTypeManager->getStorage('user')->loadMultiple($ids);
          foreach ($users as $userload) {
            $emails[] = $userload->getEmail();
          }
        }
        $admin_email = implode(',', $emails);
        if (!empty($other_emails)) {
          $admin_email .= ',';
        }
      }
      if (!empty($other_emails)) {
        $admin_email .= $other_emails;
      }
      error_log('Emails to receive: ' . $admin_email);
      // Set a dummy no reply email if email list is not empty.
      // @codingStandardsIgnoreStart
      // $to = empty($admin_email) ? \Drupal::config('system.site')->get('mail') : 'noreply@noreply.com';
      // @codingStandardsIgnoreEnd
      $system_site_email = $this->configFactory->get('system.site')->get('mail');
      $to = $system_site_email;
      $params = [
        'body' => $body,
        'subject' => $subject,
      ];

      if (!empty($admin_email)) {
        $params['bcc'] = $admin_email;
      }

      if (strlen($admin_email) === 0) {
        return;
      }
      $key = 'user_admin_role_notification_key';
      if (empty($to) || empty($system_site_email)) {
        $this->getLogger('user_admin_role_notification')->error($this->t('From and To email addresses should not be empty.'));
        return;
      }
      $this->mailManager->mail('user_admin_role_notification', $key, $to, 'en', $params, $system_site_email, TRUE);
      $this->getLogger('user_admin_role_notification')->notice($this->t('User admin role notification sent to @emails.', ['@emails' => $admin_email]));
    }
  }

}
