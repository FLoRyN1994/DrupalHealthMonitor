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
use Drupal\hm_core\SiteListBuilder;
use Drupal\views\EntityViewsData;

/**
 * Defines a monitored Drupal site.
 */
#[ContentEntityType(
  id: 'hm_site',
  label: new TranslatableMarkup('Site'),
  label_collection: new TranslatableMarkup('Sites'),
  label_singular: new TranslatableMarkup('site'),
  label_plural: new TranslatableMarkup('sites'),
  entity_keys: [
    'id' => 'id',
    'label' => 'name',
    'uuid' => 'uuid',
  ],
  handlers: [
    'storage' => SqlContentEntityStorage::class,
    'views_data' => EntityViewsData::class,
    'list_builder' => SiteListBuilder::class,
    'form' => [
      'add' => HealthMonitorEntityForm::class,
      'edit' => HealthMonitorEntityForm::class,
      'delete' => ContentEntityDeleteForm::class,
    ],
    'route_provider' => ['html' => AdminHtmlRouteProvider::class],
  ],
  links: [
    'collection' => '/admin/health-monitor/sites',
    'add-form' => '/admin/health-monitor/sites/add',
    'canonical' => '/admin/health-monitor/sites/{hm_site}',
    'edit-form' => '/admin/health-monitor/sites/{hm_site}/edit',
    'delete-form' => '/admin/health-monitor/sites/{hm_site}/delete',
  ],
  admin_permission: 'administer health monitor',
  base_table: 'hm_site',
)]
final class Site extends HealthMonitorEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entityType): array {
    $fields = parent::baseFieldDefinitions($entityType);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Name'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['base_url'] = BaseFieldDefinition::create('uri')
      ->setLabel(new TranslatableMarkup('Base URL'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', ['type' => 'uri', 'weight' => 10])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['environment'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Environment'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'prod' => 'Production',
        'stage' => 'Staging',
        'dev' => 'Development',
      ])
      ->setDefaultValue('prod')
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 20])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['client'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Client'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'hm_client')
      ->setDisplayOptions('form', ['type' => 'entity_reference_autocomplete', 'weight' => 30])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['agent_key'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Agent key'))
      ->setDescription(new TranslatableMarkup('The Key module record containing the shared agent secret.'))
      ->setSetting('target_type', 'key')
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 40])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Status'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'active' => 'Active',
        'paused' => 'Paused',
        'disabled' => 'Disabled',
      ])
      ->setDefaultValue('active')
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 50])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['health_score'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Health score'))
      ->setDescription(new TranslatableMarkup('A value from 0 to 100, recalculated after analysis.'))
      ->setSetting('min', 0)
      ->setSetting('max', 100)
      ->setDefaultValue(100)
      ->setDisplayOptions('form', ['type' => 'number', 'weight' => 60])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
