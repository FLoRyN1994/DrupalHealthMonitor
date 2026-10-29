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
use Drupal\hm_core\FindingListBuilder;
use Drupal\hm_core\Form\HealthMonitorEntityForm;
use Drupal\views\EntityViewsData;

/**
 * Defines an analyzed issue for a monitored site.
 */
#[ContentEntityType(
  id: 'hm_finding',
  label: new TranslatableMarkup('Finding'),
  label_collection: new TranslatableMarkup('Findings'),
  label_singular: new TranslatableMarkup('finding'),
  label_plural: new TranslatableMarkup('findings'),
  entity_keys: [
    'id' => 'id',
    'label' => 'project_name',
    'uuid' => 'uuid',
  ],
  handlers: [
    'storage' => SqlContentEntityStorage::class,
    'views_data' => EntityViewsData::class,
    'list_builder' => FindingListBuilder::class,
    'form' => [
      'add' => HealthMonitorEntityForm::class,
      'edit' => HealthMonitorEntityForm::class,
      'delete' => ContentEntityDeleteForm::class,
    ],
    'route_provider' => ['html' => AdminHtmlRouteProvider::class],
  ],
  links: [
    'collection' => '/admin/health-monitor/findings',
    'add-form' => '/admin/health-monitor/findings/add',
    'canonical' => '/admin/health-monitor/findings/{hm_finding}',
    'edit-form' => '/admin/health-monitor/findings/{hm_finding}/edit',
    'delete-form' => '/admin/health-monitor/findings/{hm_finding}/delete',
  ],
  admin_permission: 'administer health monitor',
  base_table: 'hm_finding',
)]
final class Finding extends HealthMonitorEntityBase {

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

    $fields['project_name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Project name'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 10])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Type'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'security' => 'Security update',
        'update' => 'Regular update',
        'unsupported' => 'Unsupported project',
        'd12_blocker' => 'Drupal 12 blocker',
      ])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 20])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['severity'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Severity'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'critical' => 'Critical',
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
        'info' => 'Informational',
      ])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 30])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['current_version'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Current version'))
      ->setSetting('max_length', 100)
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 40])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['recommended_version'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Recommended version'))
      ->setSetting('max_length', 100)
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 50])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['resolved'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Resolved'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', ['type' => 'boolean_checkbox', 'weight' => 60])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
