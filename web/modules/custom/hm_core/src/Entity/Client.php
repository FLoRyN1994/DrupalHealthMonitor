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
use Drupal\hm_core\ClientListBuilder;
use Drupal\hm_core\Form\HealthMonitorEntityForm;
use Drupal\views\EntityViewsData;

/**
 * Defines a client that owns monitored sites.
 */
#[ContentEntityType(
  id: 'hm_client',
  label: new TranslatableMarkup('Client'),
  label_collection: new TranslatableMarkup('Clients'),
  label_singular: new TranslatableMarkup('client'),
  label_plural: new TranslatableMarkup('clients'),
  entity_keys: [
    'id' => 'id',
    'label' => 'name',
    'uuid' => 'uuid',
  ],
  handlers: [
    'storage' => SqlContentEntityStorage::class,
    'views_data' => EntityViewsData::class,
    'list_builder' => ClientListBuilder::class,
    'form' => [
      'add' => HealthMonitorEntityForm::class,
      'edit' => HealthMonitorEntityForm::class,
      'delete' => ContentEntityDeleteForm::class,
    ],
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
  ],
  links: [
    'collection' => '/admin/health-monitor/clients',
    'add-form' => '/admin/health-monitor/clients/add',
    'canonical' => '/admin/health-monitor/clients/{hm_client}',
    'edit-form' => '/admin/health-monitor/clients/{hm_client}/edit',
    'delete-form' => '/admin/health-monitor/clients/{hm_client}/delete',
  ],
  admin_permission: 'administer health monitor',
  base_table: 'hm_client',
)]
final class Client extends HealthMonitorEntityBase {

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

    $fields['contact_email'] = BaseFieldDefinition::create('email')
      ->setLabel(new TranslatableMarkup('Contact email'))
      ->setDisplayOptions('form', ['type' => 'email_default', 'weight' => 10])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['retainer_tier'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Retainer tier'))
      ->setSetting('max_length', 100)
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 20])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Notes'))
      ->setDisplayOptions('form', ['type' => 'string_textarea', 'weight' => 30])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
