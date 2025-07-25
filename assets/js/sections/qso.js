var favs = {};
var selected_sat;
var selected_sat_mode;
var scps = [];
let lookupCall = null;
let preventLookup = false;

// if the dxcc id changes we need to update the state dropdown and clear the county value to avoid wrong data
$("#dxcc_id").on('change', function () {
	updateStateDropdown('#dxcc_id', '#stateInputLabel', '#location_us_county', '#stationCntyInputQso');
	$('#stationCntyInputQso').val('');
	$('#dxcc_id').multiselect('refresh');
});

function resetTimers(qso_manual) {
	if (typeof qso_manual !== 'undefined' && qso_manual != 1) {
		handleStart = setInterval(function () { getUTCTimeStamp($('.input_start_time')); }, 500);
		handleEnd = setInterval(function () { getUTCTimeStamp($('.input_end_time')); }, 500);
		handleDate = setInterval(function () { getUTCDateStamp($('.input_date')); }, 1000);
	}
}

function getUTCTimeStamp(el) {
	var now = new Date();
	$(el).attr('value', ("0" + now.getUTCHours()).slice(-2) + ':' + ("0" + now.getUTCMinutes()).slice(-2) + ':' + ("0" + now.getUTCSeconds()).slice(-2));
}

function getUTCDateStamp(el) {
	var now = new Date();
	$(el).attr('value', ("0" + now.getUTCDate()).slice(-2) + '-' + ("0" + (now.getUTCMonth() + 1)).slice(-2) + '-' + now.getUTCFullYear());
}


$('#stationProfile').on('change', function () {
	var stationProfile = $('#stationProfile').val();
	$.ajax({
		url: base_url + 'index.php/qso/get_station_power',
		type: 'post',
		data: { 'stationProfile': stationProfile },
		success: function (res) {
			$('#transmit_power').val(res.station_power);
			latlng=[res.lat,res.lng];
			$("#sat_name").change();
		},
		error: function () {
			$('#transmit_power').val('');
		},
	});
	// [eQSL default msg] change value on change station profle //
	qso_set_eqsl_qslmsg(stationProfile, false, '.qso_panel');
});

// [eQSL default msg] change value on clic //
$('.qso_panel .qso_eqsl_qslmsg_update').off('click').on('click', function () {
	qso_set_eqsl_qslmsg($('.qso_panel #stationProfile').val(), true, '.qso_panel');
	$('#charsLeft').text(" ");
});

$(document).on("keydown", function (e) {
	if (e.key === "Escape" && $('#callsign').val() != '') { // escape key maps to keycode `27`
		// console.log("Escape key pressed");
		reset_fields();
		$('#callsign').trigger("focus");
	}
});

// Sanitize some input data
$('#callsign').on('input', function () {
	$(this).val($(this).val().replace(/\s/g, ''));
	$(this).val($(this).val().replace(/0/g, 'Ø'));
	$(this).val($(this).val().replace(/\./g, '/P'));
	$(this).val($(this).val().replace(/\ /g, ''));
});

$('#locator').on('input', function () {
	$(this).val($(this).val().replace(/\s/g, ''));
});

$("#check_cluster").on("click", function () {
	$.ajax({ url: dxcluster_provider + "/qrg_lookup/" + $("#frequency").val() / 1000, cache: false, dataType: "json" }).done(
		function (dxspot) {
			reset_fields();
			$("#callsign").val(dxspot.spotted);
			$("#callsign").trigger("blur");
		}
	);
});

function set_timers() {
	setTimeout(function () {
		var callsignValue = localStorage.getItem("quicklogCallsign");
		if (callsignValue !== null && callsignValue !== undefined) {
			$("#callsign").val(callsignValue);
			$("#mode").trigger("focus");
			localStorage.removeItem("quicklogCallsign");
		}
	}, 100);
}

function invalidAntEl() {
	var saveQsoButtonText = $("#saveQso").html();
	$("#noticer").removeClass("");
	$("#noticer").addClass("alert alert-warning");
	$("#noticer").html(lang_invalid_ant_el+" "+parseFloat($("#ant_el").val()).toFixed(1));
	$("#noticer").show();
	$("#saveQso").html(saveQsoButtonText).prop("disabled", false);
}

$("#qso_input").off('submit').on('submit', function (e) {
	var _submit = true;
	if ((typeof qso_manual !== "undefined") && (qso_manual == "1")) {
		if ($('#qso_input input[name="end_time"]').length == 1) { _submit = testTimeOffConsistency(); }
	}
	if (_submit) {
		var saveQsoButtonText = $("#saveQso").html();
		$("#saveQso").html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> ' + saveQsoButtonText + '...').prop('disabled', true);
		manual_addon = '?manual=' + qso_manual;
		e.preventDefault();
		$.ajax({
			url: base_url + 'index.php/qso' + manual_addon,
			method: 'POST',
			type: 'post',
			timeout: 10000,
			data: $(this).serialize(),
			success: function (resdata) {
				result = JSON.parse(resdata);
				if (result.message == 'success') {
					activeStationId = result.activeStationId;
					activeStationOP = result.activeStationOP;
					activeStationTXPower = result.activeStationTXPower;
					$("#noticer").removeClass("");
					$("#noticer").addClass("alert alert-info");
					$("#noticer").html("QSO Added");
					$("#noticer").show();
					prepare_next_qso(saveQsoButtonText);
					$("#noticer").fadeOut(2000);
					processBacklog();	// If we have success with the live-QSO, we could also process the backlog
				} else {
					$("#noticer").removeClass("");
					$("#noticer").addClass("alert alert-warning");
					$("#noticer").html(result.errors);
					$("#noticer").show();
					$("#saveQso").html(saveQsoButtonText).prop("disabled", false);
				}
			},
			error: function () {
				saveToBacklog(JSON.stringify(this.data),manual_addon);
				prepare_next_qso(saveQsoButtonText);
				$("#noticer").removeClass("");
				$("#noticer").addClass("alert alert-info");
				$("#noticer").html("QSO Added to Backlog");
				$("#noticer").show();
				$("#noticer").fadeOut(5000);
			}
		});
	}
	return false;
});

function prepare_next_qso(saveQsoButtonText) {
	reset_fields();
	htmx.trigger("#qso-last-table", "qso_event")
	$("#saveQso").html(saveQsoButtonText).prop("disabled", false);
	$("#callsign").val("");
	var triggerEl = document.querySelector('#myTab a[href="#qso"]')
	bootstrap.Tab.getInstance(triggerEl).show() // Select tab by name
	$("#callsign").trigger("focus");
}

var processingBL=false;

async function processBacklog() {
	if (!processingBL) {
		processingBL=true;
		const Qsobacklog = JSON.parse(localStorage.getItem('qso-backlog')) || [];
		for (const entry of [...Qsobacklog]) {
			try {
				await $.ajax({url: base_url + 'index.php/qso' + entry.manual_addon,  method: 'POST', type: 'post', data: JSON.parse(entry.data),
					success: function(resdata) {
						Qsobacklog.splice(Qsobacklog.findIndex(e => e.id === entry.id), 1);
					},
					error: function() {
						entry.attempts++;
					}});
			} catch (error) {
				entry.attempts++;
			}
		}
		localStorage.setItem('qso-backlog', JSON.stringify(Qsobacklog));
		processingBL=false;
	}
}

function saveToBacklog(formData,manual_addon) {
	const backlog = JSON.parse(localStorage.getItem('qso-backlog')) || [];
	const entry = {
		id: Date.now(),
		timestamp: new Date().toISOString(),
		data: formData,
		manual_addon: manual_addon,
		attempts: 0
	};
	backlog.push(entry);
	localStorage.setItem('qso-backlog', JSON.stringify(backlog));
}

window.addEventListener('beforeunload', processBacklog());	// process possible QSO-Backlog on unload of page
window.addEventListener('pagehide', processBacklog());		// process possible QSO-Backlog on Hide of page (Mobile-Browsers)

$('#reset_time').on("click", function () {
	var now = new Date();
	$('#start_time').attr('value', ("0" + now.getUTCHours()).slice(-2) + ':' + ("0" + now.getUTCMinutes()).slice(-2) + ':' + ("0" + now.getUTCSeconds()).slice(-2));
	$("[id='start_time']").each(function () {
		$(this).attr("value", ("0" + now.getUTCHours()).slice(-2) + ':' + ("0" + now.getUTCMinutes()).slice(-2) + ':' + ("0" + now.getUTCSeconds()).slice(-2));
	});
});



// Function to format the current time as HH:MM or HH:MM:SS
function formatTime(date, includeSeconds) {
	let time = ("0" + date.getUTCHours()).slice(-2) + ":" + ("0" + date.getUTCMinutes()).slice(-2);
	if (includeSeconds) {
		time += ":" + ("0" + date.getUTCSeconds()).slice(-2);
	}
	return time;
}

// Event listener for resetting start time
$("#reset_start_time").on("click", function () {
	var now = new Date();

	// Format start and end times
	let startTime = formatTime(now, qso_manual != 1);
	let endTime = formatTime(now, qso_manual != 1);

	// Update all elements with id 'start_time'
	$("[id='start_time']").each(function () {
		$(this).val(startTime);
	});

	// Update all elements with id 'end_time'
	$("[id='end_time']").each(function () {
		$(this).val(endTime);
	});

	// Update the start date
	$("#start_date").val(
		("0" + now.getUTCDate()).slice(-2) +
		"-" +
		("0" + (now.getUTCMonth() + 1)).slice(-2) +
		"-" +
		now.getUTCFullYear()
	);
});

