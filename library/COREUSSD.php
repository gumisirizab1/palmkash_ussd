<?php

class COREUSSD extends Palmkash {

    function __construct() {
        parent::__construct();
    }


    function VerifyMobile($params) {
             return $this->db->SelectData("SELECT * FROM palm_test_account WHERE telephone_number='".$params['msisdn']."' AND status='active' ");
    }

    function ManageRequestSession($params) {
        //Check If Session Already Exists:
        $res = $this->GetSession($params);
        if (count($res) > 0) {
            return 1;
        } else {
            //Register Session On DB.
            $postData['session_date'] = date('Y-m-d G:i:s');
            $postData['session_id'] = $params['sessionId'];
            $postData['telephone_number'] = $params['msisdn'];
          //  $postData['session_execution_log_file'] = $params['execlogfile'];
            $this->db->InsertData("palm_log_session_data", $postData);
            return 0;
        }
    }

    function MenuOptionHandler($params, $status) {
        if ($status == 0) {
            //No State Exists

                $call_fxn[0]['ussd_new_state'] = 1;

            $this->log->ExeLog($params, "COREUSSD::MenuOptionHandler Initial Session Call. Returned New State ..." . $call_fxn[0]['ussd_new_state'], 2);
        } else {
            $state = $this->GetCurrentState($params);
            $this->log->ExeLog($params, "COREUSSD::MenuOptionHandler GetCurrentState. Returned New State ...".var_export($state,true), 2);

            if ($state[0]['state_type'] == 'input') {
                $this->StoreInputValues($params, $state[0]);
                $choice = '-1';
            } else {
                $choice = $params['subscriberInput'];
            }
            $this->log->ExeLog($params, "COREUSSD::MenuOptionHandler Serching For Option From " .
                    $state[0]['current_state'] . ' When User Choice Is ' . $choice, 2);
            $call_fxn = $this->GetNextState($state[0]['current_state'], $choice);
            $this->log->ExeLog($params, "COREUSSD::MenuOptionHandler::GetNextState Returned state " . var_export($call_fxn,true), 2);

            if(count($call_fxn)==0){
            //set it to previous state previous_state
      			$prev_state=$state[0]['current_state'];
      			if($state[0]['call_fxn_name']==''){
                   $call_fxn[0]['ussd_new_state'] =  $prev_state;
      			}else{ //if called prev, refer to main
                   $call_fxn[0]['ussd_new_state'] =  1;
      			}

            }


            $this->log->ExeLog($params, "COREUSSD::MenuOptionHandler Continued Session Returned Function To Call..." . $call_fxn[0]['ussd_new_state'], 2);
        }
        $response = $this->DisplayMenu($params, $call_fxn);
        return $response;
    }

    function DisplayMenu($params, $fxn_array) {
        $this->OperationWatch($params, $fxn_array[0]['ussd_new_state']);

        $menu = $this->GetStateFull($fxn_array[0]['ussd_new_state']);
        $this->log->ExeLog($params, "COREUSSD::DisplayMenu GetStateFull Returning Array for the menu " . var_export($menu, true), 2);
        $response_array = $this->PrepareMenu($params, $menu);

        return $response_array;
    }

    function PrepareMenu($params, $menu) {

          $result = array();
        if ($menu[0]['fxn_call_flag'] == 1) {
            $this->log->ExeLog($params, "COREUSSD::DisplayMenu PrepareMenu Required to make remote function call to " . $menu[0]['call_fxn_name'], 2);
            $result = $this->{$menu[0]['call_fxn_name']}($params);

   $this->log->ExeLog($params, "COREUSSD::External function call Call Result " . var_export($result, true), 2);
           if(isset($result['language'])){
            $menu = $this->GetStateFull(15);
            // $this->log->ExeLog($params, "COREUSSD::GetStateFull Normal Member language ".$ln_text." and Menu text " . var_export($menu, true), 2);
          }
            //$prepared_response = $this->ReplacePlaceHolders($params, $menu[0][$ln_text], $result);
          //  $resp['state'] = $menu[0]['state_indicator'];
          //  $resp['msg_response'] = $prepared_response;
        }else{

          $result=$params;
        }
        $ln = $this->GetSessionLanguage($params);
        if ($ln[0]['session_language_pref'] == '') {
            $ln_text = 'text_en';
        } else {
            $ln_text = 'text_' . $ln[0]['session_language_pref'];
        }
        $this->log->ExeLog($params, "COREUSSD::MenuArray Session Language Is " . $ln_text, 2);

        //$this->log->ExeLog($params, "COREUSSD::DisplayMenu PrepareMenu No Remote Function Call Required", 2);
        $prepared_response = $this->ReplacePlaceHolders($params, $menu[0][$ln_text], $result);
        $resp['state'] = $menu[0]['state_indicator'];
        $resp['msg_response'] = $prepared_response;

         $response = $this->MenuArray($params, $resp);
        return $response;

    }

    function MenuArray($params, $resp) {
        $response_array = array(
            'msisdn' => $params['msisdn'],
            'sessionid' => $params['sessionId'],
            'transactionid' => $params['transactionId'],
            'freeflow' => array(
                'freeflowState' => $resp['state']
            ),
            'applicationResponse' => $resp['msg_response'],
        );
        return $response_array;
    }

    function ReplacePlaceHolders($request, $text, $array) {
        $this->log->ExeLog($request, 'COREUSSD::ReplacePlaceHolders Fired for ' . $text . ' With Data '
                . var_export($array, true), 2);
			 $new_text = $text;

			if(isset($array['start_station'])){
	          $new_text = str_replace("[ORIGIN]", $array['start_station'], $new_text);
			}
			if(isset($array['end_station'])){
	          $new_text = str_replace("[DESTINATION]", $array['end_station'], $new_text);
			}

			if(isset($array['time_available'])){
	          $new_text = str_replace("[TIMES]", $array['time_available'], $new_text);
			}

			if(isset($array['booking_info'])){
	          $new_text = str_replace("[BOOK_INFO]", $array['booking_info'], $new_text);
			}

			if(isset($array['amount'])){
	          $new_text = str_replace("[AMOUNT]", $array['amount'], $new_text);
			}

			if(isset($array['jackpot_prediction'])){
	          $new_text = str_replace("[JACKPOT_PREDICTION]", $array['jackpot_prediction'], $new_text);
			}


        return $new_text;
    }

}