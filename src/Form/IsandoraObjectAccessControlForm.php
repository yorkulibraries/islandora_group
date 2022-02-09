<?php
namespace Drupal\islandora_group\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\islandora_group\Utilities;
use Drupal\node\NodeInterface;
use Drupal\media\Entity\Media;

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
        $groups_by_name = Utilities::arrange_group_by_name();
        $group_options = [];
        foreach ($groups_by_name as $group_id => $group) {
            $group_options[$group_id] = $group->label() . "  <a href='$group_id' target='_blank'>View permissions</a>" ;
        }



        $options_available_media = [];
        $options_unvailable_media = [];
        foreach (\Drupal::service('islandora.utils')->getMedia($node) as $media) {
            $terms = $media->get('field_access_terms')->referencedEntities();
            if (count($terms) > 0) {
                $options_unvailable_media[$media->id()] = $media->getName() . "  <a href='/media/".$media->id()."/access-control' target='_blank'>Configure</a>";
            }
            else {
                $options_available_media[$media->id()] = $media->getName() . "  <a href='/media/".$media->id()."/access-control' target='_blank'>Configure</a>";
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
            '#title' => $this->t("Node"),
            '#open' => TRUE,
        ];
        $form['access-control']['node']['access-control'] = [
            '#type' => 'checkboxes',
            '#options' => $group_terms,
            '#title' => $this->t('Adding this node to the following groups: '),
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
                '#title' => $this->t('Adding the available media to above group: '),
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
        // get the node
        $node = \Drupal\node\Entity\Node::load($form_state->getValues()['nid']);

        // get selected group
        $selected_groups = array_values(array_filter($form_state->getValues()['access-control']['node']['access-control']));

        // untag field access terms in node level first
        Utilities::untag_existed_field_access_terms($node);

        // clearing group relation with islandora object
        Utilities::clear_group_relation_by_islandora_object($node);

        $i = 0;
        foreach ($node->get('field_access_terms')->referencedEntities() as $term) {
            if ($node->get("field_access_terms")->get($i) !== null) {
                $node->get("field_access_terms")->removeItem($i);
            }
            $i++;
        }
        // set selected term id
        foreach ($selected_groups as $group_id) {
            $node->field_access_terms[] = ['target_id' => $group_id];
        }
        $node->save();

        // add this node to group
        Utilities::adding_islandora_object_to_group($node);


        $selected_media = array_values(array_filter($form_state->getValues()['access-control']['media']['access-control']));
        foreach ($selected_media as $media_id) {
            $media = Media::load($media_id);

            foreach ($selected_groups as $group_id) {
                $media->field_access_terms[] = ['target_id' => $group_id];
            }
            $media->save();
            Utilities::adding_media_only_into_group($media);
        }



    }

}