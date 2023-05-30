<?php

require_once 'politicaldata.civix.php';

/**
 * Implementation of hook_civicrm_config
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function politicaldata_civicrm_config(&$config)
{
  _politicaldata_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function politicaldata_civicrm_xmlMenu(&$files)
{
  _politicaldata_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function politicaldata_civicrm_install()
{
  _politicaldata_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function politicaldata_civicrm_uninstall()
{
  _politicaldata_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function politicaldata_civicrm_enable()
{
  _politicaldata_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function politicaldata_civicrm_disable()
{
  _politicaldata_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function politicaldata_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL)
{
  return _politicaldata_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function politicaldata_civicrm_managed(&$entities)
{
  _politicaldata_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_caseTypes
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function politicaldata_civicrm_caseTypes(&$caseTypes)
{
  _politicaldata_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implementation of hook_civicrm_alterSettingsFolders
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function politicaldata_civicrm_alterSettingsFolders(&$metaDataFolders = NULL)
{
  _politicaldata_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

function politicaldata_civicrm_postCommit($op, $objectName, $id, &$objectref)
{
  //custom field IDs for wards, local authority, CCG and constituency
  defined('MAPIT_WARD') ?: define('MAPIT_WARD', '12');
  defined('MAPIT_LA') ?: define('MAPIT_LA', '249');
  defined('MAPIT_DISTRICT') ?: define('MAPIT_DISTRICT', '269');
  defined('MAPIT_CCG') ?: define('MAPIT_CCG', '250');
  defined('MAPIT_CONSTITUENCY') ?: define('MAPIT_CONSTITUENCY', '11');

  if ($objectName != 'Address') { return; }
  if (!in_array($op, array('create', 'edit'))) { return; }
  if ($objectref->is_primary != 1) { return; }

  $contact_id = $objectref->contact_id;
  $postcode = str_replace(' ', '', $objectref->postal_code);
  $country = $objectref->country_id;

   //bail if no postcode
  if (!isset($postcode) or empty($postcode)) { return; }

  //bail if the country explicitly isn't the UK. If there isn't a country try anyway
  if (isset($country) and $country != 'null' and $country != 1226) { return; }

  //bail if there's no contact ID (creating locations on the Manage Event page seems to do this)
  if (!$contact_id) { return; }

  //get API key from db (set via civicrm/mapit/settings)
  $result = civicrm_api3('Setting', 'get', array(
    'sequential' => 1,
    'return' => array("mapitkey"),
  ));

  $apikey = array_key_exists('mapitkey', $result['values'][0]) ? $result['values'][0]['mapitkey'] : '';

  $url = 'https://mapit.mysociety.org/postcode/' . $postcode;
  if ($apikey) {
      $url .= '?api_key=' . $apikey;
  }

  $politicaldata = curlGetMapItData($url);

  if (!$politicaldata) { return; }

  //get appropriate ward and council, depending on whether it's a unitary authority
  $wardID = $politicaldata['shortcuts']['ward'];
  $councilID =  $politicaldata['shortcuts']['council'];

  if (array_key_exists($councilID, $politicaldata['areas'])) {
      $countycouncil = $politicaldata['areas'][$councilID]['name'];
      if $politicaldata['areas'][$councilID]['generation_low'] =< 48 {
              $politicaldata = curlMapItData($url . '&generation=48' )
                             }
  }

  if (politicaldata_array_key_exists_r('county', $politicaldata)) {
    $wardID = $politicaldata['shortcuts']['ward']['district'];
    $districtcouncilID = $politicaldata['shortcuts']['council']['district'];
    $districtcouncil = $politicaldata['areas'][$districtcouncilID]['name'];
  }


  $ward = $politicaldata['areas'][$wardID]['name'];

  //Parliamentary Constituency
  $constituencyID = $politicaldata['shortcuts']['WMC'];
  $constituency = $politicaldata['areas'][$constituencyID]['name'];

  //for all others it's identical (afaik), so can search directly
  //starts working for latlong at this point, as latlong lookups only have the 'areas' array
  if ($politicaldata['areas']) {
    $areas = $politicaldata['areas'];
  } else {
    $areas = $politicaldata;
  }

  $areaNames = mapAreaTypesToNames($areas);

  $ccg = $areaNames['CCG'];

  // assign to the custom fields

  //actually save to DB
  $result = civicrm_api3('CustomValue', 'create', [
    'entity_id' => $objectref->contact_id,
    'custom_' . (MAPIT_WARD) => $ward,
    'custom_' . (MAPIT_LA) => $countycouncil,
    'custom_' . (MAPIT_DISTRICT) => $districtcouncil,
    'custom_' . (MAPIT_CCG) => $ccg,
    'custom_' . (MAPIT_CONSTITUENCY) => $constituency,
  ]);
}

function curlGetMapItData($url) {
  //Initiate curl
  $ch = curl_init();
  //Return the response as a string (if false it prints the response)
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  //set 4 seconds max wait time
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
  //set the url
  curl_setopt($ch, CURLOPT_URL, $url);
  //execute
  $result = curl_exec($ch);
  $politicaldata = json_decode($result, true);

  //bail if no data
  if (!$politicaldata) {
    error_log('mapit returned no data');
    Civi::log()->error('Mapit returned no data');

    return; }
  
  // if (curl_errno($ch)) {
  //     error_log('Curl error: ' . curl_error($ch));
  //     Civi::log()->error('Curl error: ' . curl_error($ch));
  // }

  if (array_key_exists("error", $politicaldata)) {
    CRM_Core_Session::setStatus("Mapit service returned an error: " . $politicaldata["error"], "Mapit Error", "error");
    return;
  }
  
  return $politicaldata;
}

function mapAreaTypesToNames($areas)
{
  $map = [];
  foreach ($areas as $area) {
    $map[$area['type']] = $area['name'];
  }
  return $map;
}

function politicaldata_array_key_exists_r($needle, $haystack)
{
  $result = array_key_exists($needle, $haystack);
  if ($result) {
    return $result;
  }
  foreach ($haystack as $v) {
    if (is_array($v)) {
      $result = politicaldata_array_key_exists_r($needle, $v);
    }
    if ($result) return $result;
  }
  return $result;
}

function politicaldata_searchForType($id, $array)
{
  foreach ($array as $key => $val) {
    if ($val['type'] === $id) {
      return $key;
    }
  }
  return null;
}
