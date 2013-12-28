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

pbc = {}; // the main namespace
// all of the plants we know about and their data - keys are short latin names
pbc.plants = {};

// data for one plant
pbc.Plant = function(short_latin, image_list, flower_color, bloom, invasive,
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
/* control the display of this plant: action is "expand", "collapse",
   or "switch" (flip the current state) */
pbc.Plant.prototype.display = function(action) {

  var plant_inner = document.getElementById(this.short_latin + '_inner');
  // we can get called even when this plant isn't currently being displayed
  if (!plant_inner) {
    return;
  }
  var inner_is_open = (plant_inner.style.display === "block");
  if (action === "expand" || (action === "switch" && !inner_is_open)) {
    var inner_content =
      document.getElementById(this.short_latin + '_inner').firstChild;
    if (!inner_content) { // display this plant's data
      document.getElementById(this.short_latin + '_ec').src =
        'collapse.png';
      var fragment = document.createDocumentFragment();
      var components = [];
      var div_text = "";
      // images
      var images_array = [];
      div_text += "<div>\\n";
      for (var i = 0, length = this.image_list.length; i < length; ++i) {
        var image = pbc.dom.ce('img', {'alt' : this.short_latin,
              'src' : "<?php echo $image_path ?>" +
              this.short_latin + this.image_list[i] + '.jpg'});
        var image_div = pbc.dom.ce('div', {'class' : 'image'}, [image]);
        images_array.push(image_div);
      }
      var images_br = pbc.dom.ce('br', {'class' : 'clearing_break'});
      var images_div = pbc.dom.ce('div', {}, images_array.concat(images_br));
      components.push(images_div);
      // we display these properties if they're set;
      // "t" is text, "v" is value
      var maybe_properties = [{t : 'Flower color:', v : this.color},
                              {t : 'Bloom period:', v : this.bloom},
                              {t : 'C value:', v : this.c_value},
                              {t : 'Wetland indicator:',
                               v : this.w_i},
                              {t : '::Non-native::', v : this.invasive}];
                              
      for (var i = 0, length = maybe_properties.length; i < length; ++i) {
        var thisProperty = maybe_properties[i];
        if (thisProperty.v != '') {
          components.push(pbc.dom.ce('span', {'class' : 'column'},
                                     [pbc.dom.tn(thisProperty.t)]));
          components.push(pbc.dom.tn(thisProperty.v));
          components.push(document.createElement('br'));
        }
      }
      // WI restricted?
      if (this.restricted) {
        components.push(pbc.dom.ce('span', {'class' : 'column'},
                                   [pbc.dom.tn(this.restricted)]));
        components.push(document.createElement('br'));
      }
      // notes
      components.push(pbc.dom.ce('span', {'class' : 'column'},
                                 [pbc.dom.tn('Notes:')]));
      var notes_div = pbc.dom.ce('div', {'class' : 'notes'});
      // we let the browser parse anything that contains html text
      notes_div.innerHTML = this.notes;
      components.push(notes_div);
      
      var data_div = pbc.dom.ce('div', {}, components);
      document.getElementById(this.short_latin).style.borderBottom =
        "1px solid black";
      plant_inner.appendChild(data_div);
      plant_inner.style.display = "block";
    }
    else { // content already exists, just show it
      document.getElementById(this.short_latin + '_ec').src =
        'collapse.png';
      document.getElementById(this.short_latin).style.borderBottom =
        "1px solid black";
      plant_inner.style.display = "block";
    }
  }
  else if ((action == "collapse") || (action == "switch" && inner_is_open)) {
    document.getElementById(this.short_latin + '_inner').style.display = "none";
    document.getElementById(this.short_latin).style.borderBottom = "";
    document.getElementById(this.short_latin + '_ec').src = 'expand.png';
  }
};

///////////////////////// Start ajax handling /////////////////////////////
//// (initially ajax was the source of more of the data, but at this point
//// all we're using it for is getting sorted/trimmed plant lists from sql)
/* return our standard ajax onreadystate change function; open_plants
   is the list of plants that were already open when the ajax request
   was made */
pbc.ajax_handler = function(request, open_plants) {

  return function() {
    if (request.readyState === 4) {
      if (request.status === 200) {
        var dom = request.responseXML;
        if (!dom || !dom.documentElement ||
            dom.documentElement.nodeName === 'parsererror') {
          pbc.error_alert("Ajax error: " + request.responseText.substr(0, 200));
          return;
        }
        //alert(request.responseText);
        var maybe_error = dom.getElementsByTagName('error');
        if (maybe_error[0]) {
          pbc.error_alert(maybe_error[0].firstChild.data);
          return;
        }
        var closeups_xml = dom.getElementsByTagName('closeups')[0];
        pbc.handle_ajax_closeups(closeups_xml);
        var list_xml = dom.getElementsByTagName('list')[0];
        pbc.handle_ajax_list(list_xml, open_plants);
        var blooms_xml = dom.getElementsByTagName('bloom')[0];
        pbc.handle_ajax_blooms(blooms_xml);
      }
    }
  };
};

/* handle return data (dom) from an ajax request for new closeups */
pbc.handle_ajax_closeups = function(dom) {

  if (dom) {
    pbc.clear_timer("closeups_loading");
    var invasives_only =
      dom.getElementsByTagName('invasives_only')[0].firstChild.data;
    invasives_only = (invasives_only === "true") ? 1 : 0;
    var closeups = dom.getElementsByTagName('closeup');
    var fragment = document.createDocumentFragment();
    var latin_array = []; // remember the plants we add
    for (var i = 0, length = closeups.length; i < length; ++i) {
      var thisCloseup = closeups[i];
      var common =
        thisCloseup.getElementsByTagName('common')[0].firstChild.data;
      var latin = thisCloseup.getElementsByTagName('short_latin')[0].firstChild.data;
      latin_array.push(latin);
      var image_number = 
        thisCloseup.getElementsByTagName('image_number')[0].firstChild.data;
      //<div class="closeup"><img class="closeup_img" src="' + '../pbc_flora/flora_images/' + latin + '_' + image_number + 'c.jpg" alt="' + common + '"/><br /><div class="closeup_description"><input type="checkbox" class="inline_checkbox" id="' + latin + '_checkbox"/><span class="link_text" id="' + latin + '_closeup">' + common + '</span></div></div>;
      var text = document.createTextNode(common);
      var link_text = pbc.dom.create_element("span",
                        {"class" : "link_text", "id" : latin + '_closeup'},
                        [text]);
      var checkbox = pbc.dom.create_element("input",
                       {'type' : 'checkbox', 'class' : 'inline_checkbox',
                        'id' : latin + '_checkbox'});
      var description = pbc.dom.create_element('div',
                          {"class" : 'closeup_description'},
                          [checkbox, link_text]);
      var image = pbc.dom.create_element('img',
                    {'class' : 'closeup_img',
                     'src' : '<?php echo $image_path ?>' + latin +
                        image_number + 'c.jpg',
                     'alt' : common});
      var outerDiv = pbc.dom.create_element('div', {'class' : 'closeup'},
                                            [image, description]);
      fragment.appendChild(outerDiv);
    }
    var inner_closeups = document.getElementById("inner_closeups_div");
    // remember this fragment
    pbc.closeups_text_cache[invasives_only] = fragment.cloneNode(true);
    pbc.dom.replace_children(inner_closeups, fragment);
    document.getElementById("closeup_checks").style.display = "block";
    document.getElementById("inner_closeups_div").style.display = "block";
    // remember these plants (for open all checked plants)
    pbc.closeup_plants[invasives_only] = latin_array;
    pbc.update_onclick_handlers(latin_array, '_closeup', "expand", true);
    pbc.update_event_handler("click", document.getElementById("open_checks"),
                             pbc.open_checked_closeups);
    pbc.update_event_handler("click",
                             document.getElementById("clear_checkboxes"),
                             pbc.clear_closeup_checks);
  }
};

/* plant_data_displayer is an object used for creating html fragments
that display the title information for a given plant;
get_data_from_xml retrieves plant data (for one plant) from an xml
fragment, while append_div takes an html fragment as input and appends
a plant entry for the input plant to the fragment; the list_order
argument to the constructor determines what/in what order data is
displayed*/
pbc.plant_data_displayer = function(list_order) {

  //// private data (but see note on getters)
  // each plant display looks like title_div followed by inner_div
  // (cf. write_divs below)
  var title_div = null;
  var inner_div = null;
  var common = null;
  var aliases_fragment = null;
  var latin = null;
  var short_latin = null;
  var family = null;
  var title_prefix_tag = '';
  var title_prefix = null;
  var write_divs = function(fragment) {
    // apparently appending a null child raises an exception
    if (aliases_fragment) {
      title_div.appendChild(aliases_fragment);
    }
    fragment.appendChild(title_div);
    fragment.appendChild(inner_div);
  };
  // end private data

  var that = {}; // our return plant display object
  that.short_latin = function() { return short_latin; }

  // set this object's data values from an xml fragment argument
  // this object is created once but repopulated many times by this
  // function, so be sure to reset every member on every call
  that.get_data_from_xml = function(plant_xml) {

    short_latin = plant_xml.getElementsByTagName('short_latin')[0].firstChild.data;
    var plant = pbc.plants[short_latin];
    
    if (plant.aliases) {
      aliases_fragment = document.createDocumentFragment();
      aliases_fragment.appendChild(document.createElement('br'));
      var aliases_node = pbc.dom.ce('span', {'class' : 'alias'});
      // (we use innerHTML for any text that may contain html markup)
      aliases_node.innerHTML = 'Aliases: ' + plant.aliases;
      aliases_fragment.appendChild(aliases_node);
    }
    else {
      aliases_fragment = null;
    }
    common = plant.common;
    latin = plant.latin;
    family = plant.family;
    var image = pbc.dom.create_element('img',
      {'class' : 'expand_collapse', 'id' : short_latin + '_ec',
          'src' : 'expand.png', 'alt' : 'expand or collapse ' + latin});
    title_div = pbc.dom.create_element('div',
      {'id' : short_latin, 'class' : 'outer'}, [image]);
    inner_div = pbc.dom.create_element('div',
      {'id' : short_latin + '_inner', 'class' : 'inner'});
    if (title_prefix_tag) {
      var maybe_prefix_value = plant[title_prefix_tag];
      title_prefix = maybe_prefix_value ? maybe_prefix_value + ': ' : '';
    }
    else {
      title_prefix = '';
    }
  };

  // append_div takes a fragment as argument and adds this object's data to
  // the fragment as appropriate, depending on list_order
  that.append_div = (function() {

      switch(list_order) {
      case "common":
      case "bloom":
      case "color":
      case "c_value":
      case "w_i":
        title_prefix_tag = list_order;
        if (list_order === 'common' || list_order == 'bloom') {
          title_prefix_tag = '';
        }
        return function(fragment) {
          
          title_div.innerHTML +=
            '<span class="title"><span class="main_title">' +
            title_prefix + common + ' (<span class="latin">' + latin +
            '</span>) [' + family + ']</span>';
          write_divs(fragment);
        };
        break;

      case "latin":
        title_prefix_tag = '';
        return function(fragment) {
          title_div.innerHTML +=
            '<span class="title"><span class="main_title latin">' + latin +
            '</span> (' + common + ') [' + family + ']</span>';
          write_divs(fragment);
        };
        break;

      case "family":
        title_prefix_tag = '';
        return function(fragment) {
          title_div.innerHTML +=
            '<span class="title"><span class="main_title">' + family +
            '</span> [' + common + ' (<span class="latin">' + latin +
            '</span>)]</span>';
          write_divs(fragment);
        };
        break;

      default:
        alert ("oOps - bad list order: " + list_order);
        break;
      }//end switch
    })();

  return that;
};

/* handle return data (dom) from an ajax request for new plant list
data; open_plants are the plants from the plant list that were open
before the current request - open any on the old list that are present
in the new list */
pbc.handle_ajax_list = function(dom, open_plants) {

  if (dom) {
    pbc.clear_timer("plant_list_loading");
    var list = dom.getElementsByTagName('plant');
    var order = dom.getElementsByTagName('order')[0].firstChild.data;
    if (order === 'flower') {
      order = 'color'; // oOps
    }
    var data_processor = pbc.plant_data_displayer(order);
    var fragment = document.createDocumentFragment();
    var latin_array = []; // remember the plants we add
    for (var i = 0, length = list.length; i < length; ++i) {
      data_processor.get_data_from_xml(list[i]);
      data_processor.append_div(fragment);
      latin_array.push(data_processor.short_latin());
    }
    // the last one is special (TODO: this will break)
    fragment.lastChild.previousSibling.setAttribute('class', 'outer last_outer');
    pbc.dom.replace_children(document.getElementById("plant_list_div"), fragment);
    pbc.list_plants = latin_array;
    pbc.update_onclick_handlers(latin_array, '_ec', 'switch');
    var count = pbc.dom.tn("(" + latin_array.length + " plants)");
    pbc.dom.replace_children(document.getElementById("plant_count"), count);
    // reopen ids that were open before the request
    for (var i = 0, length = open_plants.length; i < length; ++i) {
      pbc.plants[open_plants[i]].display("expand");
    }
  }
};

/* handle return data (dom) from an ajax request for new bloom table
data */
pbc.handle_ajax_blooms = function(dom) {

  if (dom) {
    pbc.clear_timer("blooms_loading");
    pbc.clear_timer("internal_blooms_loading");
    var invasives_only = dom.getElementsByTagName('invasives_only')[0].firstChild.data;
    invasives_only = (invasives_only === "true") ? 1 : 0;
    var bloom_code_today = +dom.getElementsByTagName('bloom_code_today')[0].firstChild.data;
    var bloom_order = dom.getElementsByTagName('order')[0].firstChild.data;
    if (bloom_order == 'common') {
      document.getElementById('bloom_radio_common_option').checked = 'checked';
    }
    else {
      document.getElementById('bloom_radio_date_option').checked = 'checked';
    }
    // remove the old table body
    var table = document.getElementById('bloom_table');
    var table_body = table.tBodies[0];
    for (var i = table_body.rows.length - 1; i >= 0; --i) {
      table_body.deleteRow(i);
    }
    var latin_array = []; // remember the plants in the table
    var blooms = dom.getElementsByTagName('bloom_data');
    var number_of_blooms = blooms.length;
    var fragment = document.createDocumentFragment();
    // create the rows
    for (var i = 0, length = blooms.length; i < length; ++i) {
      var thisBloom = blooms[i];
      var common = thisBloom.getElementsByTagName('common')[0].firstChild.data;
      var short_latin = thisBloom.getElementsByTagName('short_latin')[0].firstChild.data;
      latin_array.push(short_latin);
      var anchor_id = short_latin + '_ref';
      var latin_id = short_latin + '_bloom_check';
      var blooming = "";
      var bloom_start = +thisBloom.getElementsByTagName('bloom_start')[0].firstChild.data;
      var bloom_end = +thisBloom.getElementsByTagName('bloom_end')[0].firstChild.data;
      if (bloom_start <= bloom_code_today && bloom_end > bloom_code_today) {
        blooming = 'th_blooming';
      }
      //// create a row
      //<tr><th><span class="' + blooming + '"><input type="checkbox" class="inline_checkbox" id="' + latin_id + '"/><span class="anchor_link" id="' + anchor_id + '">' + common + '</span></th>' + bloom_row + '</tr>
      var title_checkbox = pbc.dom.ce('input', {'type' : 'checkbox',
            'class' : 'inline_checkbox', 'id' : latin_id});
      var title_link = pbc.dom.ce('span', {'class' : 'anchor_link', 'id' : anchor_id},
                            [pbc.dom.tn(common)]);
      var title_span = pbc.dom.ce('span', {'class' : blooming},
                                  [title_checkbox, title_link]);
      var title_th = pbc.dom.ce('th', {}, [title_span]);
      var bloom_row = pbc.get_bloom_row(bloom_start, bloom_end, bloom_code_today);
      var tr = pbc.dom.ce('tr', {}, [title_th].concat(bloom_row));
      fragment.appendChild(tr);
      if (i > 0 && i % 20 == 0 && i + 4 < number_of_blooms) {
        // add another months row
        var empty_th = pbc.dom.ce('th', {'class' : 'table_header',
              'scope' : 'col'});
        var months_array = pbc.get_table_months();
        var months_row = pbc.dom.ce('tr', {}, [empty_th].concat(months_array));
        fragment.appendChild(months_row);
      }
    }
    table_body.appendChild(fragment);
    document.getElementById("inner_blooms_div").style.display = "block";
    // click on a plant name opens the plant
    pbc.update_onclick_handlers(latin_array, '_ref', "expand", true);
    // store the current list (for open all checked)
    pbc.bloom_plants[invasives_only] = latin_array;
  }
};
///////////////////////// End ajax handling ///////////////////////////////

/* return the list of currently open plants */
pbc.get_open_list_plants = function() {

  var return_array = [];
  for (var i = 0, length = pbc.list_plants.length; i < length; ++i) {
    var key = pbc.list_plants[i];
    var elt = document.getElementById(key + "_inner");
    if (elt && elt.firstChild) {
      return_array.push(key);
    }
  }
  return return_array;
};

pbc.get_closeup_plants_array = function() {

  return pbc.closeup_plants;
};

/* the closeups check has changed */
pbc.closeups_oncheck_function = function() {

  var checked = pbc.box_is_checked("closeups_box");
  if (checked) {
    var closeup_div = document.getElementById("inner_closeups_div");
    var invasives_only = pbc.box_is_checked("invasive_box");
    var closeup_fragment = pbc.closeups_text_cache[invasives_only];
    if (!closeup_fragment) { // get the data
      var closeups_string = "closeups=true";
      if (invasives_only) {
	closeups_string += "&invasive=true";
      }
      var request = pbc.ajax_object();
      request.onreadystatechange = function() {
	if (request.readyState === 1) {
	  closeup_div.style.display = "block";
	  pbc.set_loading("closeups_loading");
	}
	else if (request.readyState === 4) {
	  pbc.ajax_handler(request, [])();
	}
      };
      request.open("GET", "pbc_flora_ajax.php?" + closeups_string, true);
      request.send(null);
    }
    else { // load saved data
      document.getElementById("closeup_checks").style.display = "block";
      pbc.closeups_text_cache[invasives_only] = closeup_fragment.cloneNode(true);
      pbc.dom.replace_children(closeup_div, closeup_fragment);
      closeup_div.style.display = "block";
      var name_array = pbc.closeup_plants[invasives_only];
      pbc.update_onclick_handlers(name_array, '_closeup', "expand", true);
      pbc.update_event_handler("click", document.getElementById("open_checks"), pbc.open_checked_closeups);
      pbc.update_event_handler("click", document.getElementById("clear_checkboxes"), pbc.clear_closeup_checks);
    }
  }
  else { // closeups not checked    
    document.getElementById("closeup_checks").style.display = "none";
    document.getElementById("inner_closeups_div").style.display = "none";
  }
};

pbc.get_bloom_plants_array = function() {

  return pbc.bloom_plants;
};

/* the bloom table check has changed */
pbc.blooms_oncheck_function = function() {

  if (pbc.box_is_checked("blooms_box")) {
    var invasives_only = pbc.box_is_checked("invasive_box");
    var blooms_string = "bloom_order=" + pbc.get_bloom_order();
    if (invasives_only) {
      blooms_string += "&invasive=true";
    }
    var request = pbc.ajax_object();
    request.onreadystatechange = function() {
      if (request.readyState === 1) {
        pbc.set_loading("blooms_loading");
      }
      else if (request.readyState === 4) {
        pbc.ajax_handler(request, [])();
      }
    };
    request.open("GET", "pbc_flora_ajax.php?" + blooms_string, true);
    request.send(null);
  }
  else { // blooms not checked
    document.getElementById("inner_blooms_div").style.display = "none";
  }
};

/* user changed the bloom table order */
pbc.bloom_function = function() {

  pbc.set_loading("internal_blooms_loading");
  var get_string = "bloom_order=" + pbc.get_bloom_order();
  var list_value = document.getElementById("sort_value").value;
  if (pbc.box_is_checked("invasive_box")) {
    get_string += "&invasive=true";
  }
  var request = pbc.ajax_object();
  request.onreadystatechange = pbc.ajax_handler(request);
  request.open("GET", "pbc_flora_ajax.php?" + get_string, true);
  request.send(null);
};


/* return the value of the bloom table order radio buttons */
pbc.get_bloom_order = function() {
  
  var bloom_form = document.getElementById("bloom_form");
  if (bloom_form) {
    var bloom_radio_array = bloom_form.bloom_order;
    for (var i = 0, length = bloom_radio_array.length; i < length; ++i) {
      if (bloom_radio_array[i].checked) {
        return bloom_radio_array[i].value;
      }
    }
  }
  else {
    return "bloom";
  }
};

/* return the table row text for a plant with the input bloom data */
pbc.get_bloom_row = function(bloom_start, bloom_end, bloom_code_today) {

  // [Note: \u00A0 is unicode for &nbsp; - DOM doesn't do entities]
  var td_array = [];
  // late March is a special case
  if (bloom_start == 13) {
    var on_today = "on";
    if (bloom_code_today == 13) {
      on_today = "today";
    }
    td_array.push(pbc.dom.ce('td', {'class' : 'td_march_' + on_today}, 
                             [pbc.dom.tn('\u00A0')]));
  }
  else {
    td_array.push(pbc.dom.ce('td', {'class' : 'td_march_off'}, 
                             [pbc.dom.tn('\u00A0')]));
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
      td_array.push(pbc.dom.ce('td', {'class' : 'td_' + j + '_' + on_off}, 
                               [pbc.dom.tn('\u00A0')]));
    }
  }
  return td_array;
};

