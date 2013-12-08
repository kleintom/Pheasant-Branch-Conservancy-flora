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
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
  <title>PBC plants</title>
  <meta http-equiv="content-type" content="text/html;charset=utf-8" />
  <link rel="stylesheet" type="text/css" href="pbc_plant.css" />
</head>

<body>
  <h1>Plants of <a id="pbc_title" href="http://www.pheasantbranch.org/">Pheasant Branch Conservancy</a></h1>
   
  <h3>Corrections and suggestions encouraged!  Email pbcflora@gmail.com</h3>
<?php

///////////////// mysql setup
$log_ip_string = "plant"; // the init code will log this connection
// "exports" $mysql for the mysql connection
require './php_mysql_init.inc';
///////////////// end mysql setup

// full lists of available plants
$names_result = $mysql->query("select common,latin,short_latin from flora order by common");

$common_names = array();
$latin_names = array();
$short_latin_names = array();
while ($entries = $names_result->fetch_assoc()) {
  $common_names[] = $entries['common'];
  $latin_names[] = $entries['latin'];
  $short_latin_names[] = $entries['short_latin'];
}
$names_result->free();

// input plant(s)
$plants = $_GET['plant'];
// We get a string if there was one select item of if the user filled in the
// query by hand.
if (!is_array($plants)) {
  // Exploding an empty string returns an array with one element!
  $plants = $plants ? explode(',', $plants) : array();
}
// only keep the first 25
$plants = array_slice($plants, 0, 25);

// verify $plants (splits $plants into $verified_plants and $unverified_plants)
$verification_array = verify_plants($plants, $short_latin_names);
$verified_plants = $verification_array[0];
$verified_plant_count = count($verified_plants);
$unverified_plants = $verification_array[1];
$unverified_plant_count = count($unverified_plants);

// let the user know if there were unverified plant names
if ($unverified_plant_count > 0) {
  if ($unverified_plant_count == 1) {
    $invalid_substring = " plant wasn't ";
  }
  else {
    $invalid_substring = " plants weren't ";
  }
  echo "<p id='bad_plants_div'>The following {$invalid_substring} found: " .
    htmlentities(implode(', ', $unverified_plants)) . ".</p>";
}

// give a link to the full plants site that will open the verified plants
$plants_to_open = "";
if ($verified_plant_count > 0) {
  $plants_to_open = "?plant=" . implode(',', $verified_plants);
}
$full_site = $local ? "http://localhost/reduced_flora/pbc_flora.html" :
  "http://pheasantbranch.org/flora/index.html";
?>
<p class="clearing">
  <a id="full_site_link" href="<?php echo $full_site ?><?php echo $plants_to_open ?>">Open the full plants website.</a>
</p>
<?php

//////////////////// create the select form, selecting the current plant
//////////////////// if there's exactly one verified plant
?>
<form action="pbc_plant.php" id="float_form" method="get">
  <p>
  <select name="plant[]" multiple="multiple" class="select" size="20">
<?php
// set the verified plants as selected
$selected_plants = $verified_plants;
$select_count = count($common_names);
for ($i = 0; $i < $select_count; ++$i) {
  $this_short_latin = $short_latin_names[$i];
  $selected = "";
  foreach ($selected_plants as $index => $plant) {
    if ($plant == $this_short_latin) {
      $selected = "selected='selected'";
      unset($selected_plants[$index]);
      array_values($selected_plants);
      break;
    }
  }
  echo "<option value='{$short_latin_names[$i]}' {$selected}>{$common_names[$i]} ({$latin_names[$i]})</option>";
}
?>
  </select>
  <br />
   <input type="submit" class="submit" value="Display selected plant(s)" />
  </p>
</form>
<p id="form_notes">
  To select a single plant, click on the plant.
  To add more plants to your selection, hold down the control key as you click
  more plants.  To choose a range of plants, click the first plant, then hold
  down the shift key and click on the last plant.
  <br class="clearing" />
</p>
<?php
///////////////////////// end select form

if ($verified_plant_count == 0) {
?>
</body>
</html>
<?php
  exit();
}

///////////////////////// Display the verified plants
$result = $mysql->query("select * from flora where short_latin in ('" .
                        implode("','", $verified_plants) . "') order by common;");

