<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class API extends CI_Controller {

	// Do absolutely nothing
	function index()
	{
		echo "nothing to see";
	}

	function help()
	{
		$this->load->model('user_model');

		// Check if users logged in

		if($this->user_model->validate_session() == 0) {
			// user is not logged in
			redirect('user/login');
		}

		$this->load->model('api_model');

		$data['api_keys'] = $this->api_model->keys();

		$data['page_title'] = "API";

		$this->load->view('interface_assets/header', $data);
		$this->load->view('api/help');
		$this->load->view('interface_assets/footer');
	}


	function edit($key) {
		$this->load->model('user_model');

		// Check if users logged in

		if($this->user_model->validate_session() == 0) {
			// user is not logged in
			redirect('user/login');
		}

		$this->load->model('api_model');

		$this->load->helper(array('form', 'url'));

        $this->load->library('form_validation');

        $this->form_validation->set_rules('api_desc', 'API Description', 'required');
        $this->form_validation->set_rules('api_key', 'API Key is required do not change this field', 'required');

        $data['api_info'] = $this->api_model->key_description($key);

        if ($this->form_validation->run() == FALSE)
        {
  	      	$data['page_title'] = "Edit API Description";

			$this->load->view('interface_assets/header', $data);
			$this->load->view('api/description');
			$this->load->view('interface_assets/footer');
		}
		else
		{
			// Success!

			$this->api_model->update_key_description($this->input->post('api_key'), $this->input->post('api_desc'));

			$this->session->set_flashdata('notice', 'API Key <b>'.$this->input->post('api_key')."</b> description has been updated.");

			redirect('api/help');
		}

	}

	function generate($rights) {
		$this->load->model('user_model');

		// Check if users logged in

		if($this->user_model->validate_session() == 0) {
			// user is not logged in
			redirect('user/login');
		}


		$this->load->model('api_model');

		$data['api_keys'] = $this->api_model->generate_key($rights);

		redirect('api/help');
	}

	function delete($key) {
		$this->load->model('user_model');

		// Check if users logged in

		if($this->user_model->validate_session() == 0) {
			// user is not logged in
			redirect('user/login');
		}


		$this->load->model('api_model');

		$this->api_model->delete_key($key);

		$this->session->set_flashdata('notice', 'API Key <b>'.$key."</b> has been deleted");

		redirect('api/help');
	}

	// Example of authing
	function auth($key) {
		$this->load->model('api_model');
			header("Content-type: text/xml");
		if($this->api_model->access($key) == "No Key Found" || $this->api_model->access($key) == "Key Disabled") {
			echo "<auth>";
			echo "<message>Key Invalid - either not found or disabled</message>";
			echo "</auth>";
		} else {
			echo "<auth>";
			echo "<status>Valid</status>";
			echo "<rights>".$this->api_model->access($key)."</rights>";
			echo "</auth>";
		}
	}

	function check_auth($key) {
		$this->load->model('api_model');
			header("Content-type: text/xml");
		if($this->api_model->access($key) == "No Key Found" || $this->api_model->access($key) == "Key Disabled") {
			// set the content type as json
			header("Content-type: application/json");

			// set the http response code to 401
			http_response_code(401);

			// return the json with the status as failed
			echo json_encode(['status' => 'failed', 'reason' => "missing or invalid api key"]);
		} else {
			// set the content type as json
			header("Content-type: application/json");

			// set the http response code to 200
			http_response_code(200);
			// return the json
			echo json_encode(['status' => 'valid', 'rights' => $this->api_model->access($key)]);
		}
	}

	function station_info($key) {
		$this->load->model('api_model');
		$this->load->model('stations');
		header("Content-type: application/json");
		if(substr($this->api_model->access($key),0,1) == 'r') { /* Checkpermission for  _r_eading */
			$this->api_model->update_last_used($key);
			$userid = $this->api_model->key_userid($key);
 			$station_ids = array();
			$stations=$this->stations->all_of_user($userid);
 			foreach ($stations->result() as $row) {
				$result['station_id']=$row->station_id;
				$result['station_profile_name']=$row->station_profile_name;
				$result['station_gridsquare']=$row->station_gridsquare;
				$result['station_callsign']=$row->station_callsign;;
				$result['station_active']=$row->station_active;
 				array_push($station_ids, $result);
 			}
			echo json_encode($station_ids);
		} else {
			http_response_code(401);
			echo json_encode(['status' => 'failed', 'reason' => "missing or invalid api key"]);
		}
	}


  	/*
	*
	*	Function: QSO
	*	Task: allows passing of ADIF data to Cloudlog
	*/
	function qso() {
		header('Content-type: application/json');

		$this->load->model('api_model');

		$this->load->model('stations');

		$return_msg = array();
		$return_count = 0;

		// Decode JSON and store
		$obj = json_decode(file_get_contents("php://input"), true);
		if ($obj === NULL) {
			// Decoding not valid try simple www-x-form-urlencoded
		    $objTmp = file_get_contents("php://input");
		    parse_str($objTmp, $obj);
		    if ($obj === NULL) {
		        echo json_encode(['status' => 'failed', 'reason' => "wrong JSON"]);
		        die();
		    }
		}

		if(!isset($obj['key']) || $this->api_model->authorize($obj['key']) == 0) {
		   http_response_code(401);
		   echo json_encode(['status' => 'failed', 'reason' => "missing api key"]);
		   die();
		}

		$userid = $this->api_model->key_userid($obj['key']);

		if(!isset($obj['station_profile_id']) || $this->stations->check_station_against_user($obj['station_profile_id'], $userid) == false) {
			http_response_code(401);
			echo json_encode(['status' => 'failed', 'reason' => "station id does not belong to the API key owner."]);
			die();
		}

		if($obj['type'] == "adif" && $obj['string'] != "") {
			// Load the logbook model for adding QSO records
			$this->load->model('logbook_model');

			// Load ADIF Parser
			$this->load->library('adif_parser');

			// Feed in the ADIF string
			$this->adif_parser->feed($obj['string']);

			// Create QSO Record
			while($record = $this->adif_parser->get_record())
			{
				if(count($record) == 0)
				{
					break;
				};


				if(isset($obj['station_profile_id'])) {
					if(isset($record['station_callsign']) && $this->stations->check_station_against_callsign($obj['station_profile_id'], $record['station_callsign']) == false) {
						http_response_code(401);
						echo json_encode(['status' => 'failed', 'reason' => "station callsign does not match station callsign in station profile."]);
						die();
					}

					if(!(isset($record['call'])) || (trim($record['call']) == '')) {
						http_response_code(401);
						echo json_encode(['status' => 'failed', 'reason' => "QSO Call is empty."]);
						die();
					}

					$this->api_model->update_last_used($obj['key']);

					$msg = $this->logbook_model->import($record, $obj['station_profile_id'], NULL, NULL, NULL, NULL, NULL, NULL, false, false, true);

					if ( $msg == "" ) {
						$return_count++;
					} else {
						$return_msg[] = $msg;
					}
				}

			};
			http_response_code(201);
			echo json_encode(['status' => 'created', 'type' => $obj['type'], 'string' => $obj['string'], 'imported_count' => $return_count, 'messages' => $return_msg ]);

		}

	}

	// API function to check if a callsign is in the logbook already
	function logbook_check_callsign() {
		header('Content-type: application/json');

		$this->load->model('api_model');

		// Decode JSON and store
		$obj = json_decode(file_get_contents("php://input"), true);
		if ($obj === NULL) {
		    echo json_encode(['status' => 'failed', 'reason' => "wrong JSON"]);
			return;
		}

		if(!isset($obj['key']) || $this->api_model->authorize($obj['key']) == 0) {
		   http_response_code(401);
		   echo json_encode(['status' => 'failed', 'reason' => "missing api key"]);
			return;
		}

		if(!isset($obj['logbook_public_slug']) || !isset($obj['callsign'])) {
		   http_response_code(401);
		   echo json_encode(['status' => 'failed', 'reason' => "missing fields"]);
			return;
		}

		if($obj['logbook_public_slug'] != "" && $obj['callsign'] != "") {

			$logbook_slug = $obj['logbook_public_slug'];
			$callsign = $obj['callsign'];

			// If $obj['band'] exists
			if(isset($obj['band'])) {
				$band = $obj['band'];
			} else {
				$band = null;
			}

			$this->load->model('logbooks_model');

			if($this->logbooks_model->public_slug_exists($logbook_slug)) {
				$logbook_id = $this->logbooks_model->public_slug_exists_logbook_id($logbook_slug);
				if($logbook_id != false)
				{
					// Get associated station locations for mysql queries
					$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($logbook_id);
	
					if (!$logbooks_locations_array) {
						// Logbook not found
						http_response_code(404);
						echo json_encode(['status' => 'failed', 'reason' => "Empty Logbook"]);
						die();
					}
				} else {
					// Logbook not found
					http_response_code(404);
					echo json_encode(['status' => 'failed', 'reason' => $logbook_slug." has no associated station locations"]);
					die();
				}
				// Search Logbook for callsign
				$this->load->model('logbook_model');

				$result = $this->logbook_model->check_if_callsign_worked_in_logbook($callsign, $logbooks_locations_array, $band);

				http_response_code(201);
				if($result > 0)
				{
					echo json_encode(['callsign' => $callsign, 'result' => 'Found']);
				} else {
					echo json_encode(['callsign' => $callsign, 'result' => 'Not Found']);
				}
			} else {
				// Logbook not found
				http_response_code(404);
				echo json_encode(['status' => 'failed', 'reason' => "logbook not found"]);
				die();
			}

		}

	}

	// API function to check if a grid is in the logbook already
	function logbook_check_grid() {
		header('Content-type: application/json');

		$this->load->model('api_model');

		// Decode JSON and store
		$obj = json_decode(file_get_contents("php://input"), true);
		if ($obj === NULL) {
		    echo json_encode(['status' => 'failed', 'reason' => "wrong JSON"]);
		}

		if(!isset($obj['key']) || $this->api_model->authorize($obj['key']) == 0) {
		   http_response_code(401);
		   echo json_encode(['status' => 'failed', 'reason' => "missing api key"]);
		}

		if(!isset($obj['logbook_public_slug']) || !isset($obj['grid'])) {
		   http_response_code(401);
		   echo json_encode(['status' => 'failed', 'reason' => "missing fields"]);
			return;
		}

		if($obj['logbook_public_slug'] != "" && $obj['grid'] != "") {

			$logbook_slug = $obj['logbook_public_slug'];
			$grid = $obj['grid'];

			// If $obj['band'] exists
			if(isset($obj['band'])) {
				$band = $obj['band'];
			} else {
				$band = null;
			}

			$this->load->model('logbooks_model');

			if($this->logbooks_model->public_slug_exists($logbook_slug)) {
				$logbook_id = $this->logbooks_model->public_slug_exists_logbook_id($logbook_slug);
				if($logbook_id != false)
				{
					// Get associated station locations for mysql queries
					$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($logbook_id);
	
					if (!$logbooks_locations_array) {
						// Logbook not found
						http_response_code(404);
						echo json_encode(['status' => 'failed', 'reason' => "Empty Logbook"]);
						die();
					}
				} else {
					// Logbook not found
					http_response_code(404);
					echo json_encode(['status' => 'failed', 'reason' => $logbook_slug." has no associated station locations"]);
					die();
				}
				// Search Logbook for callsign
				$this->load->model('logbook_model');

				$result = $this->logbook_model->check_if_grid_worked_in_logbook($grid, $logbooks_locations_array, $band);

				http_response_code(201);
				if($result > 0)
				{
					echo json_encode(['gridsquare' => strtoupper($grid), 'result' => 'Found']);
				} else {
					echo json_encode(['gridsquare' => strtoupper($grid), 'result' => 'Not Found']);
				}
			} else {
				// Logbook not found
				http_response_code(404);
				echo json_encode(['status' => 'failed', 'reason' => "logbook not found"]);
				die();
			}

		}

	}


	/**
	 * Check if a country has been worked before and confirmed in various ways
	 * 
	 * This API endpoint checks if a specific country (derived from callsign) has been
	 * worked before in a given logbook, and whether it has been confirmed via different
	 * confirmation methods (QSL, LoTW, eQSL, QRZ).
	 * 
	 * @api POST /api/logbook_check_country
	 * @header Content-Type application/json
	 * 
	 * @param string key Required. API authentication key
	 * @param string logbook_public_slug Required. Public slug identifier for the logbook
	 * @param string callsign Required. Callsign to lookup country for
	 * @param string type Optional. Type of contact ("sat" for satellite, empty for regular)
	 * @param string band Optional. Amateur radio band (required for non-satellite contacts)
	 * @param string mode Optional. Amateur radio mode (required for non-satellite contacts)
	 * 
	 * @return json Returns JSON object with:
	 *   - workedBefore: boolean indicating if country was worked before
	 *   - confirmed: object with confirmation status for qsl, lotw, eqsl, qrz
	 * 
	 * @throws 401 Unauthorized - Missing or invalid API key, missing required fields
	 * @throws 404 Not Found - Logbook not found or empty logbook
	 * @throws 400 Bad Request - Invalid JSON format
	 * 
	 * @example
	 * Request:
	 * {
	 *   "key": "your-api-key",
	 *   "logbook_public_slug": "my-logbook",
	 *   "callsign": "W1AW",
	 *   "band": "20M",
	 *   "mode": "SSB"
	 * }
	 * 
	 * Response:
	 * {
	 *   "workedBefore": true,
	 *   "confirmed": {
	 *     "qsl": true,
	 *     "lotw": false,
	 *     "eqsl": false,
	 *     "qrz": false
	 *   }
	 * }
	 */
	function logbook_check_country()
	{
		header('Content-type: application/json');

		$this->load->model('api_model');

		// Decode JSON and store
		$obj = json_decode(file_get_contents("php://input"), true);
		if ($obj === NULL) {
			echo json_encode(['status' => 'failed', 'reason' => "wrong JSON"]);
			return;
		}

		if(!isset($obj['key']) || $this->api_model->authorize($obj['key']) == 0) {
		   http_response_code(401);
		   echo json_encode(['status' => 'failed', 'reason' => "missing api key"]);
		   return;
		}

		if(!isset($obj['logbook_public_slug']) || !isset($obj['callsign'])) {
		   http_response_code(401);
		   echo json_encode(['status' => 'failed', 'reason' => "missing fields"]);
		   return;
		}

		// Load models
		$this->load->model('logbook_model');
		$this->load->model('logbooks_model');

		$date = date("Y-m-d");
		$callsign = $obj['callsign'];
		$logbook_slug = $obj['logbook_public_slug'];
		$type = isset($obj['type']) ? $obj['type'] : '';
		$band = isset($obj['band']) ? $obj['band'] : '';
		$mode = isset($obj['mode']) ? $obj['mode'] : '';

		$callsign_dxcc_lookup = $this->logbook_model->dxcc_lookup($callsign, $date);
		$country = $callsign_dxcc_lookup['entity'];

		$return = [
			"workedBefore" => false,
			"confirmed" => [
				"qsl" => false,
				"lotw" => false,
				"eqsl" => false,
				"qrz" => false
			]
		];

		if($this->logbooks_model->public_slug_exists($logbook_slug)) {
			$logbook_id = $this->logbooks_model->public_slug_exists_logbook_id($logbook_slug);
			if($logbook_id != false)
			{
				// Get associated station locations for mysql queries
				$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($logbook_id);

				if (!$logbooks_locations_array) {
					// Logbook not found
					http_response_code(404);
					echo json_encode(['status' => 'failed', 'reason' => "Empty Logbook"]);
					die();
				}
			} else {
				// Logbook not found
				http_response_code(404);
				echo json_encode(['status' => 'failed', 'reason' => $logbook_slug." has no associated station locations"]);
				die();
			}

			if (!empty($logbooks_locations_array)) {
				if ($type == "sat") {
					$this->db->where('COL_PROP_MODE', 'SAT');
				} else {
					$this->db->where('COL_MODE', $this->logbook_model->get_main_mode_from_mode($mode));
					$this->db->where('COL_BAND', $band);
					$this->db->where('COL_PROP_MODE !=', 'SAT');
				}

				$this->db->where_in('station_id', $logbooks_locations_array);
				$this->db->where('COL_COUNTRY', urldecode($country));

				$query = $this->db->get($this->config->item('table_name'), 1, 0);
				foreach ($query->result() as $workedBeforeRow) {
					$return['workedBefore'] = true;
				}

				// Check each confirmation type separately
				$confirmation_types = [
					'qsl' => "COL_QSL_RCVD='Y'",
					'lotw' => "COL_LOTW_QSL_RCVD='Y'",
					'eqsl' => "COL_EQSL_QSL_RCVD='Y'",
					'qrz' => "COL_QRZCOM_QSO_DOWNLOAD_STATUS='Y'"
				];

				foreach ($confirmation_types as $type_key => $where_clause) {
					if ($type == "SAT") {
						$this->db->where('COL_PROP_MODE', 'SAT');
					} else {
						$this->db->where('COL_MODE', $this->logbook_model->get_main_mode_from_mode($mode));
						$this->db->where('COL_BAND', $band);
						$this->db->where('COL_PROP_MODE !=', 'SAT');
					}

					$this->db->where_in('station_id', $logbooks_locations_array);
					$this->db->where('COL_COUNTRY', urldecode($country));
					$this->db->where($where_clause);

					$query = $this->db->get($this->config->item('table_name'), 1, 0);

					if ($query->num_rows() > 0) {
						$return['confirmed'][$type_key] = true;
					}
				}

				http_response_code(201);
				echo json_encode($return, JSON_PRETTY_PRINT);
			} else {
				http_response_code(201);
				echo json_encode($return, JSON_PRETTY_PRINT);
			}
		} else {
			// Logbook not found
			http_response_code(404);
			echo json_encode(['status' => 'failed', 'reason' => "logbook not found"]);
			die();
		}
	}

	/* ENDPOINT for Rig Control */

	function radio() {
		header('Content-type: application/json');

		$this->load->model('api_model');

		//$json = '{"radio":"FT-950","frequency":14075,"mode":"SSB","timestamp":"2012/04/07 16:47"}';

		$this->load->model('cat');

		//var_dump(file_get_contents("php://input"), true);

		// Decode JSON and store
		$obj = json_decode(file_get_contents("php://input"), true);

		if(!isset($obj['key']) || $this->api_model->authorize($obj['key']) == 0) {
			http_response_code(401);
			echo json_encode(['status' => 'failed', 'reason' => "missing api key"]);
			die();
		}

		$this->api_model->update_last_used($obj['key']);

		$user_id = $this->api_model->key_userid($obj['key']);

		// Store Result to Database
		$this->cat->update($obj, $user_id);

		// Return Message

		$arr = array('status' => 'success');

		echo json_encode($arr);

	}

	/*
	*
	*	Stats API function calls
	*
	*/

	function statistics($key = null) {
		header('Content-type: application/json');
		$this->load->model('logbook_model');

		$data['todays_qsos'] = $this->logbook_model->todays_qsos(null, $key);
		$data['total_qsos'] = $this->logbook_model->total_qsos(null, $key);
		$data['month_qsos'] = $this->logbook_model->month_qsos(null, $key);
		$data['year_qsos'] = $this->logbook_model->year_qsos(null, $key);

		http_response_code(201);
		echo json_encode(['Today' => $data['todays_qsos'], 'total_qsos' => $data['total_qsos'], 'month_qsos' => $data['month_qsos'], 'year_qsos' => $data['year_qsos']]);

	}

	function lookup() {
		// start benchmarking
		$this->output->enable_profiler(TRUE);
		/*
		*
		*	Callsign lookup function for Cloudlogs logging page or thirdparty systems
		*	which want to show previous QSO data on their system.
		*
		*	TODO
		*	- Local data make one database call ONLY
		*	- Add eQSL status
		*	- Add Callbook returned data
		*	- Add QSO before data array
		*	- Add options for checking based on band/mode/sat
		*
		*/


		// Make sure users logged in
		$this->load->model('user_model');
		if(!$this->user_model->authorize($this->config->item('auth_mode'))) { return; }


		$this->load->model("logbook_model");
		$date = date("Y-m-d");

		// Return Array
		$return = [
			"callsign" => "",
			"dxcc" => false,
			"dxcc_lat" => "",
			"dxcc_long" => "",
			"dxcc_cqz" => "",
			"name" => "",
			"gridsquare"  => "",
			"location"  => "",
			"iota_ref" => "",
			"state" => "",
			"us_county" => "",
			"qsl_manager" => "",
			"bearing" 		=> "",
			"workedBefore" => false,
			"lotw_member" => false,
			"suffix_slash" => "", // Suffix Slash aka Portable
		];


		/*
		*
		*	Handle POST data being sent to check lookups
		*
		*/
			$raw_input = json_decode(file_get_contents("php://input"), true);

			$lookup_callsign = strtoupper($raw_input['callsign']);


		/*
		*
		*	Handle Callsign field
		*
		*/
			$return['callsign'] = $lookup_callsign;

		/*
		*
		*	Lookup DXCC and Suffix information
		*
		*/

			$callsign_dxcc_lookup = $this->logbook_model->dxcc_lookup($lookup_callsign, $date);

			$last_slash_pos = strrpos($lookup_callsign, '/');

			if(isset($last_slash_pos) && $last_slash_pos > 4) {
				$suffix_slash = $last_slash_pos === false ? $lookup_callsign : substr($lookup_callsign, $last_slash_pos + 1);
				switch ($suffix_slash) {
				    case "P":
				        $suffix_slash_item = "Portable";
				        break;
				    case "M":
				        $suffix_slash_item = "Mobile";
				    case "MM":
				        $suffix_slash_item =  "Maritime Mobile";
				        break;
				    default:
				    	// If its not one of the above suffix slashes its likely dxcc
				    	$ans2 = $this->logbook_model->dxcc_lookup($suffix_slash, $date);
				    	$suffix_slash_item = null;
				}

				$return['suffix_slash'] = $suffix_slash_item;
			}

			// If the final slash is a DXCC then find it!
			if (isset($ans2['call'])) {
				$return['dxcc'] = $ans2['entity'];
				$return['dxcc_lat'] = $ans2['lat'];
				$return['dxcc_long'] = $ans2['long'];
				$return['dxcc_cqz'] = $ans2['cqz'];
			} else {
				$return['dxcc'] = $callsign_dxcc_lookup['entity'];
				$return['dxcc_lat'] = $callsign_dxcc_lookup['lat'];
				$return['dxcc_long'] = $callsign_dxcc_lookup['long'];
				$return['dxcc_cqz'] = $callsign_dxcc_lookup['cqz'];
			}

		/*
		*
		*	Pool any local data we have for a callsign
		*
		*/
			$call_lookup_results = $this->logbook_model->call_lookup_result($lookup_callsign);

			if($call_lookup_results != null)
			{
				$return['name'] = $call_lookup_results->COL_NAME;
				$return['gridsquare'] = $call_lookup_results->COL_GRIDSQUARE;
				$return['location'] = $call_lookup_results->COL_QTH;
				$return['iota_ref'] = $call_lookup_results->COL_IOTA;
				$return['qsl_manager'] = $call_lookup_results->COL_QSL_VIA;
				$return['state'] = $call_lookup_results->COL_STATE;
				$return['us_county'] = $call_lookup_results->COL_CNTY;

				if ($return['gridsquare'] != "") {
					$return['latlng'] = $this->qralatlng($return['gridsquare']);
				}

			}


		/*
		*
		*	Check if callsign is active on LoTW
		*
		*/


		/*
		*
		*	Output Returned data
		*
		*/
		echo json_encode($return, JSON_PRETTY_PRINT);
		return;

		// End benchmarking
		$this->output->enable_profiler(FALSE);
	}

	function qralatlng($qra) {
		$this->load->library('Qra');
		$latlng = $this->qra->qra2latlong($qra);
		return $latlng;
	}

	/**
	 * Check if a callsign has been worked before in various combinations
	 * 
	 * This API endpoint performs comprehensive "worked before" checks for a given callsign,
	 * including checks by band, mode, and DXCC entity. Useful for contest logging and
	 * dupe checking.
	 * 
	 * @api POST /api/worked_before
	 * @header Content-Type application/json
	 * 
	 * @param string key Required. API authentication key
	 * @param string logbook_public_slug Required. Public slug identifier for the logbook
	 * @param string callsign Required. Callsign to check
	 * @param string frequency Required. Frequency in MHz (e.g., "14.205")
	 * @param string mode Required. Amateur radio mode (e.g., "SSB", "CW", "FT8")
	 * 
	 * @return json Returns JSON object with:
	 *   - callsign: object with worked status for callsign
	 *     - any: boolean - worked before on any band/mode
	 *     - band: boolean - worked before on this band
	 *     - mode: boolean - worked before in this mode
	 *     - bandMode: boolean - worked before on this band and mode
	 *   - dxcc: object with worked status for DXCC entity
	 *     - any: boolean - DXCC entity worked before on any band/mode
	 *     - band: boolean - DXCC entity worked before on this band
	 *     - mode: boolean - DXCC entity worked before in this mode
	 *     - bandMode: boolean - DXCC entity worked before on this band and mode
	 *   - info: object with additional information
	 *     - band: string - derived band from frequency
	 *     - dxccEntity: string - DXCC entity name for the callsign
	 * 
	 * @throws 401 Unauthorized - Missing or invalid API key, missing required fields
	 * @throws 404 Not Found - Logbook not found or empty logbook
	 * @throws 400 Bad Request - Invalid JSON format
	 * 
	 * @example
	 * Request:
	 * {
	 *   "key": "your-api-key",
	 *   "logbook_public_slug": "my-logbook",
	 *   "callsign": "W1AW",
	 *   "frequency": "14.205",
	 *   "mode": "SSB"
	 * }
	 * 
	 * Response:
	 * {
	 *   "callsign": {
	 *     "any": true,
	 *     "band": false,
	 *     "mode": true,
	 *     "bandMode": false
	 *   },
	 *   "dxcc": {
	 *     "any": true,
	 *     "band": true,
	 *     "mode": true,
	 *     "bandMode": true
	 *   },
	 *   "info": {
	 *     "band": "20M",
	 *     "dxccEntity": "United States"
	 *   }
	 * }
	 */
	function worked_before()
	{
		header('Content-type: application/json');

		$this->load->model('api_model');

		// Decode JSON and store
		$obj = json_decode(file_get_contents("php://input"), true);
		if ($obj === NULL) {
			http_response_code(400);
			echo json_encode(['status' => 'failed', 'reason' => "Invalid JSON format"]);
			return;
		}

		if(!isset($obj['key']) || $this->api_model->authorize($obj['key']) == 0) {
		   http_response_code(401);
		   echo json_encode(['status' => 'failed', 'reason' => "Missing or invalid API key"]);
		   return;
		}

		$required_fields = ['callsign', 'frequency', 'mode'];
		foreach ($required_fields as $field) {
			if (!isset($obj[$field]) || empty($obj[$field])) {
				http_response_code(400);
				echo json_encode(['status' => 'failed', 'reason' => "Missing required field: $field"]);
				return;
			}
		}

		// Load models
		$this->load->model('logbook_model');
		$this->load->model('logbooks_model');
		$this->load->model('bands');

		$date = date("Y-m-d");
		$callsign = strtoupper(trim($obj['callsign']));
		$logbook_slug = $obj['logbook_public_slug'];
		$frequency = floatval($obj['frequency']);
		$mode = strtoupper(trim($obj['mode']));

		// Get band from frequency
		$band = $this->bands->get_band_from_freq($frequency);
		if (!$band) {
			http_response_code(400);
			echo json_encode(['status' => 'failed', 'reason' => "Invalid frequency: $frequency MHz"]);
			return;
		}

		// Get DXCC entity for callsign
		$callsign_dxcc_lookup = $this->logbook_model->dxcc_lookup($callsign, $date);
		$dxcc_entity = $callsign_dxcc_lookup['entity'];

		// Initialize return structure
		$return = [
			"callsign" => [
				"any" => false,
				"band" => false,
				"mode" => false,
				"bandMode" => false
			],
			"dxcc" => [
				"any" => false,
				"band" => false,
				"mode" => false,
				"bandMode" => false
			],
			"info" => [
				"band" => $band,
				"dxccEntity" => $dxcc_entity
			]
		];

		// Verify logbook exists and get station locations
		if(!$this->logbooks_model->public_slug_exists($logbook_slug)) {
			http_response_code(404);
			echo json_encode(['status' => 'failed', 'reason' => "Logbook not found"]);
			return;
		}

		$logbook_id = $this->logbooks_model->public_slug_exists_logbook_id($logbook_slug);
		if($logbook_id === false) {
			http_response_code(404);
			echo json_encode(['status' => 'failed', 'reason' => "$logbook_slug has no associated station locations"]);
			return;
		}

		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($logbook_id);
		if (!$logbooks_locations_array) {
			http_response_code(404);
			echo json_encode(['status' => 'failed', 'reason' => "Empty logbook"]);
			return;
		}

		$this->api_model->update_last_used($obj['key']);

		// Get main mode for comparison
		$main_mode = $this->logbook_model->get_main_mode_from_mode($mode);

		// Check callsign worked before - any band/mode
		$this->db->select('1');
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->where('COL_CALL', $callsign);
		$this->db->where('COL_PROP_MODE !=', 'SAT'); // Exclude satellite
		$query = $this->db->get($this->config->item('table_name'), 1);
		$return['callsign']['any'] = $query->num_rows() > 0;

		// Check callsign worked before - this band
		$this->db->select('1');
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->where('COL_CALL', $callsign);
		$this->db->where('COL_BAND', $band);
		$this->db->where('COL_PROP_MODE !=', 'SAT');
		$query = $this->db->get($this->config->item('table_name'), 1);
		$return['callsign']['band'] = $query->num_rows() > 0;

		// Check callsign worked before - this mode
		$this->db->select('1');
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->where('COL_CALL', $callsign);
		$this->db->where('COL_MODE', $main_mode);
		$this->db->where('COL_PROP_MODE !=', 'SAT');
		$query = $this->db->get($this->config->item('table_name'), 1);
		$return['callsign']['mode'] = $query->num_rows() > 0;

		// Check callsign worked before - this band and mode
		$this->db->select('1');
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->where('COL_CALL', $callsign);
		$this->db->where('COL_BAND', $band);
		$this->db->where('COL_MODE', $main_mode);
		$this->db->where('COL_PROP_MODE !=', 'SAT');
		$query = $this->db->get($this->config->item('table_name'), 1);
		$return['callsign']['bandMode'] = $query->num_rows() > 0;

		// Check DXCC entity worked before - any band/mode
		$this->db->select('1');
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->where('COL_COUNTRY', $dxcc_entity);
		$this->db->where('COL_PROP_MODE !=', 'SAT');
		$query = $this->db->get($this->config->item('table_name'), 1);
		$return['dxcc']['any'] = $query->num_rows() > 0;

		// Check DXCC entity worked before - this band
		$this->db->select('1');
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->where('COL_COUNTRY', $dxcc_entity);
		$this->db->where('COL_BAND', $band);
		$this->db->where('COL_PROP_MODE !=', 'SAT');
		$query = $this->db->get($this->config->item('table_name'), 1);
		$return['dxcc']['band'] = $query->num_rows() > 0;

		// Check DXCC entity worked before - this mode
		$this->db->select('1');
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->where('COL_COUNTRY', $dxcc_entity);
		$this->db->where('COL_MODE', $main_mode);
		$this->db->where('COL_PROP_MODE !=', 'SAT');
		$query = $this->db->get($this->config->item('table_name'), 1);
		$return['dxcc']['mode'] = $query->num_rows() > 0;

		// Check DXCC entity worked before - this band and mode
		$this->db->select('1');
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->where('COL_COUNTRY', $dxcc_entity);
		$this->db->where('COL_BAND', $band);
		$this->db->where('COL_MODE', $main_mode);
		$this->db->where('COL_PROP_MODE !=', 'SAT');
		$query = $this->db->get($this->config->item('table_name'), 1);
		$return['dxcc']['bandMode'] = $query->num_rows() > 0;

		http_response_code(200);
		echo json_encode($return, JSON_PRETTY_PRINT);
	}

	/**
	 * Retrieve the last 10 QSO entries in compact format
	 * 
	 * This API endpoint returns the most recent 10 QSO entries from a logbook
	 * in a compact format suitable for quick display or status checking.
	 * 
	 * @api POST /api/recent_qsos
	 * @header Content-Type application/json
	 * 
	 * @param string key Required. API authentication key
	 * @param string logbook_public_slug Required. Public slug identifier for the logbook
	 * @param int limit Optional. Number of QSOs to return (default: 10, max: 50)
	 * 
	 * @return json Returns JSON array with recent QSOs:
	 *   - timestamp: string - QSO date and time (ISO format)
	 *   - callsign: string - Contacted callsign
	 *   - name: string - Operator name (if available)
	 *   - band: string - Amateur radio band
	 *   - mode: string - Amateur radio mode
	 *   - rst_sent: string - RST sent
	 *   - rst_rcvd: string - RST received
	 *   - country: string - DXCC entity
	 *   - comment: string - QSO comment (if any)
	 * 
	 * @throws 401 Unauthorized - Missing or invalid API key
	 * @throws 404 Not Found - Logbook not found or empty logbook
	 * @throws 400 Bad Request - Invalid JSON format
	 * 
	 * @example
	 * Request:
	 * {
	 *   "key": "your-api-key",
	 *   "logbook_public_slug": "my-logbook",
	 *   "limit": 5
	 * }
	 * 
	 * Response:
	 * [
	 *   {
	 *     "timestamp": "2025-07-24T14:30:00Z",
	 *     "callsign": "W1AW",
	 *     "name": "John Smith",
	 *     "band": "20M",
	 *     "mode": "SSB",
	 *     "rst_sent": "59",
	 *     "rst_rcvd": "59",
	 *     "country": "United States",
	 *     "comment": "Nice signal!"
	 *   },
	 *   {
	 *     "timestamp": "2025-07-24T14:25:00Z",
	 *     "callsign": "VK3ABC",
	 *     "name": "",
	 *     "band": "20M",
	 *     "mode": "CW",
	 *     "rst_sent": "599",
	 *     "rst_rcvd": "579",
	 *     "country": "Australia",
	 *     "comment": ""
	 *   }
	 * ]
	 */
	function recent_qsos()
	{
		header('Content-type: application/json');

		$this->load->model('api_model');

		// Decode JSON and store
		$obj = json_decode(file_get_contents("php://input"), true);
		if ($obj === NULL) {
			http_response_code(400);
			echo json_encode(['status' => 'failed', 'reason' => "Invalid JSON format"]);
			return;
		}

		if(!isset($obj['key']) || $this->api_model->authorize($obj['key']) == 0) {
		   http_response_code(401);
		   echo json_encode(['status' => 'failed', 'reason' => "Missing or invalid API key"]);
		   return;
		}

		if(!isset($obj['logbook_public_slug'])) {
		   http_response_code(400);
		   echo json_encode(['status' => 'failed', 'reason' => "Missing required field: logbook_public_slug"]);
		   return;
		}

		// Load models
		$this->load->model('logbooks_model');

		$logbook_slug = $obj['logbook_public_slug'];
		$limit = isset($obj['limit']) ? intval($obj['limit']) : 10;
		
		// Ensure limit is within reasonable bounds
		if ($limit < 1) $limit = 10;
		if ($limit > 50) $limit = 50;

		// Verify logbook exists and get station locations
		if(!$this->logbooks_model->public_slug_exists($logbook_slug)) {
			http_response_code(404);
			echo json_encode(['status' => 'failed', 'reason' => "Logbook not found"]);
			return;
		}

		$logbook_id = $this->logbooks_model->public_slug_exists_logbook_id($logbook_slug);
		if($logbook_id === false) {
			http_response_code(404);
			echo json_encode(['status' => 'failed', 'reason' => "$logbook_slug has no associated station locations"]);
			return;
		}

		$logbooks_locations_array = $this->logbooks_model->list_logbook_relationships($logbook_id);
		if (!$logbooks_locations_array) {
			http_response_code(404);
			echo json_encode(['status' => 'failed', 'reason' => "Empty logbook"]);
			return;
		}

		$this->api_model->update_last_used($obj['key']);

		// Get recent QSOs
		$this->db->select('
			DATE_FORMAT(CONCAT(COL_TIME_ON, " ", COALESCE(COL_TIME_ON, "00:00:00")), "%Y-%m-%dT%H:%i:%sZ") as timestamp,
			COL_CALL as callsign,
			COALESCE(COL_NAME, "") as name,
			COL_BAND as band,
			COALESCE(COL_SUBMODE, COL_MODE) as mode,
			COALESCE(COL_RST_SENT, "") as rst_sent,
			COALESCE(COL_RST_RCVD, "") as rst_rcvd,
			COALESCE(COL_COUNTRY, "") as country,
			COALESCE(COL_COMMENT, "") as comment
		');
		$this->db->where_in('station_id', $logbooks_locations_array);
		$this->db->order_by('COL_TIME_ON', 'DESC');
		$this->db->order_by('COL_PRIMARY_KEY', 'DESC');
		$this->db->limit($limit);
		
		$query = $this->db->get($this->config->item('table_name'));
		$results = $query->result_array();

		http_response_code(200);
		echo json_encode($results, JSON_PRETTY_PRINT);
	}

	/**
	 * QRZ.com Callsign Lookup API
	 * 
	 * This API endpoint performs a comprehensive callsign lookup using QRZ.com's XML API.
	 * Returns full callsign data including profile information, location details, and
	 * profile image links. Optionally calculates distance and bearing from a reference
	 * station location.
	 * 
	 * @api POST /api/qrz_lookup
	 * @header Content-Type application/json
	 * 
	 * @param string key Required. API authentication key
	 * @param string callsign Required. Callsign to lookup
	 * @param int station_profile_id Optional. Your station profile ID for distance/bearing calculation
	 * 
	 * @return json Returns JSON object with comprehensive QRZ.com data:
	 *   - callsign: string - The callsign from QRZ
	 *   - name: string - Operator name (first name or full name based on privacy settings)
	 *   - gridsquare: string - Maidenhead grid locator (up to 8 characters)
	 *   - city: string - City/location
	 *   - lat: string - Latitude
	 *   - long: string - Longitude
	 *   - dxcc: string - DXCC entity code
	 *   - iota: string - IOTA island reference
	 *   - qslmgr: string - QSL manager
	 *   - image: string - URL to profile image
	 *   - state: string - State (US callsigns only)
	 *   - us_county: string - US County (US callsigns only)
	 *   - distance: string - Distance from your station (if station_profile_id provided)
	 *   - bearing: string - Bearing from your station (if station_profile_id provided)
	 *   - error: string - Error message if lookup failed
	 * 
	 * @throws 401 Unauthorized - Missing or invalid API key, missing required fields, invalid station profile
	 * @throws 503 Service Unavailable - QRZ.com credentials not configured or authentication failed
	 * @throws 400 Bad Request - Invalid JSON format
	 * 
	 * @example
	 * Request:
	 * {
	 *   "key": "your-api-key",
	 *   "callsign": "W1AW",
	 *   "station_profile_id": 1
	 * }
	 * 
	 * Response:
	 * {
	 *   "callsign": "W1AW",
	 *   "name": "Hiram Percy Maxim",
	 *   "gridsquare": "FN31pr",
	 *   "city": "Newington",
	 *   "lat": "41.714775",
	 *   "long": "-72.727260",
	 *   "dxcc": "291",
	 *   "iota": "",
	 *   "qslmgr": "",
	 *   "image": "https://s3.amazonaws.com/files.qrz.com/q/w1aw/w1aw.jpg",
	 *   "state": "CT",
	 *   "us_county": "Hartford",
	 *   "distance": "1247 km",
	 *   "bearing": "072°"
	 * }
	 */
	function qrz_lookup()
	{
		header('Content-type: application/json');

		// Get JSON input
		$json = file_get_contents('php://input');
		$obj = json_decode($json, true);

		// Validate JSON
		if (!$obj) {
			http_response_code(400);
			echo json_encode(['status' => 'failed', 'reason' => 'Invalid JSON format']);
			return;
		}

		// Load required models
		$this->load->model('api_model');
		$this->load->model('stations');

		// Validate API key
		if (!isset($obj['key']) || !$this->api_model->authorize($obj['key'])) {
			http_response_code(401);
			echo json_encode(['status' => 'failed', 'reason' => 'Missing or invalid API key']);
			return;
		}

		// Validate required fields
		if (!isset($obj['callsign']) || empty(trim($obj['callsign']))) {
			http_response_code(401);
			echo json_encode(['status' => 'failed', 'reason' => 'Missing callsign parameter']);
			return;
		}

		$callsign = strtoupper(trim($obj['callsign']));
		$station_profile_id = isset($obj['station_profile_id']) ? intval($obj['station_profile_id']) : null;
		$my_gridsquare = null;

		// If station profile ID is provided, validate it and get gridsquare
		if ($station_profile_id) {
			$userid = $this->api_model->key_userid($obj['key']);
			
			// Check if station belongs to the API key owner
			if (!$this->stations->check_station_against_user($station_profile_id, $userid)) {
				http_response_code(401);
				echo json_encode(['status' => 'failed', 'reason' => 'Station profile does not belong to API key owner']);
				return;
			}
			
			// Get station profile details
			$station_profile = $this->stations->profile_clean($station_profile_id);
			if ($station_profile && !empty($station_profile->station_gridsquare)) {
				$my_gridsquare = strtoupper(trim($station_profile->station_gridsquare));
			}
		}

		// Update API usage tracking
		$this->api_model->update_last_used($obj['key']);

		// Check if QRZ credentials are configured in the global config
		$qrz_username = $this->config->item('qrz_username');
		$qrz_password = $this->config->item('qrz_password');

		if (empty($qrz_username) || empty($qrz_password)) {
			http_response_code(503);
			echo json_encode(['status' => 'failed', 'reason' => 'QRZ.com credentials not configured in system']);
			return;
		}

		// Load QRZ library
		$this->load->library('qrz');

		try {
			// Get QRZ session key
			$session_key = $this->qrz->session($qrz_username, $qrz_password);
			if (empty($session_key)) {
				http_response_code(503);
				echo json_encode(['status' => 'failed', 'reason' => 'QRZ.com authentication failed']);
				return;
			}

			// Search for callsign
			$qrz_data = $this->qrz->search($callsign, $session_key, $this->config->item('use_fullname'));

			if (isset($qrz_data['error'])) {
				http_response_code(404);
				echo json_encode(['status' => 'failed', 'reason' => 'QRZ.com error: ' . $qrz_data['error']]);
				return;
			}

			if (empty($qrz_data) || empty($qrz_data['callsign'])) {
				http_response_code(404);
				echo json_encode(['status' => 'failed', 'reason' => "Callsign $callsign not found in QRZ.com database"]);
				return;
			}

			// Calculate distance and bearing if both gridsquares are available
			if ($my_gridsquare && !empty($qrz_data['gridsquare'])) {
				$this->load->library('qra');
				
				try {
					// Calculate distance in kilometers
					$distance_km = $this->qra->distance($my_gridsquare, $qrz_data['gridsquare'], 'K');
					$qrz_data['distance'] = round($distance_km, 0) . ' km';
					
					// Calculate bearing in degrees
					$bearing_deg = $this->qra->bearing($my_gridsquare, $qrz_data['gridsquare'], 'D');
					$qrz_data['bearing'] = sprintf('%03d°', round($bearing_deg));
					
				} catch (Exception $e) {
					// If calculation fails, add empty values
					$qrz_data['distance'] = '';
					$qrz_data['bearing'] = '';
				}
			} else {
				// No calculation possible
				$qrz_data['distance'] = '';
				$qrz_data['bearing'] = '';
			}

			// Return the QRZ data
			http_response_code(200);
			echo json_encode($qrz_data, JSON_PRETTY_PRINT);

		} catch (Exception $e) {
			http_response_code(503);
			echo json_encode(['status' => 'failed', 'reason' => 'QRZ.com service error: ' . $e->getMessage()]);
			return;
		}
	}
}
