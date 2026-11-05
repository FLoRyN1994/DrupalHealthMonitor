<?php

declare(strict_types=1);

namespace Drupal\hm_core\Analysis;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hm_core\Entity\Finding;

/**
 * Upserts current findings and resolves findings disproved by new evidence.
 */
final class FindingReconciler {

  /**
   * Constructs the finding reconciler.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Reconciles all deterministic finding types for one analyzed project.
   *
   * @param int $siteId
   *   Monitored site entity ID.
   * @param string $projectName
   *   Drupal.org project machine name.
   * @param array<string, \Drupal\hm_core\Analysis\FindingCandidate> $candidates
   *   Current candidates keyed by finding type.
   * @param string $source
   *   Evidence source machine name.
   */
  public function reconcile(
    int $siteId,
    string $projectName,
    array $candidates,
    string $source = 'release_data',
  ): void {
    $storage = $this->entityTypeManager->getStorage('hm_finding');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('site', $siteId)
      ->condition('project_name', $projectName)
      ->condition('source', $source)
      ->execute();
    $existing_by_type = [];
    foreach ($storage->loadMultiple($ids) as $entity) {
      if ($entity instanceof Finding) {
        $existing_by_type[(string) $entity->get('type')->value][] = $entity;
      }
    }

    foreach (['security', 'update', 'unsupported', 'd12_blocker'] as $type) {
      $matches = $existing_by_type[$type] ?? [];
      $candidate = $candidates[$type] ?? NULL;
      if ($candidate === NULL) {
        $this->setResolved($matches, TRUE);
        continue;
      }

      $finding = array_shift($matches);
      if (!$finding instanceof Finding) {
        $finding = $storage->create([
          'site' => $siteId,
          'project_name' => $projectName,
          'type' => $candidate->type,
          'source' => $source,
        ]);
        if (!$finding instanceof Finding) {
          throw new \UnexpectedValueException('Finding storage returned an invalid entity.');
        }
      }
      $finding->set('severity', $candidate->severity);
      $finding->set('current_version', $candidate->currentVersion);
      $finding->set('recommended_version', $candidate->recommendedVersion);
      $finding->set('resolved', FALSE);
      $finding->save();
      $this->setResolved($matches, TRUE);
    }
  }

  /**
   * Resolves findings for projects absent from a complete newer snapshot.
   *
   * @param int $siteId
   *   Monitored site entity ID.
   * @param string[] $installedProjects
   *   Installed drupal.org project machine names.
   */
  public function resolveRemovedProjects(
    int $siteId,
    array $installedProjects,
  ): void {
    $storage = $this->entityTypeManager->getStorage('hm_finding');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('site', $siteId)
      ->condition('resolved', FALSE)
      ->condition('source', 'release_data');
    if ($installedProjects !== []) {
      $query->condition('project_name', $installedProjects, 'NOT IN');
    }
    $entities = $storage->loadMultiple($query->execute());
    $this->setResolved(array_values($entities), TRUE);
  }

  /**
   * Changes resolved state only when necessary.
   *
   * @param array<int, mixed> $entities
   *   Potential Finding entities.
   * @param bool $resolved
   *   New resolved state.
   */
  private function setResolved(array $entities, bool $resolved): void {
    foreach ($entities as $entity) {
      if ($entity instanceof Finding
        && (bool) $entity->get('resolved')->value !== $resolved
      ) {
        $entity->set('resolved', $resolved)->save();
      }
    }
  }

}
