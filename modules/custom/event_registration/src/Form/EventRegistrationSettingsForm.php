<?php

namespace Drupal\event_registration\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;


class EventRegistrationSettingsForm extends ConfigFormBase {

  protected Connection $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('database'));
  }

  protected function getEditableConfigNames() {
    return ['event_registration.settings'];
  }

  public function getFormId() {
    return 'event_registration_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    /* ========== FORM FIELDS ========== */

    $form['event_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Event Name'),
      '#required' => TRUE,
    ];

    $form['event_category'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Category'),
      '#options' => [
        '' => '- Select -',
        'Online Workshop' => 'Online Workshop',
        'Hackathon' => 'Hackathon',
        'Conference' => 'Conference',
        'One-day Workshop' => 'One-day Workshop',
      ],
      '#required' => TRUE,
    ];

    $form['registration_start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Registration Start Date'),
      '#required' => TRUE,
    ];

    $form['registration_end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Registration End Date'),
      '#required' => TRUE,
    ];

    $form['event_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Event Date'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {

    $start = strtotime($form_state->getValue('registration_start_date'));
    $end   = strtotime($form_state->getValue('registration_end_date'));
    $event = strtotime($form_state->getValue('event_date'));

    if ($start >= $end) {
      $form_state->setErrorByName('registration_end_date',
        $this->t('Registration end date must be after start date.'));
    }

    if ($event <= $end) {
      $form_state->setErrorByName('event_date',
        $this->t('Event date must be after registration end date.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {

  $start = strtotime($form_state->getValue('registration_start_date'));
  $end   = strtotime($form_state->getValue('registration_end_date'));
  $event = strtotime($form_state->getValue('event_date'));
  $now   = \Drupal::time()->getCurrentTime();

  $this->database->insert('event_config')
    ->fields([
      'event_name' => $form_state->getValue('event_name'),
      'event_category' => $form_state->getValue('event_category'),
      'registration_start_date' => $start,
      'registration_end_date' => $end,
      'event_date' => $event,
    ])
    ->execute();

  // ✅ ADMIN FEEDBACK (only after save)
  if ($now < $start) {
    $this->messenger()->addWarning(
      $this->t('Registration will start on @date', [
        '@date' => date('d M Y', $start),
      ])
    );
  }
  elseif ($now > $end) {
    $this->messenger()->addError(
      $this->t('Registration ended on @date', [
        '@date' => date('d M Y', $end),
      ])
    );
  }
  else {

  $link = Link::fromTextAndUrl(
    $this->t('Click here to open the Event Registration Form'),
    Url::fromRoute(
  'event_registration.register',
  [],
  ['attributes' => ['target' => '_blank']]
)

  )->toString();

  $this->messenger()->addStatus(
    $this->t('Registration is OPEN. @link', [
      '@link' => $link,
    ])
  );
}


  parent::submitForm($form, $form_state);
}
}