/* return a month th element for the bloom table */
pbc.get_month_th = function(month, columnspan) {

  return pbc.dom.ce('th', {'class' : 'table_header', 'scope' : 'col',
        'colspan' : columnspan}, [pbc.dom.tn(month)]);
};

/* return an array of <th> elements for the months in the bloom table */
pbc.get_table_months = function() {

  var return_array = [];
  return_array.push(pbc.get_month_th('M', 1));
  return_array.push(pbc.get_month_th('Apr', 4));
  return_array.push(pbc.get_month_th('May', 4));
  return_array.push(pbc.get_month_th('June', 4));
  return_array.push(pbc.get_month_th('July', 4));
  return_array.push(pbc.get_month_th('Aug', 4));
  return_array.push(pbc.get_month_th('Sep', 4));
  return_array.push(pbc.get_month_th('Oct', 4));
  return return_array;
};

/* the global "non-natives only" check has been changed */
pbc.invasive_onclick_handler = function() {

  var get_string = "sort=" + document.getElementById("sort_value").value;
  var invasives_only = pbc.box_is_checked("invasive_box");
  if (invasives_only) {
    get_string += "&invasive=true";
  }
  if (pbc.box_is_checked("closeups_box")) {
    if (pbc.closeups_text_cache[invasives_only] === '') {
      // request new closeup data
      pbc.set_loading("closeups_loading");
      get_string += "&closeups=true";
    }
    else { // load the old data
      pbc.closeups_oncheck_function();
    }
  }
  if (pbc.box_is_checked("blooms_box")) {
    pbc.set_loading("blooms_loading");
    get_string += "&bloom_order=" + pbc.get_bloom_order();
  }
  pbc.set_loading("plant_list_loading");
  var request = pbc.ajax_object();
  request.onreadystatechange = function() {
    if (request.readyState === 4) {
      pbc.ajax_handler(request, pbc.get_open_list_plants())();
      //window.scrollTo(0,0);
    }
  };
  request.open("GET", "pbc_flora_ajax.php?" + get_string, true);
  request.send(null);
};

