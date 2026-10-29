<?php

declare(strict_types=1);

namespace Drupal\hm_core\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hm_core\Form\HealthMonitorEntityForm;
use Drupal\hm_core\SnapshotListBuilder;
use Drupal\views\EntityViewsData;

/**
 * Defines an append-only snapshot received from a monitored site.
 */
#[ContentEntityType(
  id: 'hm_snapshot',
  label: new TranslatableMarkup('Snapshot'),
  label_collection: new TranslatableMarkup('Snapshots'),
  label_singular: new TranslatableMarkup('snapshot'),
  label_plural: new TranslatableMarkup('snapshots'),
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
  handlers: [
    'storage' => SqlContentEntityStorage::class,
    'views_data' => EntityViewsData::class,
    'list_builder' => SnapshotListBuilder::class,
    'form' => [
      'add' => HealthMonitorEntityForm::class,
      'edit' => HealthMonitorEntityForm::class,
      'delete' => ContentEntityDeleteForm::class,
    ],
    'route_provider' => ['html' => AdminHtmlRouteProvider::class],
  ],
  links: [
    'collection' => '/admin/health-monitor/snapshots',
    'add-form' => '/admin/health-monitor/snapshots/add',
    'canonical' => '/admin/health-monitor/snapshots/{hm_snapshot}',
    'edit-form' => '/admin/health-monitor/snapshots/{hm_snapshot}/edit',
    'delete-form' => '/admin/health-monitor/snapshots/{hm_snapshot}/delete',
  ],
  admin_permission: 'administer health monitor',
  base_table: 'hm_snapshot',
)]
final class Snapshot extends HealthMonitorEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entityType): array {
    $fields = parent::baseFieldDefinitions($entityType);

    $fields['site'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Site'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'hm_site')
      ->setDisplayOptions('form', ['type' => 'entity_reference_autocomplete', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['checked_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Checked at'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', ['type' => 'datetime_timestamp', 'weight' => 10])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['core_version'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Drupal core version'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 50)
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 20])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['php_version'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('PHP version'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 50)
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 30])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['modules'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Modules JSON'))
      ->setDescription(new TranslatableMarkup('The normalized module version data received from the agent.'))
      ->setDisplayOptions('form', ['type' => 'string_textarea', 'weight' => 40])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['pending_updates'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Pending database updates'))
      ->setSetting('min', 0)
      ->setDefaultValue(0)
      ->setDisplayOptions('form', ['type' => 'number', 'weight' => 50])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['raw_payload'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Raw payload'))
      ->setDescription(new TranslatableMarkup('The original JSON payload retained for diagnostics.'))
      ->setDisplayOptions('form', ['type' => 'string_textarea', 'weight' => 60])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
