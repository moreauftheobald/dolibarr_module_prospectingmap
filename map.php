<?php
/* Copyright (C) 2001-2004  Rodolphe Quiedeville    <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2016  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012  Regis Houssin           <regis.houssin@capnetworks.com>
 * Copyright (C) 2012       Marcos García           <marcosgdf@gmail.com>
 * Copyright (C) 2013-2015  Raphaël Doursenaud      <rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2015       Florian Henry           <florian.henry@open-concept.pro>
 * Copyright (C) 2016       Josep Lluis Amador      <joseplluis@lliuretic.cat>
 * Copyright (C) 2016       Ferran Marcet      		<fmarcet@2byte.es>
 * Copyright (C) 2017       Juanjo Menent      		<jmenent@2byte.es>
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

/**
 *	\file       htdocs/prospectingmap/map.php
 *	\ingroup    prospectingmap
 *	\brief      Page to show map of prospects
 */

// Change this following line to use the correct relative path (../, ../../, etc)
$res=0;
if (! $res && file_exists("../main.inc.php")) $res=@include '../main.inc.php';			// to work if your module directory is into a subdir of root htdocs directory
if (! $res && file_exists("../../main.inc.php")) $res=@include '../../main.inc.php';		// to work if your module directory is into a subdir of root htdocs directory
if (! $res) die("Include of main fails");
dol_include_once('/contact/class/contact.class.php');
dol_include_once('/core/class/html.formother.class.php');
dol_include_once('/core/lib/company.lib.php');
dol_include_once('/categories/class/categorie.class.php');
dol_include_once('/core/lib/company.lib.php');
dol_include_once('/core/class/html.formcompany.class.php');
dol_include_once('/societe/class/client.class.php');
dol_include_once('/prospectingmap/lib/prospectingmap.lib.php');
dol_include_once('/advancedictionaries/class/html.formdictionary.class.php');
dol_include_once('/advancedictionaries/class/dictionary.class.php');

$langs->loadLangs(array("companies", "commercial", "customers", "suppliers", "bills", "compta", "categories", "prospectingmap@prospectingmap"));

// Security check
$socid = GETPOST('socid','int');
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user,'societe',$socid,'');

$search_categ  = GETPOST('search_categ','array');

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('prospectmap'));

$object = new Societe($db);


/*
 * Actions
 */

if (GETPOST('cancel')) { $action='list'; $massaction=''; }
if (! GETPOST('confirmmassaction') && $massaction != 'presend' && $massaction != 'confirm_presend') { $massaction=''; }

$parameters=array();
$reshook=$hookmanager->executeHooks('doActions',$parameters, $object, $action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook)) {
    // Did we click on purge search criteria ?
    if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) // All tests are required to be compatible with all browsers
    {
        $search_categ = array();
    }
}

/*
 * View
 */

$form=new Form($db);
$formother=new FormOther($db);
$companystatic=new Societe($db);
$formcompany=new FormCompany($db);
$categ = New Categorie($db);

$title = $langs->trans("ProspectingMapMapOfourn");

$sql = "SELECT s.rowid, s.nom as name, s.name_alias, s.town, s.zip, ";
$sql.= " s.fk_prospectlevel, s.prefix_comm, s.client, s.fournisseur, s.canvas, s.status as status,";
$sql.= " s.email, s.phone, s.fk_pays,";
$sql.= " s.address,";
$sql.= " state.code_departement as state_code, state.nom as state_name,";
if(!empty($search_categ)){
	$sql.= " c.color as color, c.rowid as stcomm_id,";
}
$sql.= " pmc.longitude, pmc.latitude";
// Add fields from hooks
$parameters=array();
$reshook=$hookmanager->executeHooks('printFieldListSelect',$parameters);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;
$sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."prospectingmap_coordinate as pmc on (pmc.fk_soc = s.rowid)";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."c_departements as state on (state.rowid = s.fk_departement)";
if(!empty($search_categ)){
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."categorie_fournisseur as cat on (cat.fk_soc = s.rowid)";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."categorie as c on (cat.fk_categorie = c.rowid)";
}
// We'll need this table joined to the select in order to filter by sale
$sql.= " WHERE  s.entity IN (".getEntity('societe').")";
if(!empty($search_categ)){
	$sql.= "AND  cat.fk_categorie IN (".implode(',', $search_categ).")";
}
// Add where from hooks
$parameters=array();
$reshook=$hookmanager->executeHooks('printFieldListWhere',$parameters);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;
$sql.= ' GROUP BY s.rowid';

$resql = $db->query($sql);
if (! $resql) {
    dol_print_error($db);
    exit;
}

