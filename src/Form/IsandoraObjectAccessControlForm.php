<?php
namespace Drupal\islandora_group\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\islandora_group\Utilities;
use Drupal\node\NodeInterface;
use Drupal\media\Entity\Media;
use Drupal\taxonomy\Entity\Term;

class IsandoraObjectAccessControlForm extends FormBase {

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
        // Get the access terms for the node.
        $options_available_media = [];
        $options_unvailable_media = [];
        foreach (\Drupal::service('islandora.utils')->getMedia($node) as $media) {
            $terms = $media->get('field_access_terms')->referencedEntities();
            if (count($terms) > 0) {
                $options_unvailable_media[$media->id()] = $media->getName() . "  <a href='/media/".$media->id()."/access-control' target='_blank'>Configure seperately</a>";
            }
            else {
                $options_available_media[$media->id()] = $media->getName() . "  <a href='/media/".$media->id()."/access-control' target='_blank'>Configure seperately</a>";
            }
        }

        $group_terms = Utilities::getIslandoraAccessTerms();
        $node_term_default = [];
        $node_terms = $node->get('field_access_terms')->referencedEntities();
        if (!empty($node_terms)) {
            // no term, exist
            foreach ($node_terms as $nt) {
                $node_term_default[] = $nt->id();
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
        $form['access-control']['node']['access-control'] = [
            '#type' => 'checkboxes',
            '#options' => $group_terms,
            '#title' => $this->t('Select group(s) to add to: '),
            '#default_value' => $node_term_default
        ];

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

                $childnode_terms = $childNode->get('field_access_terms')->referencedEntities();
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
            }

            if (count($options_available_children) > 0) {
                $form['access-control']['children-nodes']['include-meida'] = array(
                    '#type' => 'checkbox',
                    '#title' => $this->t('Include their media as well.'),
                );

                $form['access-control']['children-nodes']['access-control'] = array(
                    '#type' => 'checkboxes',
                    '#options' => $options_available_children,
                    '#title' => $this->t('Select the following children nodes:'),
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
    /*public function validateForm(array &$form, FormStateInterface $form_state) {
        $selected_groups = array_values(array_filter($form_state->getValues()['access-control']['node']['access-control']));
        if (isset($selected_groups) && count($selected_groups) <= 0) {
            $form_state->setErrorByName('access-control', $this->t('Please select the group(s) to apply for this node and its content'));
        }
    }*/

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        // get the node
        $node = \Drupal\node\Entity\Node::load($form_state->getValues()['nid']);

        // get selected group
        $selected_groups = array_values(array_filter($form_state->getValues()['access-control']['node']['access-control']));

        // 1. clear field_access_terms in media level
        Utilities::untag_existed_field_access_terms($node);

        // 2. clearing group relation with islandora object
        Utilities::clear_group_relation_by_entity($node);

        // set selected term id
        $targets = [];
        foreach ($selected_groups as $term_id) {
            $targets[] = ['target_id' => $term_id];
        }
        if (count($targets) > 0) {
            $node->set('field_access_terms', $targets);
            $node->save();
        }
        // add this node to group
        //Utilities::adding_islandora_object_to_group($node);

        // get selected media
        $selected_media = array_values(array_filter($form_state->getValues()['access-control']['media']['access-control']));
        foreach ($selected_media as $media_id) {
            $media = Media::load($media_id);

            // clear field_access_terms in media level
            Utilities::untag_existed_field_access_terms($media);

            if (count($targets) > 0) {
                $media->set('field_access_terms', $targets);
                $media->save();
            }
            //Utilities::adding_media_only_into_group($media);
        }


        $children_nodes = array_values(array_filter($form_state->getValues()['access-control']['children-nodes']['access-control']));
        foreach ($children_nodes as $cnid) {
            // get selected child node
            $child = \Drupal\node\Entity\Node::load($cnid);

            // clearing group relation with islandora object
            Utilities::clear_group_relation_by_entity($child);

            // clear field_access_terms in media level
            Utilities::untag_existed_field_access_terms($child);

            // set selected term id
            if (count($targets) > 0) {
                $child->set('field_access_terms', $targets);
                $child->save();
            }
            // add this node to group
            //Utilities::adding_islandora_object_to_group($child);

            // TODO : UI configure add child's media to group
            if ($form_state->getValues()['access-control']['children-nodes']['include-meida'] == true) {
                $child_medias = \Drupal::service('islandora.utils')->getMedia($child);
                foreach ($child_medias as $child_media) {

                    // clear field_access_terms in media level
                    Utilities::untag_existed_field_access_terms($child_media);

                    if (count($targets) > 0) {
                        $child_media->set('field_access_terms', $targets);
                        $child_media->save();
                    }
                    //Utilities::adding_media_only_into_group($child_media);
                }
            }
        }
    }

}