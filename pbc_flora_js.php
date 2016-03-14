<?php
/*
   @source: https://github.com/kleintom/Pheasant-Branch-Conservancy-flora
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

// "exports" $mysql as the mysql connection
require 'php_mysql_init.inc';
?>
/*
   @source: https://github.com/kleintom/Pheasant-Branch-Conservancy-flora
   @licstart  The following is the entire license notice for the JavaScript
   code in this page.

   Copyright (C) 2011 Tom Klein

   The JavaScript code in this page is free software: you can
   redistribute it and/or modify it under the terms of the GNU
   General Public License (GNU GPL) as published by the Free Software
   Foundation, either version 3 of the License, or (at your option)
   any later version.  The code is distributed WITHOUT ANY WARRANTY;
   without even the implied warranty of MERCHANTABILITY or FITNESS
   FOR A PARTICULAR PURPOSE.  See the GNU GPL for more details.

   As additional permission under GNU GPL version 3 section 7, you
   may distribute non-source (e.g., minimized or compacted) forms of
   that code without the copy of the GNU GPL normally required by
   section 4, provided you include this license notice and a URL
   through which recipients can access the Corresponding Source.

   @licend  The above is the entire license notice for the JavaScript code in
   this page.
*/
(function() {
var pbc = {
  // All of the plants we know about and their data - keys are short latin
  // names, values are Plant objects.
  plants : {},
  // Plants lists below are of short latin names; key 1 is for the invasives
  // only list, 0 is for the full list.
  closeup_plants : {1: [], 0: []},
  list_plants : [],
  bloom_plants : {1: [], 0: []},
  // Cache the closeup div text.
  closeups_text_cache : {1 : '', 0 : ''},
  // Timers for loading divs.  Count is kept to provide a progress indicator.
  timers : {
    "initial_plant_list_loading" : {timer_id : 0, count : 0},
    "plant_list_loading" : {timer_id : 0, count : 0},
    "closeups_loading" : {timer_id : 0, count : 0},
    "blooms_loading" : {timer_id : 0, count : 0},
    "internal_blooms_loading" : {timer_id : 0, count : 0}
  }
};

// Data for one plant.
var Plant = function(short_latin, image_list, flower_color, bloom, invasive,
                     restricted, notes, c_value, wetland_indicator,
                     common, latin, family, aliases) {

  this.short_latin = short_latin;
  this.image_list = image_list;
  this.color = flower_color;
  this.bloom = bloom;
  this.invasive = invasive;
  this.restricted = restricted;
  this.notes = notes;
  this.c_value = c_value;
  this.w_i = wetland_indicator;
  this.common = common;
  this.latin = latin;
  this.family = family;
  this.aliases = aliases;
};
// Control the display of this plant: action is "expand", "collapse", or
// "switch" (flip the current state).
Plant.prototype.display = function(action) {

  var plant_inner = $('#' + this.short_latin + '_inner');
  // We can get called even when this plant isn't currently being displayed.
  if (!plant_inner.length) {
    return;
  }
  var inner_is_open = plant_inner.is(":visible");
  if (action === "expand" || (action === "switch" && !inner_is_open)) {
    var inner_content =
        $('#' + this.short_latin + '_inner').children(":first-child");
    if (!inner_content.length) { // display this plant's data
      $('#' + this.short_latin + '_ec')
        .removeClass("expand").addClass("collapse");
      var components = [];
      // images
      var images_array = [];
      for (var i = 0, length = this.image_list.length; i < length; ++i) {
        var image_div = $("<div>", {'class' : 'image'});
        $("<img>", {'alt' : this.short_latin,
                    'src' : "<?php echo $image_path ?>" +
                    this.short_latin + this.image_list[i] + '.jpg'})
          .appendTo(image_div);
        images_array.push(image_div);
      }
      var images_br = $("<br>", {'class' : 'clearing_break'});
      var images_div = $("<div>").append(images_array.concat(images_br));
      components.push(images_div);
      // We display these properties if they're set;
      // "t" is text, "v" is value.
      var maybe_properties = [{t : 'Flower color:', v : this.color},
                              {t : 'Bloom period:', v : this.bloom},
                              {t : 'C value:', v : this.c_value},
                              {t : 'Wetland indicator:',
                               v : this.w_i},
                              {t : '::Non-native::', v : this.invasive}];

      for (var i = 0, length = maybe_properties.length; i < length; ++i) {
        var thisProperty = maybe_properties[i];
        if (thisProperty.v != '') {
          components.push($("<span>", {'class' : 'column'}).text(thisProperty.t));
          components.push(document.createTextNode(thisProperty.v));
          components.push($("<br>"));
        }
      }
      // WI restricted?
      if (this.restricted) {
        components.push($("<span>", {'class' : 'column'}).text(this.restricted));
        components.push($("<br>"));
      }
      // notes
      components.push($("<span>", {'class' : 'column'}).text('Notes:'));
      var notes_div = $("<div>", {'class' : 'notes'})
                      .append($.parseHTML(this.notes));
      components.push(notes_div);

      $('#' + this.short_latin).css("borderBottom", "1px solid black");
      plant_inner.append(components).show();
    }
    else { // content already exists, just show it
      $('#' + this.short_latin + '_ec')
        .removeClass("expand").addClass("collapse");
      $('#' + this.short_latin).css("borderBottom", "1px solid black");
      plant_inner.show();
    }
  }
  else if ((action == "collapse") || (action == "switch" && inner_is_open)) {
    var short_latin_id = '#' + this.short_latin;
    $(short_latin_id + '_inner').hide();
    $(short_latin_id).css("borderBottom", "");
    $(short_latin_id + '_ec').removeClass("collapse").addClass("expand");
  }
};

//// Start pbc dom utility functions /////////////////////////////////
pdom = {};
/* attributes is an object, children is an array */
pdom.create_element = function(type, attributes, children) {

  var element = document.createElement(type);
  if (attributes) {
    for (var name in attributes) {
      element.setAttribute(name, attributes[name]);
    }
  }
  if (children) {
    for (var i = 0, length = children.length; i < length; ++i) {
      element.appendChild(children[i]);
    }
  }
  return element;
};
pdom.ce = pdom.create_element;

// createTextNode is some kind of weird wrapper object that you can't reference
// directly...
pdom.tn = function(text) {
  return document.createTextNode(text);
};
//// End pbc dom utility functions ///////////////////////////////////

///////////////////////// Start ajax handling /////////////////////////////
//// (Initially ajax was the source of more of the data, but now the full plant
//// list is loaded and stored here via php, so all we use ajax for is getting
//// sorted/trimmed lists of plant names from sql.)

// open_plants is the list of plants that was already open when the request was
// made.
var request_ajax_data = function(query_data, open_plants) {

  $.ajax({
    url: "pbc_flora_ajax.php",
    data: query_data,
    type: "GET",
    dataType: "xml",
  }).done(ajax_handler(open_plants));
};

// open_plants is the list of plants that was already open when the request was
// made.
var ajax_handler = function(open_plants) {

  open_plants = open_plants || [];

  return function(dom) {

    if (!dom || !dom.documentElement ||
        dom.documentElement.nodeName === 'parsererror') {
      error_alert("Ajax error: please try again.");
      return;
    }
    var maybe_error = dom.getElementsByTagName('error');
    if (maybe_error[0]) {
      error_alert(maybe_error[0].firstChild.data);
      return;
    }
    var closeups_xml = dom.getElementsByTagName('closeups')[0];
    handle_ajax_closeups(closeups_xml);
    var list_xml = dom.getElementsByTagName('list')[0];
    handle_ajax_list(list_xml, open_plants);
    var blooms_xml = dom.getElementsByTagName('bloom')[0];
    handle_ajax_blooms(blooms_xml);
  };
};

// Handle return data (dom) from an ajax request for new closeups.
var handle_ajax_closeups = function(dom) {

  if (!dom) { return; }

  clear_timer("closeups_loading");
  var closeups = dom.getElementsByTagName('closeup');
  var fragment = document.createDocumentFragment();
  var latin_array = []; // remember the plants we add
  for (var i = 0, length = closeups.length; i < length; ++i) {
    var thisCloseup = closeups[i];
    var common =
        thisCloseup.getElementsByTagName('common')[0].firstChild.data;
    var latin =
        thisCloseup.getElementsByTagName('short_latin')[0].firstChild.data;
    latin_array.push(latin);
    var image_number =
        thisCloseup.getElementsByTagName('image_number')[0].firstChild.data;
    //<div class="closeup"><img class="closeup_img" src="' + '../pbc_flora/flora_images/' + latin + '_' + image_number + 'c.jpg" alt="' + common + '"/><br /><div class="closeup_description"><input type="checkbox" class="inline_checkbox" id="' + latin + '_checkbox"/><span class="link_text" id="' + latin + '_closeup">' + common + '</span></div></div>;
    var link_text = $("<span>", {"class" : "link_text",
                                 "id" : latin + '_closeup'}).text(common);
    var checkbox = $("<input>", {'type' : 'checkbox',
                                 'class' : 'inline_checkbox',
                                 'id' : latin + '_checkbox'});
    var description = $("<div>", {"class" : 'closeup_description'})
        .append([checkbox, link_text]);
    var image = $("<img>", {'class' : 'closeup_img',
                            'src' : '<?php echo $image_path ?>' + latin +
                            image_number + 'c.jpg',
                            'alt' : common});
    var outerDiv = $("<div>", {'class' : 'closeup'})
        .append([image, description]);
    fragment.appendChild(outerDiv.get(0));
  }
  var inner_closeups = $("#inner_closeups_div");
  // Remember this fragment.
  var invasives_only =
      dom.getElementsByTagName('invasives_only')[0].firstChild.data;
  invasives_only = (invasives_only === "true") ? 1 : 0;
  pbc.closeups_text_cache[invasives_only] = fragment.cloneNode(true);
  inner_closeups.empty().append(fragment);
  $("#closeup_checks").show();
  $("#inner_closeups_div").show();
  // Remember these plants (for "open all checked plants").
  pbc.closeup_plants[invasives_only] = latin_array;
};

// plant_data_displayer returns an object used for creating html fragments that
// display the title information for a given plant; its get_data_from_xml method
// retrieves plant data (for one plant) from an xml fragment, while its
// append_div method takes an html fragment as input and appends a plant entry
// for the input plant to the fragment; the list_order argument to the
// constructor determines what/in what order data is displayed.
var plant_data_displayer = function(list_order) {

  //// Private data
  // Each plant display looks like title_div followed by inner_div
  // (cf. write_divs below).
  var title_div = null;
  var inner_div = null;
  var common = '';
  var aliases_fragment = null;
  var latin = '';
  var short_latin = '';
  var family = '';
  var title_prefix_key = '';
  if (list_order === 'color' || list_order === 'c_value' || list_order === 'w_i') {
    title_prefix_key = list_order;
  }
  var title_prefix = '';
  var write_divs = function(fragment) {
    if (aliases_fragment) {
      title_div.appendChild(aliases_fragment);
    }
    fragment.appendChild(title_div);
    fragment.appendChild(inner_div);
  };
  // end private data

  var that = {}; // our return plant display object
  that.short_latin = function() { return short_latin; }

  // Set this object's data values from an xml fragment argument.
  // This object is created once but repopulated many times by this
  // function, so be sure to reset every member on every call.
  that.get_data_from_xml = function(plant_xml) {

    short_latin =
      plant_xml.getElementsByTagName('short_latin')[0].firstChild.data;
    var plant = pbc.plants[short_latin];

    if (plant.aliases) {
      aliases_fragment = document.createDocumentFragment();
      aliases_fragment.appendChild(document.createElement('br'));
      var aliases_node = pdom.ce('span', {'class' : 'alias'});
      // plant.aliases may contain markup.
      aliases_node.innerHTML = 'Aliases: ' + plant.aliases;
      aliases_fragment.appendChild(aliases_node);
    }
    else {
      aliases_fragment = null;
    }
    common = plant.common;
    latin = plant.latin;
    family = plant.family;
    var image = pdom.create_element('span',
                                    {'class' : 'expand_collapse expand',
                                     'id' : short_latin + '_ec'});
                                     /*'src' : 'expand-r.png',
                                     'alt' : 'expand or collapse ' + latin});*/
    title_div = pdom.create_element('div',
                                    {'id' : short_latin, 'class' : 'outer'},
                                    [image]);
    inner_div = pdom.create_element('div',
                                    {'id' : short_latin + '_inner',
                                     'class' : 'inner'});
    if (title_prefix_key) {
      var maybe_prefix_value = plant[title_prefix_key];
      title_prefix = maybe_prefix_value ? maybe_prefix_value + ': ' : '';
    }
    else {
      title_prefix = '';
    }
  };

  // append_div takes a fragment as argument and adds this object's data to
  // the fragment as appropriate, depending on list_order.
  that.append_div = (function() {

      switch(list_order) {
      case "common":
      case "bloom":
      case "color":
      case "c_value":
      case "w_i":
        return function(fragment) {
          title_div.innerHTML +=
            '<span class="title"><span class="main_title">' +
            title_prefix + common +
            ' <span class="sec_title">(<span class="latin">' + latin +
            '</span>) <span class="ter_title">:: ' + family +
            '</span></span></span>';
          write_divs(fragment);
        };
        break;

      case "latin":
        return function(fragment) {
          title_div.innerHTML +=
            '<span class="title"><span class="main_title latin">' + latin +
            '</span> <span class="sec_title">(' + common +
            ') <span class="ter_title">:: ' + family + '</span></span></span>';
          write_divs(fragment);
        };
        break;

      case "family":
        return function(fragment) {
          title_div.innerHTML +=
            '<span class="title"><span class="main_title">' + family +
            '</span><br />' + common +
            ' (<span class="latin">' + latin +
            '</span>)';
          write_divs(fragment);
        };
        break;

      default:
        alert ("oOps - bad list order: " + list_order);
        return function(fragment) { };
        break;
      } // end switch
    })();

  return that;
};

// Handle return data (dom) from an ajax request for new plant list data;
// open_plants are the plants from the plant list that were open before the
// current request - open any on the old list that are present in the new list.
var handle_ajax_list = function(dom, open_plants) {

  if (!dom) { return; }

  clear_timer("plant_list_loading");
  var list = dom.getElementsByTagName('plant');
  var order = dom.getElementsByTagName('order')[0].firstChild.data;
  if (order === 'flower') {
    order = 'color'; // oOps
  }
  var data_processor = plant_data_displayer(order);
  var fragment = document.createDocumentFragment();
  var latin_array = []; // remember the plants we add
  for (var i = 0, length = list.length; i < length; ++i) {
    data_processor.get_data_from_xml(list[i]);
    data_processor.append_div(fragment);
    latin_array.push(data_processor.short_latin());
  }
  $("#plant_list_div").empty().append(fragment);
  pbc.list_plants = latin_array;
  $("#plant_count").empty().text("(" + latin_array.length + " plants)");
  // reopen ids that were open before the request
  for (var i = 0, length = open_plants.length; i < length; ++i) {
    pbc.plants[open_plants[i]].display("expand");
  }
};

// Handle return data (dom) from an ajax request for new bloom table data.
var handle_ajax_blooms = function(dom) {

  if (!dom) { return; }

  clear_timer("blooms_loading");
  clear_timer("internal_blooms_loading");
  var invasives_only =
      dom.getElementsByTagName('invasives_only')[0].firstChild.data;
    invasives_only = (invasives_only === "true") ? 1 : 0;
  var bloom_code_today =
      +dom.getElementsByTagName('bloom_code_today')[0].firstChild.data;
  var bloom_order = dom.getElementsByTagName('order')[0].firstChild.data;
  if (bloom_order == 'common') {
    document.getElementById('bloom_radio_common_option').checked = 'checked';
  }
  else {
    document.getElementById('bloom_radio_date_option').checked = 'checked';
  }
  // Remove the old table body.
  var table = document.getElementById('bloom_table');
  var table_body = table.tBodies[0];
  for (var i = table_body.rows.length - 1; i >= 0; --i) {
    table_body.deleteRow(i);
  }
  var latin_array = []; // remember the plants in the table
  var blooms = dom.getElementsByTagName('bloom_data');
  var number_of_blooms = blooms.length;
  var fragment = document.createDocumentFragment();
  // Create the rows.
  for (var i = 0, length = blooms.length; i < length; ++i) {
    var thisBloom = blooms[i];
    var common = thisBloom.getElementsByTagName('common')[0].firstChild.data;
    var short_latin =
        thisBloom.getElementsByTagName('short_latin')[0].firstChild.data;
    latin_array.push(short_latin);
    var anchor_id = short_latin + '_ref';
    var latin_id = short_latin + '_bloom_check';
    var blooming = "";
    var bloom_start =
        +thisBloom.getElementsByTagName('bloom_start')[0].firstChild.data;
    var bloom_end =
        +thisBloom.getElementsByTagName('bloom_end')[0].firstChild.data;
    if (bloom_start <= bloom_code_today && bloom_end > bloom_code_today) {
      blooming = 'th_blooming';
    }
    //// Create a row.
    //<tr><th><span class="' + blooming + '"><input type="checkbox" class="inline_checkbox" id="' + latin_id + '"/><span class="anchor_link" id="' + anchor_id + '">' + common + '</span></th>' + bloom_row + '</tr>
    var title_checkbox = pdom.ce('input', {'type' : 'checkbox',
                                           'class' : 'inline_checkbox',
                                           'id' : latin_id});
    var title_link = pdom.ce('span', {'class' : 'anchor_link',
                                      'id' : anchor_id},
                             [pdom.tn(common)]);
    var title_span = pdom.ce('span', {'class' : blooming},
                             [title_checkbox, title_link]);
    var title_th = pdom.ce('th', {}, [title_span]);
    var bloom_row = get_bloom_row(bloom_start, bloom_end, bloom_code_today);
    var tr = pdom.ce('tr', {}, [title_th].concat(bloom_row));
    fragment.appendChild(tr);
    if (i > 0 && i % 20 == 0 && i + 4 < number_of_blooms) {
      // Add another months row.
      var empty_th = pdom.ce('th', {'class' : 'table_header',
                                    'scope' : 'col'});
      var months_array = get_table_months();
      var months_row = pdom.ce('tr', {}, [empty_th].concat(months_array));
      fragment.appendChild(months_row);
    }
  }
  table_body.appendChild(fragment);
  document.getElementById("inner_blooms_div").style.display = "block";
  // Store the current list (for "open all checked").
  pbc.bloom_plants[invasives_only] = latin_array;
};
///////////////////////// End ajax handling ///////////////////////////////

// Return the list of currently open plants.
var get_open_list_plants = function() {

  var return_array = [];
  for (var i = 0, length = pbc.list_plants.length; i < length; ++i) {
    var key = pbc.list_plants[i];
    var elt = $('#' + key + "_inner");
    if ($('#' + key + "_inner").children(":first-child").length) {
      return_array.push(key);
    }
  }
  return return_array;
};

var get_closeup_plants_array = function() {

  return pbc.closeup_plants;
};

// The closeups check has changed.
var closeups_oncheck_function = function() {

  if (box_is_checked("closeups_box")) {
    var closeup_div = $("#inner_closeups_div");
    var invasives_only = box_is_checked("invasive_box");
    var closeup_fragment = pbc.closeups_text_cache[invasives_only];
    if (!closeup_fragment) { // get the data
      var query_data = { "closeups" : "true" };
      if (invasives_only) {
        query_data["invasive"] = "true";
      }
      request_ajax_data(query_data);
      closeup_div.show();
      set_loading("closeups_loading");
    }
    else { // load saved data
      pbc.closeups_text_cache[invasives_only] = closeup_fragment.cloneNode(true);
      closeup_div.empty().append(closeup_fragment).show();
      var name_array = pbc.closeup_plants[invasives_only];
      $("#closeup_checks").show();
    }
  }
  else { // closeups not checked
    $("#closeup_checks").hide();
    $("#inner_closeups_div").hide();
  }
};

var get_bloom_plants_array = function() {

  return pbc.bloom_plants;
};

// The bloom table check has changed.
var blooms_oncheck_function = function() {

  if (box_is_checked("blooms_box")) {
    var query_data = { "bloom_order" : get_bloom_order() };
    if (box_is_checked("invasive_box")) {
      query_data["invasive"] = "true";
    }
    request_ajax_data(query_data);
    set_loading("blooms_loading");
  }
  else { // blooms not checked
    $("#inner_blooms_div").hide();
  }
};

// User changed the bloom table order.
var bloom_function = function() {

  var query_data = { "bloom_order" : get_bloom_order() };
  if (box_is_checked("invasive_box")) {
    query_data["invasive"] = "true";
  }
  request_ajax_data(query_data);
  set_loading("internal_blooms_loading");
};

// Return the value of the bloom table order radio buttons.
var get_bloom_order = function() {

  return $("input[name=bloom_order]:checked").val();
};

// Return the table row text for a plant with the input bloom data.
var get_bloom_row = function(bloom_start, bloom_end, bloom_code_today) {

  // [Note: \u00A0 is unicode for &nbsp; - DOM doesn't do entities]
  var td_array = [];
  // late March is a special case
  if (bloom_start == 13) {
    var on_today = "on";
    if (bloom_code_today == 13) {
      on_today = "today";
    }
    td_array.push(pdom.ce('td', {'class' : 'td_march_' + on_today},
                          [pdom.tn('\u00A0')]));
  }
  else {
    td_array.push(pdom.ce('td', {'class' : 'td_march_off'},
                          [pdom.tn('\u00A0')]));
  }
  // April to October
  for (var i = 20; i <= 80; i += 10) { //"month"
    for (var j = 0; j <= 3; j++) { //"week"
      var k = i + j;
      var on_off = "off";
      if (k >= bloom_start && k < bloom_end) {
	on_off = "on";
	if (k == bloom_code_today) {
	  on_off = "today";
	}
      }
      td_array.push(pdom.ce('td', {'class' : 'td_' + j + '_' + on_off},
                            [pdom.tn('\u00A0')]));
    }
  }
  return td_array;
};

// Return a month <th> element for the bloom table.
var get_month_th = function(month, columnspan) {

  return pdom.ce('th', {'class' : 'table_header',
                        'scope' : 'col',
                        'colspan' : columnspan},
                 [pdom.tn(month)]);
};

// Return an array of <th> elements for the months in the bloom table.
var get_table_months = function() {

  var return_array = [];
  return_array.push(get_month_th('M', 1));
  return_array.push(get_month_th('Apr', 4));
  return_array.push(get_month_th('May', 4));
  return_array.push(get_month_th('June', 4));
  return_array.push(get_month_th('July', 4));
  return_array.push(get_month_th('Aug', 4));
  return_array.push(get_month_th('Sep', 4));
  return_array.push(get_month_th('Oct', 4));
  return return_array;
};

// The global "non-natives only" check has been changed.
var invasive_onclick_handler = function() {

  var query_data = { "sort" : $("#sort_value").val() };

  var invasives_only = box_is_checked("invasive_box");
  if (invasives_only) {
    query_data["invasive"] = "true";
  }
  if (box_is_checked("closeups_box")) {
    if (pbc.closeups_text_cache[invasives_only] === '') {
      // Request new closeup data.
      set_loading("closeups_loading");
      query_data["closeups"] = "true";
    }
    else { // load the old data
      closeups_oncheck_function();
    }
  }
  if (box_is_checked("blooms_box")) {
    set_loading("blooms_loading");
    query_data["bloom_order"] = get_bloom_order();
  }
  set_loading("plant_list_loading");
  request_ajax_data(query_data, get_open_list_plants());
};

var scroll_to_first_open_plant = function(plant_list) {

  var first_open = "";
  if (plant_list.length) {
    for (var i = 0, length = pbc.list_plants.length; i < length; ++i) {
      var key = pbc.list_plants[i];
      var elt = $("#" + key + "_inner");
      if (elt.children(":first-child").length && plant_list.contains(key)) {
	first_open = key;
	break;
      }
    }
  }
  else {
    for (var i = 0, length = pbc.list_plants.length; i < length; ++i) {
      var key = pbc.list_plants[i];
      var elt = $("#" + key + "_inner");
      if (elt.children(":first-child").length) {
	first_open = key;
	break;
      }
    }
  }
  if (first_open) {
    scroll_to_element(first_open);
  }
};

// Return a function that returns the plants in the list returned by
// check_plants_array_function that are checked (where you need to append
// checkbox_id_extension to the names returned by check_plants_array_fucntion to
// find the checkbox to examine) -- just read the darn function ;-)
var create_get_checked_plants_function = function(check_plants_array_function,
                                                  checkbox_id_extension) {

  return function() {
    var return_array = [];
    var check_plants =
        check_plants_array_function()[box_is_checked("invasive_box")];
    for (var i = 0, length = check_plants.length; i < length; ++i) {
      var key = check_plants[i];
      var elt = $('#' + key + checkbox_id_extension);
      if (elt.prop("checked")) {
	return_array.push(key);
      }
    }
    return return_array;
  };
};

var create_checks_clearer = function(get_checks_function,
                                     checkbox_id_extension) {

  return function() {
    var checked_boxes = get_checks_function();
    for (var i = 0, length = checked_boxes.length; i < length; ++i) {
      $('#' + checked_boxes[i] + checkbox_id_extension).prop("checked", false);
    }
  };
};

var box_is_checked = function(box_id) {

  // (We may use the return value as an object key, so return an int, not a
  // bool (IE doesn't like bool keys).)
  var box = $('#' + box_id);
  if (box.length) {
    return box.prop("checked") ? 1 : 0;
  }
  return 0;
};

// Set the loading_id "loading..." div to display loading_text (call clear_timer
// on the same loading_id to stop).
var set_loading = function(loading_id, loading_text) {

  if (pbc.timers[loading_id]["timer_id"]) { // timer's already running
    return;
  }

  if (!loading_text) {
    loading_text = "Loading";
  }
  $('#' + loading_id).show();
  var timer_function = function() {
    var ellipses_nodes = [$(document.createTextNode(loading_text))];
    var ellipse_index = pbc.timers[loading_id]["count"];
    pbc.timers[loading_id]["count"] = (pbc.timers[loading_id]["count"] + 1) % 3;
    for (var i = 0; i < 3; ++i) {
      if (i != ellipse_index) {
        ellipses_nodes.push($(document.createTextNode('..')));
      }
      else {
        ellipses_nodes.push($("<span>").css("color", "#84A884").text('..'));
      }
    }
    $('#' + loading_id).empty().append(ellipses_nodes);
  };
  timer_function();
  pbc.timers[loading_id]["timer_id"] = setInterval(timer_function, 400);
};

// Cf. set_loading.
clear_timer = function(loading_id) {

  if (pbc.timers[loading_id]["timer_id"]) {
    clearInterval(pbc.timers[loading_id]["timer_id"]);
    pbc.timers[loading_id]["timer_id"] = 0;
    pbc.timers[loading_id]["count"] = 0;
  }
  $('#' + loading_id).hide();
};

// Create a function that opens all of the plants returned by
// get_checks_function, and scroll to the first opened one.
var create_open_checks_function = function(get_checks_function) {

  return function() {
    var checked_plants = get_checks_function();
    if (checked_plants.length > 0) {
      //    collapse_all_function();
      for (var i = 0, length = checked_plants.length; i < length; ++i) {
	pbc.plants[checked_plants[i]].display("expand");
      }
      scroll_to_first_open_plant(checked_plants);
    }
  };
};

// Input is an array of user input possible plant names in shortened latin form.
// Open those that validate and scroll to the first one. This function should
// only be called at load time, and it alters maybe_plants.
var open_maybe_plants = function(maybe_plants) {

  // Uppercase first letter of each candidate plant.
  for (var i = 0, length = maybe_plants.length; i < length; ++i) {
    var this_maybe_plant = maybe_plants[i];
    // http://stackoverflow.com/questions/1026069/capitalize-first-letter-of-string-in-javascript
    maybe_plants[i] = this_maybe_plant.charAt(0).toUpperCase() +
      this_maybe_plant.slice(1);
  }

  // Get the short latin names from the pbc.plants keys.
  var short_latin_names = [];
  for (var plant in pbc.plants) {
    short_latin_names.push(plant);
  }

  validated_plants = [];
  for (var i = 0, iLength = short_latin_names.length; i < iLength; ++i) {
    for (var j = 0, jLength = maybe_plants.length; j < jLength; ++j) {
      var this_maybe_plant = maybe_plants[j];
      if (this_maybe_plant === short_latin_names[i]) {
        var validated_plant = short_latin_names[i];
        validated_plants.push(validated_plant);
        pbc.plants[validated_plant].display("expand");
        // Delete this maybe plant.
        maybe_plants.splice(j, 1);
        --j;
      }
    }
    if (maybe_plants.length == 0) {
      break;
    }
  }
  scroll_to_first_open_plant(validated_plants);
};

// Collapse all plants in the plant list.
var collapse_all_function = function() {

  for (var i = 0, length = pbc.list_plants.length; i < length; ++i) {
    pbc.plants[pbc.list_plants[i]].display("collapse");
  }
  scroll_to_element("select_form_div");
};

var create_called_function = function(callee, expand_collapse) {

  return function() {
    pbc.plants[callee].display(expand_collapse);
  };
};

var create_called_jump_function = function(callee, expand_collapse) {

  return function() {
    pbc.plants[callee].display(expand_collapse);
    scroll_to_element(callee);
  };
};

var handle_plant_click = function(short_latin, expand_collapse, jump) {

  pbc.plants[short_latin].display(expand_collapse);
  if (jump === "jump") {
    scroll_to_element(short_latin);
  }
};

/* http://www.quirksmode.org/js/findpos.html */
var height_of_element = function(element) {

  var curtop = 0;
  if (element.offsetParent) {
    do {
      curtop += element.offsetTop;
    } while (element = element.offsetParent);
  }
  return curtop;
};

//var scroll_to_element = function(element_id) {

//  var element = $(element_id);
//  if (element) {
//    window.scrollTo(0, height_of_element(element));
//  }
//  else {
//    window.scrollTo(0,0);
//  }
//};

var scroll_to_element = function(element_id) {

  var min_scroll_time = 200;
  var max_scroll_time = 700;
  var full_scroll =
      $("#links_div").offset().top - $("#closeups_title").offset().top;

  var cur_scroll = $(window).scrollTop();
  var scroll_to = $("#" + element_id).offset().top;
  var scroll_ratio = Math.abs(scroll_to - cur_scroll) / full_scroll;
  var scroll_time = Math.max(max_scroll_time * scroll_ratio, min_scroll_time);
  $("html, body").animate({scrollTop: scroll_to}, scroll_time);
};

var error_alert = function(message) {

  if (!message) {
    alert("Sorry, your browser doesn't seem to be supported - a more recent browser may be required.");
  }
  else {
    alert(message);
  }
};

var get_checked_closeups =
    create_get_checked_plants_function(get_closeup_plants_array, "_checkbox");
var get_checked_blooms =
    create_get_checked_plants_function(get_bloom_plants_array, "_bloom_check");
var clear_closeup_checks =
    create_checks_clearer(get_checked_closeups, "_checkbox");
var clear_bloom_checks =
    create_checks_clearer(get_checked_blooms, "_bloom_check");
var open_checked_closeups = create_open_checks_function(get_checked_closeups);
var open_checked_blooms = create_open_checks_function(get_checked_blooms);

// Find the right xmlhttprequest generator for this browser
// (based on Javascript: the definitive guide by David Flanagan (O'Reilly 2006))
var ajax_object = (function() {

  var factories = [function() { return new XMLHttpRequest(); },
                   function() { return new ActiveXObject("MSXML2.XMLHTTP"); },
                   function() { return new ActiveXObject("Microsoft.XMLHTTP"); }];
  for (var i = 0, length = factories.length; i < length; ++i) {
    try {
      var factory = factories[i];
      var request = factory();
      if (request != null) {
	return factory;
      }
    }
    catch(e) {
      continue;
    }
  }
  return error_alert;
})();

// http://stackoverflow.com/questions/237104/javascript-array-containsobj
Array.prototype.contains = function(obj) {

  var i = this.length;
  while (i--) {
    if (this[i] === obj) {
      return true;
    }
  }
  return false;
};

<?php
///////////////////////// Create the plant objects
// pbc.plants holds all data for each plant in the database.
$result = $mysql->query("select pbc_images,owen_images,arb_images,garner_images,
  short_latin,bloom,invasive,wi,color,c_value,w_i,notes,
  common,latin,family,aliases from flora");
while ($entries = $result->fetch_assoc()) {
  $short_latin = $entries['short_latin'];
  $expand_collapse = $short_latin . "_ec";
  //// images
  $image_list = $entries["pbc_images"] . "," . $entries["owen_images"] .
    "," . $entries["arb_images"] . "," . $entries["garner_images"];
  $images = explode(",", $image_list);
  // Create a javascript array of images.
  $js_image_list = "[";
  foreach ($images as $image) {
    if ($image != "") {
      $js_image_list .= $image . ",";
    }
  }
  // Remove the trailing comma.
  if ($js_image_list != "[") {
    $js_image_list = substr($js_image_list, 0, strlen($js_image_list)-1);
  }
  $js_image_list .= "]";
  //// bloom period
  $bloom = '';
  $bloom_value = $entries['bloom'];
  if ($bloom_value != '') {
    $start = code_to_string($bloom_value, "start");
    $end = code_to_string($bloom_value, "end");
    $bloom = $start . " to " . $end;
  }
  //// wetland indicator
  $wetland_indicator = '';
  $wetland_indicator_value = $entries['w_i'];
  if ($wetland_indicator_value != '') {
    $wetland_indicator = strtoupper($wetland_indicator_value);
  }
  //// non-native
  $inv_text = '';
  $inv_text_value = $entries['invasive'];
  if ($inv_text_value != '') {
    switch ($inv_text_value) {
    case 'y':
      $inv_text = "weedy";
      break;
    case 'yes':
      $inv_text = "potentially invasive";
      break;
    case 'Yes':
      $inv_text = " ";
      break;
    case 'YES':
      $inv_text = "invasive -- consider control";
      break;
    }
  }
  //// WI restricted
  $wi_text = '';
  $wi_text_value = $entries['wi'];
  if ($wi_text_value != '') {
    switch ($wi_text_value) {
    case 'r':
      $wi_text = ":::: Restricted in Wisconsin ::::";
      break;
    case 'p':
      $wi_text = ":::: Prohibited in Wisconsin ::::";
      break;
    }
  }
  //// notes
  $notes = strtr($entries['notes'], array("\n"=>""));
  // &#39; is the html entity for '
  $notes = strtr($notes, array("'"=>'&#39;'));
  $common = strtr($entries['common'], array("'"=>'&#39;'));
  $latin = $entries['latin'];
  $family = strtr($entries['family'], array("'"=>'&#39;'));
  $aliases = strtr($entries['aliases'], array("'"=>'&#39;'));
  echo <<<OUT
    pbc.plants["$short_latin"] =
    new Plant("$short_latin", $js_image_list,
              "{$entries['color']}", "$bloom", "$inv_text", "$wi_text",
              '$notes', "{$entries['c_value']}", "$wetland_indicator",
              '$common', '$latin', '$family', '$aliases');

OUT;
} // end while(entries)
$result->free();
?>

$(document).ready(function() {

  $("#closeups_box, #blooms_box, #invasive_box").prop("checked", false);
  $("#bloom_radio_date_option").prop("checked", true);

  $("#select_form_common").prop("selected", true);

  $("#site_notes_button").on("click", function() {

    var button_img = $("#site_notes_img");

    var currently_collapsed =
        (button_img.attr("src").lastIndexOf('up.png') === -1) ? true : false;
    if (currently_collapsed) {
      $("#site_notes_content").show();
      button_img.attr("src", "up.png");
    }
    else {
      $("#site_notes_content").hide();
      button_img.attr("src", "down.png");
    }
  });

  // Generate the initial load - only fill in all of the missing controls
  // if we get a successful response.
  var initial_load_function = function(dom) {
      // We fill in most of the fully loaded page here since until we get the
      // plant list, nothing else is going to work.

      $("#closeups_title, #closeups_div, #plant_list_title, " +
        "#select_form_div, #footer_wrap").show();
      $("#blooms_loading, #inner_blooms_div").hide();

      $("#inner_closeups_div").on("click", "span.link_text", function() {
        var short_latin = this.id.slice(0,-8);
        handle_plant_click(short_latin, "expand", "jump");
        return false;
      });
      $("#closeups_box").on("click", closeups_oncheck_function);
      $("#open_checks").on("click", open_checked_closeups);
      $("#clear_checkboxes").on("click", clear_closeup_checks);

      $("#plant_list_div").on("click", "span.expand_collapse", function() {
        var short_latin = this.id.slice(0, -3);
        handle_plant_click(short_latin, "switch");
        return false;
      });

      $("#blooms_body").on("click", "span.anchor_link", function() {
        var short_latin = this.id.slice(0, -4);
        handle_plant_click(short_latin, "expand", "jump");
        return false;
      });
      $("#blooms_box").on("click", blooms_oncheck_function);
      $("input[name=bloom_order]").on("click", bloom_function);
      $("#bloom_open_checks").on("click", open_checked_blooms);
      $("#bloom_clear_checks").on("click", clear_bloom_checks);

      var mql = window.matchMedia("(min-width: 800px)");
      var mqHandler = function (mql) {
        if (mql.matches) {
          $("#bloom_table_div").show();
        }
        else {
          $("#bloom_table_div").hide();
        }
      };
      mql.addListener(mqHandler);
      mqHandler(mql);

      $("#sort_value").on("change", function() {
        var sort_value = $("#sort_value").val();
        var query_data = { "sort" : sort_value };
        if (box_is_checked("invasive_box")) {
          query_data["invasive"] = "true";
        }
        set_loading("plant_list_loading");
        request_ajax_data(query_data, get_open_list_plants());
      });

      //// Fill in the plant list data.
      ajax_handler()(dom);
      clear_timer("initial_plant_list_loading");

      //// Bottom bar stuff.
      ////// collapse
      $("#collapse_text").on("click", collapse_all_function);
      $("#invasive_box").on("click", invasive_onclick_handler);

      $("#goto_closeups").on("click", function() {
        scroll_to_element("closeups_title")
      });
      $("#goto_list").on("click", function() {
        scroll_to_element("plant_list_title");
      });
      $("#goto_table").on("click", function() {
        scroll_to_element("bloom_table_div");
      });
      $("#goto_links").on("click", function() {
        scroll_to_element("links_div");
      });

      //// If the initial location included an anchor, jump and open it.
      var search = location.search;
      if (search && search.slice(0, 7) === '?plant=') {
        search_plants = search.slice(7);
        var search_plants_array = search_plants.split(',');
        // Only keep the first 25.
        search_plants_array = search_plants_array.slice(0, 25);
        open_maybe_plants(search_plants_array);
      }
  }; // end initial_load_function

  // Set loading divs while we fetch the initial data.
  set_loading("initial_plant_list_loading", "Loading plant list");

  // Request the initial plant list.
  $.ajax({
    url: "pbc_flora_ajax.php",
    data: { "new" : "true", "sort" : "common" },
    type: "GET",
    dataType: "xml",
  })
    .done(initial_load_function)
    .fail(function() {
      alert("The request for plant data failed; you can try reloading the " +
            "page or trying again at a later time.  If the problem persists " +
            "please email pbcflora@gmail.com.");
    });

}); // end document DOM ready
})();
<?php

// Return the string for the month/week $code which is either the "start" or
// the "end" of a bloom period ($start_end).
function code_to_string($code, $start_end) {

  static $dictionary = array(13=>"late March",20=>"April",21=>"early April",22=>"mid April",23=>"late April",24=>"April",30=>"May",31=>"early May",32=>"mid May",33=>"late May",34=>"May",40=>"June",41=>"early June",42=>"mid June",43=>"late June",44=>"June",50=>"July",51=>"early July",52=>"mid July",53=>"late July",54=>"July",60=>"August",61=>"early August",62=>"mid August",63=>"late August",64=>"August",70=>"September",71=>"early September",72=>"mid September",73=>"late September",74=>"September",80=>"October",81=>"early October",82=>"mid October",83=>"late October",84=>"October");
  if ($start_end == "start") {
    return $dictionary[intval(substr(strval($code),0,2))];
  }
  else {
    return $dictionary[intval(substr(strval($code),2,2))];
  }
}

?>
