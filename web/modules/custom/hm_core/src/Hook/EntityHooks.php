<?php

declare(strict_types=1);

namespace Drupal\hm_core\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\hm_core\SiteCheckScheduler;

/**
 * Keeps Health Monitor entity relationships consistent.
 */
final class EntityHooks {

  /**
   * Entity types that belong to exactly one monitored site.
   */
  private const SITE_DEPENDENT_TYPES = ['hm_finding', 'hm_snapshot', 'hm_ai_report'];

  /**
   * Entities deleted per storage call, bounding memory use.
   */
  private const DELETE_CHUNK = 100;

  /**
   * Constructs the entity hooks.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly SiteCheckScheduler $siteCheckScheduler,
  ) {}

  /**
   * Deletes data that only has meaning for a deleted monitored site.
   *
   * Without this, findings, snapshots, reports, and uptime rows would remain
   * with a dangling site reference, inflating fleet totals and storage.
   */
  #[Hook('hm_site_delete')]
  public function siteDelete(EntityInterface $site): void {
    $siteId = (int) $site->id();
    foreach (self::SITE_DEPENDENT_TYPES as $entityTypeId) {
      $storage = $this->entityTypeManager->getStorage($entityTypeId);
      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('site', $siteId)
        ->execute();
      foreach (array_chunk($ids, self::DELETE_CHUNK) as $chunk) {
        $storage->delete($storage->loadMultiple($chunk));
      }
    }

    $this->database->delete('hm_uptime_check')
      ->condition('site_id', $siteId)
      ->execute();
    $this->siteCheckScheduler->markProcessed($siteId);
  }

  /**
   * Prevents deleting a client that still owns monitored sites.
   *
   * A Site requires its Client, so deleting the client would leave sites
   * with a broken required reference. Operators must reassign or delete the
   * sites first.
   */
  #[Hook('hm_client_access')]
  public function clientAccess(EntityInterface $client, string $operation, AccountInterface $account): AccessResultInterface {
    if ($operation !== 'delete' || $client->isNew()) {
      return AccessResult::neutral();
    }

    $siteCount = (int) $this->entityTypeManager->getStorage('hm_site')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('client', $client->id())
      ->count()
      ->execute();

    return AccessResult::forbiddenIf(
      $siteCount > 0,
      'The client still has monitored sites. Reassign or delete them first.',
    )->addCacheTags(['hm_site_list'])->addCacheableDependency($client);
  }

}
