<?php

/*
=====================================================
 Social login PRO
-----------------------------------------------------
 http://www.intoeetive.com/
-----------------------------------------------------
 Copyright (c) 2011-2012 Yuri Salimovskiy
=====================================================
 This software is intended for usage with
 ExpressionEngine CMS, version 2.0 or higher
=====================================================
 File: mcp.social_login_pro.php
-----------------------------------------------------
 Purpose: Integration of EE membership with social networks
=====================================================
*/

if ( ! defined('BASEPATH'))
{
    exit('Invalid file request');
}

require_once PATH_THIRD.'social_login_pro/config.php';

class Social_login_pro_mcp {

    public $version = SOCIAL_LOGIN_PRO_ADDON_VERSION;
    
    public $settings = array();
    
    public $docs_url = "http://www.intoeetive.com/docs/social_login_pro.html";
    
    public function __construct() { 
        // Make a local reference to the ExpressionEngine super object 
        $query = ee()->db->query("SELECT settings FROM exp_modules WHERE module_name='Social_login_pro' LIMIT 1");
        $this->settings = unserialize($query->row('settings')); 
        ee()->lang->loadfile('shorteen');
        ee()->lang->loadfile('social_login_pro');
        
        if (version_compare(APP_VER, '2.6.0', '>='))
        {
        	ee()->view->cp_page_title = lang('social_login_pro_module_name');
        }
        else
        {
        	ee()->cp->set_variable('cp_page_title', lang('social_login_pro_module_name'));
        }
    }

