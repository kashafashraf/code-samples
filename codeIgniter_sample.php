<?php 

class Requests extends App_Controller {

  public $data = array();
	
  function __construct()
  {
    parent::__construct();
    $this->load->helper(array('alpha', 'beta'));
	  $this->load->model('Requests_model');
	  $this->load->model('Units_model');
    $this->load->model('User_model');
    $this->load->model('Programming_model');
    $this->load->model('Audit_model');  
    
    # set adhoc message
    get_adhoc_message();      
  }                                                              
  /* Control login-restricted pages */
  public function _remap($method, $params = array())
  {   
      # Check if admin is not logged in
      if($this->session->userdata('admin_logged_in')===FALSE) {
            header('Location: '.$this->config->item('base_url'));
      }
      else
      {
          # if admin is logged in then validate the user access for the operation
          $curoperation = check_operation('requests_controller_permissions', $method);
          if($curoperation!=""){
            # check if current user is allowed to execute the current operation
            $oper_res = explode('_', $curoperation);
            //$this->load->model('User_model');
            if($this->User_model->userModuleOperationAllowed($oper_res[0], $oper_res[1], ($this->session->userdata('admin_id')))){
              return call_user_func_array(array($this, $method), $params);
            }else{
              show_permission_denied();             
            }
            
          }  
          else
            show_invalid_request();  
      }
  }  
  
  /* Landing page */
  public function index()
	{
	  redirect('/requests/listrequests/');
	}
  
  /* list all requests */
  public function listrequests(){

    

    $data['title'] = "Requests List&nbsp;-&nbsp;" . $this->config->item('application_default_title'); 
    $count_result = $this->Requests_model->getRequestsList($this->session->userdata('admin_id'), 1);
    $data['total_requests'] = $count_result->total_requests;
    $data['list_auto_refresh'] = $this->session->userdata('requests_list_auto_refresh');

    # Pagination
    $config['base_url'] = $this->config->item('base_url').'/requests/listrequests/';
    $config['total_rows'] = $data['total_requests'];
    $config['per_page'] = 20;
    $this->pagination->initialize($config);
    $data['pagination'] = $this->pagination->create_links();
    $start_from = $this->uri->segment(3) > 0 ? $this->uri->segment(3) : 0;
    $data['start_from'] = $start_from;
    $data['requestslist'] = $this->Requests_model->getRequestsListPager($config['per_page'], $start_from, $this->session->userdata('admin_id'));
    $data['userlist'] = $this->User_model->getUsersList();
    $data['customerslist'] = $this->Devices_model->getClientsList();
    $data['projectslist'] = $this->Requests_model->getProjectsList();
    
    # Pagination
    
    
    $this->load->view('header', $data);
    $this->load->view('show_requests',$data);
    $this->load->view('footer');
  }
  
     
	/* List all request Types */   
   public function types(){
     $data['title'] = "Job Types&nbsp;-&nbsp;" . $this->config->item('application_default_title'); 
     $this->load->model('Requests_model');
     $data['requesttypeslist'] = $this->Requests_model->getJobTypes();
     $this->load->view('header', $data);
     $this->load->view('show_request_types', $data);
     $this->load->view('footer');
   	
   } 

  
  # List All Reason Codes
  public function reason_codes(){
     $data['title'] = "Manage Reasons Codes&nbsp;-&nbsp;" . $this->config->item('application_default_title'); 
     
     
     # Pagination
     $config['base_url'] = $this->config->item('base_url').'/requests/reason_codes/';
     $config['total_rows'] = count($this->Requests_model->getReasonCodesList(0));
     $config['per_page'] = 20;
     $this->pagination->initialize($config);
     $data['pagination'] = $this->pagination->create_links();
     $start_from = $this->uri->segment(3) > 0 ? $this->uri->segment(3) : 0;
     $data['fileslist'] = $this->Requests_model->getReasonCodesList($config['per_page'], $start_from);
     # Pagination
     
     
     $this->load->view('header', $data);
     $this->load->view('show_reason_codes', $data);
     $this->load->view('footer');
  
  }

  public function add_reason_code(){
  
    $this->load->helper(array('form', 'url'));
		$this->load->library('form_validation');
    
		if ($this->form_validation->run('create_reason_code') == FALSE)
		{
      $data['title'] = "Create Reason Code&nbsp;-&nbsp;" . $this->config->item('application_default_title');
      $data['last_code_used'] = $this->Requests_model->getLastReasonCode();
      $this->load->view('header', $data);
      $this->load->view('create_reason_code', $data);
      $this->load->view('footer');
		}
		else
		{
			$data['title'] = "Reason Code Added Successfully&nbsp;-&nbsp;" . $this->config->item('application_default_title');
			$this->Requests_model->addReasonCode();
      $this->load->view('header', $data);
			$this->load->view('reason_code_added');
      $this->load->view('footer');
		}
  
  }

  /* edit reason code */
  public function edit_reason_code($id){
  
    $this->load->helper(array('form', 'url'));
		$this->load->library('form_validation');
    
		if ($this->form_validation->run('edit_reason_code') == FALSE)
		{
      $data['title'] = "Edit Reason Code&nbsp;-&nbsp;" . $this->config->item('application_default_title');
			
			/* if form posted show posted values in the form*/
      $reason_code_details = $this->Requests_model->getReasonCodeDetails($id);
      $data['reason_code_details'] = $reason_code_details;
      if($this->session->userdata('admin_id')==1){
        $this->load->view('header', $data);
        $this->load->view('edit_reason_code', $data);
      }  
      else{
        show_permission_denied();
      }  
      $this->load->view('footer');
		}
		else
		{
			$data['title'] = "Project Edited Successfully&nbsp;-&nbsp;" . $this->config->item('application_default_title');
			$this->Requests_model->editReasonCode($id);
      $this->load->view('header', $data);
			$this->load->view('project_edited');
      $this->load->view('footer');
		}
  
  }
  
  
   
}
