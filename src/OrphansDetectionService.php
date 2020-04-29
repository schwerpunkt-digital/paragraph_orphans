<?php

namespace Drupal\paragraph_orphans;

use Drupal\Core\Entity\EntityInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;


class OrphansDetectionService {
  
  private $entityFieldManager = null;

  public __construct(EntityFieldManagerInterface $entityFieldManager) {
    $this->entityFieldManager = $entityFieldManager;
  }

  protected getParagraphFieldsOfEntity(EntityInterface $entity) {
    $entityTypeId = $entity->getEntityTypeId();
    $entityBundle = $entity->bundle();
    /** @var $entityFieldManager \Drupal\Core\Entity\EntityFieldManagerInterface */
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $fields = $entityFieldManager->getFieldDefinitions($entityTypeId, $entityBundle);
    return array_filter($fields, function($field) {
      return $field->getSetting('target_type') == 'paragraph';
    });
  }

  public paragraphIsOrphan(ParagraphInterface $paragraph) {
    /** @var $parent Paragraph */
    $parent = $paragraph->getParentEntity();
    $query = \Drupal::entityQuery('paragraph');
    $or_group = $query->orConditionGroup();
    foreach ($this->getParagraphFieldsOfEntity($parent) as $field) {
      $or_group->condition($field->getName() . '.target_id', $paragraph->id());
    }
    $result = $query
      ->condition($or_group)
      ->execute();

    if (count($result) > 0) {
      // $paragraph is referenced by its parent. Check the parent.
      return $this->paragraphIsOrphan($parent);
    }
    else {
      return FALSE;
    }
  }
}
