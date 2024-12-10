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
      $form_state->set('table', $this->getWebformsWithIpSubmissions());
    }

    $webforms = $form_state->get('table');

    $form['container'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Webforms'),
    ];

    $form['container']['introduction'] = [
      '#markup' => $this->t('<p>Helps delete collected IP addresses. When you delete IPs for a form the module will also disable the tracking of user IP address</p>'),
    ];

    $form['container']['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Title'),
        $this->t('Number of collected IPs'),
        $this->t('Action'),
      ],
    ];

    foreach ($webforms as $webform_id => $webform) {
      $form['container']['table'][$webform_id][$webform['title']] = [
        '#markup' => $webform['title'],
      ];

      $form['container']['table'][$webform_id][$webform['number']] = [
        '#markup' => $webform['number'],
      ];

      $form['container']['table'][$webform_id]['delete'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
        '#name' => $webform_id,
        '#submit' => ['::deleteRow'],
        '#limit_validation_errors' => [],
        '#webform_id' => $webform_id,
      ];
    }

    return $form;
  }

  /**
   * Retrieves a list of webforms with submissions containing IP addresses.
   *
   * @return array
   *   An associative array where each key is a webform ID, and each value is a
   *   sub-array containing:
   *   - 'title': The title of the webform.
   *   - 'number': The number of submissions with IP addresses.
   */
  private function getWebformsWithIpSubmissions(): array {
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
        $result[$webform_id] = [
          'title' => $webform->label(),
          'number' => $count,
        ];
      }
    }

    return $result;
  }

  /**
   * Removes a webform row in the table and updates the associated IP addresses.
   *
   * This method handles the deletion of a webform from the table stored in
   * the form state. It also updates the IP addresses associated with the
   * webform's submissions, disables user IP tracking for the webform,
   * and displays a confirmation message to the user.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form, including user input and metadata.
   */
  public function deleteRow(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $webform_id = $triggering_element['#webform_id'];

    $webforms = $form_state->get('table') ?? [];
    if (isset($webforms[$webform_id])) {
      $updated = $this->updateSubmissionIps($webform_id);
      if ($updated > 0) {
        $title = $webforms[$webform_id]['title'];
        unset($webforms[$webform_id]);
        $form_state->set('table', $webforms);

        \Drupal::messenger()
          ->addMessage($this->t('@number IP addresses are delated from "@$webform_title" submissions.', [
            '@number' => $updated,
            '@$webform_title' => $title,
          ]));
        $form_state->setRebuild();

        $this->disableTheTrackingOfUserIpAddress($webform_id);
      }
    }
  }

  /**
   * Removes IP addresses from submissions of a specific webform.
   *
   * This function processes submissions of the given webform in batches to
   * avoid memory issues. It iterates through the submissions, checks for
   * IP addresses, and clears the 'remote_addr' field if an IP is present.
   *
   * @param string $webform_id
   *   The ID of the webform whose submissions will be processed.
   *
   * @return int
   *   The number of submissions updated (IP addresses removed).
   */
  private function updateSubmissionIps(string $webform_id): int {

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

  /**
   * Disables the tracking of user IP addresses for a specific webform.
   *
   * This function modifies the settings of the specified webform to disable
   * the tracking of remote IP addresses, if it is not already disabled.
   *
   * @param string $webform_id
   *   The ID of the webform for which IP address tracking will be disabled.
   */
  private function disableTheTrackingOfUserIpAddress(string $webform_id) {

    $webform = \Drupal::entityTypeManager()
      ->getStorage('webform')
      ->load($webform_id);

    if ($webform) {
      $settings = $webform->getSettings();
      if (!$settings['form_disable_remote_addr']) {
        $settings['form_disable_remote_addr'] = TRUE;
        $webform->setSettings($settings);
        $webform->save();
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // No need to implement.
  }
}