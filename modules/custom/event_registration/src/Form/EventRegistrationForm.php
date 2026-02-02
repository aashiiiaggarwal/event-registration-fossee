<?php

namespace Drupal\event_registration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Mail\MailManagerInterface;

class EventRegistrationForm extends FormBase {

  protected Connection $database;
  protected MailManagerInterface $mailManager;

  public function __construct(Connection $database, MailManagerInterface $mail_manager) {
    $this->database = $database;
    $this->mailManager = $mail_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('plugin.manager.mail')
    );
  }

  public function getFormId() {
    return 'event_registration_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $current_time = time();

    // Fetch all active events from DB
    $events = $this->database->select('event_config', 'e')
      ->fields('e', ['id', 'event_name', 'event_category', 'event_date'])
      ->condition('registration_start_date', $current_time, '<=')
      ->condition('registration_end_date', $current_time, '>=')
      ->execute()
      ->fetchAll();

    if (empty($events)) {
      $form['message'] = [
        '#markup' => '<p>' . $this->t('No events open for registration currently.') . '</p>',
      ];
      return $form;
    }

    // Extract unique categories from active events
    $categories = array_unique(array_map(fn($e) => $e->event_category, $events));

    // Get selected values or defaults for dependent dropdowns
    $selected_category = $form_state->getValue('event_category') ?: reset($categories);
    $event_dates = $this->getEventDates($selected_category);

    $selected_date = $form_state->getValue(['event_date_wrapper', 'event_date']) ?: array_key_first($event_dates);
    $event_names = $this->getEventNames($selected_category, $selected_date);

    $selected_event_id = $form_state->getValue('event_name') ?: null;

    // =============== BASIC FIELDS =================
    $form['full_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Full Name'),
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('full_name') ?: '',
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email Address'),
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('email') ?: '',
    ];

    $form['college_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('College Name'),
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('college_name') ?: '',
    ];

    $form['department'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Department'),
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('department') ?: '',
    ];

    // =============== EVENT CATEGORY =================
    $form['event_category'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Category'),
      '#options' => array_combine($categories, $categories),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateEventDates',
        'wrapper' => 'event-date-wrapper',
        'event' => 'change',
      ],
      '#default_value' => $selected_category,
    ];

    // =============== EVENT DATE =================
    $form['event_date_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'event-date-wrapper'],
    ];

    $form['event_date_wrapper']['event_date'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Date'),
      '#options' => $event_dates,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateEventNames',
        'wrapper' => 'event-name-wrapper',
        'event' => 'change',
      ],
      '#default_value' => $selected_date,
    ];

    // =============== EVENT NAME =================
    $form['event_name_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'event-name-wrapper'],
    ];

    $form['event_name_wrapper']['event_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Name'),
      '#options' => $event_names,
      '#required' => TRUE,
      '#default_value' => $selected_event_id,
    ];

    // =============== SUBMIT BUTTON =================
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Register'),
    ];

    return $form;
  }

  // AJAX callback for updating event dates when category changes
  public function updateEventDates(array $form, FormStateInterface $form_state) {
    return $form['event_date_wrapper'];
  }

  // AJAX callback for updating event names when date changes
  public function updateEventNames(array $form, FormStateInterface $form_state) {
    return $form['event_name_wrapper'];
  }

  // Helper: Get event dates for a category (distinct, active)
  protected function getEventDates(string $category): array {
    $current_time = time();

    $dates = $this->database->select('event_config', 'e')
      ->fields('e', ['event_date'])
      ->condition('event_category', $category)
      ->condition('registration_start_date', $current_time, '<=')
      ->condition('registration_end_date', $current_time, '>=')
      ->distinct()
      ->execute()
      ->fetchCol();

    $options = [];
    foreach ($dates as $date) {
      // Key: timestamp, Value: formatted date string
      $options[$date] = date('d M Y', $date);
    }

    return $options;
  }

  // Helper: Get event names for category + date, returns array keyed by event ID
  protected function getEventNames(string $category, $date): array {
    if (!$date) {
      return [];
    }

    $events = $this->database->select('event_config', 'e')
      ->fields('e', ['id', 'event_name'])
      ->condition('event_category', $category)
      ->condition('event_date', $date)
      ->execute()
      ->fetchAll();

    $options = [];
    foreach ($events as $event) {
      $options[$event->id] = $event->event_name;
    }
    return $options;
  }

  // Validate form inputs
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('email', $this->t('Invalid email format.'));
    }

    foreach (['full_name', 'college_name', 'department'] as $field) {
      if (preg_match('/[^a-zA-Z0-9 ]/', $form_state->getValue($field))) {
        $form_state->setErrorByName($field, $this->t('Special characters are not allowed.'));
      }
    }

    $event_id = $form_state->getValue('event_name');

    // Check if user already registered for this event
    $exists = $this->database->select('event_registration', 'r')
      ->condition('email', $email)
      ->condition('event_id', $event_id)
      ->countQuery()
      ->execute()
      ->fetchField();

    if ($exists) {
      $form_state->setErrorByName('email', $this->t('You have already registered for this event.'));
    }
  }

  // Handle form submission
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Insert registration record
    $this->database->insert('event_registration')
      ->fields([
        'event_id' => $form_state->getValue('event_name'),
        'full_name' => $form_state->getValue('full_name'),
        'email' => $form_state->getValue('email'),
        'college_name' => $form_state->getValue('college_name'),
        'department' => $form_state->getValue('department'),
        'created' => time(),
      ])
      ->execute();

    // Fetch event details to include in emails
    $event = $this->database->select('event_config', 'e')
      ->fields('e')
      ->condition('id', $form_state->getValue('event_name'))
      ->execute()
      ->fetchObject();

    $user_email = $form_state->getValue('email');

    // Prepare email message content
    $message_text = $this->t("Dear @name,\n\nYou have successfully registered for the event:\nEvent Name: @event_name\nCategory: @category\nEvent Date: @date\n\nThank you for registering.",
      [
        '@name' => $form_state->getValue('full_name'),
        '@event_name' => $event->event_name,
        '@category' => $event->event_category,
        '@date' => date('d M Y', $event->event_date),
      ]
    );

    // Send confirmation email to user
    $this->mailManager->mail(
      'event_registration',               // module name
      'registration_confirmation',        // mail key
      $user_email,                       // recipient email
      \Drupal::currentUser()->getPreferredLangcode(),
      ['message' => $message_text]
    );

    // Send notification to admin if enabled
    $config = $this->config('event_registration.settings');
    $admin_email = $config->get('admin_email') ?: \Drupal::config('system.site')->get('mail');

    if ($config->get('admin_notification_enabled') && $admin_email) {
      $this->mailManager->mail(
        'event_registration',
        'registration_confirmation',
        $admin_email,
        \Drupal::currentUser()->getPreferredLangcode(),
        ['message' => $message_text]
      );
    }

    $this->messenger()->addStatus($this->t('Registration successful. A confirmation email has been sent.'));
  }

}