$num = $db->num_rows($resql);

llxHeader('', $title);

print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" name="formfilter">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';

print_barre_liste($title, '', $_SERVER["PHP_SELF"], '', '', '', '', '', $num, 'title_companies');

$moreforfilter = '';

// Prospect status
$arraystcomm=array();
foreach($categ->get_full_arbo('supplier') as $key => $val) {
    $arraystcomm[$val['id']] = ($langs->trans($val['label']));
}
$moreforfilter.='<div class="divsearchfield">';
$moreforfilter.=$langs->trans('suppliercateg'). ': ';
$moreforfilter.=multiselect_javascript_code($search_categ, 'search_categ');
$save_conf = $conf->use_javascript_ajax;
$conf->use_javascript_ajax = 0;
$moreforfilter.=$form->selectarray('search_categ', $arraystcomm, '', 0, 0, 0, '', 0, 0, 0, '','minwidth300 maxwidth300');
$conf->use_javascript_ajax = $save_conf;
$moreforfilter.='</div>';

$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldPreListTitle', $parameters);    // Note that $action and $object may have been modified by hook
if (empty($reshook)) $moreforfilter .= $hookmanager->resPrint;
else $moreforfilter = $hookmanager->resPrint;

if (!empty($moreforfilter)) {
    print '<div class="liste_titre liste_titre_bydiv centpercent">';
    print $moreforfilter;
    print '<div class="divsearchfield">';
    print $form->showFilterButtons();
    print '</div>';
    print '</div>';
}

print '</form>';

/**
 * Build geoJSON datas.
 */
$icon = dol_buildpath('/prospectingmap/img/dot.png', 1);
$stcomm_list = [];
$geojsonProspectMarkers = [
    'type' => 'FeatureCollection',
    'crs' => [
        'type' => 'name',
        'properties' => [
            'name' => 'EPSG:3857'
        ]
    ],
    'features' => [],
];

