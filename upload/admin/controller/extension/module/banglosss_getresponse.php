<?php
/* * *****************Bangloss_getresponse.php**********************************
 * @Module name    : GetResponse Integration by BANGLOSS for OpenCart 3.x
 * @type            : Controller Class
 * @class name      : BanglossGetresponse
 * @description     : Controller to allow admin to set API including API generation instuction for OpenCart 3.x. 
 * @author          : MD SOLIMAN FARAGY 	
 * @url             : https://www.sfaragy.com/      
 * @support         : solimankhulna@gmail.com	
 * @copyright       : MD SOLIMAN FARAGY	
 * ********************************************************** */
class ControllerExtensionModuleBanglossGetresponse extends Controller
{

	private $error = array();
	private $gr_apikey;
	private $get_response;
	private $campaigns;
	private $campaign;
	private $custom_fields = array();
	private $allow_fields = array('telephone', 'country', 'city', 'address', 'postcode');

	public function __construct($registry)
	{
		parent::__construct($registry);

		$this->gr_apikey = $this->config->get('module_getresponse_apikey');

		if (!empty($this->gr_apikey)) {
			$this->get_response = new GetResponseApiV3($this->gr_apikey);
		}
	}
 
    /*****************Function index**********************************
    * @type            : Function
    * @function name   : index
    * @description     : Load user interface for admin module settings            
    *                    
    * @param           : null integer value
    * @return          : Data to respective view 
    * ********************************************************** */
	public function index() {
		$this->load->language('extension/module/bangloss_getresponse');
		$this->load->model('localisation/language');
		$this->load->model('design/layout');
		$this->document->setTitle($this->language->get('heading_title'));

		$data = array();
		$data = $this->saveSettings($data);
		$data = $this->assignLanguage($data);
		$data = $this->assignSettings($data);
		$data = $this->assignBreadcrumbs($data);

		if (!empty($this->gr_apikey)) {
			$data = $this->assignAutoresponders($data);
			$data = $this->assignForms($data);
			$data = $this->assignAccounts($data);
			$data['campaigns'] = $this->getCampaigns();
		}

		$data['layouts'] = $this->model_design_layout->getLayouts();
		$data['action'] = $this->url->link('extension/module/bangloss_getresponse', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/bangloss_getresponse.tpl', $data));
	}

    /*****************Function assignAutoresponders**********************************
    * @type            : Function
    * @function name   : assignAutoresponders
    * @description     : Automatically response as per cmapaing           
    *                    
    * @param           : API Data Array
    * @return          : Response Data
    * ********************************************************** */
	private function assignAutoresponders($data) {
		$autoresponders = $this->get_response->getAutoresponders();
		$data['campaign_days'] = array();

		if (isset($autoresponders->httpStatus) && $autoresponders->httpStatus != 200) {
			$this->session->data['error_warning'] = $autoresponders->codeDescription;

			return $data;
		}

		if (!empty($autoresponders) && is_object($autoresponders)) {
			foreach ($autoresponders as $autoresponder) {
				if ($autoresponder->triggerSettings->dayOfCycle == null) {
					continue;
				}

				$data['campaign_days'][$autoresponder->triggerSettings->subscribedCampaign->campaignId][$autoresponder->triggerSettings->dayOfCycle] = array(
						'day' => $autoresponder->triggerSettings->dayOfCycle,
						'name' => $autoresponder->subject,
						'status' => $autoresponder->status
				);
			}
		}

		return $data;
	}

    /*****************Function assignForms**********************************
    * @type            : Function
    * @function name   : assignForms
    * @description     : Assign WebForms           
    *                    
    * @param           : API Data Array
    * @return          : Response Array
    * ********************************************************** */
	private function assignForms($data) {
		$forms = $this->get_response->getForms();
		$new_forms = array();
		$old_forms = array();

		if (!empty($forms)) {
			foreach ($forms as $form) {
				if (isset($form->formId) && !empty($form->formId) && $form->status == 'published') {
					$new_forms[] = array('id' => $form->formId, 'name' => $form->name, 'url' => $form->scriptUrl);
				}
			}
		}

		$webforms = $this->get_response->getWebForms();

		if (!empty($webforms)) {
			foreach ($webforms as $form) {
				if (isset($form->webformId) && !empty($form->webformId) && $form->status == 'enabled') {
					$old_forms[] = array('id' => $form->webformId, 'name' => $form->name, 'url' => $form->scriptUrl);
				}
			}
		}

		$data['new_forms'] = $new_forms;
		$data['old_forms'] = $old_forms;

		return $data;
	}

    /*****************Function assignBreadcrumbs**********************************
    * @type            : Function
    * @function name   : assignBreadcrumbs
    * @description     : Preapring breadcrumbs for the view          
    *                    
    * @param           : Data Array
    * @return          : Response Array
    * ********************************************************** */
	private function assignBreadcrumbs($data) {
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/bangloss_getresponse', 'user_token=' . $this->session->data['user_token'], true)
		);

		return $data;
	}

