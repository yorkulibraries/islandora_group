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
        // get access control field from config
        $access_control_field = Utilities::getAccessControlFieldinNode($node);

        if (!isset($access_control_field)) {
            \Drupal::messenger()->addWarning(t('The content type - <i>'.$node->bundle().'</i> does not have an access control field. 
                Please set the field for access control by <a href="/admin/config/access-control/islandora_group">clicking here</a>.'));
            return [];
        }
        
        // Get the access terms for the node.
        $options_available_media = [];
        $options_unvailable_media = [];
        foreach (\Drupal::service('islandora.utils')->getMedia($node) as $media) {
            // get access control field from config
            $access_control_field = Utilities::getAccessControlFieldinMedia($media);

            $terms = $media->get($access_control_field)->referencedEntities();
            if (count($terms) > 0) {
                $options_unvailable_media[$media->id()] = $media->getName() . "  <a href='/media/".$media->id()."/access-control' target='_blank'>Configure seperately</a>";
            }
            else {
                $options_available_media[$media->id()] = $media->getName() . "  <a href='/media/".$media->id()."/access-control' target='_blank'>Configure seperately</a>";
            }
        }

        $group_terms = Utilities::getIslandoraAccessTermsinTable();//Utilities::getIslandoraAccessTerms();
        $node_term_default = [];

        $node_terms = $node->get($access_control_field)->referencedEntities();
        if (!empty($node_terms)) {
            // no term, exist
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
        $form['#tree'] = true;

        $form['nid'] = [
            '#type' => 'hidden',
            '#value' => $node->id()
        ];
        $form['access-control'] = [
            '#type' => 'container'
        ];
        $form['access-control']['node'] = [
            '#type' => 'details',
            '#title' => $this->t($node->getTitle()),
            '#open' => TRUE,
        ];
        /*$form['access-control']['node']['access-control'] = [
            '#type' => 'checkboxes',
            '#options' => $group_terms,
            '#title' => $this->t('Select group(s) to add to: '),
            '#default_value' => $node_term_default
        ];*/

        $header = [
            'group_id' => $this->t('Group ID'),
            'group_name' => $this->t('Group Name'),
            'group_permission' => $this->t('Permission'),
            'group_member' => $this->t('Users'),
        ];

        $form['access-control']['node']['access-control'] = array(
            '#type' => 'tableselect',
            '#header' => $header,
            '#options' => $group_terms,
            '#default_value' => $node_term_default,
            '#empty' => $this->t('No users found'),
            '#prefix' => $this->t('<p><h3>Select which group(s) to add this node to:</h3></p>')
        );

        $form['access-control']['media'] = [
            '#type' => 'details',
            '#title' => $this->t("Media"),
            '#open' => TRUE,
        ];

        if (count($options_available_media) > 0) {
            $form['access-control']['media']['access-control'] = [
                '#type' => 'checkboxes',
                '#title' => $this->t('Select media to add to the above group(s)'),
                '#options' => $options_available_media,

            ];
        }

        if (count($options_unvailable_media) > 0) {
            $form['access-control']['media']['not-access-control'] = [
                '#type' => 'checkboxes',
                '#title' => $this->t('The following media already has access control: '),
                '#options' => $options_unvailable_media,
                '#default_value' => array_keys($options_unvailable_media),
                '#disabled' => true
            ];
            $form['access-control']['media']['override'] = array(
                '#type' => 'checkbox',
                '#title' => $this->t('Override access control.'),
            );
        }


        if (Utilities::isCollection($node)) {
            // check if this node is collection, redirect to confirm form
            // get children nodes
            $query = \Drupal::entityQuery('node')
                ->condition('status', 1)
                ->condition('type','islandora_object')
                ->condition('field_member_of', $node->id());
            $childrenNIDs = $query->execute();

            $options_available_children = [];
            $options_unvailable_children = [];

            $options = [];
            foreach ($childrenNIDs as $cnid) {
                $childNode = \Drupal::entityTypeManager()->getStorage('node')->load($cnid);

                // get access control field from config
                $access_control_field = Utilities::getAccessControlFieldinNode($childNode);

                $childnode_terms = $childNode->get($access_control_field)->referencedEntities();
                if (count($childnode_terms) > 0) {
                    $options_unvailable_children[$cnid] = $childNode->getTitle() . '. <a href="/node/'.$childNode->id().'/access-control" target="_blank">Configure seperately</a>';
                }
                else {
                    $options_available_children[$cnid] = $childNode->getTitle() . '. <a href="/node/'.$childNode->id().'/access-control" target="_blank">Configure seperately</a>';
                }
            }
            $form['access-control']['children-nodes'] = [
                '#type' => 'details',
                '#title' => $this->t("Children Node"),
                '#open' => TRUE,
            ];

            if (count($options_unvailable_children) > 0) {
                $form['access-control']['children-nodes']['not-access-control'] = [
                    '#type' => 'checkboxes',
                    '#title' => $this->t('The following children nodes already has access control: '),
                    '#options' => $options_unvailable_children,
                    '#default_value' => array_keys($options_unvailable_children),
                    '#disabled' => true
                ];
                $form['access-control']['children-nodes']['override'] = array(
                    '#type' => 'checkbox',
                    '#title' => $this->t('Override access control.'),
                );
            }

            if (count($options_available_children) > 0) {
                $form['access-control']['children-nodes']['access-control'] = array(
                    '#type' => 'checkboxes',
                    '#options' => $options_available_children,
                    '#title' => $this->t('Select the following children nodes:'),
                );
                $form['access-control']['children-nodes']['include-media'] = array(
                    '#type' => 'checkbox',
                    '#title' => $this->t('Include their media as well.'),
                );
            }
        }

        $form['submit'] = array(
            '#type' => 'submit',
            '#value' => 'Apply',
        );

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        // get selected group
        $selected_groups = array_values(array_filter($form_state->getValues()['access-control']['node']['access-control']));

        // set selected term id
        $targets = [];
        foreach ($selected_groups as $term_id) {
            $targets[] = ['target_id' => $term_id];
        }

        // tagging the parent node level
        $operations = array(
            array('\Drupal\islandora_group\Utilities::taggingFieldAccessTermsNode', array($form_state->getValues()['nid'], $targets)),
        );

        // get selected media
        $selected_media = array_values(array_filter($form_state->getValues()['access-control']['media']['access-control']));
        foreach ($selected_media as $media_id) {
            $media = Media::load($media_id);

            // tag the selected media of the node
            $operations[] = array('\Drupal\islandora_group\Utilities::taggingFieldAccessTermMedia', array($media, $targets));
        }

        // handle override
        if ($form_state->getValues()['access-control']['media']['override'] == true) {
            // Override the access control for already set media
            $override_media = array_values(array_filter($form_state->getValues()['access-control']['media']['not-access-control']));
            foreach ($override_media as $omid) {
                $media = Media::load($omid);
                // tag the override media
                $operations[] = array('\Drupal\islandora_group\Utilities::taggingFieldAccessTermMedia', array($media, $targets));
            }
        }

        // for children node
        $children_nodes = array_values(array_filter($form_state->getValues()['access-control']['children-nodes']['access-control']));
        foreach ($children_nodes as $cnid) {
            // get selected child node
            $child = Node::load($cnid);

            // tagging the child node
            $operations[] = array('\Drupal\islandora_group\Utilities::taggingFieldAccessTermsNode', array($cnid, $targets));

            // TODO : UI configure add child's media to group
            if ($form_state->getValues()['access-control']['children-nodes']['include-media'] == true) {
                $child_medias = \Drupal::service('islandora.utils')->getMedia($child);
                foreach ($child_medias as $child_media) {
                    $operations[] = array('\Drupal\islandora_group\Utilities::taggingFieldAccessTermMedia', array($child_media, $targets));
                }
            }
        }

        // for override children nodes
        if ($form_state->getValues()['access-control']['children-nodes']['override'] == true) {
            // Override the access control for already set media
            $override_childnodes = array_values(array_filter($form_state->getValues()['access-control']['children-nodes']['not-access-control']));
            foreach ($override_childnodes as $cnid) {
                // get selected child node
                $child = Node::load($cnid);

                // tagging the child node
                $operations[] = array('\Drupal\islandora_group\Utilities::taggingFieldAccessTermsNode', array($cnid, $targets));

                // TODO : UI configure add child's media to group
                if ($form_state->getValues()['access-control']['children-nodes']['include-media'] == true) {
                    $child_medias = \Drupal::service('islandora.utils')->getMedia($child);
                    foreach ($child_medias as $child_media) {
                        $operations[] = array('\Drupal\islandora_group\Utilities::taggingFieldAccessTermMedia', array($child_media, $targets));
                    }
                }
            }
        }

        $batch = array(
            'title' => t('Applying access control...'),
            'operations' => $operations,
            'finished' => 'islandora_group_batch_finished',
            'progress_message' => $this->t('Applied @current out of @total.'),
            'error_message' => $this->t('Access control has encountered an error.'),
        );
        batch_set($batch);
    }
}