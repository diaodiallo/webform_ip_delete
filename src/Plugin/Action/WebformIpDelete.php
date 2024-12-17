<?php

namespace Drupal\webform_ip_delete\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an action to delete collected IP addresses..
 *
 * @Action(
 *   id = "webform_ip_delete_action",
 *   label = @Translation("Helps delete collected IP addresses"),
 *   type = "webform"
 * )
 */
class WebformIpDelete extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    // Ensure we are working with a webform entity.
    if ($entity instanceof Webform) {
      $webform_id = $entity->id();

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

        foreach ($submission_ids as $key => $submission_id) {
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

      if ($updated > 0) {
        \Drupal::messenger()
          ->addMessage($this->t('@number IP addresses are delated from "@$webform_title" submissions.', [
            '@number' => $updated,
            '@$webform_title' => $entity->label(),
          ]));
      }
      else {
        \Drupal::messenger()
          ->addMessage($this->t('There are no collected IP addresses for "@$webform_title".', [
            '@$webform_title' => $entity->label(),
          ]));
      }

    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    // Check if the user has permission to administer webforms.
    $access = $object->access('update', $account, TRUE);

    return $return_as_object ? $access : $access->isAllowed();
  }

}