    public function index()
    {
        ee()->load->helper('form');
    	ee()->load->library('table');  
        ee()->load->library('javascript');
        
        $providers_view = '';
        $providers = array();
        
        foreach(scandir(PATH_THIRD.'social_login_pro/libraries/') as $file) {
            if (is_file(PATH_THIRD.'social_login_pro/libraries/'.$file)) 
            {
                $providers[] = str_replace("_oauth.php", "", $file);
            }
        }
        
        $outputjs = "
            $(\".editAccordion\").css(\"borderTop\", $(\".editAccordion\").css(\"borderBottom\")); 
            $(\".editAccordion h3\").click(function() {
                if ($(this).hasClass(\"collapsed\")) { 
                    $(this).siblings().slideDown(\"fast\"); 
                    $(this).removeClass(\"collapsed\").parent().removeClass(\"collapsed\"); 
                } else { 
                    $(this).siblings().slideUp(\"fast\"); 
                    $(this).addClass(\"collapsed\").parent().addClass(\"collapsed\"); 
                }
            }); 
        ";
        
        $custom_fields = array();
        $custom_fields[''] = '';
        ee()->db->select('m_field_id, m_field_label');
        ee()->db->order_by('m_field_order', 'asc');
        $q = ee()->db->get('exp_member_fields');
        foreach ($q->result() as $obj)
        {
            $custom_fields[$obj->m_field_id] = $obj->m_field_label;
        }        
        
        foreach ($providers as $provider)
        {
            $data['empty'] = (isset($this->settings[ee()->config->item('site_id')][$provider]['app_id']) && $this->settings[ee()->config->item('site_id')][$provider]['app_id']!='')?false:true;
            $data['name'] = lang($provider);
            $data['docs_url'] = $this->docs_url."#".$provider;
            $data['app_register_url'] = lang($provider.'_app_register_url');
            
            $data['fields'] = array(	
                0 => array(
                        'label'=>lang($provider.'_app_id'),
                        'subtext'=>lang($provider.'_app_id_subtext'),
                        'field'=>form_input("app_id[$provider]", (isset($this->settings[ee()->config->item('site_id')][$provider]['app_id'])?$this->settings[ee()->config->item('site_id')][$provider]['app_id']:''), 'style="width: 80%"')
                    ),
                1 => array(
                        'label'=>lang($provider.'_app_secret'),
                        'subtext'=>lang($provider.'_app_secret_subtext'),
                        'field'=>form_input("app_secret[$provider]", (isset($this->settings[ee()->config->item('site_id')][$provider]['app_secret'])?$this->settings[ee()->config->item('site_id')][$provider]['app_secret']:''), 'style="width: 80%"')
                    ),
                2 => array(
                        'label'=>lang($provider.'_custom_field'),
                        'subtext'=>lang($provider.'_custom_field_subtext'),
                        'field'=>form_dropdown("custom_field[$provider]", $custom_fields, (isset($this->settings[ee()->config->item('site_id')][$provider]['custom_field'])?$this->settings[ee()->config->item('site_id')][$provider]['custom_field']:''))
                    )
            );
            if (in_array($provider, array('twitter', 'facebook', 'linkedin', 'yahoo', 'appdotnet', 'google')))
            {
                $data['fields'][3] = array(
                        'label'=>lang($provider.'_enable_posts'),
                        'subtext'=>lang($provider.'_enable_posts_subtext'),
                        'field'=>form_checkbox("enable_posts[$provider]", 'y', (isset($this->settings[ee()->config->item('site_id')][$provider]['enable_posts']) && $this->settings[ee()->config->item('site_id')][$provider]['enable_posts']=='n')?false:true)
                    );
            }
      		
            if (in_array($provider, array('twitter', 'instagram', 'appdotnet')))
            {
                $data['fields'][4] = array(
                        'label'=>lang($provider.'_follow_username'),
                        'subtext'=>lang($provider.'_follow_username_subtext'),
                        'field'=>form_input("follow_username[$provider]", (isset($this->settings[ee()->config->item('site_id')][$provider]['follow_username'])?$this->settings[ee()->config->item('site_id')][$provider]['follow_username']:''))
                    );
            }
            $providers_view .= ee()->load->view('provider', $data, TRUE);
        }
        
        $vars = array();
        $vars['providers'] = $providers_view;

        $theme_folder_path = PATH_THIRD_THEMES .'social_login/';
        
        $icon_sets = array();
        foreach(scandir($theme_folder_path) as $dir) {
            if (substr($dir, 0, 1)!='.' && is_dir($theme_folder_path.$dir)) 
            {
                $icon_sets[$dir] = $dir;
            }
        }
        
        $act = ee()->db->query("SELECT action_id FROM exp_actions WHERE class='Social_login_pro' AND method='request_token'");
        $vars['settings']['act_value']	= $act->row('action_id');
        
        $act = ee()->db->query("SELECT action_id FROM exp_actions WHERE class='Social_login_pro' AND method='access_token'");
        $access_token_url = trim(ee()->config->item('site_url'), '/').'/?ACT='.$act->row('action_id');
        $vars['settings']['callback_uri']	= $access_token_url;
        
        $act = ee()->db->query("SELECT action_id FROM exp_actions WHERE class='Social_login_pro' AND method='access_token_loggedin'");
        $access_token_url = trim(ee()->config->item('site_url'), '/').'/?ACT='.$act->row('action_id');
        $vars['settings']['callback_uri_loggedin']	= $access_token_url;
        
        $vars['settings']['prevent_duplicate_assoc'] = form_checkbox('prevent_duplicate_assoc', 'y', (isset($this->settings[ee()->config->item('site_id')]['prevent_duplicate_assoc'])?$this->settings[ee()->config->item('site_id')]['prevent_duplicate_assoc']:false));
        
        $member_groups = array();
        ee()->db->select('group_id, group_title');
        ee()->db->where('group_id NOT IN (1,2,4)');
        $q = ee()->db->get('member_groups');
        foreach ($q->result() as $obj)
        {
            $member_groups[$obj->group_id] = $obj->group_title;
        }
        $vars['settings']['member_group']	= form_dropdown('member_group', $member_groups, (isset($this->settings[ee()->config->item('site_id')]['member_group'])?$this->settings[ee()->config->item('site_id')]['member_group']:''));
        
        $vars['settings']['force_pending_if_no_email']	= form_checkbox('force_pending_if_no_email', 'y', (isset($this->settings[ee()->config->item('site_id')]['force_pending_if_no_email'])?$this->settings[ee()->config->item('site_id')]['force_pending_if_no_email']:false));
        
        $vars['settings']['email_is_username']	= form_checkbox('email_is_username', 'y', (isset($this->settings[ee()->config->item('site_id')]['email_is_username'])?$this->settings[ee()->config->item('site_id')]['email_is_username']:false));
        
        $statuses = ee('Model')->get('Status')->all();
		
		$statuses = array();
		$statuses['open'] = lang('open');
		$statuses['closed'] = lang('closed');

        foreach ($statuses as $status) {
            $status_name = ($status == 'open' OR $status == 'closed') ? lang($status) : $status;
            $statuses[$status] = $status_name;
        }
		
		$selected_statuses = (isset($this->settings[ee()->config->item('site_id')]['trigger_statuses'])?$this->settings[ee()->config->item('site_id')]['trigger_statuses']:array('open'));

		$vars['settings']['trigger_statuses']	= '';
		foreach ($statuses as $status=>$lang)
		{
			$vars['settings']['trigger_statuses'] .= form_checkbox('trigger_statuses[]', $status, in_array($status, $selected_statuses)).NBS.NBS.$lang.BR.BR;
		}
        
        $vars['settings']['icon_set']	= form_dropdown('icon_set', $icon_sets, (isset($this->settings[ee()->config->item('site_id')]['icon_set'])?$this->settings[ee()->config->item('site_id')]['icon_set']:'bar'));
        
        $url_shortening_services = array(
                                    'googl'=>lang('googl'),
                                    'isgd'=>lang('isgd'),
                                    'bitly'=>lang('bitly'),
                                    'yourls'=>lang('yourls'),
                                    'lessn-more'=>lang('lessn-more'),
                                    'cloud-app'=>lang('cloud-app')
                                );
        
        $vars['settings']['url_shortening_service']	= form_dropdown('url_shortening_service', $url_shortening_services, (isset($this->settings[ee()->config->item('site_id')]['url_shortening_service'])?$this->settings[ee()->config->item('site_id')]['url_shortening_service']:'googl'));
        
        $act = ee()->db->query("SELECT action_id FROM exp_actions WHERE class='Shorteen' AND method='process'");
        $shotren_url = trim(ee()->config->item('site_url'), '/').'/?ACT='.$act->row('action_id');
        
        $shorteen_settings_q = ee()->db->select('settings')->from('modules')->where('module_name', 'Shorteen')->limit(1)->get();
        $shorteen_settings = unserialize($shorteen_settings_q->row('settings'));
        $secret = (isset($shorteen_settings['shorteen_secret']))?$shorteen_settings['shorteen_secret']:'';
        
        $outputjs .= "
            ts = new Date();
            $('.shortening_reveal').click(function(){
                $('#shorturl').html('');
                $('#shortening_test_table').toggle('slow');
                return false;
            });
            $('#test_shortening').click(function(){
                $('#shorturl').html('<img src=\"".ee()->config->item('theme_folder_url')."/cp_global_images/indicator.gif\" alt=\"please wait\" />');
                $.get('$shotren_url', {
                        'service'   : $('select[name=url_shortening_service]').val(),
                        'url'       : encodeURIComponent($('input[name=long_url]').val()),
                        'secret'	: '$secret',
                        'ts'        : ts.getTime()
                    }, function(msg) {
                        $('#shorturl').html('<a href=\"'+msg+'\">'+msg+'</a>');
                    }
                );
                return false;
            });
        ";
        $vars['shortening_test_table'] = array(
                                    lang('long_url').' '.form_input('long_url', ee()->config->item('site_url'), 'style="width: 100%"'),
                                    '<div id="shorturl" style="width: 10em"></div>',
                                    '<a href="#" class="submit" id="test_shortening">'.lang('test_shortening').'</a>'
        );
        
        $vars['settings']['custom_profile_fields']	= '';
        $vars['settings']['full_name']	= form_dropdown('full_name', $custom_fields, (isset($this->settings[ee()->config->item('site_id')]['full_name'])?$this->settings[ee()->config->item('site_id')]['full_name']:''));
        $vars['settings']['first_name']	= form_dropdown('first_name', $custom_fields, (isset($this->settings[ee()->config->item('site_id')]['first_name'])?$this->settings[ee()->config->item('site_id')]['first_name']:''));
        $vars['settings']['last_name']	= form_dropdown('last_name', $custom_fields, (isset($this->settings[ee()->config->item('site_id')]['last_name'])?$this->settings[ee()->config->item('site_id')]['last_name']:''));
        $vars['settings']['gender']	= form_dropdown('gender', $custom_fields, (isset($this->settings[ee()->config->item('site_id')]['gender'])?$this->settings[ee()->config->item('site_id')]['gender']:''));
        
        
        ee()->javascript->output(str_replace(array("\n", "\t"), '', $outputjs));

        return array(
            'heading'       => 'Settings',
            'body'          => ee()->load->view('settings', $vars, true),
            'breadcrumb'    => array(
                ee('CP/URL', 'addons/settings/social_login_pro')->compile() => 'Social Login Pro'
            )
        );
    }

    public function save_settings()
    {

        ee()->load->library('table');  
        
        $site_id = ee()->config->item('site_id');
        
        foreach(scandir(PATH_THIRD.'social_login_pro/libraries/') as $file) {
            if (is_file(PATH_THIRD.'social_login_pro/libraries/'.$file)) 
            {
                $providers[] = str_replace("_oauth.php", "", $file);
            }
        }
        
        $settings = array();
        ee()->db->select('site_id')
                    ->from('sites');
        $q = ee()->db->get();
        foreach ($q->result() as $obj)
        {
            if (isset($this->settings[$obj->site_id]))
            {
                foreach ($this->settings[$obj->site_id] as $key=>$value)
                {
                    $settings[$obj->site_id][$key] = $value;
                }
            }
        }
        
        $custom_field_used = array();

        foreach ($providers as $provider)
        {
            $settings[$site_id][$provider]['app_id'] = trim($_POST["app_id"]["$provider"]);
            $settings[$site_id][$provider]['app_secret'] = trim($_POST["app_secret"]["$provider"]);
            $settings[$site_id][$provider]['custom_field'] = $_POST["custom_field"]["$provider"];
            if (isset($_POST["follow_username"]["$provider"]))
            {
                $settings[$site_id][$provider]['follow_username'] = $_POST["follow_username"]["$provider"];
            }
            $settings[$site_id][$provider]['enable_posts'] = (isset($_POST["enable_posts"]["$provider"]) && $_POST["enable_posts"]["$provider"]=='y')?'y':'n';
            
            if ( ($settings[$site_id][$provider]['app_id']!=''||$settings[$site_id][$provider]['app_secret']!=''||$settings[$site_id][$provider]['custom_field']!='') )
            {
                $custom_field_used[] = $_POST["custom_field"]["$provider"];
            }
            if ( ($settings[$site_id][$provider]['app_id']!=''||$settings[$site_id][$provider]['app_secret']!=''||$settings[$site_id][$provider]['custom_field']!='') && ($settings[$site_id][$provider]['app_id']==''||$settings[$site_id][$provider]['app_secret']==''||$settings[$site_id][$provider]['custom_field']=='') )
            {
                ee()->session->set_flashdata('message_failure', ee()->lang->line('provide_all_settings_for').' '.ee()->lang->line($provider));    
                ee()->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=social_login_pro');
                return;  
            }
        }
        
        $custom_field_used_uniq = array_unique($custom_field_used); 
        if(count($custom_field_used_uniq) != count($custom_field_used)) 
        {
            ee()->session->set_flashdata('message_failure', ee()->lang->line('cannot_use_duplicate_custom_fields'));    
            ee()->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=social_login_pro');
            return;    
        }
        
        $settings[$site_id]['member_group'] = $_POST["member_group"];
        $settings[$site_id]['full_name'] = $_POST["full_name"];
        $settings[$site_id]['first_name'] = $_POST["first_name"];
        $settings[$site_id]['last_name'] = $_POST["last_name"];
        $settings[$site_id]['gender'] = $_POST["gender"];
        $settings[$site_id]['icon_set'] = $_POST["icon_set"];
        $settings[$site_id]['url_shortening_service'] = $_POST["url_shortening_service"];
        $settings[$site_id]['trigger_statuses'] = $_POST["trigger_statuses"];
        $settings[$site_id]['prevent_duplicate_assoc'] = (isset($_POST["prevent_duplicate_assoc"])&&$_POST["prevent_duplicate_assoc"]=='y')?true:false;
        $settings[$site_id]['force_pending_if_no_email'] = (isset($_POST["force_pending_if_no_email"])&&$_POST["force_pending_if_no_email"]=='y')?true:false;
        $settings[$site_id]['email_is_username'] = (isset($_POST["email_is_username"])&&$_POST["email_is_username"]=='y')?true:false;
        
        ee()->db->where('module_name', 'Social_login_pro');
        ee()->db->update('modules', array('settings' => serialize($settings)));
        
        ee()->session->set_flashdata('message_success', ee()->lang->line('preferences_updated'));  
        ee()->functions->redirect(ee('CP/URL', 'addons/settings/social_login_pro'));      
        
    }

    public function templates()
    {
    	$site_id = ee()->config->item('site_id');
        
        ee()->load->helper('form');
    	ee()->load->library('table');

    	$vars = array();
                              
        $tmpls = array('entry_submission_absolute_end', 'insert_comment_end', 'forum_submit_post_end', 'member_member_register');
        foreach ($tmpls as $tmpl)
        {
            ee()->db->select('template_id, enable_template, template_data')
                        ->from('social_login_templates')
                        ->where('site_id', $site_id)
                        ->where('template_name', $tmpl)
                        ->limit(1);
            $q = ee()->db->get();
            if ($q->num_rows > 0)
            {
                $enable_template = $q->row('enable_template');
                $template_data = $q->row('template_data');
                $template_id = $q->row('template_id');
            }
            else
            {
                $enable_template = 'y';
                $template_data = ee()->lang->line($tmpl.'_tmpl');
                $template_id = '';
            }
            $vars['data'][$tmpl] = array(	
                'template_data'	=> form_textarea($tmpl, $template_data).form_hidden("id[$tmpl]", $template_id),
                'enable_template'	=> form_checkbox("enable[$tmpl]", 'y', ($enable_template=='y')?true:false).' '.lang('enable_template')
        		);
        }
        
    	return ee()->load->view('templates', $vars, TRUE);
	
    }

    public function save_templates()
    {
    	$site_id = ee()->config->item('site_id');
   
        $tmpls = array('entry_submission_absolute_end', 'insert_comment_end', 'forum_submit_post_end', 'member_member_register');

        if (!empty($_POST))
        {
            foreach ($tmpls as $tmpl)
            {
                $data['template_data'] = ee()->input->post($tmpl);
                $data['enable_template'] = (isset($_POST["enable"]["$tmpl"]) && $_POST["enable"]["$tmpl"]=='y')?'y':'n';
                if (isset($_POST["id"]["$tmpl"]) && $_POST["id"]["$tmpl"]!='')
                {
                    ee()->db->where('template_id', ee()->security->xss_clean($_POST["id"]["$tmpl"]));
                    ee()->db->update('social_login_templates', $data);
                }
                else
                {
                    $data['site_id'] = $site_id;
                    $data['template_name'] = $tmpl;
                    ee()->db->insert('social_login_templates', $data);
                }
                
            }
        }
        
        ee()->session->set_flashdata('message_success', ee()->lang->line('updated'));
        
        ee()->functions->redirect(ee('CP/URL', 'addons/settings/social_login_pro/templates'));
	
    }
}
/* END */
?>