// Event listener for resetting end time
$("#reset_end_time").on("click", function () {
	var now = new Date();

	// Format end time
	let endTime = formatTime(now, qso_manual != 1);

	// Update all elements with id 'end_time'
	$("[id='end_time']").each(function () {
		$(this).val(endTime);
	});
});

$('#fav_add').on("click", function (event) {
	save_fav();
});

$(document).on("click", "#fav_del", function (event) {
	del_fav($(this).attr('name'));
});

$(document).on("click", "#fav_recall", function (event) {
	$('#sat_name').val(favs[this.innerText].sat_name);
	if (favs[this.innerText].sat_name) {
		$("#sat_name").change();
	}
	$('#sat_mode').val(favs[this.innerText].sat_mode);
	$('#band_rx').val(favs[this.innerText].band_rx);
	$('#band').val(favs[this.innerText].band);
	$('#frequency_rx').val(favs[this.innerText].frequency_rx);
	$('#frequency').val(favs[this.innerText].frequency).trigger("change");
	$('#selectPropagation').val(favs[this.innerText].prop_mode);
	$('#mode').val(favs[this.innerText].mode).on("change");
});


function del_fav(name) {
	if (confirm("Are you sure to delete Fav?")) {
		$.ajax({
			url: base_url + 'index.php/user_options/del_fav',
			method: 'POST',
			dataType: 'json',
			contentType: "application/json; charset=utf-8",
			data: JSON.stringify({ "option_name": name }),
			success: function (result) {
				get_fav();
			}
		});
	}
}

function get_fav() {
	$.ajax({
		url: base_url + 'index.php/user_options/get_fav',
		method: 'GET',
		dataType: 'json',
		contentType: "application/json; charset=utf-8",
		success: function (result) {
			$("#fav_menu").empty();
			for (const key in result) {
				$("#fav_menu").append('<label class="dropdown-item" style="display: flex; justify-content: space-between;"><span id="fav_recall">' + key + '</span><span class="badge bg-danger" id="fav_del" name="' + key + '"><i class="fas fa-trash-alt"></i></span></label>');
			}
			favs = result;
		}
	});
}

function save_fav() {
	var payload = {};
	payload.sat_name = $('#sat_name').val();
	payload.sat_mode = $('#sat_mode').val();
	payload.band_rx = $('#band_rx').val();
	payload.band = $('#band').val();
	payload.frequency_rx = $('#frequency_rx').val();
	payload.frequency = $('#frequency').val();
	payload.prop_mode = $('#selectPropagation').val();
	payload.mode = $('#mode').val();
	$.ajax({
		url: base_url + 'index.php/user_options/add_edit_fav',
		method: 'POST',
		dataType: 'json',
		contentType: "application/json; charset=utf-8",
		data: JSON.stringify(payload),
		success: function (result) {
			get_fav();
		}
	});
}


var bc_bandmap = new BroadcastChannel('qso_window');
bc_bandmap.onmessage = function (ev) {
	if (ev.data == 'ping' && qso_manual == 0) {
		bc_bandmap.postMessage('pong');
	}
}

var bc = new BroadcastChannel('qso_wish');
bc.onmessage = function (ev) {
	if (qso_manual == 0) {
		if (ev.data.ping) {
			let message = {};
			message.pong = true;
			bc.postMessage(message);
		} else {
			// console.log(ev.data);
			let delay = 0;
			if ($("#callsign").val() != "") {
				reset_fields();
				delay = 600;
			}
			setTimeout(() => {
				if (ev.data.frequency != null) {
					$('#frequency').val(ev.data.frequency).trigger("change");
					$("#band").val(frequencyToBand(ev.data.frequency));
				}
				if (ev.data.frequency_rx != "") {
					$('#frequency_rx').val(ev.data.frequency_rx);
					$("#band_rx").val(frequencyToBand(ev.data.frequency_rx));
				}
				$("#callsign").val(ev.data.call);
				$("#callsign").focusout();
				$("#callsign").blur();
			}, delay);
		}
	}
} /* receive */

$("#sat_name").on('change', function () {
	var sat = $("#sat_name").val();
	if (sat == "") {
		$("#sat_mode").val("");
		$("#selectPropagation").val("");
		stop_az_ele_ticker();
	} else {
		get_tles();
	}
});


var satupdater;

function stop_az_ele_ticker() {
	if (satupdater) {
		clearInterval(satupdater);
	}
	$("#ant_az").val('');
	$("#ant_el").val('');
}

function start_az_ele_ticker(tle) {
	const lines = tle.tle.trim().split('\n');

	// Initialize a satellite record
	var satrec = satellite.twoline2satrec(lines[0], lines[1]);

	// Define the observer's location in radians
	var observerGd = {
		longitude: satellite.degreesToRadians(latlng[1]),
		latitude: satellite.degreesToRadians(latlng[0]),
		height: 0.370
	};

	function updateAzEl() {
		let dateParts=$('#start_date').val().split("-");
		let timeParts=$("#start_time").val().split(":");
		try {
			var time = new Date(Date.UTC(
				parseInt(dateParts[2]),parseInt(dateParts[1])-1,parseInt(dateParts[0]),
				parseInt(timeParts[0]),parseInt(timeParts[1]),(parseInt(timeParts[2] ?? 0))
			));
			if (isNaN(time.getTime())) {
				throw new Error("Invalid date");
			}
			var positionAndVelocity = satellite.propagate(satrec, time);
			var gmst = satellite.gstime(time);
			var positionEcf = satellite.eciToEcf(positionAndVelocity.position, gmst);
			var observerEcf = satellite.geodeticToEcf(observerGd);
			var lookAngles = satellite.ecfToLookAngles(observerGd, positionEcf);
			let az=(satellite.radiansToDegrees(lookAngles.azimuth).toFixed(2));
			let el=(satellite.radiansToDegrees(lookAngles.elevation).toFixed(2));
			$("#ant_az").val(parseFloat(az).toFixed(1));
			$("#ant_el").val(parseFloat(el).toFixed(1));
		} catch(e) {
			$("#ant_az").val('');
			$("#ant_el").val('');
		}
	}
	satupdater=setInterval(updateAzEl, 1000);
}

function get_tles() {
	stop_az_ele_ticker();
	$.ajax({
		url: base_url + 'index.php/satellite/get_tle',
		type: 'post',
		data: {
			sat: $("#sat_name").val(),
		},
		success: function (data) {
			if (data !== null) {
				start_az_ele_ticker(data);
			}
		},
		error: function (data) {
			console.log('Something went wrong while trying to fetch TLE for sat: '+$("#sat_name"));
		},
	});
}

if ($("#sat_name").val() !== '') {
	get_tles();
}

$('#stateDropdown').on('change', function () {
	var state = $("#stateDropdown option:selected").text();
	var dxcc = $("#dxcc_id option:selected").val();

	if (state != "") {
		switch (dxcc) {
			case '6':
			case '110':
			case '291':
				$("#stationCntyInputQso").prop('disabled', false);
				$('#stationCntyInputQso').selectize({
					maxItems: 1,
					closeAfterSelect: true,
					loadThrottle: 250,
					valueField: 'name',
					labelField: 'name',
					searchField: 'name',
					options: [],
					create: false,
					load: function (query, callback) {
						var state = $("#stateDropdown option:selected").text();

						if (!query || state == "") return callback();
						$.ajax({
							url: base_url + 'index.php/qso/get_county',
							type: 'GET',
							dataType: 'json',
							data: {
								query: query,
								state: state,
							},
							error: function () {
								callback();
							},
							success: function (res) {
								callback(res);
							}
						});
					}
				});
				break;
			case '15':
			case '54':
			case '61':
			case '126':
			case '151':
			case '288':
			case '339':
			case '170':
			case '21':
			case '29':
			case '32':
			case '281':
				$("#stationCntyInputQso").prop('disabled', false);
				break;
			default:
				$("#stationCntyInputQso").prop('disabled', true);
		}

	} else {
		$("#stationCntyInputQso").prop('disabled', true);
		//$('#stationCntyInputQso')[0].selectize.destroy();
		$("#stationCntyInputQso").val("");
	}
});

$(document).on('change', 'input', function () {
	var optionslist = $('.satellite_modes_list')[0].options;
	var value = $(this).val();
	for (var x = 0; x < optionslist.length; x++) {
		if (optionslist[x].value === value) {

			// Store selected sat mode
			selected_sat_mode = value;

			// get Json file
			$.getJSON(site_url + "/satellite/satellite_data", function (data) {

				// Build the options array
				var sat_modes = [];
				$.each(data, function (key, val) {
					if (key == selected_sat) {
						$.each(val.Modes, function (key1, val2) {
							if (key1 == selected_sat_mode) {

								if ((val2[0].Downlink_Mode == "LSB" && val2[0].Uplink_Mode == "USB") || (val2[0].Downlink_Mode == "USB" && val2[0].Uplink_Mode == "LSB")) { // inverting Transponder? set to SSB
									$("#mode").val("SSB");
								} else {
									$("#mode").val(val2[0].Uplink_Mode);
								}
								$("#band").val(frequencyToBand(val2[0].Uplink_Freq));
								$("#band_rx").val(frequencyToBand(val2[0].Downlink_Freq));
								$("#frequency").val(val2[0].Uplink_Freq).trigger("change");
								$("#frequency_rx").val(val2[0].Downlink_Freq);
								$("#selectPropagation").val('SAT');
							}
						});
					}
				});

			});
		}
	}
});

