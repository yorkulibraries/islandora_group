<?php
namespace Drupal\islandora_group\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\islandora_group\Utilities;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\media\Entity\Media;

class MediaAccessControlForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'media_access_control_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, MediaInterface $media = NULL) {
        // Get the access terms for the node.
        $group_terms = Utilities::getIslandoraAccessTermsinTable();//Utilities::getIslandoraAccessTerms();
        $node_term_default = [];
        $node_terms = $media->get('field_access_terms')->referencedEntities();
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
        $form['#title'] = t($media->getName() . ' Media Access Control');
        $form['#tree'] = true;

        $form['media_id'] = [
            '#type' => 'hidden',
            '#value' => $media->id()
        ];
        $form['access-control'] = [
            '#type' => 'container'
        ];
        $form['access-control']['media'] = [
            '#type' => 'details',
            '#title' => $this->t("Access control with Groups"),
            '#open' => TRUE,
        ];

        $header = [
            'group_id' => $this->t('Group ID'),
            'group_name' => $this->t('Group Name'),
            'group_permission' => $this->t('Permission'),
            'group_member' => $this->t('Users'),
        ];

        $form['access-control']['media']['access-control'] = array(
            '#type' => 'tableselect',
            '#header' => $header,
            '#options' => $group_terms,
            '#default_value' => $node_term_default,
            '#empty' => $this->t('No users found'),
        );

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
        // selected group
        $selected_groups = array_values(array_filter($form_state->getValues()['access-control']['media']['access-control']));

        // media
        $media = Media::load($form_state->getValues()['media_id']);

        // untag field access terms in node level first
        Utilities::untag_existed_field_access_terms($media);

        // clear group relation with media
        Utilities::clear_group_relation_by_entity($media);

        if (count($selected_groups) > 0) {
            $targets = [];
            foreach ($selected_groups as $term_id) {
                $targets[] = ['target_id' => $term_id];
            }
            if (count($targets) > 0) {
                $media->set('field_access_terms', $targets);
                $media->save();
            }
            // add media to selected group
            Utilities::adding_media_only_into_group($media);
        }


    }

}