while ($entry = $result->fetch_assoc()) {

  // images
  $images = '<div>';
  $image_list = $entry["pbc_images"] . "," . $entry["owen_images"] .
    "," . $entry["arb_images"] . "," . $entry["garner_images"];
  $image_array = explode(",", $image_list);
  foreach ($image_array as $image_number) {
    if ($image_number != "") {
      $this_common = $entry['common'];
      $this_latin = $entry['short_latin'];
      $this_image_file = $image_path . $this_latin . $image_number . '.jpg';
      $images .= "<div class='image'><img alt='{$this_common}' src='{$this_image_file}' /></div>";
    }
  }
  $images .= "<br class='clearing' /></div>";

  // aliases
  $aliases = '';
  $aliases_value = $entry['aliases'];
  if ($aliases_value) {
    $aliases = "<br /><span class='alias'>Aliases: {$aliases_value}</span>";
  }

  // flower color
  $color = '';
  $color_value = $entry['color'];
  if ($color_value) {
    $color = "<span class='column'>Flower color:</span>{$color_value}<br />";
  }

  // c value
  $c_value = '';
  $c_value_value = $entry['c_value'];
  if ($c_value_value) {
    $c_value = "<span class='column'>C value:</span>{$c_value_value}<br />";
  }

  // wetland indicator
  $w_i = '';
  $w_i_value = strtoupper($entry['w_i']);
  if ($w_i_value) {
    $w_i = "<span class='column'>Wetland indicator:</span>{$w_i_value}<br />";
  }

  // bloom period
  $bloom = '';
  $bloom_code = $entry['bloom'];
  if ($bloom_code) {
    $start = code_to_string($bloom_code, "start");
    $end = code_to_string($bloom_code, "end");
    $bloom_substring = $start . " to " . $end;
    $bloom = "<span class='column'>Bloom period:</span>{$bloom_substring}<br />";
  }

  // invasive
  $invasive = '';
  $invasive_value = $entry['invasive'];
  if ($invasive_value) {
    switch ($invasive_value) {
    case 'y':
      $invasive_substring = "weedy";
      break;
    case 'yes':
      $invasive_substring = "potentially invasive";
      break;
    case 'Yes':
      $invasive_substring = " ";
      break;
    case 'YES':
      $invasive_substring = "invasive -- consider control";
      break;
    }
    $invasive = "<span class='column'>::Non-native::</span>{$invasive_substring}<br />";
  }

  // WI restricted/prohibited
  $restricted = '';
  $wi_value = $entry['wi'];
  if ($wi_value) {
    switch ($wi_value) {
    case 'r':
      $restricted_substring = ":::: Restricted in Wisconsin ::::";
      break;
    case 'p':
      $restricted_substring = ":::: Prohibited in Wisconsin ::::";
      break;
    }
    $restricted = "<span class='column'>{$restricted_substring}</span><br />";
  }

  // notes (I'm not sure who's adding escapes on quotes (the mysql query?), and
  // I don't understand why they don't show up in the <a href="... in addition
  // to "regular" text)
  $notes = "<span class='column'>Notes:</span><div class='notes'>" .
    stripslashes($entry['notes']) . '</div>';


?>
  <div class='outer'>
     <span class='title'>
       <span class='main_title'><?php echo $entry['common']; ?></span>
       (<span class='latin'><?php echo $entry['latin'] ?></span>)
       [<?php echo $entry['family'] ?>]</span>
       <?php echo $aliases ?>                                                                   
  </div>
  <div class='inner'>
    <?php echo $images ?>
    <?php echo $color ?>
    <?php echo $bloom ?>
    <?php echo $c_value ?>
    <?php echo $w_i ?>
    <?php echo $invasive ?>
    <?php echo $restricted ?>
    <?php echo $notes ?>
  </div>
<?php
}
$result->free();
?>

</body>
</html>

<?php
///////////////////////// Utility functions ///////////////////////////////////

// convert the bloom period code into a readable string
function code_to_string($code, $start_end) {

  static $dictionary = array(13=>"late March",20=>"April",21=>"early April",22=>"mid April",23=>"late April",24=>"April",30=>"May",31=>"early May",32=>"mid May",33=>"late May",34=>"May",40=>"June",41=>"early June",42=>"mid June",43=>"late June",44=>"June",50=>"July",51=>"early July",52=>"mid July",53=>"late July",54=>"July",60=>"August",61=>"early August",62=>"mid August",63=>"late August",64=>"August",70=>"September",71=>"early September",72=>"mid September",73=>"late September",74=>"September",80=>"October",81=>"early October",82=>"mid October",83=>"late October",84=>"October");
  if ($start_end == "start") {
    return $dictionary[intval(substr(strval($code),0,2))];
  }
  else {
    return $dictionary[intval(substr(strval($code),2,2))];
  }
}

// Separates $input_plants (an array) into verified and unverified plants.
// A plant is verified if it matches a short latin
// name (concatenate first three letters of genus and species).
// Returns an array with first element an array of verified plants,
// second element an array of unverified plants.
function verify_plants($input_plants, $short_latin_names) {

  if (count($input_plants) == 0) {
    return array(array(), array());
  }
  $unverified_plants = $input_plants;
  $unverified_plants_count = count($unverified_plants);
  for ($i = 0; $i < $unverified_plants_count; ++$i) {
    $unverified_plants[$i] = ucfirst($unverified_plants[$i]);
  }

  $verified_plants = array();
  $latin_count = count($short_latin_names);
  for ($i = 0; $i < $latin_count; ++$i) {
    $compress = false;
    foreach ($unverified_plants as $index => $test_plant) {
      if ($test_plant == $short_latin_names[$i]) {
        $verified_plants[] = $short_latin_names[$i];
        // deletes the value at $index
        unset($unverified_plants[$index]);
        $compress = true;
      }
    }
    if ($compress) {
      // removes unset values by shifting down
      array_values($unverified_plants);
      if (count($unverified_plants) == 0) {
        break;
      }
    }
  }
  return array($verified_plants, $unverified_plants);
}

?>