$(document).on('change', 'input', function () {
	var optionslist = $('.satellite_names_list')[0].options;
	var value = $(this).val();
	for (var x = 0; x < optionslist.length; x++) {
		if (optionslist[x].value === value) {
			$("#sat_mode").val("");
			$('.satellite_modes_list').find('option').remove().end();
			selected_sat = value;
			// get Json file
			$.getJSON(site_url + "/satellite/satellite_data", function (data) {

				// Build the options array
				var sat_modes = [];
				$.each(data, function (key, val) {
					if (key == value) {
						$.each(val.Modes, function (key1, val2) {
							//console.log (key1);
							sat_modes.push('<option value="' + key1 + '">' + key1 + '</option>');
						});
					}
				});

				// Add to the datalist
				$('.satellite_modes_list').append(sat_modes.join(""));

			});
		}
	}
});

function changebadge(entityname) {
	if ($("#sat_name").val() != "") {
		$.getJSON(base_url + 'index.php/logbook/jsonlookupdxcc/' + convert_case(entityname) + '/SAT/0/0', function (result) {

			$('#callsign_info').removeClass("lotw_info_orange");
			$('#callsign_info').removeClass("text-bg-secondary");
			$('#callsign_info').removeClass("text-bg-success");
			$('#callsign_info').removeClass("text-bg-danger");
			$('#callsign_info').attr('title', '');

			if (result.confirmed) {
				$('#callsign_info').addClass("text-bg-success");
				$('#callsign_info').attr('title', 'DXCC was already worked and confirmed in the past on this band and mode!');
			} else if (result.workedBefore) {
				$('#callsign_info').addClass("text-bg-success");
				$('#callsign_info').addClass("lotw_info_orange");
				$('#callsign_info').attr('title', 'DXCC was already worked in the past on this band and mode!');
			} else {
				$('#callsign_info').addClass("text-bg-danger");
				$('#callsign_info').attr('title', 'New DXCC, not worked on this band and mode!');
			}
		})
	} else {
		$.getJSON(base_url + 'index.php/logbook/jsonlookupdxcc/' + convert_case(entityname) + '/0/' + $("#band").val() + '/' + $("#mode").val(), function (result) {
			// Reset CSS values before updating
			$('#callsign_info').removeClass("lotw_info_orange");
			$('#callsign_info').removeClass("text-bg-secondary");
			$('#callsign_info').removeClass("text-bg-success");
			$('#callsign_info').removeClass("text-bg-danger");
			$('#callsign_info').attr('title', '');

			if (result.confirmed) {
				$('#callsign_info').addClass("text-bg-success");
				$('#callsign_info').attr('title', 'DXCC was already worked and confirmed in the past on this band and mode!');
			} else if (result.workedBefore) {
				$('#callsign_info').addClass("text-bg-success");
				$('#callsign_info').addClass("lotw_info_orange");
				$('#callsign_info').attr('title', 'DXCC was already worked in the past on this band and mode!');
			} else {
				$('#callsign_info').addClass("text-bg-danger");
				$('#callsign_info').attr('title', 'New DXCC, not worked on this band and mode!');
			}
		})
	}
}

$('#btn_reset').on("click", function () {
	preventLookup = true;

	if (lookupCall) {
		lookupCall.abort();
	}

	reset_fields();

	// make sure the focusout event is finished before we allow a new lookup
	setTimeout(() => {
		preventLookup = false;
	}, 100);
});

$('#btn_fullreset').on("click", function () {
	reset_to_default();
});

function reset_to_default() {
	reset_fields();
	panMap(activeStationId);
	$("#stationProfile").val(activeStationId);
	$("#selectPropagation").val("");
	$("#frequency_rx").val("");
	$("#band_rx").val("");
	$("#transmit_power").val(activeStationTXPower);
	$("#sat_name").val("");
	$("#sat_mode").val("");
	$("#ant_az").val("");
	$("#ant_el").val("");
	$("#distance").val("");
	stop_az_ele_ticker();
}

/* Function: reset_fields is used to reset the fields on the QSO page */
function reset_fields() {
	$('#locator_info').text("");
	$('#comment').val("");
	$('#country').val("");
	$('#continent').val("");
	$('#email').val("");
	$('#region').val("");
	$('#ham_of_note_info').text("");
	$('#ham_of_note_link').html("");
	$('#ham_of_note_link').removeAttr('href');
	$('#ham_of_note_line').hide();
	$('#lotw_info').text("");
	$('#lotw_info').attr('data-bs-original-title', "");
	$('#lotw_info').removeClass("lotw_info_red");
	$('#lotw_info').removeClass("lotw_info_yellow");
	$('#lotw_info').removeClass("lotw_info_orange");
	$('#qrz_info').text("").hide();
	$('#hamqth_info').text("").hide();
	$('#dxcc_id').val("").multiselect('refresh');
	$('#cqz').val("");
	$('#ituz').val("");
	$('#name').val("");
	$('#qth').val("");
	$('#locator').val("");
	$('#ant_path').val("");
	$('#iota_ref').val("");
	$("#locator").removeClass("confirmedGrid");
	$("#locator").removeClass("workedGrid");
	$("#locator").removeClass("newGrid");
	$("#callsign").val("");
	$("#callsign").removeClass("confirmedGrid");
	$("#callsign").removeClass("workedGrid");
	$("#callsign").removeClass("newGrid");
	$('#callsign_info').removeClass("text-bg-secondary");
	$('#callsign_info').removeClass("text-bg-success");
	$('#callsign_info').removeClass("text-bg-danger");
	$('#callsign-image').attr('style', 'display: none;');
	$('#callsign-image-content').text("");
	$("#operator_callsign").val(activeStationOP);
	$('#qsl_via').val("");
	$('#callsign_info').text("");
	$('#stateDropdown').val("");
	$('#qso-last-table').show();
	$('#partial_view').hide();
	$('.callsign-suggest').hide();
	$("#distance").val("");
	setRst($(".mode").val());
	var $select = $('#sota_ref').selectize();
	var selectize = $select[0].selectize;
	selectize.clear();
	var $select = $('#wwff_ref').selectize();
	var selectize = $select[0].selectize;
	selectize.clear();
	var $select = $('#pota_ref').selectize();
	var selectize = $select[0].selectize;
	selectize.clear();
	var $select = $('#darc_dok').selectize();
	var selectize = $select[0].selectize;
	selectize.clear();
	$('#stationCntyInputQso').val("");
	$select = $('#stationCntyInputQso').selectize();
	selectize = $select[0].selectize;
	selectize.clear();

	var $select = $('#sota_ref').selectize();
	var selectize = $select[0].selectize;
	selectize.clear();

	$('#notes').val("");

	$('#sig').val("");
	$('#sig_info').val("");
	$('#sent').val("N");
	$('#sent-method').val("");
	$('#qsl_via').val("");

	mymap.setView(pos, 12);
	mymap.removeLayer(markers);
	$('.callsign-suggest').hide();
	$('.awardpane').remove();
	$('#timesWorked').html(lang_qso_title_previous_contacts);
	updateStateDropdown('#dxcc_id', '#stateInputLabel', '#location_us_county', '#stationCntyInputEdit');
	clearTimeout();
	set_timers();
	resetTimers(qso_manual);
}

