var modalloading=false;

$('#band').change(function(){
	var band = $("#band option:selected").text();
	if (band != "SAT") {
		$("#sat").val('All');
		$("#orbits").val('All');
		$("#sats_div").hide();
        $("#sats").hide(); // used in activated_gridmap
		$("#orbits_div").hide();
        $("#orbits").hide(); // used in activated_gridmap
		$("#satslabel").hide();
		$("#orbitslabel").hide();
        $('#propagation').val('').prop('disabled', false);
	} else {
		$("#sats_div").show();
        $("#sats").show(); // used in activated_gridmap
		$("#orbits_div").show();
        $("#orbits").show(); // used in activated_gridmap
		$("#orbitslabel").show();
		$("#satslabel").show();
        $('#propagation').val('SAT').prop('disabled', true);
	}
});

var map;
if (typeof(visitor) !== 'undefined' && visitor != true) {
   var grid_two = '';
   var grid_four = '';
   var grid_six = '';
   var grid_two_confirmed = '';
   var grid_four_confirmed = '';
   var grid_six_confirmed = '';
}

function gridPlot(form, visitor=true) {
    $(".ld-ext-right-plot").addClass('running');
    $(".ld-ext-right-plot").prop('disabled', true);
    $('#plot').prop("disabled", true);
    // If map is already initialized
    var container = L.DomUtil.get('gridsquare_map');

    if(container != null){
        container._leaflet_id = null;
        container.remove();
        $("#gridmapcontainer").append('<div id="gridsquare_map" class="map-leaflet" style="width: 100%;"></div>');
        set_map_height();
    }

    if (typeof type == 'undefined') { type=''; }
    if (type == "activated") {
        ajax_url = site_url + '/activated_gridmap/getGridsjs';
    } else if (type == "worked") {
        ajax_url = site_url + '/gridmap/getGridsjs';
    } else {
        ajax_url = site_url + '/gridmap/getGridsjs';
    }

    if (visitor != true) {
    $.ajax({
		url: ajax_url,
		type: 'post',
		data: {
			band: $("#band").val(),
            mode: $("#mode").val(),
            qsl:  $("#qsl").is(":checked"),
            lotw: $("#lotw").is(":checked"),
            eqsl: $("#eqsl").is(":checked"),
            qrz: $("#qrz").is(":checked"),
            sat: $("#sat").val(),
            orbit: $("#orbits").val(),
            propagation: $('#propagation').val()
		},
		success: function (data) {
            $('.cohidden').show();
            set_map_height();
            $(".ld-ext-right-plot").removeClass('running');
            $(".ld-ext-right-plot").prop('disabled', false);
            $('#plot').prop("disabled", false);
            grid_two = data.grid_2char;
            grid_four = data.grid_4char;
            grid_six = data.grid_6char;
            grid_two_confirmed = data.grid_2char_confirmed;
            grid_four_confirmed = data.grid_4char_confirmed;
            grid_six_confirmed = data.grid_6char_confirmed;
            plot(visitor, grid_two, grid_four, grid_six, grid_two_confirmed, grid_four_confirmed, grid_six_confirmed);

		},
		error: function (data) {
		},
	});
   } else {
      plot(visitor, grid_two, grid_four, grid_six, grid_two_confirmed, grid_four_confirmed, grid_six_confirmed);
   };
}