pbc.scroll_to_first_open_plant = function(plant_list) {

  var first_open = "";
  if (plant_list.length) {
    for (var i = 0, length = pbc.list_plants.length; i < length; ++i) {
      var key = pbc.list_plants[i];
      var elt = document.getElementById(key + "_inner");
      if (elt && elt.firstChild && plant_list.contains(key)) {
	first_open = key;
	break;
      }
    }
  }
  else {
    for (var i = 0, length = pbc.list_plants.length; i < length; ++i) {
      var key = pbc.list_plants[i];
      var elt = document.getElementById(key + "_inner");
      if (elt && elt.firstChild) {
	first_open = key;
	break;
      }
    }
  }
  if (first_open) {
    pbc.scroll_to_element(first_open);
  }
};

/* return a function that returns the plants in the list returned by
   check_plants_array_function that are checked (where you need to
   append checkbox_id_extension to the names returned by
   check_plants_array_fucntion to find the checkbox to examine) --
   just read the darn function ;-)*/
pbc.create_get_checked_plants_function = function(check_plants_array_function,
                                                  checkbox_id_extension) {

  return function() {
    var return_array = [];
    var check_plants = check_plants_array_function()[pbc.box_is_checked("invasive_box")];
    for (var i = 0, length = check_plants.length; i < length; ++i) {
      var key = check_plants[i];
      var elt = document.getElementById(key + checkbox_id_extension);
      if (elt && elt.checked) {
	return_array.push(key);
      }
    }
    return return_array;
  };
};

