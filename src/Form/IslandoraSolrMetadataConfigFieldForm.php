<?php

namespace Drupal\islandora_solr_metadata\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Component\Utility\NestedArray;

/**
 * Form to configure fields in the solr metadata display.
 */
class IslandoraSolrMetadataConfigFieldForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_solr_metadata_config_field_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $config_id = NULL, $escaped_field_name = NULL) {
    $form_state->loadInclude('islandora_solr', 'inc', 'includes/utilities');
    $form_state->loadInclude('islandora_solr_metadata', 'inc', 'includes/config');
    $form_state->loadInclude('islandora', 'inc', 'includes/content_model.autocomplete');
    $field_name = islandora_solr_restore_slashes($escaped_field_name);
    $get_default = function ($value, $default = '') use ($config_id, $field_name) {
      module_load_include('inc', 'islandora_solr_metadata', 'includes/db');
      static $field_info = NULL;
      if ($field_info === NULL) {
        $fields = islandora_solr_metadata_get_fields($config_id);
        $field_info = $fields[$field_name];
      }
      $exists = FALSE;
      $looked_up = NestedArray::getValue($field_info, (array) $value, $exists);
      return $exists ? $looked_up : $default;
    };

    $form['#tree'] = TRUE;
    $form['wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Field config'),
    ];

    $set = & $form['wrapper'];
    $set['display_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Display Label'),
      '#description' => $this->t('A human-readable label to display alongside values found for this field.'),
      '#default_value' => $get_default('display_label', $field_name),
    ];
    $set['hyperlink'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hyperlink?'),
      '#description' => $this->t('Should each value for this field be linked to a search to find objects with the value in this field?'),
      '#default_value' => $get_default('hyperlink', FALSE),
    ];
    $set['uri_replacement'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URI/PID Replacement Field'),
      '#description' => $this->t('If the value of this field represents a Fedora URI or PID, a Solr field can be specified to replace that value, e.g., with the object label instead of the full URI.'),
      '#default_value' => $get_default('uri_replacement', ''),
      '#autocomplete_route_name' => 'islandora_solr.autocomplete_luke',
    ];
    if (islandora_solr_is_date_field($field_name)) {
      $set['date_format'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Date format'),
        '#default_value' => $get_default('date_format', ''),
        '#description' => $this->t('The format of the date, as it will be displayed in the search results. Use <a href="@url" target="_blank">PHP date()</a> formatting. Works best when the date format matches the granularity of the source data. Otherwise it is possible that there will be duplicates displayed.', [
          '@url' => 'http://php.net/manual/function.date.php',
        ]),
      ];
    }
    // Add in truncation fields for metadata field.
    $truncation_config = [
      'default_values' => [
        'truncation_type' => $get_default([
          'truncation',
          'truncation_type',
        ], 'separate_value_option'),
        'max_length' => $get_default([
          'truncation',
          'max_length',
        ], 0),
        'word_safe' => $get_default(['truncation', 'word_safe'], FALSE),
        'ellipsis' => $get_default([
          'truncation',
          'ellipsis',
        ], FALSE),
        'min_wordsafe_length' => $get_default([
          'truncation',
          'min_wordsafe_length',
        ], 1),
      ],
      'min_wordsafe_length_input_path' => "wrapper[truncation][word_safe]",
    ];
    islandora_solr_metadata_add_truncation_to_form($set, $truncation_config);
    $permissions = $get_default(['permissions'], [
      'enable_permissions' => FALSE,
      'permissions' => [],
    ]);
    islandora_solr_metadata_append_permissions_and_actions($permissions, $set);

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save field configuration'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue(['wrapper', 'hyperlink']) && $form_state->getValue([
      'wrapper',
      'truncation',
      'max_length',
    ]) > 0) {
      $form_state->setError($form['wrapper']['hyperlink'], $this->t('Either hyperlinking or truncation can be used, but not both together on the same field. Disable one.'));
      $form_state->setError($form['wrapper']['truncation']['max_length']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    list($config_id, $escaped_field_name) = $form_state->getBuildInfo()['args'];
    $field_name = islandora_solr_restore_slashes($escaped_field_name);

    $fields = islandora_solr_metadata_get_fields($config_id);
    $field_info = $fields[$field_name];
    $field_info = $form_state->getValue(['wrapper']) + $field_info;
    islandora_solr_metadata_update_fields($config_id, [$field_info]);

    $form_state->setRedirect('islandora_solr_metadata.config', ['configuration_id' => $config_id]);
  }

}