$("#callsign").on("focusout", function () {
	if ($(this).val().length >= 3 && preventLookup == false) {

		$("#noticer").fadeOut(1000);

		// Temp store the callsign
		var temp_callsign = $(this).val();

		/* Find and populate DXCC */
		$('.callsign-suggest').hide();

		if ($("#sat_name").val() != "") {
			var json_band = "SAT";
		} else {
			var json_band = $("#band").val();
		}
		var json_mode = $("#mode").val();

		var find_callsign = $(this).val().toUpperCase();
		var callsign = find_callsign;

		find_callsign = find_callsign.replace(/\//g, "-");
		find_callsign = find_callsign.replaceAll('Ø', '0');

		// Replace / in a callsign with - to stop urls breaking
		lookupCall = $.getJSON(base_url + 'index.php/logbook/json/' + find_callsign + '/' + json_band + '/' + json_mode + '/' + $('#stationProfile').val() + '/' + $('#start_date').val() + '/' + last_qsos_count, async function (result) {

			// Make sure the typed callsign and json result match
			if ($('#callsign').val().toUpperCase().replaceAll('Ø', '0') == result.callsign) {

				// Reset QSO fields
				resetDefaultQSOFields();

				if (result.dxcc.entity != undefined) {
					$('#country').val(convert_case(result.dxcc.entity));
					$('#callsign_info').text(convert_case(result.dxcc.entity));

					if ($("#sat_name").val() != "") {
						//logbook/jsonlookupgrid/io77/SAT/0/0
						await $.getJSON(base_url + 'index.php/logbook/jsonlookupcallsign/' + find_callsign + '/SAT/0/0', function (result) {
							// Reset CSS values before updating
							$('#callsign').removeClass("workedGrid");
							$('#callsign').removeClass("confirmedGrid");
							$('#callsign').removeClass("newGrid");
							$('#callsign').attr('title', '');
							$('#ham_of_note_info').text("");
							$('#ham_of_note_link').html("");
							$('#ham_of_note_link').removeAttr('href');
							$('#ham_of_note_line').hide();

							if (result.confirmed) {
								$('#callsign').addClass("confirmedGrid");
								$('#callsign').attr('title', 'Callsign was already worked and confirmed in the past on this band and mode!');
							} else if (result.workedBefore) {
								$('#callsign').addClass("workedGrid");
								$('#callsign').attr('title', 'Callsign was already worked in the past on this band and mode!');
							}
							else {
								$('#callsign').addClass("newGrid");
								$('#callsign').attr('title', 'New Callsign!');
							}
						})
					} else {
						await $.getJSON(base_url + 'index.php/logbook/jsonlookupcallsign/' + find_callsign + '/0/' + $("#band").val() + '/' + $("#mode").val(), function (result) {
							// Reset CSS values before updating
							$('#callsign').removeClass("confirmedGrid");
							$('#callsign').removeClass("workedGrid");
							$('#callsign').removeClass("newGrid");
							$('#callsign').attr('title', '');
							$('#ham_of_note_info').text("");
							$('#ham_of_note_link').html("");
							$('#ham_of_note_link').removeAttr('href');
							$('#ham_of_note_line').hide();

							if (result.confirmed) {
								$('#callsign').addClass("confirmedGrid");
								$('#callsign').attr('title', 'Callsign was already worked and confirmed in the past on this band and mode!');
							} else if (result.workedBefore) {
								$('#callsign').addClass("workedGrid");
								$('#callsign').attr('title', 'Callsign was already worked in the past on this band and mode!');
							} else {
								$('#callsign').addClass("newGrid");
								$('#callsign').attr('title', 'New Callsign!');
							}

						})
					}

					changebadge(result.dxcc.entity);

				}

				if (result.lotw_member == "active") {
					$('#lotw_info').text("LoTW");
					if (result.lotw_days > 365) {
						$('#lotw_info').addClass('lotw_info_red');
					} else if (result.lotw_days > 30) {
						$('#lotw_info').addClass('lotw_info_orange');
						$lotw_hint = ' lotw_info_orange';
					} else if (result.lotw_days > 7) {
						$('#lotw_info').addClass('lotw_info_yellow');
					}
					$('#lotw_link').attr('href', "https://lotw.arrl.org/lotwuser/act?act=" + callsign.replace('Ø', '0'));
					$('#lotw_link').attr('target', "_blank");
					$('#lotw_info').attr('data-bs-toggle', "tooltip");
					if (result.lotw_days == 1) {
						$('#lotw_info').attr('data-bs-original-title', lang_lotw_upload_day_ago);
					} else {
						$('#lotw_info').attr('data-bs-original-title', lang_lotw_upload_days_ago.replace('%x', result.lotw_days));
					}
					$('[data-bs-toggle="tooltip"]').tooltip();
				}
				$('#qrz_info').html('<a target="_blank" href="https://www.qrz.com/db/' + callsign.replaceAll('Ø', '0') + '"><img width="30" height="30" src="' + base_url + 'images/icons/qrz.com.png"></a>');
				$('#qrz_info').attr('title', 'Lookup ' + callsign + ' info on qrz.com').removeClass('d-none');
				$('#qrz_info').show();
				$('#hamqth_info').html('<a target="_blank" href="https://www.hamqth.com/' + callsign.replaceAll('Ø', '0') + '"><img width="30" height="30" src="' + base_url + 'images/icons/hamqth.com.png"></a>');
				$('#hamqth_info').attr('title', 'Lookup ' + callsign + ' info on hamqth.com').removeClass('d-none');
				$('#hamqth_info').show();

				var $dok_select = $('#darc_dok').selectize();
				var dok_selectize = $dok_select[0].selectize;
				if ((result.dxcc.adif == '230') && (($("#callsign").val().trim().length) > 0)) {
					$.get(base_url + 'index.php/lookup/dok/' + $('#callsign').val().toUpperCase().replaceAll('Ø', '0').replaceAll('/','-'), function (result) {
						if (result) {
							dok_selectize.addOption({ name: result });
							dok_selectize.setValue(result, false);
						}
					});
				} else {
					dok_selectize.clear();
				}

				$.getJSON(base_url + 'index.php/lookup/ham_of_note/' + $('#callsign').val().toUpperCase().replaceAll('Ø', '0').replaceAll('/','-'), function (result) {
					if (result) {
						$('#ham_of_note_info').html('<span class="minimize">'+result.description+'</span>');
						if (result.link != null) {
							$('#ham_of_note_link').html(" "+result.linkname);
							$('#ham_of_note_link').attr('href', result.link);
						}
						$('#ham_of_note_line').show("slow");

						var minimized_elements = $('span.minimize');
						var maxlen = 50;

						minimized_elements.each(function(){
							var t = $(this).text();
							if(t.length < maxlen) return;
							$(this).html(
								t.slice(0,maxlen)+'<span>... </span><a href="#" class="more">'+lang_qso_more+'</a><span style="display:none;">'+ t.slice(maxlen,t.length)+' <a href="#" class="less">'+lang_qso_less+'</a></span>'
							);
						});

						$('a.more', minimized_elements).click(function(event){
							event.preventDefault();
							$(this).hide().prev().hide();
							$(this).next().show();
						});

						$('a.less', minimized_elements).click(function(event){
							event.preventDefault();
							$(this).parent().hide().prev().show().prev().show();
						});

					}
				});
				$('#dxcc_id').val(result.dxcc.adif).multiselect('refresh');
				await updateStateDropdown('#dxcc_id', '#stateInputLabel', '#location_us_county', '#stationCntyInputEdit');
				if (result.callsign_cqz != '' && (result.callsign_cqz >= 1 && result.callsign_cqz <= 40)) {
					$('#cqz').val(result.callsign_cqz);
				} else {
					$('#cqz').val(result.dxcc.cqz);
				}

				if (result.callsign_ituz != '') {
					$('#ituz').val(result.callsign_ituz);
				} else {
					$('#ituz').val(result.dxcc.ituz);
				}

				var redIcon = L.icon({
					iconUrl: icon_dot_url,
					iconSize: [18, 18], // size of the icon
				});

				// Set Map to Lat/Long
				markers.clearLayers();
				mymap.setZoom(8);
				if (typeof result.latlng !== "undefined" && result.latlng !== false) {
					var marker = L.marker([result.latlng[0], result.latlng[1]], { icon: redIcon });
					mymap.panTo([result.latlng[0], result.latlng[1]]);
					mymap.setView([result.latlng[0], result.latlng[1]], 8);
				} else {
					var marker = L.marker([result.dxcc.lat, result.dxcc.long], { icon: redIcon });
					mymap.panTo([result.dxcc.lat, result.dxcc.long]);
					mymap.setView([result.dxcc.lat, result.dxcc.long], 8);
				}

				markers.addLayer(marker).addTo(mymap);


				/* Find Locator if the field is empty */
				if ($('#locator').val() == "") {
					$('#locator').val(result.callsign_qra);
					$('#locator_info').html(result.bearing);

					if (result.callsign_distance != "" && result.callsign_distance != 0) {
						document.getElementById("distance").value = result.callsign_distance;
					}

					if (result.callsign_qra != "") {
						if (result.confirmed) {
							$('#locator').addClass("confirmedGrid");
							$('#locator').attr('title', 'Grid was already worked and confirmed in the past');
						} else if (result.workedBefore) {
							$('#locator').addClass("workedGrid");
							$('#locator').attr('title', 'Grid was already worked in the past');
						} else {
							$('#locator').addClass("newGrid");
							$('#locator').attr('title', 'New grid!');
						}
					} else {
						$('#locator').removeClass("workedGrid");
						$('#locator').removeClass("confirmedGrid");
						$('#locator').removeClass("newGrid");
						$('#locator').attr('title', '');
					}

				}

				/* Find Operators Name */
				if ($('#qsl_via').val() == "") {
					$('#qsl_via').val(result.qsl_manager);
				}

				/* Find Operators Name */
				if ($('#name').val() == "") {
					$('#name').val(result.callsign_name);
				}

				/* Find Operators E-mail */
				if ($('#email').val() == "") {
					$('#email').val(result.callsign_email);
				}

				if ($('#continent').val() == "") {
					$('#continent').val(result.dxcc.cont);
				}

				if ($('#qth').val() == "") {
					$('#qth').val(result.callsign_qth);
				}

				/* Find link to qrz.com picture */
				if (result.image != "n/a") {
					$('#callsign-image-content').html('<img class="callsign-image-pic" href="' + result.image + '" data-fancybox="images" src="' + result.image + '" style="cursor: pointer;">');
					$('#callsign-image').attr('style', 'display: true;');
				}

				/*
					* Update state with returned value
					*/
				if ($("#stateDropdown").val() == "") {
					$("#stateDropdown").val(result.callsign_state);
				}

				/*
					* Update county with returned value for USA only for now
					* and make sure control is enabled for others
					* with cnty info
					*/
				var dxcc = $('#dxcc_id').val();
				switch (dxcc) {
					case '6':
					case '110':
					case '291':
						selectize_usa_county('#stateDropdown', '#stationCntyInputQso');
						if ($('#stationCntyInputQso').has('option').length == 0 && result.callsign_us_county != "") {
							var county_select = $('#stationCntyInputQso').selectize();
							var county_selectize = county_select[0].selectize;
							county_selectize.addOption({ name: result.callsign_us_county });
							county_selectize.setValue(result.callsign_us_county, false);
						}
						break;
					case '15':
					case '54':
					case '61':
					case '126':
					case '151':
					case '288':
					case '339':
					case '170':
					case '21':
					case '29':
					case '32':
					case '281':
						if (result.callsign_state == "") {
							$("#stationCntyInputQso").prop('disabled', true);
						} else {
							$("#stationCntyInputQso").prop('disabled', false);
							$("#stationCntyInputQso").val(result.callsign_us_county);
						}
						break;
					default:
						$("#stationCntyInputQso").prop('disabled', false);
					}


				if (result.timesWorked != "") {
					if (result.timesWorked == '0') {
						$('#timesWorked').html(lang_qso_title_not_worked_before);
					} else {
						$('#timesWorked').html(result.timesWorked + ' ' + lang_qso_title_times_worked_before);
					}
				} else {
					$('#timesWorked').html(lang_qso_title_previous_contacts);
				}
				if ($('#iota_ref').val() == "") {
					$('#iota_ref').val(result.callsign_iota);
				}
				// Hide the last QSO table
				$('#qso-last-table').hide();
				$('#partial_view').show();
				/* display past QSOs */
				$('#partial_view').html(result.partial);

				// Get DXCC Summary
				loadAwardTabs(function() {
					getDxccResult(result.dxcc.adif, convert_case(result.dxcc.entity));
				});
			}
			// else {
			// 	console.log("Callsigns do not match, skipping lookup");
			// 	console.log("Typed Callsign: " + $('#callsign').val());
			// 	console.log("Returned Callsign: " + result.callsign);
			// }
		});
	} else {
		// Reset QSO fields
		resetDefaultQSOFields();
	}
})

// This function executes the call to the backend for fetching cq summary and inserted table below qso entry
function getCqResult() {
	satOrBand = $('#band').val();
	if ($('#selectPropagation').val() == 'SAT') {
		satOrBand = 'SAT';
	}
	$.ajax({
		url: base_url + 'index.php/lookup/search',
		type: 'post',
		data: {
			type: 'cq',
			cqz: $('#cqz').val(),
			reduced_mode: true,
			current_band: satOrBand,
			current_mode: $('#mode').val(),
		},
		success: function (html) {
            $('#cq-summary').empty();
			$('#cq-summary').append(lang_summary_cq + ' ' + $('#cqz').val() + '.');
            $('#cq-summary').append(html);
		}
	});
}

// This function executes the call to the backend for fetching was summary and inserted table below qso entry
function getWasResult() {
	$('#state-summary').empty();
	if ($('#stateDropdown').val() === '') {
		$('#state-summary').append(lang_summary_warning_empty_state);
		return;
	}

	let dxccid = $('#dxcc_id').val();
	if (!['291', '6', '110'].includes(dxccid)) {
		$('#state-summary').append(lang_summary_state_valid);
		return;
	}
	satOrBand = $('#band').val();
	if ($('#selectPropagation').val() == 'SAT') {
		satOrBand = 'SAT';
	}
	$.ajax({
		url: base_url + 'index.php/lookup/search',
		type: 'post',
		data: {
			type: 'was',
			was: $('#stateDropdown').val(),
			reduced_mode: true,
			current_band: satOrBand,
			current_mode: $('#mode').val(),
		},
		success: function (html) {
			$('#state-summary').append(lang_summary_state + ' ' + $('#stateDropdown').val() + '.');
            $('#state-summary').append(html);
		}
	});
}

// This function executes the call to the backend for fetching sota summary and inserted table below qso entry
function getSotaResult() {
	$('#sota-summary').empty();
	if ($('#sota_ref').val() === '') {
		$('#sota-summary').append(lang_summary_warning_empty_sota);
		return;
	}
	satOrBand = $('#band').val();
	if ($('#selectPropagation').val() == 'SAT') {
		satOrBand = 'SAT';
	}
	$.ajax({
		url: base_url + 'index.php/lookup/search',
		type: 'post',
		data: {
			type: 'sota',
			sota: $('#sota_ref').val(),
			reduced_mode: true,
			current_band: satOrBand,
			current_mode: $('#mode').val(),
		},
		success: function (html) {
			$('#sota-summary').append(lang_summary_sota + ' ' + $('#sota_ref').val() + '.');
            $('#sota-summary').append(html);
		}
	});
}

// This function executes the call to the backend for fetching pota summary and inserted table below qso entry
function getPotaResult() {
	let potaref = $('#pota_ref').val();
	$('#pota-summary').empty();
	if (potaref === '') {
		$('#pota-summary').append(lang_summary_warning_empty_pota);
		return;
	}
	satOrBand = $('#band').val();
	if ($('#selectPropagation').val() == 'SAT') {
		satOrBand = 'SAT';
	}
	if (potaref.includes(',')) {
		let values = potaref.split(',').map(function(v) {
            return v.trim();
        }).filter(function(v) {
            return v;
        });
		let tabContent = $('#pota-summary'); // Tab content container
		tabContent.append('<div class="card"><div class="card-header"><ul style="font-size: 15px;" class="nav nav-tabs card-header-tabs pull-right" id="awardPotaTab" role="tablist"></ul></div></div>');
		tabContent.append('<div class="card-body"><div class="tab-content potatablist"></div>');

		values.forEach(function(value, index) {
			let tabId = `pota-tab-${index}`;
			let contentId = `pota-content-${index}`;

			// Append new tab
			$('#awardPotaTab').append(`
				<li class="nav-item">
					<a class="nav-link ${index === 0 ? 'active' : ''}" id="${tabId}-tab" data-bs-toggle="tab" href="#${contentId}" role="tab" aria-controls="${contentId}" aria-selected="${index === 0}">
						${value.toUpperCase()}
					</a>
				</li>
			`);

			// Append new tab content
			$('.potatablist').append(`
				<div class="tab-pane fade ${index === 0 ? 'show active' : ''}" id="${contentId}" role="tabpanel" aria-labelledby="${tabId}-tab">
				</div>
			`);

			// Make AJAX request
			$.ajax({
				url: base_url + 'index.php/lookup/search',
				type: 'POST',
				data: { type: 'pota',
						pota: value.trim(),
						reduced_mode: true,
						current_band: satOrBand,
						current_mode: $('#mode').val()
					},
				success: function(response) {
					$(`#${contentId}`).html(response); // Append response to correct tab
				},
				error: function(xhr, status, error) {
					$(`#${contentId}`).html(`<div class="text-danger">Error loading data for ${value}</div>`);
				}
			});
		});
		return;
	}
	$.ajax({
		url: base_url + 'index.php/lookup/search',
		type: 'post',
		data: {
			type: 'pota',
			pota: potaref,
			reduced_mode: true,
			current_band: satOrBand,
			current_mode: $('#mode').val(),
		},
		success: function (html) {
			$('#pota-summary').append(lang_summary_pota + ' ' + potaref + '.');
            $('#pota-summary').append(html);
		}
	});
}

// This function executes the call to the backend for fetching continent summary and inserts table below qso entry
function getContinentResult() {
	satOrBand = $('#band').val();
	if ($('#selectPropagation').val() == 'SAT') {
		satOrBand = 'SAT';
	}
	$.ajax({
		url: base_url + 'index.php/lookup/search',
		type: 'post',
		data: {
			type: 'continent',
			continent: $('#continent').val(),
				reduced_mode: true,
				current_band: satOrBand,
				current_mode: $('#mode').val(),
		},
		success: function (html) {
            $('#continent-summary').empty();
			$('#continent-summary').append(lang_summary_continent + ' ' + $('#continent').val() + '.');
            $('#continent-summary').append(html);
		}
	});
}

// This function executes the call to the backend for fetching DOK summary and inserts table below qso entry
function getDokResult() {
	satOrBand = $('#band').val();
	if ($('#selectPropagation').val() == 'SAT') {
		satOrBand = 'SAT';
	}
	$('#dok-summary').empty();
	if ($('#darc_dok').val() === '') {
		$('#dok-summary').append(lang_summary_warning_empty_dok);
		return;
	}
	$.ajax({
		url: base_url + 'index.php/lookup/search',
		type: 'post',
		data: {
			type: 'dok',
			dok: $('#darc_dok').val(),
				reduced_mode: true,
				current_band: satOrBand,
				current_mode: $('#mode').val(),
		},
		success: function (html) {
			$('#dok-summary').append(lang_summary_dok + ' ' + $('#darc_dok').val() + '.');
            $('#dok-summary').append(html);
		}
	});
}

// This function executes the call to the backend for fetching SAT summary and inserts table below qso entry
function getSatResult() {
	$('#sat-summary').empty();
	if ($('#selectPropagation').val() != 'SAT') {
		$('#sat-summary').append(lang_summary_warning_empty_sat);
		return;
	}
	$.ajax({
		url: base_url + 'index.php/lookup/sat',
		type: 'post',
		data: {
			callsign: $('#callsign').val().replace('Ø', '0'),
		},
		success: function (html) {
			$('#sat-summary').append(lang_summary_sat + ' ' + $('#callsign').val().toUpperCase() + '.');
			$('#sat-summary').append(html);
		}
	});
}

// This function executes the call to the backend for fetching iota summary and inserts table below qso entry
function getIotaResult() {
	satOrBand = $('#band').val();
	if ($('#selectPropagation').val() == 'SAT') {
		satOrBand = 'SAT';
	}
	$('#iota-summary').empty();
	if ($('#iota_ref').val() === '') {
		$('#iota-summary').append(lang_summary_warning_empty_iota);
		return;
	}
	$.ajax({
		url: base_url + 'index.php/lookup/search',
		type: 'post',
		data: {
			type: 'iota',
			iota: $('#iota_ref').val(),
				reduced_mode: true,
				current_band: satOrBand,
				current_mode: $('#mode').val(),
		},
		success: function (html) {
			$('#iota-summary').append(lang_summary_iota + ' ' + $('#iota_ref').val() + '.');
            $('#iota-summary').append(html);
		}
	});
}

// This function executes the call to the backend for fetching wwff summary and inserts table below qso entry
function getWwffResult() {
	$('#wwff-summary').empty();
	if ($('#wwff_ref').val() === '') {
		$('#wwff-summary').append(lang_summary_warning_empty_wwff);
		return;
	}
	satOrBand = $('#band').val();
	if ($('#selectPropagation').val() == 'SAT') {
		satOrBand = 'SAT';
	}
	$.ajax({
		url: base_url + 'index.php/lookup/search',
		type: 'post',
		data: {
			type: 'wwff',
			wwff: $('#wwff_ref').val(),
				reduced_mode: true,
				current_band: satOrBand,
				current_mode: $('#mode').val(),
		},
		success: function (html) {
			$('#wwff-summary').append(lang_summary_wwff + ' ' + $('#wwff_ref').val() + '.');
            $('#wwff-summary').append(html);
		}
	});
}

// This function executes the call to the backend for fetching gridsquare summary and inserts table below qso entry
function getGridsquareResult() {
	$('#gridsquare-summary').empty();
	if ($('#locator').val() === '') {
		$('#gridsquare-summary').append(lang_summary_warning_empty_gridsquare);
		return;
	}
	satOrBand = $('#band').val();
	if ($('#selectPropagation').val() == 'SAT') {
		satOrBand = 'SAT';
	}
	if ($('#locator').val().includes(',')) {
		let values = $('#locator').val().split(',').map(function(v) {
            return v.trim();
        }).filter(function(v) {
            return v;
        });
		let tabContent = $('#gridsquare-summary'); // Tab content container
		tabContent.append('<div class="card"><div class="card-header"><ul style="font-size: 15px;" class="nav nav-tabs card-header-tabs pull-right" id="awardGridTab" role="tablist"></ul></div></div>');
		tabContent.append('<div class="card-body"><div class="tab-content gridtablist"></div>');

		values.forEach(function(value, index) {
			let tabId = `grid-tab-${index}`;
			let contentId = `grid-content-${index}`;

			// Append new tab
			$('#awardGridTab').append(`
				<li class="nav-item">
					<a class="nav-link ${index === 0 ? 'active' : ''}" id="${tabId}-tab" data-bs-toggle="tab" href="#${contentId}" role="tab" aria-controls="${contentId}" aria-selected="${index === 0}">
						${value.toUpperCase()}
					</a>
				</li>
			`);

			// Append new tab content
			$('.gridtablist').append(`
				<div class="tab-pane fade ${index === 0 ? 'show active' : ''}" id="${contentId}" role="tabpanel" aria-labelledby="${tabId}-tab">
				</div>
			`);

			// Make AJAX request
			$.ajax({
				url: base_url + 'index.php/lookup/search',
				type: 'POST',
				data: { type: 'vucc',
						grid: value.trim(),
						reduced_mode: true,
						current_band: satOrBand,
						current_mode: $('#mode').val()
					},
				success: function(response) {
					$(`#${contentId}`).html(response); // Append response to correct tab
				},
				error: function(xhr, status, error) {
					$(`#${contentId}`).html(`<div class="text-danger">Error loading data for ${value}</div>`);
				}
			});
		});
		return;
	}
	$.ajax({
		url: base_url + 'index.php/lookup/search',
		type: 'post',
		data: {
			type: 'vucc',
			grid: $('#locator').val(),
				reduced_mode: true,
				current_band: satOrBand,
				current_mode: $('#mode').val(),
		},
		success: function (html) {
			$('#gridsquare-summary').append(lang_summary_gridsquare + ' ' + $('#locator').val().substring(0, 4) + '.');
            $('#gridsquare-summary').append(html);
		}
	});
}

function loadAwardTabs(callback) {
    $.ajax({
        url: base_url + 'index.php/qso/getAwardTabs',
        type: 'post',
        data: {},
        success: function (html) {
            $('.awardpane').remove();
            $('.qsopane').append('<div class="awardpane col-sm-12"></div>');
            $('.awardpane').append(html);

            // Execute callback if provided
            if (typeof callback === "function") {
                callback();
            }

			$("a[href='#cq-summary']").on('shown.bs.tab', function (e) {
				let $targetPane = $('#cq-summary');

				if (!$targetPane.data("loaded")) {
					$targetPane.data("loaded", true); // Mark as loaded
					getCqResult();
				}
			});

			$("a[href='#state-summary']").on('shown.bs.tab', function(e) {
				let $targetPane = $('#state-summary');

				if (!$targetPane.data("loaded")) {
					$targetPane.data("loaded", true); // Mark as loaded
					getWasResult();
				}
			});

			$("a[href='#pota-summary']").on('shown.bs.tab', function(e) {
				let $targetPane = $('#pota-summary');

				if (!$targetPane.data("loaded")) {
					$targetPane.data("loaded", true); // Mark as loaded
					getPotaResult();
				}
			});

			$("a[href='#continent-summary']").on('shown.bs.tab', function(e) {
				let $targetPane = $('#continent-summary');

				if (!$targetPane.data("loaded")) {
					$targetPane.data("loaded", true); // Mark as loaded
					getContinentResult();
				}
			});

			$("a[href='#sota-summary']").on('shown.bs.tab', function(e) {
				let $targetPane = $('#sota-summary');

				if (!$targetPane.data("loaded")) {
					$targetPane.data("loaded", true); // Mark as loaded
					getSotaResult();
				}
			});

			$("a[href='#gridsquare-summary']").on('shown.bs.tab', function(e) {
				let $targetPane = $('#gridsquare-summary');

				if (!$targetPane.data("loaded")) {
					$targetPane.data("loaded", true); // Mark as loaded
					getGridsquareResult();
				}
			});

			$("a[href='#wwff-summary']").on('shown.bs.tab', function(e) {
				let $targetPane = $('#wwff-summary');

				if (!$targetPane.data("loaded")) {
					$targetPane.data("loaded", true); // Mark as loaded
					getWwffResult();
				}
			});

			$("a[href='#iota-summary']").on('shown.bs.tab', function(e) {
				let $targetPane = $('#iota-summary');

				if (!$targetPane.data("loaded")) {
					$targetPane.data("loaded", true); // Mark as loaded
					getIotaResult();
				}
			});

			$("a[href='#sat-summary']").on('shown.bs.tab', function(e) {
				let $targetPane = $('#sat-summary');

				if (!$targetPane.data("loaded")) {
					$targetPane.data("loaded", true); // Mark as loaded
					getSatResult();
				}
			});

			$("a[href='#dok-summary']").on('shown.bs.tab', function(e) {
				let $targetPane = $('#dok-summary');

				if (!$targetPane.data("loaded")) {
					$targetPane.data("loaded", true); // Mark as loaded
					getDokResult();
				}
			});

			$('.dxcc-summary-reload').click(function (event) {
				let $targetPane = $('#dxcc-summary');
				$targetPane.data("loaded", false); // Mark as loaded
				getDxccResult($('#dxcc_id').val(), $('#dxcc_id option:selected').text());
			});
			$('.iota-summary-reload').click(function (event) {
				getIotaResult();
			});
			$('.dok-summary-reload').click(function (event) {
				getDokResult();
			});
			$('.wwff-summary-reload').click(function (event) {
				getWwffResult();
			});
			$('.pota-summary-reload').click(function (event) {
				getPotaResult();
			});
			$('.sota-summary-reload').click(function (event) {
				getSotaResult();
			});
			$('.cq-summary-reload').click(function (event) {
				getCqResult();
			});
			$('.state-summary-reload').click(function (event) {
				getWasResult();
			});
			$('.continent-summary-reload').click(function (event) {
				getContinentResult();
			});
			$('.gridsquare-summary-reload').click(function (event) {
				getGridsquareResult();
			});
			$('.sat-summary-reload').click(function (event) {
				getSatResult();
			});
        }
    });
}


/* time input shortcut */
$('#start_time').on('change', function () {
	var raw_time = $(this).val();
	if (raw_time.match(/^\d\[0-6]d$/)) {
		raw_time = "0" + raw_time;
	}
	if (raw_time.match(/^[012]\d[0-5]\d$/)) {
		raw_time = raw_time.substring(0, 2) + ":" + raw_time.substring(2, 4);
		$('#start_time').val(raw_time);
	}
});

$('#end_time').on('change', function () {
	var raw_time = $(this).val();
	if (raw_time.match(/^\d\[0-6]d$/)) {
		raw_time = "0" + raw_time;
	}
	if (raw_time.match(/^[012]\d[0-5]\d$/)) {
		raw_time = raw_time.substring(0, 2) + ":" + raw_time.substring(2, 4);
		$('#end_time').val(raw_time);
	}
});

/* date input shortcut */
$('#start_date').on('change', function () {
	raw_date = $(this).val();
	if (raw_date.match(/^[12]\d{3}[01]\d[0123]\d$/)) {
		raw_date = raw_date.substring(0, 4) + "-" + raw_date.substring(4, 6) + "-" + raw_date.substring(6, 8);
		$('#start_date').val(raw_date);
	}
});

/* on mode change */
$('.mode').on('change', function () {
	if ($('#radio').val() == 0 && $('#sat_name').val() == '') {
		$.get(base_url + 'index.php/qso/band_to_freq/' + $('#band').val() + '/' + $('.mode').val(), function (result) {
			$('#frequency').val(result).trigger("change");
		});
		$('#frequency_rx').val("");
	}
	$("#callsign").blur();
});

/* Calculate Frequency */
/* on band change */
$('#band').on('change', function () {
	if ($('#radio').val() == 0) {
		$.get(base_url + 'index.php/qso/band_to_freq/' + $(this).val() + '/' + $('.mode').val(), function (result) {
			$('#frequency').val(result).trigger("change");
		});
	}
	$('#frequency_rx').val("");
	$('#band_rx').val("");
	$("#selectPropagation").val("");
	$("#sat_name").val("");
	$("#sat_mode").val("");
	set_qrg();
	$("#callsign").blur();
	stop_az_ele_ticker();
});

/* On Key up Calculate Bearing and Distance */
$("#locator").on("input focus", function () {
	if ($(this).val()) {
		var qra_input = $(this).val();

		var qra_lookup = qra_input.substring(0, 4);

		if (qra_lookup.length >= 4) {

			// Check Log if satname is provided
			if ($("#sat_name").val() != "") {

				//logbook/jsonlookupgrid/io77/SAT/0/0

				$.getJSON(base_url + 'index.php/logbook/jsonlookupgrid/' + qra_lookup.toUpperCase() + '/SAT/0/0', function (result) {
					// Reset CSS values before updating
					$('#locator').removeClass("confirmedGrid");
					$('#locator').removeClass("workedGrid");
					$('#locator').removeClass("newGrid");
					$('#locator').attr('title', '');

					if (result.confirmed) {
						$('#locator').addClass("confirmedGrid");
						$('#locator').attr('title', 'Grid was already worked and confirmed in the past');
					} else if (result.workedBefore) {
						$('#locator').addClass("workedGrid");
						$('#locator').attr('title', 'Grid was already worked in the past');
					} else {
						$('#locator').addClass("newGrid");
						$('#locator').attr('title', 'New grid!');
					}
				})
			} else {
				$.getJSON(base_url + 'index.php/logbook/jsonlookupgrid/' + qra_lookup.toUpperCase() + '/0/' + $("#band").val() + '/' + $("#mode").val(), function (result) {
					// Reset CSS values before updating
					$('#locator').removeClass("confirmedGrid");
					$('#locator').removeClass("workedGrid");
					$('#locator').removeClass("newGrid");
					$('#locator').attr('title', '');

					if (result.confirmed) {
						$('#locator').addClass("confirmedGrid");
						$('#locator').attr('title', 'Grid was already worked and confimred in the past');
					} else if (result.workedBefore) {
						$('#locator').addClass("workedGrid");
						$('#locator').attr('title', 'Grid was already worked in the past');
					} else {
						$('#locator').addClass("newGrid");
						$('#locator').attr('title', 'New grid!');
					}

				})
			}
		}

		if (qra_input.length >= 4 && $(this).val().length > 0) {
			$.ajax({
				url: base_url + 'index.php/logbook/qralatlngjson',
				type: 'post',
				data: {
					qra: $(this).val(),
				},
				success: function (data) {
					// Set Map to Lat/Long
					result = JSON.parse(data);
					markers.clearLayers();
					if (typeof result[0] !== "undefined" && typeof result[1] !== "undefined") {
						var redIcon = L.icon({
							iconUrl: icon_dot_url,
							iconSize: [18, 18], // size of the icon
						});

						var marker = L.marker([result[0], result[1]], { icon: redIcon });
						mymap.setZoom(8);
						mymap.panTo([result[0], result[1]]);
						mymap.setView([result[0], result[1]], 8);
						markers.addLayer(marker).addTo(mymap);
					}
				},
				error: function () {
				},
			});

			$.ajax({
				url: base_url + 'index.php/logbook/searchbearing',
				type: 'post',
				data: {
					grid: $(this).val(),
					ant_path: $('#ant_path').val(),
					stationProfile: $('#stationProfile').val()
				},
				success: function (data) {
					$('#locator_info').html(data).fadeIn("slow");
				},
				error: function () {
					$('#locator_info').text("Error loading bearing!").fadeIn("slow");
				},
			});
			$.ajax({
				url: base_url + 'index.php/logbook/searchdistance',
				type: 'post',
				data: {
					grid: $(this).val(),
					ant_path: $('#ant_path').val(),
					stationProfile: $('#stationProfile').val()
				},
				success: function (data) {
					document.getElementById("distance").value = data;
				},
				error: function () {
					document.getElementById("distance").value = null;
				},
			});
		}
	}
});

$("#locator").on("focusout", function () {
	if ($(this).val().length == 0) {
		$('#locator_info').text("");
		document.getElementById("distance").value = null;
	}
});

$("#ant_path").on("change", function () {
	if ($("#locator").val().length > 0) {
		$.ajax({
			url: base_url + 'index.php/logbook/searchbearing',
			type: 'post',
			data: {
				grid: $('#locator').val(),
				ant_path: $('#ant_path').val(),
				stationProfile: $('#stationProfile').val()
			},
			success: function (data) {
				$('#locator_info').html(data).fadeIn("slow");
			},
			error: function () {
				$('#locator_info').text("Error loading bearing!").fadeIn("slow");
			},
		});
		$.ajax({
			url: base_url + 'index.php/logbook/searchdistance',
			type: 'post',
			data: {
				grid: $('#locator').val(),
				ant_path: $('#ant_path').val(),
				stationProfile: $('#stationProfile').val()
			},
			success: function (data) {
				$('#distance').val(data);
			},
			error: function () {
				$('#distance').val("");
			},
		});
	}
});

// Change report based on mode
$('.mode').on('change', function () {
	setRst($('.mode').val());
});

function convert_case(str) {
	var lower = str.toLowerCase();
	return lower.replace(/(^| )(\w)/g, function (x) {
		return x.toUpperCase();
	});
}

$('#dxcc_id').on('change', function () {
	$.getJSON(base_url + 'index.php/logbook/jsonentity/' + $(this).val(), function (result) {
		if (result.dxcc.name != undefined) {

			$('#country').val(convert_case(result.dxcc.name));
			$('#cqz').val(convert_case(result.dxcc.cqz));

			$('#callsign_info').removeClass("text-bg-secondary");
			$('#callsign_info').removeClass("text-bg-success");
			$('#callsign_info').removeClass("text-bg-danger");
			$('#callsign_info').attr('title', '');
			$('#callsign_info').text(convert_case(result.dxcc.name));

			changebadge(result.dxcc.name);

			// Set Map to Lat/Long it locator is not empty
			if ($('#locator').val() == "") {
				var redIcon = L.icon({
					iconUrl: icon_dot_url,
					iconSize: [18, 18], // size of the icon
				});

				markers.clearLayers();
				var marker = L.marker([result.dxcc.lat, result.dxcc.long], { icon: redIcon });
				mymap.setZoom(8);
				mymap.panTo([result.dxcc.lat, result.dxcc.long]);
				markers.addLayer(marker).addTo(mymap);
			}
		}
	});
});

//Spacebar moves to the name field when you're entering a callsign
//Similar to contesting ux, good for pileups.
$("#callsign").on("keydown", function (e) {
	if (e.which == 32) {
		$("#name").trigger("focus");
		e.preventDefault(); //Eliminate space char
	}
});


$("#callsign").on("input focus", function () {
	var ccall = $(this).val();
	if ($(this).val().length >= 3) {
		$('.callsign-suggest').show();
		$callsign = $(this).val().replace('Ø', '0');
		if (scps.filter((call => call.includes($(this).val().toUpperCase()))).length <= 0) {
			$.ajax({
				url: 'lookup/scp',
				method: 'POST',
				data: {
					callsign: $callsign.toUpperCase()
				},
				success: function (result) {
					$('.callsign-suggestions').text(result);
					scps = result.split(" ");
					highlightSCP(ccall.toUpperCase());
				}
			});
		} else {
			$('.callsign-suggestions').text(scps.filter((call) => call.includes($(this).val().toUpperCase())).join(' '));
			highlightSCP(ccall.toUpperCase());
		}
	} else {
		$('.callsign-suggest').hide();
		scps = [];
	}
});

RegExp.escape = function (text) {
	return String(text).replace(/[-[\]{}()*+?.,\\^$|#\s]/g, "\\$&");
}


function highlightSCP(term, base) {
	if (!term) return;
	base = base || document.body;
	var re = new RegExp("(" + RegExp.escape(term) + ")", "gi");
	var replacement = "<span class=\"text-primary\">" + term + "</span>";
	$(".callsign-suggestions", base).contents().each(function (i, el) {
		if (el.nodeType === 3) {
			var data = el.data;
			if (data = data.replace(re, replacement)) {
				var wrapper = $("<span>").html(data);
				$(el).before(wrapper.contents()).remove();
			}
		}
	});
}


//Reset QSO form Fields function
function resetDefaultQSOFields() {
	$('#callsign_info').text("");
	$('#locator_info').text("");
	$('#country').val("");
	$('#continent').val("");
	$("#distance").val("");
	$('#email').val("");
	$('#region').val("");
	$('#dxcc_id').val("").multiselect('refresh');
	$('#cqz').val("");
	$('#ituz').val("");
	$('#name').val("");
	$('#qth').val("");
	$('#locator').val("");
	$('#iota_ref').val("");
	$('#sota_ref').val("");
	$("#locator").removeClass("workedGrid");
	$("#locator").removeClass("confirmedGrid");
	$("#locator").removeClass("newGrid");
	$("#callsign").removeClass("workedGrid");
	$("#callsign").removeClass("confirmedGrid");
	$("#callsign").removeClass("newGrid");
	$('#callsign_info').removeClass("text-bg-secondary");
	$('#callsign_info').removeClass("text-bg-success");
	$('#callsign_info').removeClass("text-bg-danger");
	$('#stateDropdown').val("");
	$('#callsign-image').attr('style', 'display: none;');
	$('#callsign-image-content').text("");
	$('.awardpane').remove();
	$('#timesWorked').html(lang_qso_title_previous_contacts);
}

function closeModal() {
	var container = document.getElementById("modals-here")
	var backdrop = document.getElementById("modal-backdrop")
	var modal = document.getElementById("modal")

	modal.classList.remove("show")
	backdrop.classList.remove("show")

	setTimeout(function () {
		container.removeChild(backdrop)
		container.removeChild(modal)
	}, 200)
}

// [TimeOff] test Consistency timeOff value (concidering start and end are between 23:00 and 00:59) //
function testTimeOffConsistency() {
	var _start_time = $('#qso_input input[name="start_time"]').val();
	var _end_time = $('#qso_input input[name="end_time"]').val();
	$('#qso_input input[name="end_time"]').removeClass('inputError');
	$('#qso_input .warningOnSubmit').hide();
	$('#qso_input .warningOnSubmit_txt').empty();
	if (!((parseInt(_start_time.replaceAll(':', '')) <= parseInt(_end_time.replaceAll(':', '')))
		|| ((_start_time.substring(0, 2) == "23") && (_end_time.substring(0, 2) == "00")))) {
		$('#qso_input input[name="end_time"]').addClass('inputError');
		$('#qso_input .warningOnSubmit_txt').html(text_error_timeoff_less_timeon);
		$('#qso_input .warningOnSubmit').show();
		$('#qso_input input[name="end_time"]').off('change').on('change', function () { testTimeOffConsistency(); });
		return false;
	}
	return true;
}

function panMap(stationProfileIndex) {
	$.ajax({
		url: base_url + 'index.php/station/stationProfileCoords/'+stationProfileIndex,
		type: 'get',
		success: function(data) {
			result = JSON.parse(data);
			if (typeof result[0] !== "undefined" && typeof result[1] !== "undefined") {
				mymap.panTo([result[0], result[1]]);
				pos = result;
			}
		},
		error: function() {
		},
	});
}

$(document).ready(function () {
	qrg_inputtype();
	clearTimeout();
	set_timers();
	updateStateDropdown('#dxcc_id', '#stateInputLabel', '#location_us_county', '#stationCntyInputQso');

	// Clear the localStorage for the qrg units, except the quicklogCallsign and a possible backlog
	let quicklogCallsign = localStorage.getItem('quicklogCallsign');
	let QsoBacklog = localStorage.getItem('qso-backlog');

	localStorage.clear();
	if (quicklogCallsign) {
		localStorage.setItem('quicklogCallsign', quicklogCallsign);
	}
	set_qrg();

	if (QsoBacklog) {
		localStorage.setItem('qso-backlog', QsoBacklog);
	}

	$("#locator").popover({ placement: 'top', title: 'Gridsquare Formatting', content: "Enter multiple (4-digit) grids separated with commas. For example: IO77,IO78" })
	.focus(function () {
		$('#locator').popover('show');
	})
	.blur(function () {
		$('#locator').popover('hide');
	});

	$('#dxcc_id').multiselect({
		// template is needed for bs5 support
		templates: {
			button: '<button type="button" style="text-align: left !important;" class="multiselect dropdown-toggle btn btn-secondary w-auto" data-bs-toggle="dropdown" aria-expanded="false"><span class="multiselect-selected-text"></span></button>',
		},
		enableFiltering: true,
		enableFullValueFiltering: false,
		enableCaseInsensitiveFiltering: true,
		filterPlaceholder: lang_general_word_search,
		widthSynchronizationMode: 'always',
		numberDisplayed: 1,
		inheritClass: true,
		buttonWidth: '100%',
		maxHeight: 600
	});

	$('.multiselect-container .multiselect-filter', $('#dxcc_id').parent()).css({
		'position': 'sticky', 'top': '0px', 'z-index': 1, 'background-color': 'inherit', 'width': '100%', 'height': '37px'
	})

	$('#notice-alerts').delay(1000).fadeOut(5000);

	$('.callsign-suggest').hide();

	setRst($(".mode").val());

	/* On Page Load */
	var catcher = function () {
		var changed = false;
		$('form').each(function () {
			if ($(this).data('initialForm') != $(this).serialize()) {
				changed = true;
				$(this).addClass('changed');
			} else {
				$(this).removeClass('changed');
			}
		});
		if (changed) {
			return 'Unsaved QSO!';
		}
	};

	// Callsign always has focus on load
	$("#callsign").trigger("focus");

	// reset the timers on page load
	resetTimers(qso_manual);

	get_fav();

	$('#sota_ref').selectize({
		maxItems: 1,
		closeAfterSelect: true,
		loadThrottle: 250,
		valueField: 'name',
		labelField: 'name',
		searchField: 'name',
		options: [],
		create: true,
		load: function (query, callback) {
			if (!query || query.length < 3) return callback();  // Only trigger if 3 or more characters are entered
			$.ajax({
				url: base_url + 'index.php/qso/get_sota',
				type: 'GET',
				dataType: 'json',
				data: {
					query: query,
				},
				error: function () {
					callback();
				},
				success: function (res) {
					callback(res);
				}
			});
		},
		onChange: function (value) {
			if (value !== '') {
				$('#sota_info').show();
				$('#sota_info').html('<a target="_blank" href="https://summits.sota.org.uk/summit/' + value + '"><img width="32" height="32" src="' + base_url + 'images/icons/sota.org.uk.png"></a>');
				$('#sota_info').attr('title', 'Lookup ' + value + ' summit info on sota.org.uk');
			} else {
				$('#sota_info').hide();
			}
		}
	});

	$('#wwff_ref').selectize({
		maxItems: 1,
		closeAfterSelect: true,
		loadThrottle: 250,
		valueField: 'name',
		labelField: 'name',
		searchField: 'name',
		options: [],
		create: true,
		load: function (query, callback) {
			if (!query || query.length < 3) return callback();  // Only trigger if 3 or more characters are entered
			$.ajax({
				url: base_url + 'index.php/qso/get_wwff',
				type: 'GET',
				dataType: 'json',
				data: {
					query: query,
				},
				error: function () {
					callback();
				},
				success: function (res) {
					callback(res);
				}
			});
		},
		onChange: function (value) {
			if (value !== '') {
				$('#wwff_info').show();
				$('#wwff_info').html('<a target="_blank" href="https://www.cqgma.org/zinfo.php?ref=' + value + '"><img width="32" height="32" src="' + base_url + 'images/icons/wwff.co.png"></a>');
				$('#wwff_info').attr('title', 'Lookup ' + value + ' reference info on cqgma.org');
			} else {
				$('#wwff_info').hide();
			}
		}
	});

	$('#pota_ref').selectize({
		maxItems: null,
		closeAfterSelect: true,
		loadThrottle: 250,
		valueField: 'name',
		labelField: 'name',
		searchField: 'name',
		options: [],
		create: true,
		load: function (query, callback) {
			if (!query || query.length < 3) return callback();  // Only trigger if 3 or more characters are entered
			$.ajax({
				url: base_url + 'index.php/qso/get_pota',
				type: 'GET',
				dataType: 'json',
				data: {
					query: query,
				},
				error: function () {
					callback();
				},
				success: function (res) {
					callback(res);
				}
			});
		},
		onChange: function (value) {
			if (value !== '' && value.indexOf(',') === -1) {
				$('#pota_info').show();
				$('#pota_info').html('<a target="_blank" href="https://pota.app/#/park/' + value + '"><img width="32" height="32" src="' + base_url + 'images/icons/pota.app.png"></a>');
				$('#pota_info').attr('title', 'Lookup ' + value + ' reference info on pota.co');
			} else {
				$('#pota_info').hide();
			}
		}
	});

	$('#darc_dok').selectize({
		maxItems: 1,
		closeAfterSelect: true,
		loadThrottle: 250,
		valueField: 'name',
		labelField: 'name',
		searchField: 'name',
		options: [],
		create: true,
		load: function (query, callback) {
			if (!query) return callback();  // Only trigger if at least 1 character is entered
			$.ajax({
				url: base_url + 'index.php/qso/get_dok',
				type: 'GET',
				dataType: 'json',
				data: {
					query: query,
				},
				error: function () {
					callback();
				},
				success: function (res) {
					callback(res);
				}
			});
		}
	});

	/*
	Populate the Satellite Names Field on the QSO Panel
	*/
	$.getJSON(site_url + "/satellite/satellite_data", function (data) {

		// Build the options array
		var items = [];
		$.each(data, function (key, val) {
			items.push(
				'<option value="' + key + '">' + key + '</option>'
			);
		});

		// Add to the datalist
		$('.satellite_names_list').append(items.join(""));
	});

	// Only set the frequency when not set by userdata/PHP.
	if ($('#frequency').val() == "") {
		$.get(base_url + 'index.php/qso/band_to_freq/' + $('#band').val() + '/' + $('.mode').val(), function (result) {
			$('#frequency').val(result).trigger("change");
			$('#frequency_rx').val("");
			set_qrg();
		});
	}

	// everything loaded and ready 2 go
	bc.postMessage('ready');
});