pbc.create_checks_clearer = function(get_checks_function,
                                     checkbox_id_extension) {

  return function() {
    var checked_boxes = get_checks_function();
    for (var i = 0, length = checked_boxes.length; i < length; ++i) {
    document.getElementById(checked_boxes[i] + checkbox_id_extension).checked =
      false;
    }
  };
};

pbc.box_is_checked = function(box_id) {
  
  // we may use the return value as an object index, so don't return a bool
  // since IE doesn't allow it as an index
  var box = document.getElementById(box_id);
  if (box) {
    return box.checked ? 1 : 0;
  }
  else {
    return 0;
  }
};

/* set the loading_id "loading..." div to display loading_text (call
   clear_timer on the same loading_id to stop)*/
pbc.set_loading = function(loading_id, loading_text) {

  if (pbc.timers[loading_id]["timer_id"]) { // timer's already running
    return;
  }

  if (!loading_text) {
    loading_text = "Loading";
  }
  if (!document.getElementById(loading_id)) {
    alert(loading_id);
  }
  document.getElementById(loading_id).style.display = "block";
  var timer_function = function() {
    var ellipses_nodes = [pbc.dom.tn(loading_text)];
    var ellipse_index = pbc.timers[loading_id]["count"];
    pbc.timers[loading_id]["count"] = (pbc.timers[loading_id]["count"] + 1)%3;
    for (var i = 0; i < 3; ++i) {
      if (i != ellipse_index) {
        ellipses_nodes.push(pbc.dom.tn('..'));
      }
      else {
        ellipses_nodes.push(pbc.dom.ce('span', {'style' : 'color: #84A884'},
                                       [pbc.dom.tn('..')]));
      }
    }
    pbc.dom.replace_children(document.getElementById(loading_id), ellipses_nodes);
  };
  timer_function();
  pbc.timers[loading_id]["timer_id"] = setInterval(timer_function, 400);
};

