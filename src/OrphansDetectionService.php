<?php

namespace Drupal\paragraph_orphans;

 
use Drupal\Core\Entity\EntityInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;


class OrphansDetectionService {

  private $entityFieldManager = null;

  public function __construct(EntityFieldManagerInterface $entityFieldManager) {
    $this->entityFieldManager = $entityFieldManager;
  }

  protected function getParagraphFieldsOfEntity(EntityInterface $entity) {
    $entityTypeId = $entity->getEntityTypeId();
    $entityBundle = $entity->bundle();
    /** @var $entityFieldManager \Drupal\Core\Entity\EntityFieldManagerInterface */
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $fields = $entityFieldManager->getFieldDefinitions($entityTypeId, $entityBundle);
    return array_filter($fields, function($field) {
      return $field->getSetting('target_type') == 'paragraph';
    });
  }

  public function paragraphIsOrphan(ParagraphInterface $paragraph) {
    /** @var $parent ParagraphInterface */
    $parent = $paragraph->getParentEntity();
    $parentEntityType = $parent->getEntityType();
    $parentKeys = $parentEntityType->getKeys();
    $query = \Drupal::entityQuery($parent->getEntityTypeId());
    $or_group = $query->orConditionGroup();
    $paragraph_fields = $this->getParagraphFieldsOfEntity($parent);
    foreach ($paragraph_fields as $field) {
      $or_group->condition($field->getName() . '.target_id', $paragraph->id());
    }
    $result = $query
      ->condition($or_group)
      ->condition($parentKeys['id'], $parent->id())
      ->execute();

    if (count($result) > 0) {
      if ($parent->getEntityTypeId() == 'paragraph') {
        // The paragraph is referenced by another paragraph. Check if the parent
        // is referenced.
        return $this->paragraphIsOrphan($parent);
      }
      else {
        // The paragraph is referenced by a non-paragraph entity. Its no orphan.
        return FALSE;
      }
    }
    else {
      // We did not find any references to $paragraph. This is an orphan.
      return TRUE;
    }
  }
}