    /*****************Function assignLanguage**********************************
    * @type            : Function
    * @function name   : assignLanguage
    * @description     : Loading language data for the view          
    *                    
    * @param           : Data Array
    * @return          : Response Array
    * ********************************************************** */
	private function assignLanguage($data) {
		$this->load->language('extension/module/bangloss_getresponse');
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_module'] = $this->language->get('text_module');
		$data['text_success'] = $this->language->get('text_success');
		$data['text_none'] = $this->language->get('text_none');
		$data['text_yes'] = $this->language->get('text_yes');
		$data['text_no'] = $this->language->get('text_no');
		$data['entry_title'] = $this->language->get('entry_title');
		$data['entry_export'] = $this->language->get('entry_export');
		$data['entry_apikey'] = $this->language->get('entry_apikey');
		$data['entry_apikey_hint'] = $this->language->get('entry_apikey_hint');
		$data['entry_campaign'] = $this->language->get('entry_campaign');
		$data['entry_campaign_hint'] = $this->language->get('entry_campaign_hint');
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_export'] = $this->language->get('button_export');
		$data['button_connect'] = $this->language->get('button_connect');
		$data['button_disconnect'] = $this->language->get('button_disconnect');
		$data['button_stay'] = $this->language->get('button_stay');
		$data['apikey_info'] = $this->language->get('apikey_info');
		$data['export_info'] = $this->language->get('export_info');
		$data['text_export_success'] = $this->language->get('text_export_success');
		$data['register_info'] = $this->language->get('register_info');
		$data['webform_info'] = $this->language->get('webform_info');
		$data['disconnect_info'] = $this->language->get('disconnect_info');
		$data['text_disconnect'] = $this->language->get('text_disconnect');
		$data['text_connect'] = $this->language->get('text_connect');
		$data['apikey_title'] = $this->language->get('apikey_title');
		$data['export_title'] = $this->language->get('export_title');
		$data['register_title'] = $this->language->get('register_title');
		$data['webform_title'] = $this->language->get('webform_title');
		$data['disconnect_title'] = $this->language->get('disconnect_title');
		$data['label_active'] = $this->language->get('label_active');
		$data['label_active_register'] = $this->language->get('label_active_register');
		$data['label_active_forms'] = $this->language->get('label_active_forms');
		$data['label_form'] = $this->language->get('label_form');
		$data['label_campaign'] = $this->language->get('label_campaign');
		$data['entry_campaign_description'] = $this->language->get('entry_campaign_description');
		$data['label_day_of_cycle'] = $this->language->get('label_day_of_cycle');
		$data['label_auto_queue'] = $this->language->get('label_auto_queue');
		$data['label_yes'] = $this->language->get('label_yes');
		$data['label_no'] = $this->language->get('label_no');
		$data['label_none'] = $this->language->get('label_none');
		$data['label_new_forms'] = $this->language->get('label_new_forms');
		$data['label_old_forms'] = $this->language->get('label_old_forms');
		$data['label_export_info'] = $this->language->get('label_export_info');
		$data['label_register_info'] = $this->language->get('label_register_info');
		$data['label_webform_info'] = $this->language->get('label_webform_info');
		$data['info_loading'] = $this->language->get('info_loading');
		$data['info_ajax_error'] = $this->language->get('info_ajax_error');
		return $data;
	}


