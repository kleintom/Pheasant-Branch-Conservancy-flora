<?php
/* 
   @source: 
   Copyright (C) 2011 Tom Klein

   This program is free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.
   
   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.
   
   You should have received a copy of the GNU General Public License
   along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

//Header("Cache-Control: must-revalidate");
$max_age = 60 * 60; // one hour
//header("Cache-Control: must-revalidate, max-age=$max_age, public");
header('Content-Type: text/xml');

///////////////////////// sanitize
$CLEAN = array();
$CLEAN['new'] = check_value($_GET['new'],
                            array('', 'true'),
                            'New is not true');
$CLEAN['closeups'] = check_value($_GET['closeups'],
                                 array('', 'true', 'false'),
                                 'Bad closeups boolean');
$CLEAN['invasive'] = check_value($_GET['invasive'],
                                 array('', 'true', 'false'),
                                 'Bad invasive boolean');
$CLEAN['bloom_order'] = check_value($_GET['bloom_order'], 
                                    array('', 'bloom', 'bloom,common',
                                          'common'),
                                    'Bad bloom table order');
$CLEAN['sort'] = check_value($_GET['sort'],
                             array('', 'common', 'latin', 'family',
                                   'flower', 'bloom', 'created', 'w_i',
                                   'c_value'),
                             'Bad plant list sort value');


///////////////////////// connect to mysql
if ($CLEAN['new'] == 'true') {
  // the init code will log this connection
  $log_ip_string = "flora";
}
// "exports" $link as the mysql connection
require 'php_mysql_init.inc';

// the return document
$xml = new DOMDocument('1.0', 'iso-8859-1');
$xml_root = $xml->appendChild($xml->createElement('data'));

///////////////////////// start closeups
$closeups = $CLEAN['closeups'];
if ($closeups) {
  if ($closeups == "true") {
    $closeups_root = $xml_root->appendChild($xml->createElement('closeups'));
    if ($CLEAN['invasive'] == "true") {
      $closeups_root->appendChild($xml->createElement('invasives_only', 'true'));
      $closeup_result = mysql_query("select common,short_latin,pbc_closeup,owen_closeup,arb_closeup,garner_closeup from flora where invasive!=\"\" order by color,common", $link);
    }
    else {
      $closeups_root->appendChild($xml->createElement('invasives_only', 'false'));
      $closeup_result = mysql_query("select common,short_latin,pbc_closeup,owen_closeup,arb_closeup,garner_closeup from flora order by color,common", $link);
    }
    while ($entries = mysql_fetch_array($closeup_result)) {
      $closeup_number = $entries["pbc_closeup"] . $entries['owen_closeup'] .
        $entries['arb_closeup'] . $entries['garner_closeup'];
      if ($closeup_number) {
        $thisCloseup = $xml->createElement('closeup');
        $thisCloseup->appendChild($xml->createElement('short_latin',
                                                      $entries['short_latin']));
        $thisCloseup->appendChild($xml->createElement('common',
                                                      $entries['common']));
        $thisCloseup->appendChild($xml->createElement('image_number',
                                                      $closeup_number));
        $closeups_root->appendChild($thisCloseup);
      }
    }
  }
} // end closeups

///////////////////////// start bloom table
$bloom_order = $CLEAN['bloom_order'];
if ($bloom_order) {
  $bloom_root = $xml_root->appendChild($xml->createElement('bloom'));
  if($CLEAN['invasive'] == "true") {
    $bloom_root->appendChild($xml->createElement('invasives_only', 'true'));
    $bloom_result = 
      mysql_query("select common,short_latin,bloom from flora where invasive!=\"\" order by $bloom_order", $link);
  }
  else {
    $bloom_root->appendChild($xml->createElement('invasives_only', 'false'));
    $bloom_result = 
      mysql_query("select common,short_latin,bloom from flora order by $bloom_order", $link);
  }
  $bloom_root->appendChild($xml->createElement('order', $bloom_order));
  $bloom_root->appendChild($xml->createElement('bloom_code_today',
                                               get_todays_bloom_value()));
  while ($entries = mysql_fetch_array($bloom_result)) {
    $code = $entries['bloom'];
    if ($code != '') {
      $this_bloom_data = $xml->createElement('bloom_data');
      $this_bloom_data->appendChild($xml->createElement('common',
                                                        $entries['common']));
      $this_bloom_data->appendChild($xml->createElement('short_latin',
                                                        $entries['short_latin']));
      $start = intval(substr(strval($code), 0, 2));
      $end = intval(substr(strval($code), 2, 2));
      $this_bloom_data->appendChild($xml->createElement('bloom_start',
                                                        $start));
      $this_bloom_data->appendChild($xml->createElement('bloom_end',
                                                        $end));
      $bloom_root->appendChild($this_bloom_data);
    }
  }
} // end bloom table

///////////////////////// start plant list
$order = $CLEAN['sort'];
if ($order) {
  $order_string_hash[''] = "common";
  $order_string_hash['common'] = "common";
  $order_string_hash['latin'] = "latin";
  $order_string_hash['family'] = "family, common";
  $order_string_hash['flower'] = "isnull(color), color, common";
  $order_string_hash['bloom'] = "isnull(bloom), bloom, common";
  $order_string_hash['created'] = "created desc, common";
  $order_string_hash['w_i'] = 'isnull(w_i), field(upper(w_i), "UPL-", "UPL", "UPL*", "UPL+",  "FACU-", "FACU", "FACU*", "FACU+", "FAC-", "FAC", "FAC*", "FAC+", "FACW-", "FACW", "FACW*", "FACW+", "OBL-", "OBL", "OBL*", "OBL+", "NI"), isnull(color), color, common';
  $order_string_hash['c_value'] = "isnull(c_value), c_value desc, common";
  $order_string = $order_string_hash[$order];
  if ($order_string == '') {
    $order_string = 'common';
  }

  if ($CLEAN['invasive'] != "true") {
    $result = mysql_query("select common,latin,short_latin,family,aliases,color,created,w_i,c_value from flora order by $order_string", $link);
  }
  else {
    $result = mysql_query("select common,latin,short_latin,family,aliases,color,created,w_i,c_value from flora where invasive!=\"\" order by $order_string", $link);
  }

  $list_root = $xml_root->appendChild($xml->createElement('list'));
  $list_root->appendChild($xml->createElement('order', $order));

  while ($entries = mysql_fetch_array($result)) {
    $this_plant = $xml->createElement('plant');
    $this_plant->appendChild($xml->createElement('latin', $entries['latin']));
    $this_plant->appendChild($xml->createElement('short_latin',
                                                 $entries['short_latin']));
    $this_plant->appendChild($xml->createElement('common', $entries['common']));
    $this_plant->appendChild($xml->createElement('family', $entries['family']));
    $this_plant->appendChild($xml->createElement('aliases',
                                                 $entries['aliases']));
    switch ($order) {

    case "common":
    case "bloom":
    case "":
    case "latin":
    case "family":
      break;

    case "flower":
      $this_plant->appendChild($xml->createElement('color',
                                                   ucwords($entries['color'])));
      break;

    case "created":
      $this_plant->appendChild($xml->createElement('created',
                                                   deflate_date($entries['created'])));
      break;

    case "w_i":
      $w_i = strtoupper($entries["w_i"]);
      $color = ucwords($entries["color"]);
      $this_plant->appendChild($xml->createElement('w_i', $w_i));
      $this_plant->appendChild($xml->createElement('color', $color));
      break;

    case "c_value":
      $this_plant->appendChild($xml->createElement('c_value',
                                                   $entries['c_value']));
      break;

    default:
      break;
    }// end switch($order)
    $list_root->appendChild($this_plant);
  }

} // end list
echo $xml->saveXML();

mysql_close($link);

////////////////////////////////////////
///////////////////////// end processing

function get_todays_bloom_value() {

  $today_month = intval(date("n"));
  $today_day = intval(date("j"));
  $week = 0;
  if ($today_day > 7 && $today_day <= 14) {
    $week = 1;
  }
  else if ($today_day > 14 && $today_day <= 21) {
    $week = 2;
  }
  else if ($today_day > 21) {
    $week = 3;
  }
  return ($today_month*10 - 10) + $week;
}

function deflate_date($date) {

  $no_leading_zeroes = preg_replace('/0([0-9])/', '$1', $date);
  return preg_replace('/([0-9]{1,2})-([0-9]{1,2})-([0-9]{1,2})/', '$2-$3-$1',
                      $no_leading_zeroes);
}

function check_value($input_value, $permissible_values, $error_message) {

  foreach ($permissible_values as $okay_value) {
    if ($input_value == $okay_value) {
      return $input_value;
    }
  }
  // oOps
  return_error($error_message);
}

function return_error($error_message) {

  $xml = new DOMDocument('1.0', 'iso-8859-1');
  $xml->appendChild($xml->createElement('error', 'Fatal error: ' .
                                        $error_message));
  echo $xml->saveXML();
  exit();
}

?>