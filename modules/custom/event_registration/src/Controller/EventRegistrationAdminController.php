<?php

namespace Drupal\event_registration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Drupal\Core\Form\FormStateInterface;

/**
 * Controller for listing event registrations with filters and CSV export.
 */
class EventRegistrationAdminController extends ControllerBase {

  protected Connection $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('database'));
  }

  /**
   * Display registrations with filters.
   */
  public function listRegistrations(Request $request) {
    $build = [];

    // ================= FILTER FORM =================
    $form = [
      '#type' => 'form',
      '#method' => 'get',
      '#attributes' => ['id' => 'event-registration-filters'],
      '#prefix' => '<div id="event-registration-filters-wrapper">',
      '#suffix' => '</div>',
    ];

    // Fetch distinct dates
    $dates = $this->database->select('event_config', 'e')
      ->fields('e', ['event_date'])
      ->distinct()
      ->orderBy('event_date', 'ASC')
      ->execute()
      ->fetchCol();

    $date_options = ['' => '- Select -'];
    foreach ($dates as $date) {
      $date_options[$date] = date('d M Y', $date);
    }

    $selected_date = $request->query->get('event_date', '');
    $selected_event = $request->query->get('event_id', '');

    // Event name options
    $event_options = ['' => '- Select -'];
    if ($selected_date) {
      $events = $this->database->select('event_config', 'e')
        ->fields('e', ['id', 'event_name'])
        ->condition('event_date', $selected_date)
        ->execute()
        ->fetchAll();
      foreach ($events as $event) {
        $event_options[$event->id] = $event->event_name;
      }
    }

    $form['event_date'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Date'),
      '#options' => $date_options,
      '#default_value' => $selected_date,
      '#ajax' => [
        'callback' => '::ajaxUpdateEventNames',
        'wrapper' => 'event-name-wrapper',
      ],
    ];

    $form['event_name_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'event-name-wrapper'],
      'event_id' => [
        '#type' => 'select',
        '#title' => $this->t('Event Name'),
        '#options' => $event_options,
        '#default_value' => $selected_event,
      ],
    ];

    $form['filter_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
    ];

    $form['export_csv'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export CSV'),
      '#submit' => ['::exportCsv'],
    ];

    $build['filters'] = $form;

    // ================= TABLE DATA =================
    $registrations = $this->getFilteredRegistrations($selected_date, $selected_event);

    $rows = [];
    foreach ($registrations as $record) {
      $rows[] = [
        'data' => [
          $record->full_name,
          $record->email,
          date('d M Y', $record->event_date),
          $record->college_name,
          $record->department,
          date('d M Y H:i:s', $record->created),
        ],
      ];
    }

    $header = [
      $this->t('Name'),
      $this->t('Email'),
      $this->t('Event Date'),
      $this->t('College Name'),
      $this->t('Department'),
      $this->t('Submission Date'),
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No registrations found.'),
    ];

    $build['total'] = [
      '#markup' => $this->t('Total Participants: @count', ['@count' => count($rows)]),
    ];

    return $build;
  }

  /**
   * AJAX callback to update event names when date changes.
   */
  public function ajaxUpdateEventNames(array $form, FormStateInterface $form_state) {
    $selected_date = $form_state->getValue('event_date');
    $event_options = ['' => '- Select -'];
    if ($selected_date) {
      $events = $this->database->select('event_config', 'e')
        ->fields('e', ['id', 'event_name'])
        ->condition('event_date', $selected_date)
        ->execute()
        ->fetchAll();
      foreach ($events as $event) {
        $event_options[$event->id] = $event->event_name;
      }
    }
    $form['event_name_wrapper']['event_id']['#options'] = $event_options;
    return $form['event_name_wrapper'];
  }

  /**
   * Get registrations filtered by date and event.
   */
  protected function getFilteredRegistrations($selected_date = '', $selected_event = '') {
    $query = $this->database->select('event_registration', 'r');
    $query->join('event_config', 'e', 'r.event_id = e.id');

    if ($selected_date) {
      $query->condition('e.event_date', $selected_date);
    }

    if ($selected_event) {
      $query->condition('r.event_id', $selected_event);
    }

    $query->fields('r', ['full_name', 'email', 'college_name', 'department', 'created']);
    $query->addField('e', 'event_date');

    return $query->execute()->fetchAll();
  }

  /**
   * Export filtered registrations as CSV.
   */
  public function exportCsv(array &$form, FormStateInterface $form_state) {
    $selected_date = $form_state->getValue('event_date');
    $selected_event = $form_state->getValue(['event_name_wrapper', 'event_id']);

    $registrations = $this->getFilteredRegistrations($selected_date, $selected_event);

    $response = new StreamedResponse(function () use ($registrations) {
      $handle = fopen('php://output', 'w');
      $header = ['Name', 'Email', 'College Name', 'Department', 'Submission Date', 'Event Date'];
      fputcsv($handle, $header);

      foreach ($registrations as $record) {
        fputcsv($handle, [
          $record->full_name,
          $record->email,
          $record->college_name,
          $record->department,
          date('d M Y H:i:s', $record->created),
          date('d M Y', $record->event_date),
        ]);
      }
      fclose($handle);
    });

    $filename = 'event_registrations_' . date('YmdHis') . '.csv';
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename=' . $filename);

    $response->send();
    exit;
  }

}