/* cf. pbc.set_loading */
pbc.clear_timer = function(loading_id) {

  if (pbc.timers[loading_id]["timer_id"]) {
    clearInterval(pbc.timers[loading_id]["timer_id"]);
    pbc.timers[loading_id]["timer_id"] = 0;
    pbc.timers[loading_id]["count"] = 0;
  }
  var loading_element = document.getElementById(loading_id);
  if (!loading_element) {
    alert("Lost loading id: " + loading_id);
    return;
  }
  loading_element.style.display = "none";
};

pbc.update_onclick_handlers = function(name_array, id_extension, expand_collapse, jump) {

  var create_function = jump ? pbc.create_called_jump_function : pbc.create_called_function;
  var failed = false;
  for (var i = 0, length = name_array.length; i < length; ++i) {
    var elt = document.getElementById(name_array[i] + id_extension);
    if(!elt && !failed) {
      failed = true;
      alert("no element for " + name_array[i] + id_extension);
      return;
    }
    pbc.update_event_handler("click", elt, create_function(name_array[i], expand_collapse));
  }
};

/* create a function that opens all of the plants returned by
   get_checks_function, and scroll to the first opened one */
pbc.create_open_checks_function = function(get_checks_function) {

  return function() {
    var checked_plants = get_checks_function();
    if (checked_plants.length > 0) {
      //    pbc.collapse_all_function();
      for (var i = 0, length = checked_plants.length; i < length; ++i) {
	pbc.plants[checked_plants[i]].display("expand");
      }
      pbc.scroll_to_first_open_plant(checked_plants);
    }
  };
};