    /*****************Function assignSettings**********************************
    * @type            : Function
    * @function name   : assignSettings
    * @description     : Get - Set values         
    *                    
    * @param           : Data Array
    * @return          : Response Array
    * ********************************************************** */
	private function assignSettings($data) {
		$this->enable_module = $this->config->get('module_getresponse_enable_module');
		$this->campaign = $this->config->get('module_getresponse_campaign');

		$data['module_getresponse_modules'] = $this->config->get('module_getresponse_module');

		$data['module_getresponse_form'] = $this->getGetresponseForm();

		if ($this->config->get('module_getresponse_reg')) {
			$data['module_getresponse_reg'] = $this->config->get('module_getresponse_reg');
		} else {
			$data['module_getresponse_reg'] = array('campaign' => '', 'day' => '', 'sequence_active' => 0);
		}

		if (isset($this->session->data['success'])) {
			$data['save_success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		}

		if (isset($this->session->data['error_warning'])) {
			$data['error_warning'] = $this->session->data['error_warning'];
			unset($this->session->data['error_warning']);
		}

		$data['token'] = $this->session->data['token'];
		$data['active_tab'] = isset($this->session->data['active_tab']) ? $this->session->data['active_tab'] : 'home';
		$data['module_getresponse_apikey'] = $this->gr_apikey;
		$data['module_getresponse_campaign'] = $this->campaign;

		return $data;
	}

	
    /*****************Function getGetresponseForm**********************************
    * @type            : Function
    * @function name   : getGetresponseForm
    * @description     : To get forms        
    *                    
    * @param           : Data Array
    * @return          : Response Array
    * ********************************************************** */
	private function getGetresponseForm()
	{
		$getresponseForm = $this->config->get('module_getresponse_form');

		if (!is_array($getresponseForm) || !isset($getresponseForm['id'])) {
			return ['id' => 0, 'url' => '', 'active' => 0];
		}
		if (!isset($getresponseForm['active'])) {
			$getresponseForm['active'] = 0;
		}
		return $getresponseForm;
	}

	
    /*****************Function assignAccounts**********************************
    * @type            : Function
    * @function name   : assignAccounts
    * @description     : GetResponse Account Information Collection        
    *                    
    * @param           : Data Array
    * @return          : Response Array
    * ********************************************************** */
	private function assignAccounts($data)
	{
		$accounts = $this->get_response->accounts();
		$data['module_getresponse_accounts']['name'] = $accounts->firstName . " " . $accounts->lastName;
		$data['module_getresponse_accounts']['email'] = (isset($accounts->email)) ? $accounts->email : '';
		$data['module_getresponse_accounts']['street'] = (isset($accounts->street)) ? $accounts->street : '';
		$data['module_getresponse_accounts']['zipCode'] = (isset($accounts->zipCode)) ? $accounts->zipCode : '';
		$data['module_getresponse_accounts']['city'] = (isset($accounts->city)) ? $accounts->city : '';
		$data['module_getresponse_accounts']['state'] = (isset($accounts->state)) ? $accounts->state : '';
		$countryCode = $accounts->countryCode;
		$data['module_getresponse_accounts']['countryCode'] = (isset($countryCode->countryCode)) ? $countryCode->countryCode : '';
		return $data;
	}
    
    /*****************Function saveSettings**********************************
    * @type            : Function
    * @function name   : saveSettings
    * @description     : Module settings save / edit     
    *                    
    * @param           : Data Array
    * @return          : Response Array
    * ********************************************************** */
	private function saveSettings($data) {
		$this->load->model('setting/setting');
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$request = $this->request->post;

            $this->model_setting_setting->editSetting('module_filter', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));


			if (!$this->checkApiKey($request['module_getresponse_apikey'])) {
				$this->session->data['error_warning'] = $this->language->get('error_incorrect_apikey');
			} elseif (1 == $request['module_getresponse_disconnect']) {
				$this->model_setting_setting->editSetting('module_getresponse', []);
				$this->session->data['success'] = $this->language->get('text_disconnect');
			} else {
				$this->model_setting_setting->editSetting('module_getresponse', $request);
				$this->session->data['success'] = (2 == $request['module_getresponse_disconnect']) ? $this->language->get('text_connect') : $this->language->get('text_success');
				unset($request['module_getresponse_disconnect']);
			}

			$this->session->data['active_tab'] = $request['module_getresponse_form']['current_tab'];
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		return $data;
	}

    /*****************Function checkApiKey**********************************
    * @type            : Function
    * @function name   : checkApiKey
    * @description     : Module settings save / edit     
    *                    
    * @param           : Api Key
    * @return          : Response boolean
    * ********************************************************** */
	private function checkApiKey($aKey) {
		if (empty($aKey)) {
			return false;
		} elseif ($this->config->get('module_getresponse_apikey') == $aKey) {
			return true;
		}

		$get_response = new GetResponseApiV3($apikey);
		$campaigns = $get_response->getCampaigns();

		return !(isset($campaigns->httpStatus) && $campaigns->httpStatus != 200);
	}

	
    /*****************Function validate**********************************
    * @type            : Function
    * @function name   : validate
    * @description     : Module use permission validation     
    *                    
    * @param           : null
    * @return          : Response boolean
    * ********************************************************** */
	private function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/getresponse')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
		}

