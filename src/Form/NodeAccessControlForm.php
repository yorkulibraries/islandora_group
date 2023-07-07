<?php

namespace Drupal\islandora_group\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\islandora_group\Utilities;
use Drupal\node\NodeInterface;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;

class NodeAccessControlForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_object_access_control_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    // Get access control field from config.
    $access_control_field = Utilities::getAccessControlFieldinNode($node);

    // Make sure the selected access control field valid.
    if (empty($access_control_field) || !$node->hasField($access_control_field)) {
      \Drupal::messenger()->addWarning(t('The content type - <i>' . $node->bundle() . '</i> does not have an access control field. Please set the field for access control by <a href="/admin/config/access-control/islandora_group">clicking here</a>.'));
      return [];
    }

    // Get the access terms for the node.
    $options_available_media = [];
    $options_unvailable_media = [];

    $medias = [];
    if (!empty(\Drupal::hasService('islandora.utils'))) {
      $medias = \Drupal::service('islandora.utils')->getMedia($node);
    }
    $other_medias = Utilities::getMedia($node);
    if (count($other_medias) > 0) {
      $medias = array_merge($medias, $other_medias);
    }
    foreach ($medias as $media) {
      $groups = implode(", ", Utilities::getGroupsByMedia($media->id()));
      // Get access control field from config.
      if (isset($media)) {
        $access_control_field = Utilities::getAccessControlFieldinMedia($media);
        if (isset($access_control_field)) {
          $terms = $media->get($access_control_field)->referencedEntities();
          if (count($terms) > 0) {
            $options_unvailable_media[$media->id()] = [
              'media_title' => $this->t('<a href="/media/' . $media->id() . '" target="_blank">'.$media->getName() . '</a>'),
              'groups' => $groups,
              'media_permission' => $this->t('<a href="/media/' . $media->id() . '/access-control" target="_blank">Configuration</a>'),
            ];
          }
          else {
            $options_available_media[$media->id()] = [
              'media_title' => $this->t('<a href="/media/' . $media->id() . '" target="_blank">' . $media->getName() . '</a>'),
              'groups' => $groups,
              'media_permission' => $this->t('<a href="/media/' . $media->id() . '/access-control" target="_blank">Configuration</a>'),
            ];
          }
        }
      }
    }

    $access_control_field = Utilities::getAccessControlFieldinNode($node);
    $group_terms = Utilities::getIslandoraAccessTermsinTable();
    $node_term_default = [];

    $node_terms = $node->get($access_control_field)->referencedEntities();
    if (!empty($node_terms)) {
      // No term, exist.
      foreach ($node_terms as $nt) {
        if (in_array($nt->id(), array_keys($group_terms))) {
          $node_term_default[$nt->id()] = TRUE;
        }
        else {
          $node_term_default[$nt->id()] = FALSE;
        }
      }
    }

    $form = [];
    $form['#title'] = t($node->getTitle() . ' Repository Item Access Control');
    $form['#tree'] = TRUE;

    $form['nid'] = [
      '#type' => 'hidden',
      '#value' => $node->id(),
    ];

    $form['access-control'] = [
      '#type' => 'container',
    ];

    $form['access-control']['node'] = [
      '#type' => 'details',
      '#title' => $this->t($node->getTitle()),
      '#open' => TRUE,
    ];

    $header = [
      'group_name' => $this->t('Group'),
      'group_permission' => $this->t('Group Permission'),
      'group_member' => $this->t('Accounts can access'),
    ];

    $form['access-control']['node']['access-control'] = [
      '#id' => 'group-node-table',
      '#attributes' => ['class' => ['stripe']],
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $group_terms,
      '#default_value' => $node_term_default,
      '#empty' => $this->t('No users found'),
      '#prefix' => $this->t('<p><h3>Select which group(s) to add this node to:</h3></p><div class="group-table">'),
      '#suffix' => $this->t("</div>"),
    ];

    $form['access-control']['media'] = [
      '#type' => 'details',
      '#title' => $this->t("Media"),
      '#open' => TRUE,
    ];

    $header = [
      'media_title' => $this->t('Media'),
      'groups' => $this->t("In Group(s)"),
      'media_permission' => $this->t('Access Control'),
    ];

    if (count($options_available_media) > 0) {
      $form['access-control']['media']['access-control'] = [
        '#id' => 'group-media-has-access-control-table',
        '#attributes' => ['class' => ['stripe']],
        '#type' => 'tableselect',
        '#title' => $this->t('Select media to add to the above group(s)'),
        '#options' => $options_available_media,
        '#header' => $header,
        '#prefix' => $this->t('<p><h3>Select media to add to the above group(s):</h3></p><div class="group-table">'),
        '#suffix' => $this->t("</div>"),
      ];
    }

    if (count($options_unvailable_media) > 0) {
      $form['access-control']['media']['not-access-control'] = [
        '#id' => 'group-media-has-no-access-control-table',
        '#attributes' => ['class' => ['stripe']],
        '#type' => 'table',
        '#title' => $this->t('The following media already has access control: '),
        '#rows' => $options_unvailable_media,
        '#default_value' => array_keys($options_unvailable_media),
        '#disabled' => TRUE,
        '#header' => $header,
        '#prefix' => $this->t('<p><h3>The following already have access control, please review before override them:</h3></p><div class="group-table">'),
        '#suffix' => $this->t("</div>"),
      ];

      $form['access-control']['media']['<strong>Override</strong>'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('<strong>Override</strong>'),
        '#description' => $this->t("To have the same access control with this node"),
      ];
    }

    if (Utilities::isCollection($node) > 0) {
      // Check if this node is collection, redirect to confirm form.
      // Get children nodes.
      // Get children nodes by field_member_of.
      $member_of_NIDs = [];
      $query = \Drupal::entityQuery('node')
        ->condition('status', 1)
        ->condition('field_member_of', $node->id());
      $member_of_NIDs = $query->execute();

      // Merged them.
      $childrenNids = array_merge($member_of_NIDs);

      $options_available_children = [];
      $options_unvailable_children = [];

      $options = [];

      foreach ($childrenNids as $cnid) {
        $childNode = \Drupal::entityTypeManager()->getStorage('node')->load($cnid);

        // Get access control field from config.
        $access_control_field = Utilities::getAccessControlFieldinNode($childNode);

        $groups = implode(", ", Utilities::getGroupsByNode($cnid));
        $childnode_terms = $childNode->get($access_control_field)->referencedEntities();
        if (count($childnode_terms) > 0) {
          $options_unvailable_children[$cnid] = [
            'node_title' => $this->t('<a href="/node/' . $childNode->id() . '" target="_blank">' . $childNode->getTitle() . '</a>'),
            'groups' => $groups,
            'node_permission' => $this->t('<a href="/node/' . $childNode->id() . '/access-control" target="_blank">Configuration</a>'),
          ];
        }
        else {
          $options_available_children[$cnid] = [
            'node_title' => $this->t('<a href="/node/' . $childNode->id().'" target="_blank">' . $childNode->getTitle() . '</a>'),
            'groups' => $groups,
            'node_permission' => $this->t('<a href="/node/' . $childNode->id() . '/access-control" target="_blank">Configuration</a>'),
          ];
        }
      }

      $form['access-control']['children-nodes'] = [
        '#type' => 'details',
        '#title' => $this->t("Children Nodes"),
        '#open' => TRUE,
      ];

      $header = [
        'node_title' => $this->t('Children Nodes'),
        'groups' => $this->t('In Group(s)'),
        'node_permission' => $this->t('Access Control'),
      ];

      if (count($options_available_children) > 0) {
        $form['access-control']['children-nodes']['access-control'] = [
          '#id' => 'group-children-nodes-has-no-access-control-table',
          '#attributes' => ['class' => ['stripe']],
          '#type' => 'tableselect',
          '#header' => $header,
          '#options' => $options_available_children,
          '#prefix' => $this->t('<p><h3>Select the following children nodes:</h3></p><div class="group-table">'),
          '#suffix' => $this->t("</div>"),
        ];
      }

      if (count($options_unvailable_children) > 0) {
        $form['access-control']['children-nodes']['not-access-control'] = [
          '#id' => 'group-children-nodes-has-access-control-table',
          '#attributes' => ['class' => ['stripe']],
          '#type' => 'table',
          '#header' => $header,
          '#rows' => $options_unvailable_children,
          '#prefix' => $this->t('<p><h3>The following already have access control, please review before override them:</h3></p><div class="group-table">'),
          '#suffix' => $this->t("</div>"),
          '#default_value' => array_keys($options_unvailable_children),
          '#disabled' => TRUE,
        ];

        $form['access-control']['children-nodes']['<strong>Override</strong>'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('<strong>Override</strong>'),
          '#description' => $this->t("To have the same access control with this node"),
        ];
      }
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Apply',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get selected group.
    $selected_groups = array_values(array_filter($form_state->getValues()['access-control']['node']['access-control']));

    // Set selected term id.
    $targets = [];
    foreach ($selected_groups as $term_id) {
      $targets[] = ['target_id' => $term_id];
    }

    // Tagging the parent node level.
    $operations = [
      [
        '\Drupal\islandora_group\Utilities::taggingFieldAccessTermsNode',
        [$form_state->getValues()['nid'], $targets],
      ],
    ];

    // Get selected media.
    if (isset($form_state->getValues()['access-control']['media']['access-control'])) {
      $selected_media = array_values(array_filter($form_state->getValues()['access-control']['media']['access-control']));
      foreach ($selected_media as $media_id) {
        $media = Media::load($media_id);

        // Tag the selected media of the node.
        $operations[] = [
          '\Drupal\islandora_group\Utilities::taggingFieldAccessTermMedia',
          [$media, $targets],
        ];
      }
    }

    // Handle override.
    if ($form_state->getValues()['access-control']['media']['<strong>Override</strong>'] == TRUE) {
      // Override the access control for already set media.
      $override_media = array_values(array_filter($form_state->getValues()['access-control']['media']['not-access-control']));
      foreach ($override_media as $omid) {
        $media = Media::load($omid);
        // Tag the override media.
        $operations[] = [
          '\Drupal\islandora_group\Utilities::taggingFieldAccessTermMedia',
          [$media, $targets],
        ];
      }
    }

    // For children node.
    if (isset($form_state->getValues()['access-control']['children-nodes']['access-control'])) {
      $children_nodes = array_values(array_filter($form_state->getValues()['access-control']['children-nodes']['access-control']));
      foreach ($children_nodes as $cnid) {
        // Get selected child node.
        $child = Node::load($cnid);

        // Tagging the child node.
        $operations[] = [
          '\Drupal\islandora_group\Utilities::taggingFieldAccessTermsNode',
          [$cnid, $targets],
        ];

        // TODO : UI configure add child's media to group.
        $child_medias = [];
        if (!empty(\Drupal::hasService('islandora.utils'))) {
          $child_medias = \Drupal::service('islandora.utils')->getMedia($child);
        }
        $other_medias = Utilities::getMedia($child);
        if (count($other_medias) > 0) {
          $child_medias = array_merge($child_medias, $other_medias);
        }

        foreach ($child_medias as $child_media) {
          $operations[] = [
            '\Drupal\islandora_group\Utilities::taggingFieldAccessTermMedia',
            [$child_media, $targets],
          ];
        }
      }
    }

    // For override children nodes.
    if ($form_state->getValues()['access-control']['children-nodes']['<strong>Override</strong>'] == TRUE) {
      // Override the access control for already set media.
      $override_childnodes = array_values(array_filter($form_state->getValues()['access-control']['children-nodes']['not-access-control']));
      foreach ($override_childnodes as $cnid) {
        // Get selected child node.
        $child = Node::load($cnid);

        // Tagging the child node.
        $operations[] = [
          '\Drupal\islandora_group\Utilities::taggingFieldAccessTermsNode',
          [$cnid, $targets],
        ];

        $child_medias = [];
        if (!empty(\Drupal::hasService('islandora.utils'))) {
          $child_medias = \Drupal::service('islandora.utils')->getMedia($child);
        }
        $other_medias = Utilities::getMedia($child);
        if (count($other_medias) > 0) {
          $child_medias = array_merge($child_medias, $other_medias);
        }
        foreach ($child_medias as $child_media) {
          $operations[] = [
            '\Drupal\islandora_group\Utilities::taggingFieldAccessTermMedia',
            [$child_media, $targets],
          ];
        }
      }
    }

    $batch = [
      'title' => t('Applying access control...'),
      'operations' => $operations,
      'finished' => 'islandora_group_batch_finished',
      'progress_message' => $this->t('Applied @current out of @total.'),
      'error_message' => $this->t('Access control has encountered an error.'),
    ];
    batch_set($batch);
  }

}