/* input is an array of user input possible plant names in shortened
   latin form.  Open those that validate and scroll to the first
   one. This function should only be called at load time, and it
   alters maybe_plants */
pbc.open_maybe_plants = function(maybe_plants) {

  // uppercase first letter of each candidate plant
  for (var i = 0, length = maybe_plants.length; i < length; ++i) {
    var this_maybe_plant = maybe_plants[i];
    // http://stackoverflow.com/questions/1026069/capitalize-first-letter-of-string-in-javascript
    maybe_plants[i] = this_maybe_plant.charAt(0).toUpperCase() +
    this_maybe_plant.slice(1);
  }

  // get the short latin names from the pbc.plants keys
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
        // delete this maybe plant
        maybe_plants.splice(j, 1);
        --j;
      }
    }
    if (maybe_plants.length == 0) {
      break;
    }
  }
  pbc.scroll_to_first_open_plant(validated_plants);
};

/* collapse all plants in the plant list */
pbc.collapse_all_function = function() {

  for (var i = 0, length = pbc.list_plants.length; i < length; ++i) {
    pbc.plants[pbc.list_plants[i]].display("collapse");
  }
  pbc.scroll_to_element("select_form_div");
};

pbc.create_called_function = function(callee, expand_collapse) {

  return function() {
    pbc.plants[callee].display(expand_collapse);
  };
};

pbc.create_called_jump_function = function(callee, expand_collapse) {

  return function() {
    pbc.plants[callee].display(expand_collapse);
    pbc.scroll_to_element(callee);
  };
};

/* http://www.quirksmode.org/js/findpos.html */
pbc.height_of_element = function(element) {

  var curtop = 0;
  if (element.offsetParent) {
    do {
      curtop += element.offsetTop;
    } while (element = element.offsetParent);
  }
  return curtop;
};

pbc.scroll_to_element = function(element_id) {
  
  var element = document.getElementById(element_id);
  if (element) {
    window.scrollTo(0, pbc.height_of_element(element));
  }
  else {
    window.scrollTo(0,0);
  }
};

