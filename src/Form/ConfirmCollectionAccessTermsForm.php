<?php

namespace Drupal\islandora_group\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupRelationship;
use Drupal\taxonomy\Entity\Term;

/**
 * Defines a confirmation form to confirm deletion of something by id.
 */
class ConfirmCollectionAccessTermsForm extends ConfirmFormBase {

  /**
   * ID of the item to delete.
   *
   * @var int
   */
  protected $id;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $nid = NULL) {
    $this->id = $nid;

    // Get collection node.
    $collection = \Drupal::entityTypeManager()->getStorage('node')->load($this->id);

    // Get children nodes.
    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('field_member_of', $this->id);
    $childrenNIDs = $query->execute();

    $options = [];
    foreach ($childrenNIDs as $cnid) {
      $childNode = \Drupal::entityTypeManager()->getStorage('node')->load($cnid);
      $options[$cnid] = $childNode->getTitle() . '. <a href="/node/' . $childNode->id() . '" target="_blank">Click here</a>';
    }

    // Get access control field from config.
    $access_control_field = Utilities::getAccessControlFieldinNode($collection);

    // Make sure the selected access control field valid.
    if (empty($access_control_field) || !$collection->hasField($access_control_field)) {
      return;
    }

    // Exit early if it has no assigned access terms.
    $access_terms = $collection->get($access_control_field)->referencedEntities();

    $form['groups'] = [
      '#type' => 'container',
    ];
    $form['collection'] = [
      '#type' => 'hidden',
      '#value' => $collection->id(),
    ];

    $form['groups']['#tree'] = TRUE;
    $i = 0;
    foreach ($access_terms as $term) {
      $form['groups'][$term->id()] = [
        '#type' => 'details',
        '#title' => $this->t("Group: " . $term->getName()),
        '#open' => TRUE,
      ];
      $form['groups'][$term->id()]['term-id'] = [
        '#type' => 'hidden',
        '#value' => $term->id(),
      ];

      $defaults = [];
      foreach ($childrenNIDs as $cnid) {
        // Loop through child nodes of collection check each node has access_term matched with group.
        $childNode = \Drupal::entityTypeManager()->getStorage('node')->load($cnid);

        // Get access control field from config.
        $access_control_field = Utilities::getAccessControlFieldinNode($childNode);
        if (!empty($access_control_field) && count($childNode->get($access_control_field)->referencedEntities()) > 0) {
          $childTerms = $childNode->get($access_control_field)->referencedEntities();
          foreach ($childTerms as $t) {
            if ($t->id() === $term->id()) {
              array_push($defaults, $childNode->id());
              break;
            }
          }
        }
      }
      $form['groups'][$term->id()]['select-nodes'] = [
        '#type' => 'checkboxes',
        '#options' => $options,
        '#title' => $this->t('Select the following nodes:'),
        '#default_value' => $defaults,
      ];
      $i++;
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $collection = \Drupal::entityTypeManager()->getStorage('node')->load($form_state->getValues()['collection']);
    foreach ($form_state->getValues()['groups'] as $values) {
      $termid = $values['term-id'];
      $term_name = Term::load($termid)->get('name')->value;

      foreach (array_keys($values['select-nodes']) as $nid) {
        // Get a children node of this collection.
        $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

        // Search group-node relations based on term_name.
        foreach (GroupRelationship::loadByEntity($collection) as $group_content) {
          $group = $group_content->getGroup();
          if ($group->label() === $term_name) {
            // Tagging a node.
            $this->taggingNodeWithTerm($node, $termid);
            // Add a children node to a group.
            add_entity_to_group($node);
          }
        }
      }
    }
  }

  /**
   * Tag field_access_terms of child node with a term.
   *
   * @param Drupal\node\NodeInterface $node
   *   The node.
   * @param int $termid
   *   Term id.
   *
   * @return void
   *   Nothing.
   */
  public function taggingNodeWithTerm($node, $termid) {
    // Get access control field from config.
    $access_control_field = Utilities::getAccessControlFieldinNode($node);
    if (empty($access_control_field) || !$node->hasField($access_control_field)) {
      return;
    }

    // Tag term to field_access_terms.
    $node->get($access_control_field)->appendItem([
      'target_id' => $termid,
    ]);
    $node->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return "confirm_delete_form";
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('node.add_page');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t("Adding children nodes of this Collection (ID: %id) to the following Groups:", ['%id' => $this->id]);
  }

}
