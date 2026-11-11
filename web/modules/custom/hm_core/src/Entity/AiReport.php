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
use Drupal\hm_core\AiReportListBuilder;
use Drupal\hm_core\Form\HealthMonitorEntityForm;
use Drupal\views\EntityViewsData;

/**
 * Stores a generated client-facing maintenance report.
 */
#[ContentEntityType(
  id: 'hm_ai_report',
  label: new TranslatableMarkup('AI report'),
  label_collection: new TranslatableMarkup('AI reports'),
  label_singular: new TranslatableMarkup('AI report'),
  label_plural: new TranslatableMarkup('AI reports'),
  entity_keys: [
    'id' => 'id',
    'label' => 'title',
    'uuid' => 'uuid',
  ],
  handlers: [
    'storage' => SqlContentEntityStorage::class,
    'views_data' => EntityViewsData::class,
    'list_builder' => AiReportListBuilder::class,
    'form' => [
      'edit' => HealthMonitorEntityForm::class,
      'delete' => ContentEntityDeleteForm::class,
    ],
    'route_provider' => ['html' => AdminHtmlRouteProvider::class],
  ],
  links: [
    'collection' => '/admin/health-monitor/reports/ai',
    'canonical' => '/admin/health-monitor/reports/ai/{hm_ai_report}',
    'edit-form' => '/admin/health-monitor/reports/ai/{hm_ai_report}/edit',
    'delete-form' => '/admin/health-monitor/reports/ai/{hm_ai_report}/delete',
  ],
  admin_permission: 'administer health monitor',
  base_table: 'hm_ai_report',
)]
final class AiReport extends HealthMonitorEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entityType): array {
    $fields = parent::baseFieldDefinitions($entityType);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Title'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['site'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Site'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'hm_site')
      ->setDisplayOptions('form', ['type' => 'entity_reference_autocomplete', 'weight' => 10])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['generated_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Generated at'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', ['type' => 'timestamp', 'weight' => 20])
      ->setDisplayConfigurable('view', TRUE);

    $fields['summary_text'] = BaseFieldDefinition::create('text_long')
      ->setLabel(new TranslatableMarkup('Client summary'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', ['type' => 'text_textarea', 'weight' => 30])
      ->setDisplayOptions('view', ['type' => 'text_default', 'weight' => 30])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['release_digest'] = BaseFieldDefinition::create('text_long')
      ->setLabel(new TranslatableMarkup('Release digest'))
      ->setDisplayOptions('form', ['type' => 'text_textarea', 'weight' => 40])
      ->setDisplayOptions('view', ['type' => 'text_default', 'weight' => 40])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['d12_advice'] = BaseFieldDefinition::create('text_long')
      ->setLabel(new TranslatableMarkup('Drupal 12 advice'))
      ->setDisplayOptions('form', ['type' => 'text_textarea', 'weight' => 50])
      ->setDisplayOptions('view', ['type' => 'text_default', 'weight' => 50])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['effort_estimate'] = BaseFieldDefinition::create('decimal')
      ->setLabel(new TranslatableMarkup('Effort estimate (hours)'))
      ->setRequired(TRUE)
      ->setSetting('precision', 6)
      ->setSetting('scale', 2)
      ->setDisplayOptions('view', ['type' => 'number_decimal', 'weight' => 60])
      ->setDisplayConfigurable('view', TRUE);

    $fields['model_used'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Model used'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', ['type' => 'string', 'weight' => 70])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