pbc.error_alert = function(message) {

  if (!message) {
    alert("Sorry, your browser doesn't seem to be supported - a more recent browser may be required.");
  }
  else {
    alert(message);
  }
};

//// Start pbc dom utility functions /////////////////////////////////
pbc.dom = {};
/* attributes is an object, children is an array */
pbc.dom.create_element = function(type, attributes, children) {

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

/* naming exception being made here for clarity in use */
pbc.dom.ce = pbc.dom.create_element;
/* createTextNode is some kind of weird wrapper object that you can't reference directly... */
pbc.dom.tn = function(text) { 
  return document.createTextNode(text);
};

/* http://stackoverflow.com/questions/683366/remove-all-the-children-dom-elements-in-div */
pbc.dom.remove_children = function(parent_node) {
  
  while (parent_node.hasChildNodes()) {
    parent_node.removeChild(parent_node.lastChild);
  }
}

/* make parent_node have a single child node new_node */
pbc.dom.replace_children = function(parent_node, new_nodes) {

  pbc.dom.remove_children(parent_node);
  if (new_nodes instanceof Array) {
    for (var i = 0, length = new_nodes.length; i < length; ++i) {
      parent_node.appendChild(new_nodes[i]);
    }
  }
  else {
    parent_node.appendChild(new_nodes);
  }
};
//// End pbc dom utility functions ///////////////////////////////////

// timers for loading divs
pbc.timers = {
  "initial_plant_list_loading" : {timer_id : 0, count : 0},
  "plant_list_loading" : {timer_id : 0, count : 0},
  "closeups_loading" : {timer_id : 0, count : 0},
  "blooms_loading" : {timer_id : 0, count : 0},
  "internal_blooms_loading" : {timer_id : 0, count : 0}
};
// store the closeup div text as it comes in (true for non-natives only, false for not non-natives only)
// (ff allows true/false as identifiers, ie does not)
pbc.closeups_text_cache = {1 : '', 0 : ''};

// 1 is invasives only, 0 is all
pbc.closeup_plants = {1: [], 0: []};
pbc.bloom_plants = {1: [], 0: []};
pbc.get_checked_closeups = pbc.create_get_checked_plants_function(pbc.get_closeup_plants_array, "_checkbox");
pbc.get_checked_blooms = pbc.create_get_checked_plants_function(pbc.get_bloom_plants_array, "_bloom_check");
pbc.clear_closeup_checks = pbc.create_checks_clearer(pbc.get_checked_closeups, "_checkbox");
pbc.clear_bloom_checks = pbc.create_checks_clearer(pbc.get_checked_blooms, "_bloom_check");
pbc.open_checked_closeups = pbc.create_open_checks_function(pbc.get_checked_closeups);
pbc.open_checked_blooms = pbc.create_open_checks_function(pbc.get_checked_blooms);

// find the right event handler for this browser
if (document.addEventListener) {
  pbc.update_event_handler = function(event, element, handler) {
    element.addEventListener(event, handler, false);
  };
}
else if (document.attachEvent) {
  pbc.update_event_handler = function(event, element, handler) {
    element.attachEvent("on" + event, handler);
  };
}
else {
  pbc.update_event_handler = pbc.error_alert;
}

// http://stackoverflow.com/questions/237104/javascript-array-containsobj
Array.prototype.contains = function(obj) {

  var i = this.length;
  while (i--) {
    if (this[i] === obj) {
      return true;
    }
  }
  return false;
}

<?php
///////////////////////// Create the plant objects
// pbc.plants holds all data for each plant in the database
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
  // create a javascript array of images
  $js_image_list = "[";
  foreach ($images as $image) {
    if ($image != "") {
      $js_image_list .= $image . ",";
    }
  }
  // remove the trailing comma
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
  ////
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
    new pbc.Plant("$short_latin", $js_image_list,
                  "{$entries['color']}", "$bloom", "$inv_text", "$wi_text",
                  '$notes', "{$entries['c_value']}", "$wetland_indicator",
                  '$common', '$latin', '$family', '$aliases');

OUT;
} // end while(entries)
$result->free();
?>

