<?php

namespace Drupal\user_admin_role_notification;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Logger\LoggerChannelTrait;

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
   * @var Drupal\Core\Mail\MailManager
   */
  protected $mailManager;

  /**
   * The link generator instance.
   *
   * @var Drupal\Core\Mail\MailManager
   */
  protected $linkGenerator;

  /**
   * Creates a verbose messenger.
   */
  public function __construct(ConfigFactoryInterface $config_factory, MailManagerInterface $mailManager, LinkGeneratorInterface $linkGenerator) {
    $this->configFactory = $config_factory;
    $this->mailManager = $mailManager;
    $this->linkGenerator = $linkGenerator;
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
   * @return array
   *   Array of User Uids.
   */
  public function getUsersOfAdministratorRole() {
    $ids = \Drupal::entityQuery('user')
      ->condition('status', 1)
      ->condition('roles', ['administrator'], 'IN')
      ->accessCheck(FALSE)
      ->execute();
 
    return $ids;
  }

  /**
   * Send Eamil.
   *
   * @param Drupal\Core\Session\AccountInterface $account
   *   The account that was created or updated.
   * @param bool $is_new
   *   Flag for whether account was newly created, or updated.
   */
  public function sendMail(AccountInterface $account, $is_new = FALSE) {
    global $base_url;
    $config = $this->getConfigs();
    $enabled = $config->get('user_admin_role_notification_enabled');
    
    if ($enabled) {
      
      $user_name = $account->getDisplayName();
      $url = Url::fromUri($base_url . '/user/' . $account->id());
      $internal_link = $this->linkGenerator->generate($this->t('@title', ['@title' => $user_name]), $url);
      $variables = [
        '@user_link' => $internal_link,
        '@action' => $is_new ? $this->t('created') : $this->t('added'),
      ];
      $subject = $this->t($config->get('user_admin_role_notification_email_subject'), $variables);
      $body = $this->t($config->get('user_admin_role_notification_email_body'), $variables);
      
      $admin_email = $config->get('user_admin_role_notification_email');
      if (empty($admin_email)) {
        $ids = $this->getUsersOfAdministratorRole();
        // Do we exclude the newly minted administrator user from the list?
        $emails = [];
        if (count($ids)) {
          $users = User::loadMultiple($ids);
          foreach ($users as $userload) {
            $emails[] = $userload->getEmail();
          }
        }
        $admin_email = implode(',', $emails);
      }
      // Set a dummy no reply email if email list is not empty.
      //$to = empty($admin_email) ? \Drupal::config('system.site')->get('mail') : 'noreply@noreply.com';
      $to = \Drupal::config('system.site')->get('mail');
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
      if (empty($to) || empty(\Drupal::config('system.site')->get('mail'))) {
        $this->getLogger('user_admin_role_notification')->error($this->t('From and To email addresses should not be empty.'));
        return;
      }
      $this->mailManager->mail('user_admin_role_notification', $key, $to, 'en', $params, \Drupal::config('system.site')->get('mail'), TRUE);
      $this->getLogger('user_admin_role_notification')->notice($this->t('User admin role notification sent to @emails.', ['@emails' => $admin_email]));
    }
  }

}