		return (!$this->error);
	}

    /*****************Function export**********************************
    * @type            : Function
    * @function name   : export
    * @description     : Export all contacts for campaings    
    *                    
    * @param           : null
    * @return          : json output
    * ********************************************************** */
	public function export() {
		$this->load->model('extension/module/getresponse');
		$contacts = $this->model_extension_module_getresponse->getContacts();
		$this->campaign = $this->request->post['campaign'];
		$gr_campaign = array();
		$campaigns = $this->getCampaigns();

		if (!empty($campaigns)) {
			foreach ($campaigns as $campaign) {
				if ($campaign->campaignId === $this->campaign) {
					$gr_campaign = $campaign;
				}
			}
		}

		if (empty($gr_campaign)) {
			$results = array('status' => 2, 'response' => 'There is no campaign with the specified name.');
		} else {
			$duplicated = 0;
			$queued = 0;
			$contact = 0;
			$not_added = 0;

			foreach ($contacts as $row) {
				$customs = array();
				$customs[] = array('customFieldId' => $this->getCustomFieldId('origin'), 'value' => array('opencart'));

				foreach ($this->allow_fields as $af) {
					$custom_field_id = $this->getCustomFieldId($af);
					if (!empty($row[$af]) && $custom_field_id !== false) {
						$customs[] = array('customFieldId' => $custom_field_id, 'value' => array($row[$af]));
					}
				}

				$grContact = $this->get_response->getContacts(
						array('query' => array('campaignId' => $gr_campaign->campaignId, 'email' => $row['email']))
				);

				if (isset($this->request->post['cycle_days'])) {
					$cycle_day = $this->request->post['cycle_days'];
				} else {
					$cycle_day = (!empty($grContact) && !empty($grContact->dayOfCycle)) ? $grContact->dayOfCycle : 0;
				}

				$params = array(
						'name' => $row['firstname'] . ' ' . $row['lastname'],
						'email' => $row['email'],
						'dayOfCycle' => $cycle_day,
						'campaign' => array('campaignId' => $gr_campaign->campaignId),
						'customFieldValues' => $customs
				);

				if (!empty($row['ip'])) {
					$params['ipAddress'] = $row['ip'];
				}

				try {
					$r = $this->get_response->addContact($params);
					if (is_object($r) && empty($r)) {
						$queued++;
					} elseif (is_object($r) && $r->code == 1008) {
						$duplicated++;
					} else {
						$not_added++;
					}

					$contact++;
				} catch (Exception $e) {
					$not_added++;
				}
			}

			$results = array(
					'status' => 1,
					'response' => 'All export completed. Contacts: ' . $contact . '. Queued: ' . $queued . '. Updated: ' .
							$duplicated . '. Not added (Contact already queued): ' . $not_added . '.'
			);
		}

		$this->response->setOutput(json_encode($results));
	}

    /*****************Function getCustomFieldId**********************************
    * @type            : Function
    * @function name   : getCustomFieldId
    * @description     : Get Custom Field Id   
    *                    
    * @param           : Name
    * @return          : Array
    * ********************************************************** */
	private function getCustomFieldId($name) {
		if (empty($this->custom_fields)) {
			$custom_fields = $this->get_response->getCustomFields();

			if (!empty($custom_fields)) {
				foreach ($custom_fields as $field) {
					if (isset($field->customFieldId)) {
						$this->custom_fields[strtolower($field->name)] = $field->customFieldId;
					}
				}
			}
		}

		if (isset($this->custom_fields[$name])) {
			return $this->custom_fields[$name];
		}

		$newCustom = array('name' => $name, 'type' => 'text', 'hidden' => false, 'values' => array());

		$result = $this->get_response->setCustomField($newCustom);

		if (!empty($this->custom_fields) && isset($result->customFieldId)) {
			$this->custom_fields[$result->name] = $result->customFieldId;
			return $result->customFieldId;
		}
		return false;
	}


    /*****************Function getCampaigns**********************************
    * @type            : Function
    * @function name   : getCampaigns
    * @description     : Get Campaings Data  
    *                    
    * @param           : null
    * @return          : Array
    * ********************************************************** */
	private function getCampaigns()
	{
		if (empty($this->gr_apikey)) {
			return array();
		}

		if (!empty($this->campaigns)) {
			return $this->campaigns;
		}

		$this->campaigns = $this->get_response->getCampaigns();

		if (isset($this->campaigns->httpStatus) && $this->campaigns->httpStatus != 200) {
			$this->session->data['error_warning'] = $this->campaigns->codeDescription;
			$this->campaigns = array();
		}

		return $this->campaigns;
	}
   /*****************Function install**********************************
    * @type            : Function
    * @function name   : install
    * @description     : Install module  
    *                    
    * @param           : null
    * @return          : null
    * ********************************************************** */
	public function install() {
		$this->load->model('setting/event');
		$this->model_setting_event->addEvent('bangloss_getresponse', 'post.customer.add', 'extension/module/bangloss_getresponse/on_customer_add');
	}

    /*****************Function uninstall**********************************
    * @type            : Function
    * @function name   : uninstall
    * @description     : Uninstall module  
    *                    
    * @param           : null
    * @return          : null
    * ********************************************************** */

	public function uninstall() {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEvent('BanglossGetresponse');
	}

    /*****************Function on_customer_add**********************************
    * @type            : Function
    * @function name   : on_customer_add
    * @description     : Add Customer Info  
    *                    
    * @param           : Customer ID
    * @return          : Boolean
    * ********************************************************** */
	public function on_customer_add($customer_id) {
		$this->load->model('customer/customer');
		$customer = $this->model_customer_customer->getCustomer($customer_id);
		$settings = $this->config->get('module_getresponse_reg');

		if ($settings['sequence_active'] == 0 || $customer['newsletter'] == 0) {
			return true;
		}

		$customs = array();
		$customs[] = array('customFieldId' => $this->getCustomFieldId('origin'), 'value' => array('opencart'));

		foreach ($this->allow_fields as $af) {
			if (!empty($row[$af])) {
				$customs[] = array('customFieldId' => $this->getCustomFieldId($af), 'value' => array($customer[$af]));
			}
		}

		$params = array(
				'name' => $customer['firstname'] . ' ' . $customer['lastname'],
				'email' => $customer['email'],
				'campaign' => array('campaignId' => $settings['campaign']),
				'customFieldValues' => $customs
		);

		if (!empty($row['ip'])) {
			$params['ipAddress'] = $row['ip'];
		}

		if (isset($settings['sequence_active']) && $settings['sequence_active'] == 1 && isset($settings['day'])) {
			$params['dayOfCycle'] = (int)$settings['day'];
		}

		$this->get_response->addContact($params);

		return true;
	}
}
