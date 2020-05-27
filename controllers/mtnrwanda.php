<?php

class Mtnrwanda extends Controller {

    function __construct() {
        parent::__construct();
    }


        function Index() {

            $request = file_get_contents('php://input');
            if (empty($request)) {
                $this->view->render('index');
            } else {
                $standard_array = $this->model->InterpreteRequest($request);
                $standard_array['operator'] = 'mtn_rw';
                if ($standard_array['requesttype'] == 'pull') {
                    $response_xml = $this->model->RequestHandler($request, $standard_array);
                    header('Content-Type: application/xml; charset=UTF-8');
                    echo $response_xml;

                }
                if ($standard_array['requesttype'] == 'cleanup') {
                    $this->model->SessionCleanUp($standard_array);
                }
            }
        }
}