window.onload = function() {

  // find the right xmlhttprequest generator for this browser
  // (based on Javascript: the definitive guide by David Flanagan (O'Reilly 2006))
  var factories = [function() { return new XMLHttpRequest(); },
                   function() { return new ActiveXObject("MSXML2.XMLHTTP"); },
                   function() { return new ActiveXObject("Microsoft.XMLHTTP"); }];
  for (var i = 0, length = factories.length; i < length; ++i) {
    try {
      var factory = factories[i];
      var request = factory();
      if (request != null) {
	pbc.ajax_object = factory;
        break;
      }
    }
    catch(e) {
      continue;
    }
  }
  if (!pbc.ajax_object) {
    pbc.ajax_object = pbc.error_alert;
  }
  
  /* most everything should be hidden until we have a plant list,
     since not much will work without it */
  document.getElementById("closeups_div").style.display = "none";
  document.getElementById('closeups_box').checked = false;
  pbc.update_event_handler("click", document.getElementById("closeups_box"),
                           pbc.closeups_oncheck_function);

  document.getElementById("select_form_div").style.display = 'none';
  document.getElementById("select_form_common").selected = true;
  document.getElementById('plant_list_title_div').style.display = 'none';
  document.getElementById('plant_list_loading').style.display = 'none';

  document.getElementById('blooms_box').checked = false;
  document.getElementById("bloom_table_div").style.display = "none";
  document.getElementById('bloom_radio_date_option').checked = true;
  pbc.update_event_handler("click", document.getElementById("blooms_box"),
                           pbc.blooms_oncheck_function);

  document.getElementById("footer_container").style.display = "none";
  document.getElementById('invasive_box').checked = false;
  
  // set loading divs while we fetch the initial data
  pbc.set_loading("initial_plant_list_loading", "Loading plant list");

  // generate the initial load - only fill in all of the missing controls
  // if we get a succesful response
  var request = pbc.ajax_object();
  request.onreadystatechange = function() {
    if (request.readyState === 4) {
    if (request.status == 200) {
      /* We fill in most of the fully loaded page here since until we
         get the plant list, nothing else is going to work */

    document.getElementById("closeups_div").style.display = "block";

    document.getElementById("select_form_div").style.display = 'block';
    document.getElementById("plant_list_title_div").style.display = 'block';

    document.getElementById("blooms_loading").style.display = "none";
    document.getElementById("inner_blooms_div").style.display = "none";
    document.getElementById("bloom_table_div").style.display = "block";

    document.getElementById("footer_container").style.display = "block";

    var sort_select_onclick_function = function() {
      var sort_value = document.getElementById("sort_value").value;
      var get_string = "sort=" + sort_value;
      if (pbc.box_is_checked("invasive_box")) {
	get_string += "&invasive=true";
      }
      pbc.set_loading("plant_list_loading");
      var request = pbc.ajax_object();
      request.onreadystatechange = function() {
	if (request.readyState === 4) {
	  pbc.ajax_handler(request, pbc.get_open_list_plants())();
	}
      };
      request.open("GET", "pbc_flora_ajax.php?" + get_string, true);
      request.send(null);
    };
    pbc.update_event_handler("change", document.getElementById("sort_value"),
                             sort_select_onclick_function);

    //// fill in the plant list data
    pbc.ajax_handler(request, [])();
    pbc.clear_timer("initial_plant_list_loading");

    var bloom_radio_array = document.getElementById("bloom_form").bloom_order;
    for (var i = 0, length = bloom_radio_array.length; i < length; ++i) {
      var elt = bloom_radio_array[i];
      pbc.update_event_handler("click", elt, pbc.bloom_function);
    }
    pbc.update_event_handler("click", document.getElementById("bloom_open_checks"),
                             pbc.open_checked_blooms);
    pbc.update_event_handler("click", document.getElementById("bloom_clear_checks"),
                             pbc.clear_bloom_checks);

    //// Bottom bar stuff    
    // expand
    var expand_all_function = function() {
      for (var plant in pbc.plants) {
	pbc.plants[plant].display("expand");
      }
      pbc.scroll_to_element("select_form_div");
    };
    var expand_all = document.getElementById("expand_text");
    if (expand_all) {
      pbc.update_event_handler("click", expand_all, expand_all_function);
    }
    ////// collapse
    pbc.update_event_handler("click", document.getElementById("collapse_text"),
                             pbc.collapse_all_function);

    pbc.update_event_handler("click", document.getElementById("invasive_box"),
                             pbc.invasive_onclick_handler);
    goto_closeups_function = function() {
      window.scrollTo(0, pbc.height_of_element(document.getElementById("closeups_div")));
    };
    pbc.update_event_handler("click", document.getElementById("goto_closeups"),
                             goto_closeups_function);
    goto_list_function = function() {
      window.scrollTo(0, pbc.height_of_element(document.getElementById("plant_list_title")));
    };
    pbc.update_event_handler("click", document.getElementById("goto_list"),
                             goto_list_function);
    goto_table_function = function() {
      window.scrollTo(0, pbc.height_of_element(document.getElementById("bloom_table_div")));
    };
    pbc.update_event_handler("click", document.getElementById("goto_table"),
                             goto_table_function);
    goto_links_function = function() {
      window.scrollTo(0, pbc.height_of_element(document.getElementById("links_div")));
    };
    pbc.update_event_handler("click", document.getElementById("goto_links"),
                             goto_links_function);

    //// if the initial location included an anchor, jump and open it
    var search = location.search;
    if (search && search.slice(0, 7) === '?plant=') {
      search_plants = search.slice(7);
      var search_plants_array = search_plants.split(',');
      // only keep the first 25
      search_plants_array = search_plants_array.slice(0, 25);
      pbc.open_maybe_plants(search_plants_array);
    }
    }
    else {
      alert("The request for plant data failed; you can try reloading the page or trying again at a later time.  If the problem persists please email pbcflora@gmail.com.");
    }
    } // end readyState === 4
  };
  // send a request for the initial load plant data
  request.open("GET", "pbc_flora_ajax.php?new=true&sort=common", true);
  request.send(null);
}; // end window.onload

<?php

// return the string for the month/week $code which is either the "start" or
// the "end" of a bloom period ($start_end)
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