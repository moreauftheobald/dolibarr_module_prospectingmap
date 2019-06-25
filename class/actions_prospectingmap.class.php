<?php
/* Copyright (C) 2018      Open-DSI             <support@open-dsi.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

class ActionsProspectingMap
{
    /**
     * @var DoliDB Database handler.
     */
    public $db;
    /**
     * @var string Error
     */
    public $error = '';
    /**
     * @var array Errors
     */
    public $errors = array();

    /**
     * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
     */
    public $results = array();

    /**
     * @var string String displayed by executeHook() immediately after return
     */
    public $resprints;

    /**
     * Constructor
     *
     * @param        DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Overloading the formObjectOptions function : replacing the parent's function with the one below
     *
     * @param   array() $parameters Hook metadatas (context, etc...)
     * @param   CommonObject &$object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string &$action Current action (if set). Generally create or edit or null
     * @param   HookManager $hookmanager Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    function formObjectOptions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs;

        if (in_array('thirdpartycard', explode(':', $parameters['context']))) {
            $langs->load('prospectingmap@prospectingmap');

            $icon = dol_buildpath('/prospectingmap/img/dot.png', 1);
            if (($action == 'create' || $action == 'edit') &&
                ((isset($_GET['map_longitude']) || isset($_POST['map_longitude'])) ||
                    (isset($_GET['map_latitude']) || isset($_POST['map_latitude'])))
            ) {
                $longitude = GETPOST('map_longitude', 'int');
                $latitude = GETPOST('map_latitude', 'int');
            } else {
                dol_include_once('/prospectingmap/lib/prospectingmap.lib.php');
                $coordonate = prospectingmap_get_map_location($this->db, $object->id);
                $longitude = $coordonate['lon'];
                $latitude = $coordonate['lat'];
            }

            if ($action != 'create' && $action != 'edit' &&
                (!isset($longitude) || !isset($longitude) || empty($conf->global->PROSPECTINGMAP_SHOW_MAP_INTO_COMPANY_CARD))
            )
                return 0;

            if ($action == 'create' || $action == 'edit') {
                $longitude = isset($longitude) && $longitude !== '' ? $longitude : 0;
                $latitude = isset($latitude) && $latitude !== '' ? $latitude : 0;

                $map_html = str_replace("'", "\\'", '<tr><td>' . $langs->trans('ProspectingMapLocation') . '</td>' .
                    '<td colspan="3">' .
                    '<div id="map" class="map" style="width:80%; height: 400px;"></div>' .
                    '<div id="map_button" style="margin-top: 10px;">' .
                    '<input type="hidden" name="map_longitude" id="map_longitude" value="' . $longitude . '">' .
                    '<input type="hidden" name="map_latitude" id="map_latitude" value="' . $latitude . '">' .
                    '<input type="button" id="edit_map_location" class="button" value="' . $langs->transnoentitiesnoconv('ProspectingMapEditLocation') . '">' .
                    ' &nbsp; &nbsp; ' .
                    '<input type="button" id="get_map_location" class="button" value="' . $langs->transnoentitiesnoconv('ProspectingMapGetLocation') . '">' .
                    '</div>' .
                    '</td></tr>'
                );

                $editLocationLabel = str_replace("'", "\\'", $langs->transnoentitiesnoconv('ProspectingMapEditLocation'));
                $endEditLocationLabel = str_replace("'", "\\'", $langs->transnoentitiesnoconv('ProspectingMapEndEditLocation'));
            } else {
                $map_html = '<tr><td colspan="2"><div id="map" class="map" style="width:100%; height: 300px;"></div></td></tr>';
            }

            print <<<HTML
                <link rel="stylesheet" href="https://cdn.rawgit.com/openlayers/openlayers.github.io/master/en/v5.2.0/css/ol.css" type="text/css">
                <script src="https://cdn.rawgit.com/openlayers/openlayers.github.io/master/en/v5.2.0/build/ol.js"></script>

                <script type="text/javascript">
                  $(document).ready(function() {
HTML;
            if ($action == 'create' || $action == 'edit') {
                $url = $conf->global->PROSPECTINGMAP_SEARCH_COORDINATES_URL_ADDRESS;
                parse_str($conf->global->PROSPECTINGMAP_SEARCH_COORDINATES_URL_PARAMETERS, $parameters);
                $datas = json_encode($parameters);
                $requestMode = $conf->global->PROSPECTINGMAP_SEARCH_COORDINATES_REQUEST_MODE;
                $useJqueryBlockUi = !empty($conf->global->MAIN_USE_JQUERY_BLOCKUI) ? "true" : "false";
                $errorLabelGetPosition = dol_escape_js($langs->trans('ProspectingMapErrorGetPosition'));
                $errorLabelGetPositionNotFound = dol_escape_js($langs->trans('ProspectingMapErrorGetPositionNotFound'));

                print <<<HTML
                    var _edit_map_location = false;
                    $('#email').closest('tr').before('$map_html');
                    
                    /**
                     * Add a click handler to the edit map location button.
                     */
                    $('#edit_map_location').on('click', function () {
                      _edit_map_location = !_edit_map_location;

                      if (_edit_map_location) {
                        $('#edit_map_location').val('$endEditLocationLabel');
                        $('#get_map_location').hide();
                      } else {
                        $('#edit_map_location').val('$editLocationLabel');
                        $('#get_map_location').show();
                      }
                    });

                    var input_address = $('#address');
                    var input_zipcode = $('#zipcode');
                    var input_town = $('#town');
                    var input_country_id = $('#selectcountry_id');
                    var input_state_id = $('#state_id');

                    /**
                     * Add a click handler to the get map location button.
                     */
                    $('#get_map_location').on('click', function () {
                      var full_address = '';
                      var address = input_address.length > 0 ? input_address.val() : '';
                      address = address.replace(/<br>|\\n/g, ' ').trim();
                      full_address = full_address + address.trim();
                      var zip = input_zipcode.length > 0 ? input_zipcode.val().trim() : '';
                      var town = input_town.length > 0 ? input_town.val().trim() : '';
                      full_address = full_address + (full_address.length > 0 && (zip.length > 0 || town.length > 0) ? ', ' : '') + zip + (zip.length > 0 && town.length > 0 ? ' ' : '') + town;
                      var country = input_country_id.length > 0 ? $('#selectcountry_id option:selected').text() : '';
                      country = country.replace(/\(\w+\)$/g, '').trim();
                      var department = input_state_id.length > 0 ? $('#state_id option:selected').text() : '';
                      department = department.replace(/^\w+ \- /g, '').trim();
                      full_address = full_address + (full_address.length > 0 && (country.length > 0 || department.length > 0) ? ', ' : '') + country + (country.length > 0 && department.length > 0 ? ' - ' : '') + department;

                      var url = '$url'; 
                      var datas = $datas;
                      
                      $.map(datas, function (value, index) {
                        datas[index] = value.replace('__ADDRESS__', full_address);
                      });

                      $.ajax(url, {
                        method: "$requestMode",
                        data: datas,
                        dataType: "json"
                      }).done(function(json) {
                        if (json.length > 0) {
                          set_map_location(ol.proj.fromLonLat([parseFloat(json[0].lon), parseFloat(json[0].lat)]));
                        } else {
                          console.log('ProspectingMap: Address not found: ' + full_address);
                          set_map_location([0, 0]);

                          var error_msg = "$errorLabelGetPositionNotFound : " + full_address;
                          if ($useJqueryBlockUi) {
                            $.dolEventValid("",error_msg);
                          } else {
                            /* jnotify(message, preset of message type, keepmessage) */
                            $.jnotify(error_msg, "error", 'true', { remove: function (){} } );
                          }
                        }
                      }).fail(function(jqxhr, textStatus, error) {
                          console.log('ProspectingMap: ' + error);
                          set_map_location([0, 0]);
                          
                          var error_msg = "$errorLabelGetPosition : " + error;
                          if ($useJqueryBlockUi) {
                            $.dolEventValid("",error_msg);
                          } else {
                            /* jnotify(message, preset of message type, keepmessage) */
                            $.jnotify(error_msg, "error", 'true', { remove: function (){} } );
                          }
                      });
                    });
