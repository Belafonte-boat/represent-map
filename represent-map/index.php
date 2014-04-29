<?php
if(!file_exists('include/db.php')) require_once('installer.php');
include_once "header.php";
?>

<!DOCTYPE html>
<html>
  <head>
    <!--
    This site was based on the Represent.LA project by:
    - Alex Benzer (@abenzer)
    - Tara Tiger Brown (@tara)
    - Sean Bonner (@seanbonner)

    Create a map for your startup community!
    https://github.com/abenzer/represent-map
    -->
    <title><?= $title_tag ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta charset="UTF-8">
    <link href='http://fonts.googleapis.com/css?family=Open+Sans:600italic,400,700,300' rel='stylesheet' type='text/css'>
    <link href="./bootstrap/css/bootstrap.css" rel="stylesheet" type="text/css" />
    <link href="./bootstrap/css/bootstrap-responsive.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="map.css?nocache=289671982568" type="text/css" />
    <link rel="stylesheet" media="only screen and (max-device-width: 480px)" href="mobile.css" type="text/css" />
    <script src="./scripts/jquery-1.7.1.js" type="text/javascript" charset="utf-8"></script>
    <script src="./bootstrap/js/bootstrap.js" type="text/javascript" charset="utf-8"></script>
    <script src="./bootstrap/js/bootstrap-typeahead.js" type="text/javascript" charset="utf-8"></script>
    <script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?sensor=false"></script>
    <script type="text/javascript" src="./scripts/label.js"></script>
    <script type="text/javascript">
      var script = '<script type="text/javascript" src="scripts/markerclusterer';
      if (document.location.search.indexOf('compiled') !== -1) {
        script += '_packed';
      }
      script += '.js"><' + '/script>';
      document.write(script);
    </script>
    <script type="text/javascript">
      var map;
      var infowindow = null;
      var gmarkers = [];
      var markerTitles =[];
      var highestZIndex = 0;
      var agent = "default";
      var zoomControl = true;


      // detect browser agent
      $(document).ready(function(){
        if(navigator.userAgent.toLowerCase().indexOf("iphone") > -1 || navigator.userAgent.toLowerCase().indexOf("ipod") > -1) {
          agent = "iphone";
          zoomControl = false;
        }
        if(navigator.userAgent.toLowerCase().indexOf("ipad") > -1) {
          agent = "ipad";
          zoomControl = false;
        }
      });


      // resize marker list onload/resize
      $(document).ready(function(){
        resizeList()
      });
      $(window).resize(function() {
        resizeList();
      });

      // resize marker list to fit window
      function resizeList() {
        newHeight = $('html').height() - $('#topbar').height();
        $('#list').css('height', newHeight + "px");
        $('#menu').css('margin-top', $('#topbar').height());
      }


      // initialize map
      function initialize() {
        // set map styles
        var mapStyles = [
         {
            featureType: "road",
            elementType: "geometry",
            stylers: [
              { hue: "#8800ff" },
              { lightness: 100 }
            ]
          },{
            featureType: "road",
            stylers: [
              { visibility: "on" },
              { hue: "#91ff00" },
              { saturation: -62 },
              { gamma: 1.98 },
              { lightness: 45 }
            ]
          },{
            featureType: "water",
            stylers: [
              { hue: "#005eff" },
              { gamma: 0.72 },
              { lightness: 42 }
            ]
          },{
            featureType: "transit.line",
            stylers: [
              { visibility: "off" }
            ]
          },{
            featureType: "administrative.locality",
            stylers: [
              { visibility: "on" }
            ]
          },{
            featureType: "administrative.neighborhood",
            elementType: "geometry",
            stylers: [
              { visibility: "simplified" }
            ]
          },{
            featureType: "landscape",
            stylers: [
              { visibility: "on" },
              { gamma: 0.41 },
              { lightness: 46 }
            ]
          },{
            featureType: "administrative.neighborhood",
            elementType: "labels.text",
            stylers: [
              { visibility: "on" },
              { saturation: 33 },
              { lightness: 20 }
            ]
          }
        ];

        // set map options
        var myOptions = {
          zoom: 6,
          //minZoom: 10,
          center: new google.maps.LatLng(<?= $lat_lng ?>),
          mapTypeId: google.maps.MapTypeId.ROADMAP,
          streetViewControl: false,
          mapTypeControl: false,
          panControl: false,
          zoomControl: zoomControl,
          styles: mapStyles,
          zoomControlOptions: {
            style: google.maps.ZoomControlStyle.SMALL,
            position: google.maps.ControlPosition.LEFT_CENTER
          }
        };
        map = new google.maps.Map(document.getElementById('map_canvas'), myOptions);
        zoomLevel = map.getZoom();

        // prepare infowindow
        infowindow = new google.maps.InfoWindow({
          content: "holding..."
        });

        // only show marker labels if zoomed in
        google.maps.event.addListener(map, 'zoom_changed', function() {
          zoomLevel = map.getZoom();
          if(zoomLevel <= 15) {
            $(".marker_label").css("display", "none");
          } else {
            $(".marker_label").css("display", "inline");
          }
        });

        // markers array: name, type (icon), lat, long, description, uri, address
        markers = new Array();
        <?php
          $types = Array(
              Array('startup', 'Startups'),
              Array('incubator', 'Incubators'),
              );
          $marker_id = 0;
          foreach($types as $type) {
            $places = mysql_query("SELECT * FROM places WHERE approved='1' AND type='$type[0]' ORDER BY title");
            $places_total = mysql_num_rows($places);
            while($place = mysql_fetch_assoc($places)) {
              $place[title] = htmlspecialchars_decode(addslashes(htmlspecialchars($place[title])));
              $place[description] = str_replace(array("\n", "\t", "\r"), "", htmlspecialchars_decode(addslashes(htmlspecialchars($place[description]))));
              $place[uri] = addslashes(htmlspecialchars($place[uri]));
              $place[address] = htmlspecialchars_decode(addslashes(htmlspecialchars($place[address])));
              echo "
                markers.push(['".$place[title]."', '".$place[type]."', '".$place[lat]."', '".$place[lng]."', '".$place[description]."', '".$place[uri]."', '".$place[address]."']);
                markerTitles[".$marker_id."] = '".$place[title]."';
              ";
              $count[$place[type]]++;
              $marker_id++;
            }
          }
          if($show_events == true) {
            $place[type] = "event";
            $events = mysql_query("SELECT * FROM events WHERE start_date > ".time()." AND start_date < ".(time()+9676800)." ORDER BY id DESC");
            $events_total = mysql_num_rows($events);
            while($event = mysql_fetch_assoc($events)) {
              $event[title] = htmlspecialchars_decode(addslashes(htmlspecialchars($event[title])));
              $event[description] = htmlspecialchars_decode(addslashes(htmlspecialchars($event[description])));
              $event[uri] = addslashes(htmlspecialchars($event[uri]));
              $event[address] = htmlspecialchars_decode(addslashes(htmlspecialchars($event[address])));
              $event[start_date] = date("D, M j @ g:ia", $event[start_date]);
              echo "
                markers.push(['".$event[title]."', 'event', '".$event[lat]."', '".$event[lng]."', '".$event[start_date]."', '".$event[uri]."', '".$event[address]."']);
                markerTitles[".$marker_id."] = '".$event[title]."';
              ";
              $count[$place[type]]++;
              $marker_id++;
            }
          }
        ?>

        
        // add markers
        jQuery.each(markers, function(i, val) {
          infowindow = new google.maps.InfoWindow({
            content: ""
          });

          // offset latlong ever so slightly to prevent marker overlap
          rand_x = Math.random();
          rand_y = Math.random();
          val[2] = parseFloat(val[2]) + parseFloat(parseFloat(rand_x) / 6000);
          val[3] = parseFloat(val[3]) + parseFloat(parseFloat(rand_y) / 6000);

          // show smaller marker icons on mobile
          if(agent == "iphone") {
            var iconSize = new google.maps.Size(16,19);
          } else {
            iconSize = null;
          }

          // build this marker
          var markerImage = new google.maps.MarkerImage("./images/icons/"+val[1]+".png", null, null, null, iconSize);
          var marker = new google.maps.Marker({
            position: new google.maps.LatLng(val[2],val[3]),
            map: map,
            title: '',
            clickable: true,
            infoWindowHtml: '',
            zIndex: 10 + i,
            icon: markerImage
          });
          marker.type = val[1];
          gmarkers.push(marker);

          // add marker hover events (if not viewing on mobile)
          if(agent == "default") {
            google.maps.event.addListener(marker, "mouseover", function() {
              this.old_ZIndex = this.getZIndex();
              this.setZIndex(9999);
              $("#marker"+i).css("display", "inline");
              $("#marker"+i).css("z-index", "99999");
            });
            google.maps.event.addListener(marker, "mouseout", function() {
              if (this.old_ZIndex && zoomLevel <= 15) {
                this.setZIndex(this.old_ZIndex);
                $("#marker"+i).css("display", "none");
              }
            });
          }
          
          // format marker URI for display and linking
          var markerURI = val[5];
          if(markerURI.substr(0,7) != "http://") {
            markerURI = "http://" + markerURI;
          }
          var markerURI_short = markerURI.replace("http://", "");
          var markerURI_short = markerURI_short.replace("www.", "");

          // add marker click effects (open infowindow)
          google.maps.event.addListener(marker, 'click', function () {
            infowindow.setContent(
              "<div class='marker_title'>"+val[0]+"</div>"
              + "<div class='marker_uri'><a target='_blank' href='"+markerURI+"'>"+markerURI_short+"</a></div>"
              + "<div class='marker_desc'>"+val[4]+"</div>"
              + "<div class='marker_address'>"+val[6]+"</div>"
            );
            infowindow.open(map, this);
          });

          // add marker label
          var latLng = new google.maps.LatLng(val[2], val[3]);
          var label = new Label({
            map: map,
            id: i
          });
          label.bindTo('position', marker);
          label.set("text", val[0]);
          label.bindTo('visible', marker);
          label.bindTo('clickable', marker);
          label.bindTo('zIndex', marker);
        });


        // zoom to marker if selected in search typeahead list
        $('#search').typeahead({
          source: markerTitles,
          onselect: function(obj) {
            marker_id = jQuery.inArray(obj, markerTitles);
            if(marker_id > -1) {
              map.panTo(gmarkers[marker_id].getPosition());
              map.setZoom(15);
              google.maps.event.trigger(gmarkers[marker_id], 'click');
            }
            $("#search").val("");
          }
        });

        console.log(map,gmarkers)
      var mcOptions = {gridSize: 50, maxZoom: 5};
      var markerCluster = new MarkerClusterer(map, gmarkers);
      }


      // zoom to specific marker
      function goToMarker(marker_id) {
        if(marker_id) {
          map.panTo(gmarkers[marker_id].getPosition());
          map.setZoom(15);
          google.maps.event.trigger(gmarkers[marker_id], 'click');
        }
      }

      // toggle (hide/show) markers of a given type (on the map)
      function toggle(type) {
        if($('#filter_'+type).is('.inactive')) {
          show(type);
        } else {
          hide(type);
        }
      }

      // hide all markers of a given type
      function hide(type) {
        for (var i=0; i<gmarkers.length; i++) {
          if (gmarkers[i].type == type) {
            gmarkers[i].setVisible(false);
          }
        }
        $("#filter_"+type).addClass("inactive");
      }

      // show all markers of a given type
      function show(type) {
        for (var i=0; i<gmarkers.length; i++) {
          if (gmarkers[i].type == type) {
            gmarkers[i].setVisible(true);
          }
        }
        $("#filter_"+type).removeClass("inactive");
      }

      // toggle (hide/show) marker list of a given type
      function toggleList(type) {
        $("#list .list-"+type).toggle();
      }


      // hover on list item
      function markerListMouseOver(marker_id) {
        $("#marker"+marker_id).css("display", "inline");
      }
      function markerListMouseOut(marker_id) {
        $("#marker"+marker_id).css("display", "none");
      }

      google.maps.event.addDomListener(window, 'load', initialize);
    </script>

    <? echo $head_html; ?>
  </head>
  <body>

    <!-- display error overlay if something went wrong -->
    <?php echo $error; ?>




    <!-- google map -->
    <div id="map_canvas"></div>

    <!-- topbar -->
   

    <!-- right-side gutter -->
    <div class="menu" id="menu">
      <div class="search">
            <input type="text" name="search" id="search" placeholder="Search" data-provide="typeahead" autocomplete="off" />
          </div>
      <ul class="list" id="list">
        <?php
          $types = Array(
              Array('startup', 'Startups'),
              Array('incubator', 'Incubators'),
              );
          if($show_events == true) {
            $types[] = Array('event', 'Events');
          }
          $marker_id = 0;
          foreach($types as $type) {
            if($type[0] != "event") {
              $markers = mysql_query("SELECT * FROM places WHERE approved='1' AND type='$type[0]' ORDER BY title");
            } else {
              $markers = mysql_query("SELECT * FROM events WHERE start_date > ".time()." AND start_date < ".(time()+4838400)." ORDER BY id DESC");
            }
            $markers_total = mysql_num_rows($markers);
            echo "
              <li class='category'>
                <div class='category_item'>
                  <div class='category_toggle' onClick=\"toggle('$type[0]')\" id='filter_$type[0]'></div>
                  <a href='#' onClick=\"toggleList('$type[0]');\" class='category_info'><img src='./images/icons/$type[0].png' alt='' />$type[1]<span class='total'> ($markers_total)</span></a>
                </div>
                <ul class='list-items list-$type[0]'>
            ";
            while($marker = mysql_fetch_assoc($markers)) {
              echo "
                  <li class='".$marker[type]."'>
                    <a href='#' onMouseOver=\"markerListMouseOver('".$marker_id."')\" onMouseOut=\"markerListMouseOut('".$marker_id."')\" onClick=\"goToMarker('".$marker_id."');\">".$marker[title]."</a>
                  </li>
              ";
              $marker_id++;
            }
            echo "
                </ul>
              </li>
            ";
          }
        ?>
        
        <li class="attribution">
          <!-- per our license, you may not remove this line -->
          <?=$attribution?>
        </li>
      </ul>
    </div>

   




    

  </body>
</html>