while ($obj = $db->fetch_object($resql)) {
    $stcomm_id = isset($obj->stcomm_id) ? $obj->stcomm_id : 0;

    $companystatic->id = $obj->rowid;
    $companystatic->name = $obj->name;
    $companystatic->name_alias = $obj->name_alias;
    $companystatic->address = $obj->address;
    $companystatic->town = $obj->town;
    $companystatic->zip = $obj->zip;
    $companystatic->state_name = $obj->state_name;
    $companystatic->fk_pays = $obj->fk_pays;
    $companystatic->phone = $obj->phone;

    // Description of the popup
    //------------------------------
    $address = $companystatic->getFullAddress();
    $description = $companystatic->getNomUrl(1, '', 0, 1) .
        (!empty($address) ? '<br>' . $address : '') .
        (!empty($companystatic->phone) ? '<br>' . dol_print_phone($companystatic->phone) : '') .
        (!empty($companystatic->email) ? '<br>' . dol_print_email($companystatic->email) : '');


    // Get Colors
	//------------------------------

	if (!isset($stcomm_list[$stcomm_id])) {
		$stcomm_list[$stcomm_id] = !empty($obj->color) ? '#'.$obj->color : '#FFFFFF';
	}


    // Coordinates in projection format: "EPSG:3857"
    //------------------------------
    $longitude = !empty($obj->longitude) ? $obj->longitude : 0;
    $latitude = !empty($obj->latitude) ? $obj->latitude : 0;

    // Add geoJSON point
    //------------------------------
    $geojsonProspectMarkers['features'][] = [
        'type' => 'Feature',
        'geometry' => [
            'type' => 'Point',
            'coordinates' => [$longitude, $latitude],
        ],
        'properties' => [
            'desc' => $description,
            'stcomm' => $stcomm_id,
        ],
    ];
}
?>
    <link rel="stylesheet" href="https://cdn.rawgit.com/openlayers/openlayers.github.io/master/en/v5.2.0/css/ol.css" type="text/css">
    <script src="https://cdn.rawgit.com/openlayers/openlayers.github.io/master/en/v5.2.0/build/ol.js"></script>
    <style>
        .ol-popup {
            position: absolute;
            background-color: white;
            -webkit-filter: drop-shadow(0 1px 4px rgba(0,0,0,0.2));
            filter: drop-shadow(0 1px 4px rgba(0,0,0,0.2));
            padding: 15px;
            border-radius: 10px;
            border: 1px solid #cccccc;
            bottom: 12px;
            left: -50px;
            min-width: 280px;
        }
        .ol-popup:after, .ol-popup:before {
            top: 100%;
            border: solid transparent;
            content: " ";
            height: 0;
            width: 0;
            position: absolute;
            pointer-events: none;
        }
        .ol-popup:after {
            border-top-color: white;
            border-width: 10px;
            left: 48px;
            margin-left: -10px;
        }
        .ol-popup:before {
            border-top-color: #cccccc;
            border-width: 11px;
            left: 48px;
            margin-left: -11px;
        }
        .ol-popup-closer {
            text-decoration: none;
            position: absolute;
            top: 2px;
            right: 8px;
        }
        .ol-popup-closer:after {
            content: "✖";
        }
    </style>

    <div id="map" class="map"></div>
    <div id="popup" class="ol-popup">
        <a href="#" id="popup-closer" class="ol-popup-closer"></a>
        <div id="popup-content"></div>
    </div>

    <script type="text/javascript">
        /**
         * Set map height.
         */
        var _map = $('#map');
        var _map_pos = _map.position();
        var h = Math.max(document.documentElement.clientHeight, window.innerHeight || 0);
        _map.height(h - _map_pos.top - 20);

        /**
         * Prospect markers geoJSON.
         */
        var geojsonProspectMarkers = <?php print json_encode($geojsonProspectMarkers) ?>;

        /**
         * Prospect markers styles.
         */
        var markerStyles = {};
        $.map(<?php print json_encode($stcomm_list) ?>, function (value, key) {
            if (!(key in markerStyles)) {
                markerStyles[key] = new ol.style.Style({
                    image: new ol.style.Icon(/** @type {module:ol/style/Icon~Options} */ ({
                        anchor: [0.5, 1],
                        color: value,
                        crossOrigin: 'anonymous',
                        src: '<?php print $icon ?>'
                    }))
                });
            }
        });
        var styleFunction = function(feature) {
            return markerStyles[feature.get('stcomm')];
        };

        /**
         * Prospect markers source.
         */
        var prospectSource = new ol.source.Vector({
            features: (new ol.format.GeoJSON()).readFeatures(geojsonProspectMarkers)
        });

        /**
         * Prospect markers layer.
         */
        var prospectLayer = new ol.layer.Vector({
            source: prospectSource,
            style: styleFunction
        });

        /**
         * Open Street Map layer.
         */
        var osmLayer = new ol.layer.Tile({
            source: new ol.source.OSM()
        });

        /**
         * Elements that make up the popup.
         */
        var popupContainer = document.getElementById('popup');
        var popupContent = document.getElementById('popup-content');
        var popupCloser = document.getElementById('popup-closer');

        /**
         * Create an overlay to anchor the popup to the map.
         */
        var popupOverlay = new ol.Overlay({
            element: popupContainer,
            autoPan: true,
            autoPanAnimation: {
                duration: 250
            }
        });

        /**
         * Add a click handler to hide the popup.
         * @return {boolean} Don't follow the href.
         */
        popupCloser.onclick = function() {
            popupOverlay.setPosition(undefined);
            popupCloser.blur();
            return false;
        };

        /**
         * View of the map.
         */
        var mapView = new ol.View();
        if (<?php print $num ?> == 1) {
            var feature = prospectSource.getFeatures()[0];
            var coordinates = feature.getGeometry().getCoordinates();
            mapView.setCenter(coordinates);
            mapView.setZoom(17);
        } else {
            mapView.setCenter(ol.proj.fromLonLat([0, 0]));
            mapView.setZoom(1);
        }

        /**
         * Create the map.
         */
        var map = new ol.Map({
            target: 'map',
            layers: [osmLayer, prospectLayer],
            overlays: [popupOverlay],
            view: mapView
        });

        /**
         * Fit map for markers.
         */
        if (<?php print $num ?> > 1) {
            var extent = prospectSource.getExtent();
            mapView.fit(extent, {padding: [50, 50, 50, 50], constrainResolution: false});
        }

        /**
         * Add a click handler to the map to render the popup.
         */
        map.on('singleclick', function(evt) {
            var feature = map.forEachFeatureAtPixel(evt.pixel, function (feature) {
                return feature;
            });

            if (feature) {
                var coordinates = feature.getGeometry().getCoordinates();
                popupContent.innerHTML = feature.get('desc');
                popupOverlay.setPosition(coordinates);
            } else {
                popupCloser.click();
            }
        });
		$(document).ready(function () {
			<?php
			foreach ($search_categ as $val){
				?>
				document.querySelector('[title="<?php echo $arraystcomm[$val]?>"]').style.backgroundColor="<?php echo $stcomm_list[$val]?>"; 
				<?php
			}
			?>
		});
    </script>
<?php

$db->free($resql);


llxFooter();
$db->close();