HTML;
            } else {
                $capital_label = str_replace("'", "\\'", $langs->transnoentitiesnoconv('Capital'));
                print <<<HTML
                    $("td:contains('$capital_label')").closest('table').append('$map_html');
HTML;
            }

            print <<<HTML
                    /**
                     * Marker of the company.
                     */
                    var point = new ol.Feature({
                      geometry: new ol.geom.Point([$longitude, $latitude]) //ol.proj.fromLonLat([$longitude, $latitude]))
                    });
                    point.setStyle(new ol.style.Style({
                      image: new ol.style.Icon(/** @type {module:ol/style/Icon~Options} */ ({
                        anchor: [0.5, 1],
                        color: '#e9172b',
                        crossOrigin: 'anonymous',
                        src: '$icon'
                      }))
                    }));

                    /**
                     * Company markers layer.
                     */
                    var prospectLayer = new ol.layer.Vector({
                      source: new ol.source.Vector({
                        features: [ point ]
                      })
                    });

                    /**
                     * Open Street Map layer.
                     */
                    var osmLayer = new ol.layer.Tile({
                      source: new ol.source.OSM()
                    });

                    /**
                     * View of the Map.
                     */
                    var mapView = new ol.View({
                      center: [$longitude, $latitude], //ol.proj.fromLonLat([$longitude, $latitude]),
                      zoom: 17
                    });

                    /**
                     * Create the map.
                     */
                    var map = new ol.Map({
                      target: 'map',
                      layers: [osmLayer, prospectLayer],
                      view: mapView
                    });
HTML;

            if ($action == 'create' || $action == 'edit') {
                print <<<HTML
                    /**
                     * Add a click handler to the map to render the popup.
                     */
                    map.on('singleclick', function (evt) {
                      if (_edit_map_location) {
                        //var coordinate = ol.proj.toLonLat(evt.coordinate)
                        set_map_location(evt.coordinate);
                      }
                    });

                    function set_map_location(coordinate) {
                      $('#map_longitude').val(coordinate[0] == 0 ? '' : coordinate[0]);
                      $('#map_latitude').val(coordinate[1] == 0 ? '' : coordinate[1]);
                      //var coord = ol.proj.fromLonLat(coordinate);

                      mapView.setCenter(coordinate);
                      mapView.setZoom(17);
                      point.setGeometry(new ol.geom.Point(coordinate));
                    }
HTML;
            }
            print <<<HTML
                  });
                </script>
HTML;
        }

        return 0;
    }
}