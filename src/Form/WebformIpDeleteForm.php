<?php

namespace Drupal\webform_ip_delete\form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Entity\WebformSubmission;

class WebformIpDeleteForm extends FormBase {

  public function getFormId() {
    return 'webform_ip_delete_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    if (!$form_state->has('table')) {
      $form_state->set('table', $this->get_webforms_with_ip_submissions());
    }

    $webforms = $form_state->get('table');

    $form['container'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Webforms'),
      '#description' => $this->t('Webforms with collected IP addresses'),
    ];

    $form['container']['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('ID'),
        $this->t('Title'),
        $this->t('Number of submissions'),
        $this->t('Action'),
      ],
    ];

    foreach ($webforms as $index => $webform) {
      $form['container']['table'][$index][$webform['id']] = [
        '#markup' => $webform['id'],
      ];

      $form['container']['table'][$index][$webform['title']] = [
        '#markup' => $webform['title'],
      ];

      $form['container']['table'][$index][$webform['number']] = [
        '#markup' => $webform['number'],
      ];

      $form['container']['table'][$index]['delete'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
        '#name' => 'delete_row_' . $index,
        '#submit' => ['::delete_row'],
        '#limit_validation_errors' => [],
        '#attributes' => ['data-row-index' => $index],
      ];
    }

    return $form;
  }

  private function get_webforms_with_ip_submissions(): array {
    $result = [];

    $webforms = Webform::loadMultiple();

    foreach ($webforms as $webform_id => $webform) {
      $count = 0;

      $submissions = \Drupal::entityTypeManager()
        ->getStorage('webform_submission')
        ->loadByProperties(['webform_id' => $webform_id]);

      /** @var WebformSubmissionInterface $submission */
      foreach ($submissions as $submission) {
        if (!empty($submission->get('remote_addr')->value)) {
          $count++;
        }
      }

      if ($count > 0) {
        $result[] = [
          'id' => $webform_id,
          'title' => $webform->label(),
          'number' => $count,
        ];
      }
    }

    return $result;
  }

  public function delete_row(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $row_index = $triggering_element['#attributes']['data-row-index'];

    $webforms = $form_state->get('table') ?? [];
    // todo update webform settings to desable ip.
    if (isset($webforms[$row_index])) {
      $updated = $this->update_submission_ips($webforms[$row_index]['id']);
      $webform_title = $webforms[$row_index]['title'];
      unset($webforms[$row_index]);
      $webforms = array_values($webforms);
      $form_state->set('table', $webforms);

      \Drupal::messenger()
        ->addMessage($this->t('@number IP addresses are delated from "@$webform_title" submissions.', [
          '@number' => $updated,
          '@$webform_title' => $webform_title,
        ]));
      $form_state->setRebuild();
    }
  }

  private function update_submission_ips(string $webform_id) {

    // Query pagination to avoid memory issue.
    $batch_size = 100;
    $count = \Drupal::entityQuery('webform_submission')
      ->condition('webform_id', $webform_id)
      ->accessCheck()
      ->count()
      ->execute();

    // Need to separate the two queries to have the ids instead of the count number.
    $query = \Drupal::entityQuery('webform_submission')
      ->condition('webform_id', $webform_id)->accessCheck();

    $iterations = ceil($count / $batch_size);

    $updated = 0;

    for ($i = 0; $i < $iterations; $i++) {
      $submission_ids = $query
        ->range($i * $batch_size, $batch_size)
        ->execute();

      foreach ($submission_ids as $submission_id) {
        $submission = WebformSubmission::load($submission_id);
        if ($submission && !empty($submission->get('remote_addr')->value)) {
          $submission->setSyncing(TRUE);
          $submission->set('remote_addr', NULL);
          $submission->save();
          $submission->setSyncing(FALSE);
          $updated++;
        }
      }
    }

    return $updated;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // No need to implement.
  }
}