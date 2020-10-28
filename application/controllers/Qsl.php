<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
	Controller for QSL Cards
*/

class Qsl extends CI_Controller {

    function __construct() {
        parent::__construct();

        $this->load->model('user_model');
        if(!$this->user_model->authorize(2)) { $this->session->set_flashdata('notice', 'You\'re not allowed to do that!'); redirect('dashboard'); }
    }

    public function index() {
        // Render Page
        $data['page_title'] = "QSL Cards";

        $this->load->model('qsl_model');
        $data['qslarray'] = $this->qsl_model->getQsoWithQslList();

        $this->load->view('interface_assets/header', $data);
        $this->load->view('qslcard/index');
        $this->load->view('interface_assets/footer');
    }

    public function upload() {
        // Render Page
        $data['page_title'] = "Upload QSL Cards";
        $this->load->view('interface_assets/header', $data);
        $this->load->view('qslcard/upload');
        $this->load->view('interface_assets/footer');
    }

    public function delete() {
        $this->load->model('user_model');
        if(!$this->user_model->authorize(2)) { $this->session->set_flashdata('notice', 'You\'re not allowed to do that!'); redirect('dashboard'); }

        $id = $this->input->post('id');
        $this->load->model('Qsl_model');

        $path = './assets/qslcard/';
        $file = $this->Qsl_model->getFilename($id)->row();
        $filename = $file->filename;
        unlink($path.$filename);

        $this->Qsl_model->deleteQsl($id);
    }

    public function uploadqsl() {
        $this->load->model('user_model');
        if(!$this->user_model->authorize(2)) { $this->session->set_flashdata('notice', 'You\'re not allowed to do that!'); redirect('dashboard'); }

        if (!file_exists('./assets/qslcard')) {
            mkdir('./assets/qslcard', 0755, true);
        }
        $qsoid = $this->input->post('qsoid');

        if (isset($_FILES['qslcardfront']) && $_FILES['qslcardfront']['name'] != "" && $_FILES['qslcardfront']['error'] == 0)
        {
            $result = $this->uploadQslCard($qsoid);
        }

        // Set Page Title
        $data['page_title'] = "QSL Upload";

        // Load Views
        $this->load->view('interface_assets/header', $data);
        $this->load->view('qslcard/upload_done', $result);
        $this->load->view('interface_assets/footer');
    }

    function uploadQslCard($qsoid) {
        $config['upload_path']          = './assets/qslcard';
        $config['allowed_types']        = 'jpg|gif|png';
        $array = explode(".", $_FILES['qslcardfront']['name']);
        $ext = end($array);
        $config['file_name'] = $qsoid . '_' . time() . '.' . $ext;

        $this->load->library('upload', $config);

        if ( ! $this->upload->do_upload('qslcardfront')) {
            // Upload of QSL card Failed
            $error = array('error' => $this->upload->display_errors());

            return $error;
        }
        else {
            // Load database queries
            $this->load->model('Qsl_model');

            //Upload of QSL card was successful
            $data = $this->upload->data();

            // Now we need to insert info into database about file
            $filename = $data['file_name'];
            $this->Qsl_model->saveQsl($qsoid, $filename);

            return 'Success';
        }
    }

}