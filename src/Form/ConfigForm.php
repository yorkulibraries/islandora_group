<?php

namespace Drupal\islandora_group\Form;

use Drupal\islandora_group\Utilities;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ConfigForm.
 */
class ConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      Utilities::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(Utilities::CONFIG_NAME);

    // Get list of existed groups.
    $group_types = \Drupal::service('entity_type.manager')->getStorage('group_type')->loadMultiple();

    // Get list existing taxonomy vocabulary.
    $entity = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary');
    $query = $entity->getQuery();
    $taxonomy_ids = $query->execute();

    $form['group'] = [
      '#type' => 'details',
      '#title' => $this->t("Group Configuration"),
      '#open' => TRUE,
    ];
    $form['group']['description'] = [
      '#markup' => $this->t("<p>Select a Taxonomy Vocabulary to associate with a Group Type for: </p><ul><li>When a group of that Group Type is created, a term in that vocabulary with the same name as Group name</li></ul>"),
    ];
    $is_inital = FALSE;
    foreach ($group_types as $group_type) {
      if (!empty($config->get($group_type->id(), 0))) {
        $is_inital = TRUE;
      }
      $form['group'][$group_type->id()] = [
        '#type' => 'select',
        '#name' => $group_type->id(),
        '#title' => $this->t('For <i>' . $group_type->label() . "</i> group:"),
        '#options' => $taxonomy_ids,
        '#required' => TRUE,
        '#default_value' => $config->get($group_type->id(), 0),
      ];
    }

    if ($is_inital) {
      $form['content-type'] = [
        '#type' => 'details',
        '#title' => $this->t("Access Control field - Node"),
        '#open' => TRUE,
        '#tree' => TRUE,
      ];
      $form['content-type']['description'] = [
        '#markup' => $this->t("<p>Select the access control field to associate with a Group for: </p><ul><li>When a group of that Group Type is created, a term in that vocabulary with the same name as Group name</li></ul>"),
      ];

      /*
       * Node
       */
      $node_types = \Drupal::entityTypeManager()
        ->getStorage('node_type')
        ->loadMultiple();

      foreach ($node_types as $nt_name => $node_type) {
        $fields_options = [];
        $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $nt_name);

        foreach ($fields as $fname => $field) {
          if ($field->getType() === "entity_reference"
            && isset($field->getSettings()['handler_settings']['target_bundles'])) {
            $targets = array_keys($field->getSettings()['handler_settings']['target_bundles']);
            if (in_array($config->get($group_type->id(), 0), $targets))
              $fields_options[$fname] = $fname;
          }
        }

        $f = (!empty($config->get('node-type-access-fields'))) ? $config->get('node-type-access-fields') : '';
        $form['content-type'][$nt_name]['access-control-field'] = [
          '#type' => 'select',
          '#title' => $this->t($node_type->label()),
          '#options' => $fields_options,
          "#empty_option" => t('- Select -'),
          '#default_value' => (!empty($f[$nt_name])) ? $f[$nt_name] : ''
        ];
      }

      /*
       * Media
       */
      $media_types = \Drupal::entityTypeManager()
        ->getStorage('media_type')
        ->loadMultiple();
      $form['media'] = [
        '#type' => 'details',
        '#title' => $this->t("Access Control field - Media"),
        '#open' => TRUE,
        '#tree' => TRUE,
      ];
      $form['media']['description'] = [
        '#markup' => $this->t("<p>Select the access control field to associate with a Group for: </p><ul><li>When a group of that Group Type is created, a term in that vocabulary with the same name as Group name</li></ul>"),
      ];

      foreach ($media_types as $media_name => $meida_type) {
        $fields_options = [];
        $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('media', $media_name);
        foreach ($fields as $fname => $field) {
          if ($field->getType() === "entity_reference"
            && isset($field->getSettings()['handler_settings']['target_bundles'])) {
            $targets = array_keys($field->getSettings()['handler_settings']['target_bundles']);
            if (in_array($config->get($group_type->id(), 0), $targets))
              $fields_options[$fname] = $fname;
          }
        }

        $f = (!empty($config->get('media-type-access-fields'))) ? $config->get('media-type-access-fields') : '';
        $form['media'][$media_name]['access-control-field'] = [
          '#type' => 'select',
          '#title' => $this->t($meida_type->label()),
          '#options' => $fields_options,
          "#empty_option" => t('- Select -'),
          '#default_value' => (!empty($f[$media_name])) ? $f[$media_name] : '',
        ];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable(Utilities::CONFIG_NAME);

    $groups = \Drupal::service('entity_type.manager')->getStorage('group_type')->loadMultiple();
    foreach ($groups as $group) {
      if ($form_state->getValues()[$group->id()] !== NULL) {
        $config->set($group->id(), $form_state->getValues()[$group->id()])->save();
      }
    }

    // For node.
    $field_access_control_config = [];
    foreach ($form_state->getValues()['content-type'] as $type => $field_value) {
      if (!empty($field_value['access-control-field'])) {
        $field_access_control_config[$type] = $field_value['access-control-field'];
      }
    }
    $config->set("node-type-access-fields", $field_access_control_config)->save();

    // For media.
    $field_access_control_config = [];
    foreach ($form_state->getValues()['media'] as $type => $field_value) {
      if (!empty($field_value['access-control-field'])) {
        $field_access_control_config[$type] = $field_value['access-control-field'];
      }
    }
    $config->set("media-type-access-fields", $field_access_control_config)->save();
    parent::submitForm($form, $form_state);
  }

}