function plot(visitor, grid_two, grid_four, grid_six, grid_two_confirmed, grid_four_confirmed, grid_six_confirmed) {
            var layer = L.tileLayer(jslayer, {
                maxZoom: 12,
                attribution: jsattribution,
                id: 'mapbox.streets'
            });

            map = L.map('gridsquare_map', {
            layers: [layer],
            center: [19, 0],
            zoom: 3,
            minZoom: 2,
            fullscreenControl: true,
                fullscreenControlOptions: {
                    position: 'topleft'
                },
            });

            if (visitor != true) {
               var printer = L.easyPrint({
                   tileLayer: layer,
                   sizeModes: ['Current'],
                   filename: 'myMap',
                   exportOnly: true,
                   hideControlContainer: true
               }).addTo(map);
            }

            /*Legend specific*/
            var legend = L.control({ position: "topright" });

            legend.onAdd = function(map) {
                var div = L.DomUtil.create("div", "legend");
                div.innerHTML += "<h4>" + gridsquares_gridsquares + "</h4>";
                div.innerHTML += '<i style="background: green"></i><span>' + gridsquares_gridsquares_confirmed + ' ('+grid_four_confirmed.length+')</span><br>';
                div.innerHTML += '<i style="background: red"></i><span>' + gridsquares_gridsquares_not_confirmed + ' ('+(grid_four.length - grid_four_confirmed.length)+')</span><br>';
                div.innerHTML += '<i></i><span>' + gridsquares_gridsquares_total_worked + ' ('+grid_four.length+')</span><br>';
				div.innerHTML += "<h4>" + gridsquares_fields + "</h4>";
				div.innerHTML += '<i style="background: green"></i><span>' + gridsquares_fields_confirmed + ' ('+grid_two_confirmed.length+')</span><br>';
				div.innerHTML += '<i style="background: red"></i><span>' + gridsquares_fields_not_confirmed + ' ('+(grid_two.length - grid_two_confirmed.length)+')</span><br>';
				div.innerHTML += '<i></i><span>' + gridsquares_fields_total_worked + ' ('+grid_two.length+')</span><br>';
                return div;
            };

            legend.addTo(map);

            var maidenhead = L.maidenhead().addTo(map);
            if (visitor != true) {
               map.on('mousemove', onMapMove);
               map.on('click', onMapClick);
            }
}

function spawnGridsquareModal(loc_4char) {
	if (!(modalloading)) {
		var ajax_data = ({
			'Searchphrase': loc_4char,
			'Band': $("#band").val(),
			'Mode': $("#mode").val(),
			'Sat': $("#sat").val(),
			'Orbit': $("#orbits").val(),
            'Propagation': $('#propagation').val(),
			'Type': 'VUCC'
		})
		if (type == 'activated') {
			ajax_data.searchmode = 'activated';
		}
		modalloading=true;
		$.ajax({
			url: base_url + 'index.php/awards/qso_details_ajax',
			type: 'post',
			data: ajax_data,
			success: function (html) {
		    		var dialog = new BootstrapDialog({
					title: lang_general_word_qso_data,
					cssClass: 'qso-dialog',
					size: BootstrapDialog.SIZE_WIDE,
					nl2br: false,
					message: html,
					onshown: function(dialog) {
						modalloading=false;
						$('[data-bs-toggle="tooltip"]').tooltip();
						$('.displaycontactstable').DataTable({
							"pageLength": 25,
							responsive: false,
							ordering: false,
							"scrollY":        "550px",
							"scrollCollapse": true,
							"paging":         false,
							"scrollX": true,
                            "language": {
                                url: getDataTablesLanguageUrl(),
                            },
							dom: 'Bfrtip',
							buttons: [
								'csv'
							]
						});
						// change color of csv-button if dark mode is chosen
						if (isDarkModeTheme()) {
							$(".buttons-csv").css("color", "white");
						}
                        $('.table-responsive .dropdown-toggle').off('mouseenter').on('mouseenter', function () {
                            showQsoActionsMenu($(this).closest('.dropdown'));
                        });
					},
                    onhide: function(dialog) {
                        enableMap();
                    },
					buttons: [{
						label: lang_admin_close,
						action: function(dialogItself) {
							dialogItself.close();
						}
					}]
				});
			    dialog.realize();
                    $('#gridsquare_map').append(dialog.getModal());
                    disableMap();
		    		dialog.open();
                },
			error: function(e) {
				modalloading=false;
			}
		});
	}
}

function clearMarkers() {
	$(".ld-ext-right-clear").addClass('running');
	$(".ld-ext-right-clear").prop('disabled', true);
	clicklines.forEach(function (item) {
		map.removeLayer(item)
	});
	clickmarkers.forEach(function (item) {
		map.removeLayer(item)
	});
	$(".ld-ext-right-clear").removeClass('running');
	$(".ld-ext-right-clear").prop('disabled', false);
}

$(document).ready(function(){
	gridPlot(this.form, visitor);
	$(window).resize(function () {
		set_map_height();
	});

	var target = document.body;
	var observer = new MutationObserver(function() {
		$('#dt-search-0').on('keyup', function (e) {
			tocrappyzero=$(this).val().toUpperCase().replaceAll(/0/g, 'Ø');
			$(this).val(tocrappyzero);
			$(this).trigger("input");
		});
	});
	var config = { childList: true, subtree: true};
	// pass in the target node, as well as the observer options
	observer.observe(target, config);
});
