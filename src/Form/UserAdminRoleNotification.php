<?php

namespace Drupal\user_admin_role_notification\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user_admin_role_notification\UserAdminRoleNotificationService;

/**
 * Class UserAdminRoleNotification implements settings for admin notification.
 */
class UserAdminRoleNotification extends ConfigFormBase {

  /**
   * A instance of the admin_content_notification helper services.
   *
   * @var \Drupal\user_admin_role_notification\UserAdminRoleNotificationService
   */
  protected $userAdminRoleNotificationService;

  /**
   * {@inheritdoc}
   */
  public function __construct(UserAdminRoleNotificationService $userAdminRoleNotificationService) {
    $this->userAdminRoleNotificationService = $userAdminRoleNotificationService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user_admin_role_notification.common')
    );
  }

  /**
   * Get the form_id.
   *
   * @inheritDoc
   */
  public function getFormId() {
    return 'user_admin_role_notification_form';
  }

  /**
   * Build the Form.
   *
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $config = $this->config('user_admin_role_notification.settings');
    $form = [];
    $user_admin_role_notification_enabled = $config->get('user_admin_role_notification_enabled');
    $form['user_admin_role_notification_enabled'] = [
      '#title' => $this->t('Enabled'),
      '#type' => 'checkbox',
      '#default_value' => !empty($user_admin_role_notification_enabled) ? 1 : 0,
    ];
    $send_to_role = $config->get('user_admin_role_notification_send_to_role');
    $roles[''] = '- None -';
    $roles += user_role_names(TRUE);
    if (isset($roles['authenticated'])) {
      unset($roles['authenticated']);
    }
    $form['user_admin_role_notification_send_to_role'] = [
      '#title' => $this->t('Send notifications to users of role'),
      '#type' => 'select',
      '#options' => $roles,
      '#default_value' => !empty($send_to_role) ? $send_to_role : '',
      '#description' => $this->t('Users of this role will receive notifications'),
    ];
    $trigger_role = $config->get('user_admin_role_notification_trigger_role');
    $form['user_admin_role_notification_trigger_role'] = [
      '#title' => $this->t('Role to trigger notifications'),
      '#type' => 'select',
      '#options' => $roles,
      '#default_value' => !empty($trigger_role) ? $trigger_role : '',
      '#description' => $this->t('Users of this role will trigger sending of notifications on create/add/remove/delete'),
    ];
    $user_admin_role_notification_email = $config->get('user_admin_role_notification_email');
    $form['user_admin_role_notification_email'] = [
      '#type' => 'textarea',
      '#title' => $this->t("Additional email addresses to whom the notification is to be sent"),
      '#default_value' => $user_admin_role_notification_email ?? '',
      '#description' => $this->t('If multiple, comma separate list, without comma at the end'),
    ];

    $form['user_admin_role_notification_email_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Email Settings'),
    ];

    $form['user_admin_role_notification_email_fieldset']['user_admin_role_notification_email_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Configurable email subject'),
      '#default_value' => $config->get('user_admin_role_notification_email_subject'),
      '#description' => $this->t('Enter subject of the email.'),
    ];

    $form['user_admin_role_notification_email_fieldset']['user_admin_role_notification_email_body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Configurable email body'),
      '#default_value' => $config->get('user_admin_role_notification_email_body'),
      '#description' => $this->t('Email body for the email. Use the following tokens: @user_link, @action (created or updated).'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Get Editable config names.
   *
   * @inheritDoc
   */
  protected function getEditableConfigNames() {
    return ['user_admin_role_notification.settings'];
  }

  /**
   * Add validate handler.
   *
   * @inheritDoc
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $user_input_values = $form_state->getUserInput();
    if (!empty($user_input_values['user_admin_role_notification_email']) && substr($user_input_values['user_admin_role_notification_email'], -1) == ',') {
      $form_state->setErrorByName('user_admin_role_notification_email', $this->t('Additional email addresses must not end with comma'));
    }
  }

  /**
   * Add submit handler.
   *
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user_input_values = $form_state->getUserInput();
    $config = $this->configFactory->getEditable('user_admin_role_notification.settings');
    $config->set('user_admin_role_notification_enabled', $user_input_values['user_admin_role_notification_enabled']);
    $config->set('user_admin_role_notification_send_to_role', $user_input_values['user_admin_role_notification_send_to_role']);
    $config->set('user_admin_role_notification_trigger_role', $user_input_values['user_admin_role_notification_trigger_role']);
    $config->set('user_admin_role_notification_email', $user_input_values['user_admin_role_notification_email']);
    $config->set('user_admin_role_notification_email_subject', $user_input_values['user_admin_role_notification_email_subject']);
    $config->set('user_admin_role_notification_email_body', $user_input_values['user_admin_role_notification_email_body']);
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
