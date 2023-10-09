<?php
/**
 * Created by PhpStorm.
 * User: dpino
 * Date: 9/18/18
 * Time: 8:56 PM
 */

namespace Drupal\format_strawberryfield\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\strawberryfield\Tools\Ocfl\OcflHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Cache\Cache;
use Drupal\format_strawberryfield\Tools\IiifHelper;
use Drupal\strawberryfield\Tools\StrawberryfieldJsonHelper;
use Drupal\Core\StreamWrapper\StreamWrapperManager;

/**
 * Simplistic Strawberry Field formatter.
 *
 * @FieldFormatter(
 *   id = "strawberry_ictv_formatter",
 *   label = @Translation("Strawberry Field Simple ICTV Formatter"),
 *   class = "\Drupal\format_strawberryfield\Plugin\Field\FieldFormatter\StrawberryIctvFormatter",
 *   field_types = {
 *     "strawberryfield_field"
 *   },
 *   quickedit = {
 *     "editor" = "disabled"
 *   }
 * )
 */
class StrawberryIctvFormatter extends StrawberryBaseFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return
      parent::defaultSettings() + [
      'json_key_source' => 'virus_species_id',
      'ictv_prefix_url' => 'https://ictv.global/taxonomy/taxondetails?taxnode_id=',
      'ictv_json_prefix_url' => 'https://data.ictv.global/api/taxonomyHistory.ashx?action_code=get_taxon_history&taxnode_id=',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return [
        'json_key_source' => [
          '#type' => 'textfield',
          '#title' => t('JSON Key from where to fetch ICTV virus species ID'),
          '#default_value' => $this->getSetting('json_key_source'),
          '#required' => TRUE,
        ],
        'ictv_prefix_url' => [
          '#type' => 'textfield',
          '#title' => $this->t('URL prefix to add species code to for get ICTV webpage'),
          '#default_value' => $this->getSetting('ictv_prefix_url'),
          '#required' => TRUE,
        ],
        'ictv_json_prefix_url' => [
          '#type' => 'textfield',
          '#title' => $this->t('URL prefix to add species code to for get ICTV JSON'),
          '#default_value' => $this->getSetting('ictv_json_prefix_url'),
          '#required' => TRUE,
        ]
      ] + parent::settingsForm($form, $form_state);
  }


  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    if ($this->getSetting('json_key_source')) {
      $summary[] = $this->t('ICTV species URL fetched from JSON "%json_key_source" key', [
        '%json_key_source' => $this->getSetting('json_key_source'),
      ]);
    }
    if ($this->getSetting('ictv_prefix_url')) {
      $summary[] = $this->t('Species code will be added to "%ictv_prefix_url" for ICTV webpage', [
        '%ictv_prefix_url' => $this->getSetting('ictv_prefix_url'),
      ]);
    }
    if ($this->getSetting('ictv_json_prefix_url')) {
      $summary[] = $this->t('Species code will be added to "%ictv_json_prefix_url" for ICTV JSON', [
        '%ictv_json_prefix_url' => $this->getSetting('ictv_json_prefix_url'),
      ]);
    }

    return $summary;
  }


  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    /* @var \Drupal\file\FileInterface[] $files */
    // Fixing the key to extract while coding to 'Media'
    $key_species = $this->getSetting('json_key_source');
    $ictv_prefix_url = $this->getSetting('ictv_prefix_url');
    $ictv_json_prefix_url = $this->getSetting('ictv_json_prefix_url');


    $nodeuuid = $items->getEntity()->uuid();
    $nodeid = $items->getEntity()->id();
    $fieldname = $items->getName();
    foreach ($items as $delta => $item) {
      $main_property = $item->getFieldDefinition()->getFieldStorageDefinition()->getMainPropertyName();
      $value = $item->{$main_property};

      if (empty($value)) {
        continue;
      }

      $jsondata = json_decode($item->value, true);
      // @TODO use future flatversion precomputed at field level as a property
      $json_error = json_last_error();
      if ($json_error != JSON_ERROR_NONE) {
        $message= $this->t('We could had an issue decoding as JSON your metadata for node @id, field @field',
          [
            '@id' => $nodeid,
            '@field' => $items->getName(),
          ]);
        return $elements[$delta] = ['#markup' => $this->t('ERROR')];
      }

        if ((isset($jsondata[$key_species])) AND ($jsondata[$key_species] > 0)) {
                //$ictv_json_url = $jsondata[$key_species];
                //$ictv_id = substr($ictv_json_url, -9);
                $ictv_id = $jsondata[$key_species];
                $ictv_page_url = $ictv_prefix_url.$ictv_id;
                $ictv_json_url = $ictv_json_prefix_url.$ictv_id;
                $ictvjson = file_get_contents($ictv_json_url);

                $ictv_decoded_json = json_decode($ictvjson, true);
                $ictv_taxon_name = $ictv_decoded_json['info']['taxonName'];
                $ictv_lineage = $ictv_decoded_json['info']['lineage'];
//                $ictv_lineage = str_replace(';', '<BR>', $ictv_lineage);
                $ictv_lineage = str_replace(';', '<BR>', $ictv_lineage);
                $ictv_lineage = '<strong>' . $ictv_lineage . '</strong>';



//              $ictvxml = simplexml_load_string($ictvxhtml);
//              $ictv_taxon_name = $ictvxml['taxon_name'];
//              $ictv_lineage = $ictvxml['taxa_lineage'];
//              $ictv_taxon_name = str_replace('i>', 'strong>', $ictv_taxon_name);
//                $ictv_lineage = str_replace('i>', 'strong>', $ictv_lineage);
//                $ictv_lineage = str_replace('->', '<BR>', $ictv_lineage);





                $elements[$delta] = ['#markup' => 'Species name: <a href="'.$ictv_page_url.'" target="_blank" title="Browse ICTV Taxonomy History">'.$ictv_taxon_name.'</a><BR>Lineage:<BR>'.$ictv_lineage];
        } else {
                $elements[$delta] = ['#markup' => 'No ICTV reference'];
        }

      // Get rid of empty #attributes key to avoid render error
      if (isset( $elements[$delta]["#attributes"]) && empty( $elements[$delta]["#attributes"])) {
        unset($elements[$delta]["#attributes"]);
      }
    }
    return $elements;
  }
}
