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
 File: mod.social_login_pro.php
-----------------------------------------------------
 Purpose: Integration of EE membership with social networks
=====================================================
*/


if ( ! defined('BASEPATH'))
{
    exit('Invalid file request');
}

require_once PATH_THIRD.'social_login_pro/config.php';

class Social_login_pro {

    public $return_data	= ''; // Bah!
    
    public $settings = array();
    
    public $providers = array('twitter', 'facebook', 'linkedin', 'yahoo', 'google', 'vkontakte', 'instagram', 'appdotnet', 'windows', 'edmodo');//, 'weibo');
    
    public $social_login = array();

    /** ----------------------------------------
    /**  Constructor
    /** ----------------------------------------*/

    public function __construct()
    {        
        ee()->lang->loadfile('login');
        ee()->lang->loadfile('member');
        ee()->lang->loadfile('social_login_pro');
        $query = ee()->db->query("SELECT settings FROM exp_modules WHERE module_name='Social_login_pro' LIMIT 1");
        if ($query->num_rows()>0) $this->settings = unserialize($query->row('settings')); 
    }
    /* END */

    public function form()
    {
        if (empty($this->settings)) return ee()->TMPL->no_results();
		                    
        $site_id = ee()->session->userdata('site_id');
        $data['hidden_fields']['ACT'] = ee()->functions->fetch_action_id('Social_login_pro', 'request_token');            
		$data['id']		= (ee()->TMPL->fetch_param('id')!='') ? ee()->TMPL->fetch_param('id') : 'social_login_form';
        $data['name']		= (ee()->TMPL->fetch_param('name')!='') ? ee()->TMPL->fetch_param('name') : 'social_login_form';
        $data['class']		= (ee()->TMPL->fetch_param('class')!='') ? ee()->TMPL->fetch_param('class') : 'social_login_form';
        
        if (ee()->TMPL->fetch_param('group_id')!='')
        {
            ee()->load->library('encrypt');
            $data['hidden_fields']['group_id'] = ee()->encrypt->encode(ee()->TMPL->fetch_param('group_id'));
        }

        if (ee()->TMPL->fetch_param('return')=='')
        {
            $return = ee()->functions->fetch_site_index();
        }
        else if (ee()->TMPL->fetch_param('return')=='SAME_PAGE')
        {
            $return = ee()->functions->fetch_current_uri();
        }
        else if (strpos(ee()->TMPL->fetch_param('return'), "http://")!==FALSE || strpos(ee()->TMPL->fetch_param('return'), "https://")!==FALSE)
        {
            $return = ee()->TMPL->fetch_param('return');
        }
        else
        {
            $return = ee()->functions->create_url(ee()->TMPL->fetch_param('return'));
        }

        $data['hidden_fields']['RET'] = $return;
        
        if (ee()->TMPL->fetch_param('no_email_return')=='')
        {
            $data['hidden_fields']['no_email_return'] = $return;
        }
        else if (ee()->TMPL->fetch_param('no_email_return')=='SAME_PAGE')
        {
            $data['hidden_fields']['no_email_return'] = ee()->functions->fetch_current_uri();
        }
        else if (strpos(ee()->TMPL->fetch_param('no_email_return'), "http://")!==FALSE || strpos(ee()->TMPL->fetch_param('no_email_return'), "https://")!==FALSE)
        {
            $data['hidden_fields']['no_email_return'] = ee()->TMPL->fetch_param('no_email_return');
        }
        else
        {
            $data['hidden_fields']['no_email_return'] = ee()->functions->create_url(ee()->TMPL->fetch_param('no_email_return'));
        }
        
        
        if (ee()->TMPL->fetch_param('new_member_return')!='')
        {
            if (ee()->TMPL->fetch_param('new_member_return')=='SAME_PAGE')
            {
                $data['hidden_fields']['new_member_return'] = ee()->functions->fetch_current_uri();
            }
            else if (strpos(ee()->TMPL->fetch_param('new_member_return'), "http://")!==FALSE || strpos(ee()->TMPL->fetch_param('new_member_return'), "https://")!==FALSE)
            {
                $data['hidden_fields']['new_member_return'] = ee()->TMPL->fetch_param('new_member_return');
            }
            else
            {
                $data['hidden_fields']['new_member_return'] = ee()->functions->create_url(ee()->TMPL->fetch_param('new_member_return'));
            }
        }
        
        
        if (strpos(ee()->TMPL->fetch_param('callback_uri'), "http://")!==FALSE || strpos(ee()->TMPL->fetch_param('callback_uri'), "https://")!==FALSE)
        {
            $data['hidden_fields']['callback_uri'] = ee()->TMPL->fetch_param('callback_uri');
        }
        else if (ee()->TMPL->fetch_param('callback_uri')!='')
        {
            $data['hidden_fields']['callback_uri'] = ee()->functions->create_url(ee()->TMPL->fetch_param('callback_uri'));
        }      
        
        if (ee()->TMPL->fetch_param('secure_action')=='yes')
        {
            $data['hidden_fields']['secure_action'] = 'yes';
        } 
        
        
        
        $providers_list = (ee()->TMPL->fetch_param('providers')!='') ? explode('|', ee()->TMPL->fetch_param('providers')) : array();
        
        $tagdata = ee()->TMPL->tagdata;
        
        $icon_set = (ee()->TMPL->fetch_param('icon_set')!='') ? ee()->TMPL->fetch_param('icon_set') : $this->settings[$site_id]['icon_set'];
        
        $theme_folder_path = PATH_THIRD_THEMES . 'social_login/';

        if (!is_dir($theme_folder_path.$icon_set))
        {
            $icon_set = $this->settings[$site_id]['icon_set'];
        }
        
        if (preg_match_all("/".LD."providers.*?(backspace=[\"|'](\d+?)[\"|'])?".RD."(.*?)".LD."\/providers".RD."/s", $tagdata, $matches))
		{
            $providers = array();
        
            foreach($this->providers as $provider) 
            {
                if (empty($providers_list) || in_array($provider, $providers_list))
                {
                    $providers[] = $provider;
                }
            }

            $out = '';
            $chunk = $matches[3][0];
            
            $theme_folder_url = PATH_THIRD_THEMES . 'social_login/';

            foreach ($providers as $provider)
            {
                if (isset($this->settings[$site_id]["$provider"]) && $this->settings[$site_id]["$provider"]['app_id']!='' && $this->settings[$site_id]["$provider"]['app_secret']!='' && $this->settings[$site_id]["$provider"]['custom_field']!='')
                {
                    
                    $parsed_chunk = $chunk;
                    $parsed_chunk = ee()->TMPL->swap_var_single('provider_name', $provider, $parsed_chunk);
                    $parsed_chunk = ee()->TMPL->swap_var_single('provider_title', lang($provider), $parsed_chunk);
                    $parsed_chunk = ee()->TMPL->swap_var_single('provider_icon', $theme_folder_url.$icon_set.'/'.$provider.'.png', $parsed_chunk);
                    if (ee()->session->userdata('member_id')==0)
                    {
                        $out .= $parsed_chunk;
                    }
                    else
                    {
                        $fieldname = 'm_field_id_'.$this->settings[$site_id]["$provider"]['custom_field'];
                        ee()->db->select($fieldname)
                            ->from('member_data')
                            ->where('member_id', ee()->session->userdata('member_id'))
                            ->limit(1);
                        $query = ee()->db->get();
                        if ($query->row($fieldname)=='')
                        {
                            $out .= $parsed_chunk;
                        }
                    }
                    
                }
            }
            $tagdata = str_replace($matches[0][0], $out, $tagdata);
            
            if ($matches[2][0]!='')
			{
				$tagdata = substr( trim($tagdata), 0, -$matches[2][0]);
			}
		}       
        
        if (ee()->TMPL->fetch_param('popup')=='yes')
        {
            $tagdata .= "<script type=\"text/javascript\">
var myForm = document.getElementById('".$data['id']."');
myForm.onsubmit = function() {
    var w = window.open('about:blank','SocialLoginPopup','toolbar=0,statusbar=0,menubar=0,width=800,height=600');
    this.target = 'SocialLoginPopup';
};
</script>
            ";    
            $data['hidden_fields']['popup'] = 'y';
        }        

        return ee()->functions->form_declaration($data).$tagdata."\n"."</form>";
	}

    public function request_token($provider='')
    {        
		
        @session_start();
        $session_id = session_id();

		$is_popup = (ee()->input->get_post('popup')=='y')?true:false;
        
        $site_id = ee()->session->userdata('site_id');
        
        if ($provider=='')
        {
            $provider = ee()->input->get_post('provider');
        }
        
        if ($provider=='')
        {
            $this->_show_error('general', lang('no_service_provider'), $is_popup);
            return;
        }
        
        if (!file_exists(PATH_THIRD.'social_login_pro/libraries/'.$provider.'_oauth.php'))
        {
            $this->_show_error('general', lang('provider_file_missing'), $is_popup);
            return;
        }

        //if one of the settings is empty, we can't proceed
        if ($this->settings[$site_id]["$provider"]['app_id']=='' || $this->settings[$site_id]["$provider"]['app_secret']=='' || $this->settings[$site_id]["$provider"]['custom_field']=='')
        {
            $this->_show_error('general', lang('please_provide_settings_for').' '.$providers["$provider"]['name'], $is_popup);
            return;
        }

        $this->social_login['provider'] = $provider;
        $this->social_login['auto_login'] = ee()->input->get_post('auto_login');
        $this->social_login['return'] = (ee()->input->get_post('RET')!='')?ee()->input->get_post('RET'):ee()->functions->fetch_site_index();
        $this->social_login['no_email_return'] = (ee()->input->get_post('no_email_return')!='')?ee()->input->get_post('no_email_return'):$this->social_login['return'];
        $this->social_login['new_member_return'] = (ee()->input->get_post('new_member_return')!='')?ee()->input->get_post('new_member_return'):$this->social_login['return'];
		$this->social_login['anon'] = ee()->input->get_post('anon');
        $this->social_login['group_id'] = ee()->input->get_post('group_id');
        $this->social_login['is_popup'] = $is_popup;
        $this->social_login['secure_action'] = ee()->input->get_post('secure_action');
        
        if (ee()->input->get_post('callback_uri')!='')
        {
            $this->social_login['callback_uri'] = ee()->input->get_post('callback_uri');
        }
        
        $this->_save_session_data($this->social_login, $session_id);
        
        if (ee()->session->userdata['member_id']!=0)
        {
            $act = ee()->db->query("SELECT action_id FROM exp_actions WHERE class='Social_login_pro' AND method='access_token_loggedin'");
        }
        else
        {
            $act = ee()->db->query("SELECT action_id FROM exp_actions WHERE class='Social_login_pro' AND method='access_token'");
        }
        
        if (ee()->input->get_post('callback_uri')!='')
        {
            $access_token_url = $this->social_login['callback_uri'];
        }
        else
        {
            $access_token_url = trim(ee()->config->item('site_url'), '/').'/?ACT='.$act->row('action_id');
            if (!in_array($provider, array('google', 'linkedin', 'yahoo', 'facebook')))
            {
                $access_token_url .= '&sid='.$session_id;
            }
        }
        
        if (ee()->input->get_post('secure_action')=='yes')
        {
            if (strpos($access_token_url, '//')===0)
            {
                $access_token_url = 'https:'.$access_token_url;
            }
            else
            {
                $access_token_url = str_replace('http://', 'https://', $access_token_url);
            }
        }

        //do we need publish permissions?
        $will_post = false;
        if (!isset($this->settings[$site_id][$provider]['enable_posts']) || $this->settings[$site_id][$provider]['enable_posts']=='y')
        {
            $will_post = true;
        }
        
        if ($provider=='facebook')
        {
            require_once PATH_THIRD.'social_login_pro/facebook-sdk/facebook.php';
            
            $fb_config = array();
            $fb_config['appId'] = $this->settings[$site_id]["$provider"]['app_id'];
            $fb_config['secret'] = $this->settings[$site_id]["$provider"]['app_secret'];
            
            $facebook = new Facebook($fb_config);
            
            // Modified Dec 2018 Gil Hop Studios    
            $scope = "public_profile,email"; //,user_about_me,user_status
            if ($will_post==true) $scope .= ",publish_actions";
            
            $params = array(
              'scope' => $scope,
              'redirect_uri' => $access_token_url
            );
            
            $loginUrl = $facebook->getLoginUrl($params);
            // Using state as the session data by Gilbert 2018-02-08    
            $this->_save_session_data($this->social_login, $facebook->getState());

            header("Location: $loginUrl");
            exit();
            
        }
        
        $params = array('key'=>$this->settings[$site_id]["$provider"]['app_id'], 'secret'=>$this->settings[$site_id]["$provider"]['app_secret']);

        $lib = $provider.'_oauth';
        ee()->load->library($lib, $params);
        
        
        $response = ee()->$lib->get_request_token($access_token_url, $will_post, $session_id, $is_popup);
        
        $this->social_login['token_secret'] = $response['token_secret'];
        
        $this->_save_session_data($this->social_login, $session_id);	        

        return ee()->functions->redirect($response['redirect']);
    }
 
    public function access_token()
    {
        
        if (ee()->input->get('sid')!='')
        {
            $session_id = ee()->input->get('sid');
        }
        else if (ee()->input->get('state')!='')
        {
            $session_id = ee()->input->get('state');
        }
        else
        {
            @session_start();
            $session_id = session_id();
        }
        
		$this->social_login = $this->_get_session_data($session_id);
		
        $is_popup = $this->social_login['is_popup'];
        
        $upd_data = array();
        
        if (version_compare(APP_VER, '2.2.0', '<'))
        {
            $temp_password = $upd_data['password'] = ee()->functions->hash($this->_random_string());
        }
        else
        {
            $temp_password = '';
        }
        
        
        if (ee()->input->get('multi'))
        {
            //multisite login - go on...
            return $this->_login_by_id('0', TRUE, $temp_password);
        }
        
        ee()->load->helper('url');
        
        $site_id = ee()->config->item('site_id');
        $provider = $this->social_login['provider'];

        $lib = $provider.'_oauth';
        $params = array('key'=>$this->settings[$site_id]["$provider"]['app_id'], 'secret'=>$this->settings[$site_id]["$provider"]['app_secret']);
        
        ee()->load->library($lib, $params);
        
        if ($provider=='facebook')
        {
            require_once PATH_THIRD.'social_login_pro/facebook-sdk/facebook.php';
            
            $fb_config = array();
            $fb_config['appId'] = $this->settings[$site_id]["$provider"]['app_id'];
            $fb_config['secret'] = $this->settings[$site_id]["$provider"]['app_secret'];
            
            $facebook = new Facebook($fb_config);
            
            $response = array();
            $response['access_token'] = $this->social_login['oauth_token'] = $facebook->getAccessToken();
            
        }
        else
        {
            if (in_array($provider, array('vkontakte', 'instagram', 'appdotnet', 'windows', 'google', 'edmodo', 'linkedin', 'yahoo')))
            {
                if (isset($this->social_login['callback_uri']) && $this->social_login['callback_uri']!='')
                {
                    $access_token_url = $this->social_login['callback_uri'];
                }
                else
                {
                    $act = ee()->db->query("SELECT action_id FROM exp_actions WHERE class='Social_login_pro' AND method='access_token'");
                    $access_token_url = trim(ee()->config->item('site_url'), '/').'/?ACT='.$act->row('action_id');
                    if (!in_array($provider, array('google', 'linkedin', 'yahoo')))
                    {
                        $access_token_url .= '&sid='.$session_id;
                    }
                }
                
                if (isset($this->social_login['secure_action']) && $this->social_login['secure_action']=='yes')
                {
                    if (strpos($access_token_url, '//')===0)
                    {
                        $access_token_url = 'https:'.$access_token_url;
                    }
                    else
                    {
                        $access_token_url = str_replace('http://', 'https://', $access_token_url);
                    }
                }
                
                $response = ee()->$lib->get_access_token($access_token_url, ee()->input->get('code'));
                $this->social_login['oauth_token'] = $response['access_token'];
            }
            else
            {
                $response = ee()->$lib->get_access_token(false, $this->social_login['token_secret']);
                $this->social_login['oauth_token'] = $response['oauth_token'];
                $this->social_login['oauth_token_secret'] = $response['oauth_token_secret'];
            }

            if ($provider=='yahoo')
            {
                $this->social_login['guid'] = $response['xoauth_yahoo_guid'];
            }
    
            if ($response==NULL || (isset($response['oauth_problem']) && $response['oauth_problem']!=''))
            {
                //ee()->output->show_user_error('general', array(ee()->lang->line('oauth_problem').ee()->lang->line($provider).'. '.ee()->lang->line('try_again')));
                $return = $this->social_login['return'];
                $this->_clear_session_data($session_id);
                return ee()->functions->redirect($return);
            }
        }
        
        $this->_save_session_data($this->social_login, $session_id);

        if ($provider == 'instagram')
        {
			$userdata = $response;
		}
		else
		{
			$userdata = ee()->$lib->get_user_data($response);
		}
        
        if ($userdata['custom_field']=='')
        {
            $this->_show_error('general', ee()->lang->line('oauth_problem').ee()->lang->line($provider).'. '.ee()->lang->line('try_again'), $is_popup);
            return;
        }

        //check whether member with this social ID exists
        ee()->db->select('exp_members.member_id, exp_members.email, exp_members.avatar_filename, exp_members.photo_filename')
                    ->from('exp_members')
                    ->join('exp_member_data', 'exp_members.member_id=exp_member_data.member_id', 'left')
                    ->where('m_field_id_'.$this->settings[$site_id]["$provider"]['custom_field'], $userdata['custom_field']);
        if (isset($userdata['alt_custom_field']) && $userdata['alt_custom_field']!='' && $userdata['alt_custom_field']!=$userdata['custom_field'])
        {
        	ee()->db->or_where('m_field_id_'.$this->settings[$site_id]["$provider"]['custom_field'], $userdata['alt_custom_field']);
        }
        ee()->db->limit(1);
        $query = ee()->db->get();
        if ($query->num_rows()>0)
        {
            if ($query->row('email')=='' && $userdata['email']!='')
            {
            	$upd_data['email'] = $userdata['email'];
            }
			if (!empty($upd_data))
            {
                ee()->db->where('member_id', $query->row('member_id'));
                ee()->db->update('members', $upd_data);
            }
            if (ee()->config->item('enable_avatars')=='y' && $query->row('avatar_filename')=='' && $userdata['avatar']!='')
            {
                $this->_update_avatar($query->row('member_id'), $userdata['avatar']);
            }
            if (ee()->config->item('enable_photos')=='y' && $query->row('photo_filename')=='' && $userdata['photo']!='')
            {
                $this->_update_photo($query->row('member_id'), $userdata['photo']);
            }
            $this->_store_keys($query->row('member_id'), (isset($userdata['alt_custom_field']))?$userdata['alt_custom_field']:'');
            
            return $this->_login_by_id($query->row('member_id'), FALSE, $temp_password);
        }

        //check whether member with this email address exists
        if ($userdata['email']!='')
        {
            ee()->db->select('exp_members.member_id, exp_members.avatar_filename, exp_members.photo_filename, m_field_id_'.$this->settings[$site_id]["$provider"]['custom_field'].' AS custom_field')
                        ->from('exp_members')
                        ->join('exp_member_data', 'exp_members.member_id=exp_member_data.member_id', 'left')
                        ->where('email', $userdata['email'])
                        ->limit(1);
            $query = ee()->db->get();
            if ($query->num_rows()>0)
            {
                
				if (version_compare(APP_VER, '2.2.0', '<'))
                {
                    ee()->db->where('member_id', $query->row('member_id'));
                    ee()->db->update('members', $upd_data);
                }
                if (ee()->config->item('enable_avatars')=='y' && $query->row('avatar_filename')=='' && $userdata['avatar']!='')
                {
                    $this->_update_avatar($query->row('member_id'), $userdata['avatar']);
                }
                if (ee()->config->item('enable_photos')=='y' && $query->row('photo_filename')=='' && $userdata['photo']!='')
	            {
	                $this->_update_photo($query->row('member_id'), $userdata['photo']);
	            }
                if ($query->row('custom_field')=='')
                {
                    ee()->db->where('member_id', $query->row('member_id'));
                    ee()->db->update('exp_member_data', array('m_field_id_'.$this->settings[$site_id]["$provider"]['custom_field'] => $userdata['custom_field']));
                }
                return $this->_login_by_id($query->row('member_id'), FALSE, $temp_password);
            }
        }
        
        if ( ee()->config->item('allow_member_registration') != 'y' )
		{
			$this->_show_error('general', lang('mbr_registration_not_allowed'), $is_popup);
            return;
		}
                
        if (isset($this->settings[$site_id]['email_is_username']) && $this->settings[$site_id]['email_is_username']==true && $userdata['email']!='')
        {
			$data['username']	= $userdata['email'];
		}        
        else
        {
            $data['username']	= $userdata['username'];
        }
        
        //need to make sure username is unique
        ee()->db->select('username')
                    ->from('members')
                    ->where('username', $data['username'])
                    ->limit(1);
        $q = ee()->db->get();
        if ($q->num_rows()>0)
        {
            $data['username'] = $userdata['username'].'@'.$provider;
        }
        
        $j = 1;
        do
        {
            ee()->db->select('username')
                        ->from('members')
                        ->where('username', $data['username'])
                        ->limit(1);
            $q = ee()->db->get();
            if ($q->num_rows()>0)
            {
                $data['username'] = $userdata['username'].$j;
            }
            $j++;
        } 
        while ($q->num_rows()>0);
        
        if ($userdata['email']=='' && isset($this->settings[$site_id]['force_pending_if_no_email']) && $this->settings[$site_id]['force_pending_if_no_email']==true)
        {
        	$data['group_id'] = 4; //Pending
       	}
       	else
       	{
			$data['group_id'] = (isset($this->settings[$site_id]['member_group']) && $this->settings[$site_id]['member_group']!='') ? $this->settings[$site_id]['member_group'] : ee()->config->item('default_member_group');
	        if ($this->social_login['group_id']!='')
	        {
	            ee()->load->library('encrypt');
	            $group_id = ee()->encrypt->decode($this->social_login['group_id']);
	            $member_groups = array();
	            ee()->db->select('group_id, group_title');
	            ee()->db->where('group_id NOT IN (1,2,4)');
	            $q = ee()->db->get('member_groups');
	            foreach ($q->result() as $obj)
	            {
	                $member_groups[$obj->group_id] = $obj->group_id;
	            }
	            if (in_array($group_id, $member_groups))
	            {
	                $data['group_id'] = $group_id;
	            }
	        }
   		}

		$data['password'] = '';
        $data['ip_address']  = ee()->input->ip_address();
		$data['unique_id']	= ee()->functions->random('encrypt');
		$data['join_date']	= ee()->localize->now;
		$data['email']		= $userdata['email'];
        
		$data['screen_name'] = $userdata['screen_name'];
        //need to make sure screen_name is unique
        $j = 1;
        do
        {
            ee()->db->select('screen_name')
                        ->from('members')
                        ->where('screen_name', $data['screen_name'])
                        ->limit(1);
            $q = ee()->db->get();
            if ($q->num_rows()>0)
            {
                $data['screen_name'] = $userdata['screen_name']." ".$j;
            }
            $j++;
        } 
        while ($q->num_rows()>0);
        
		$data['url']		 = prep_url($userdata['url']);
        $data['bio']         = $userdata['bio'];
        $data['occupation']  = $userdata['occupation'];
		$data['location']	 = $userdata['location'];
        if ($userdata['timezone']!='')
        {
            ee()->load->helper('date');
			$data['timezone'] = array_search($userdata['timezone'], timezones());
        }
        else
        {
            $data['timezone']	= (ee()->config->item('default_site_timezone') && ee()->config->item('default_site_timezone') != '') ? ee()->config->item('default_site_timezone') : ee()->config->item('server_timezone');
        }
        
        $data['avatar_filename'] = 'social_login/'.$provider.'.png';
        $data['avatar_width'] = '80'; 
        $data['avatar_height'] = '80';

		$data['language']	= (ee()->config->item('deft_lang')) ? ee()->config->item('deft_lang') : 'english';
		$data['time_format'] = (ee()->config->item('time_format')) ? ee()->config->item('time_format') : 'us';
		if (version_compare(APP_VER, '2.6.0', '<'))
		{
			$data['daylight_savings'] = (ee()->config->item('default_site_dst') && ee()->config->item('default_site_dst') != '') ? ee()->config->item('default_site_dst') : ee()->config->item('daylight_savings');	
		}	
        
        $data['last_visit'] = ee()->localize->now;
        $data['last_activity'] = ee()->localize->now;
		
        ee()->db->query(ee()->db->insert_string('exp_members', $data));

		$member_id = ee()->db->insert_id();

		$cust_fields['member_id'] = $member_id;
        $cust_fields['m_field_id_'.$this->settings[$site_id]["$provider"]['custom_field']] = $userdata['custom_field'];
        if (isset($this->settings[$site_id]['full_name']) && $this->settings[$site_id]['full_name']!='')
        {
            $cust_fields['m_field_id_'.$this->settings[$site_id]['full_name']] = $userdata['full_name'];
        }
        if (isset($this->settings[$site_id]['first_name']) && $this->settings[$site_id]['first_name']!='')
        {
            $cust_fields['m_field_id_'.$this->settings[$site_id]['first_name']] = $userdata['first_name'];
        }
        if (isset($this->settings[$site_id]['last_name']) && $this->settings[$site_id]['last_name']!='')
        {
            $cust_fields['m_field_id_'.$this->settings[$site_id]['last_name']] = $userdata['last_name'];
        }
        if (isset($this->settings[$site_id]['gender']) && $this->settings[$site_id]['gender']!='')
        {
            $userdata['gender'] = (in_array(strtolower($userdata['gender']), array('male','m')))?'m':'f';
            $cust_fields['m_field_id_'.$this->settings[$site_id]['gender']] = $userdata['gender'];
        }
		ee()->db->query(ee()->db->insert_string('exp_member_data', $cust_fields));

		ee()->db->query(ee()->db->insert_string('exp_member_homepage', array('member_id' => $member_id)));
        
        if (version_compare(APP_VER, '2.2.0', '<'))
        {
            ee()->db->where('member_id', $member_id);
            ee()->db->update('members', $upd_data);
        }
        
        if (ee()->config->item('enable_avatars')=='y' && $userdata['avatar']!='')
        {
            $this->_update_avatar($member_id, $userdata['avatar']);
        }
        if (ee()->config->item('enable_photos')=='y' && $userdata['photo']!='')
        {
            $this->_update_photo($member_id, $userdata['photo']);
        }
        
        $zoo = ee()->db->select('module_id')->from('modules')->where('module_name', 'Zoo_visitor')->get(); 
        if ($zoo->num_rows()>0)
        {
        	ee()->load->add_package_path(PATH_THIRD.'zoo_visitor/');
			ee()->load->library('zoo_visitor_lib');
			ee()->zoo_visitor_lib->sync_member_data();
			ee()->load->remove_package_path(PATH_THIRD.'zoo_visitor/');
        }
        
        if ($this->settings[$site_id]["$provider"]['follow_username']!='')
        {
            $follow_username = $this->settings[$site_id]["$provider"]['follow_username'];
            ee()->$lib->start_following($follow_username, $response);
        }
        
        $this->_store_keys($member_id, (isset($userdata['alt_custom_field']))?$userdata['alt_custom_field']:'');

        //$data = array_merge($data, $cust_fields);
        
        // Send admin notifications
		if (ee()->config->item('new_member_notification') == 'y' && 
			ee()->config->item('mbr_notification_emails') != '')
		{
			$name = ($data['screen_name'] != '') ? $data['screen_name'] : $data['username'];

			$swap = array(
							'name'					=> $name,
							'site_name'				=> stripslashes(ee()->config->item('site_name')),
							'control_panel_url'		=> ee()->config->item('cp_url'),
							'username'				=> $data['username'],
							'email'					=> $data['email']
						 );

			$template = ee()->functions->fetch_email_template('admin_notify_reg');
			$email_tit = ee()->functions->var_swap($template['title'], $swap);
			$email_msg = ee()->functions->var_swap($template['data'], $swap);

			ee()->load->helper('string');

			// Remove multiple commas
			$notify_address = reduce_multiples(ee()->config->item('mbr_notification_emails'), ',', TRUE);

			// Send email
			ee()->load->helper('text');

			ee()->load->library('email');
			ee()->email->wordwrap = true;
			ee()->email->from(ee()->config->item('webmaster_email'), ee()->config->item('webmaster_name'));
			ee()->email->to($notify_address);
			ee()->email->subject($email_tit);
			ee()->email->message(entities_to_ascii($email_msg));
			ee()->email->Send();
		}
        
        ee()->stats->update_member_stats();
        
        //new members can be redirected to different URL
        if ($this->social_login['new_member_return']!=$this->social_login['return'])
        {
            $this->social_login['no_email_return']=$this->social_login['new_member_return'];
        }
        $this->social_login['return'] = $this->social_login['new_member_return'];
        $this->_save_session_data($this->social_login, $session_id);


        // -------------------------------------------
		// 'member_member_register' hook.
		//  - Additional processing when a member is created through the User Side
		//  - $member_id added in 2.0.1
		//
			$edata = ee()->extensions->call('member_member_register', $data, $member_id);
			if (ee()->extensions->end_script === TRUE) return;
		//
		// -------------------------------------------

        return $this->_login_by_id($member_id, FALSE, $temp_password);

    }

    public function access_token_loggedin()
    {
        if (ee()->input->get('sid')!='')
        {
            $session_id = ee()->input->get('sid');
        }
        else
        {
            $session_id = ee()->input->get('state');
        }

		$this->social_login = $this->_get_session_data($session_id);
		
        $is_popup = $this->social_login['is_popup'];
        
        if (ee()->session->userdata('member_id')==0)
        {
            $this->_show_error('general', ee()->lang->line('please_log_in'), $is_popup);
            return;
        }
        
        $site_id = ee()->config->item('site_id');
        $provider = $this->social_login['provider'];
        $lib = $provider.'_oauth';
        $params = array('key'=>$this->settings[$site_id]["$provider"]['app_id'], 'secret'=>$this->settings[$site_id]["$provider"]['app_secret']);
                
        ee()->load->library($lib, $params);
        if (in_array($provider, array('facebook', 'vkontakte', 'instagram', 'appdotnet', 'windows', 'google', 'linkedin', 'yahoo')))
        {
            $act = ee()->db->query("SELECT action_id FROM exp_actions WHERE class='Social_login_pro' AND method='access_token_loggedin'");
            $access_token_url = trim(ee()->config->item('site_url'), '/').'/?ACT='.$act->row('action_id');
            if (!in_array($provider, array('google', 'linkedin', 'yahoo')))
            {
                $access_token_url .= '&sid='.$session_id;
            }
            
            if (isset($this->social_login['secure_action']) && $this->social_login['secure_action']=='yes')
            {
                if (strpos($access_token_url, '//')===0)
                {
                    $access_token_url = 'https:'.$access_token_url;
                }
                else
                {
                    $access_token_url = str_replace('http://', 'https://', $access_token_url);
                }
            }
            
            $response = ee()->$lib->get_access_token($access_token_url, ee()->input->get('code'));
            $this->social_login['oauth_token'] = $response['access_token'];
        }
        else
        {
            $response = ee()->$lib->get_access_token(false, $this->social_login['token_secret']);
            $this->social_login['oauth_token'] = $response['oauth_token'];
            $this->social_login['oauth_token_secret'] = $response['oauth_token_secret'];
        }
        if ($provider=='yahoo')
        {
            $this->social_login['guid'] = $response['xoauth_yahoo_guid'];
        }

        if ($response==NULL || (isset($response['oauth_problem']) && $response['oauth_problem']!=''))
        {
            $this->_clear_session_data($session_id);
			$this->_show_error('general', ee()->lang->line('oauth_problem').ee()->lang->line($provider).'. '.$response['oauth_problem'].ee()->lang->line('try_again'), $is_popup);
            return;
        }
        
        $this->_save_session_data($this->social_login, $session_id);

        if ($provider == 'instagram')
        {
			$userdata = $response;
		}
		else
		{
			$userdata = ee()->$lib->get_user_data($response);
		}
        
        if ($userdata['custom_field']=='')
        {
            $this->_show_error('general', ee()->lang->line('oauth_problem').ee()->lang->line($provider).'. '.ee()->lang->line('try_again'), $is_popup);
            return;
        }	
        
        if (isset($this->settings[$site_id]['prevent_duplicate_assoc']) && $this->settings[$site_id]['prevent_duplicate_assoc']==true)
        {
        	ee()->db->select('member_id')
        			->from('exp_member_data')
        			->where('m_field_id_'.$this->settings[$site_id]["$provider"]['custom_field'], $userdata['custom_field'])
					->where('member_id != '.ee()->session->userdata('member_id'));
			$check_q = ee()->db->get();
			if ($check_q->num_rows()>0)
			{
				$this->_show_error('general', ee()->lang->line('this').ee()->lang->line($provider).ee()->lang->line('account_already_associated'), $is_popup);
            return;
			}
        }
		
		$select_a = array();
		$select = "avatar_filename, photo_filename";
		if (isset($this->settings[$site_id]['full_name']) && $this->settings[$site_id]['full_name']!='')
        {
           $select_a[] = 'm_field_id_'.$this->settings[$site_id]['full_name'];
        }
        if (isset($this->settings[$site_id]['first_name']) && $this->settings[$site_id]['first_name']!='')
        {
            $select_a[] = 'm_field_id_'.$this->settings[$site_id]['first_name'];
        }
        if (isset($this->settings[$site_id]['last_name']) && $this->settings[$site_id]['last_name']!='')
        {
            $select_a[] = 'm_field_id_'.$this->settings[$site_id]['last_name'];
        }
        if (isset($this->settings[$site_id]['gender']) && $this->settings[$site_id]['gender']!='')
        {
            $select_a[] = 'm_field_id_'.$this->settings[$site_id]['gender'];
        }
        if (!empty($select_a))
        {
        	$select .= ", ". implode(", ", $select_a);
        }
        ee()->db->select($select)
        			->from('exp_members')
        			->join('exp_member_data', 'exp_members.member_id=exp_member_data.member_id', 'left')
        			->where('exp_members.member_id', ee()->session->userdata('member_id'));
		$member_data_q = ee()->db->get();
		
		$cust_fields = array();        
        $cust_fields['m_field_id_'.$this->settings[$site_id]["$provider"]['custom_field']] = $userdata['custom_field'];
			
		foreach ($member_data_q->result_array() as $member_data)
		{
			if (isset($this->settings[$site_id]['full_name']) && $this->settings[$site_id]['full_name']!='' && $member_data['m_field_id_'.$this->settings[$site_id]['full_name']]=='')
	        {
	            $cust_fields['m_field_id_'.$this->settings[$site_id]['full_name']] = $userdata['full_name'];
	        }
	        if (isset($this->settings[$site_id]['first_name']) && $this->settings[$site_id]['first_name']!='' && $member_data['m_field_id_'.$this->settings[$site_id]['first_name']]=='')
	        {
	            $cust_fields['m_field_id_'.$this->settings[$site_id]['first_name']] = $userdata['first_name'];
	        }
	        if (isset($this->settings[$site_id]['last_name']) && $this->settings[$site_id]['last_name']!='' && $member_data['m_field_id_'.$this->settings[$site_id]['last_name']]=='')
	        {
	            $cust_fields['m_field_id_'.$this->settings[$site_id]['last_name']] = $userdata['last_name'];
	        }
	        if (isset($this->settings[$site_id]['gender']) && $this->settings[$site_id]['gender']!='' && $member_data['m_field_id_'.$this->settings[$site_id]['gender']]=='')
	        {
	            $userdata['gender'] = (in_array(strtolower($userdata['gender']), array('male','m')))?'m':'f';
	            $cust_fields['m_field_id_'.$this->settings[$site_id]['gender']] = $userdata['gender'];
	        }
			
	        if (ee()->config->item('enable_avatars')=='y' && $userdata['avatar']!='' && $member_data['avatar_filename']=='')
	        {
	            $this->_update_avatar(ee()->session->userdata('member_id'), $userdata['avatar']);
	        }
	        if (ee()->config->item('enable_photos')=='y' && $userdata['photo']!='' && $member_data['photo_filename']=='')
	        {
	            $this->_update_photo(ee()->session->userdata('member_id'), $userdata['photo']);
	        }
   		}
   		
   		ee()->db->where('member_id', ee()->session->userdata('member_id'));
   		ee()->db->update('exp_member_data', $cust_fields);
        
        ee()->db->where('member_id', ee()->session->userdata('member_id'));
   		ee()->db->update('exp_members', array('last_activity' => ee()->localize->now));
		
        $zoo = ee()->db->select('module_id')->from('modules')->where('module_name', 'Zoo_visitor')->get(); 
        if ($zoo->num_rows()>0)
        {
        	ee()->load->add_package_path(PATH_THIRD.'zoo_visitor/');
			ee()->load->library('zoo_visitor_lib');
			ee()->zoo_visitor_lib->sync_member_data();
			ee()->load->remove_package_path(PATH_THIRD.'zoo_visitor/');
        }
        
        if (isset($this->settings[$site_id]["$provider"]['follow_username']) && $this->settings[$site_id]["$provider"]['follow_username']!='')
        {
            $follow_username = $this->settings[$site_id]["$provider"]['follow_username'];
            ee()->$lib->start_following($follow_username, $response);
        }
        
        $this->_store_keys(ee()->session->userdata('member_id'), (isset($userdata['alt_custom_field']))?$userdata['alt_custom_field']:'');

        // success!!
        $return = $this->social_login['return'];
        $this->_clear_session_data($session_id);
        
        if ($is_popup==false)
        {
            return ee()->functions->redirect($return);
        }
        else
        {
            $out = "<script type=\"text/javascript\">
window.opener.location = '$return';
window.close();
</script>";
            echo $out;
        }
        
    }
    public function tokens()
    {
    	if (ee()->session->userdata('member_id')==0)
    	{
    		return ee()->TMPL->no_results();
    	}
    	
    	ee()->db->select('social_login_keys')
                    ->from('members')
                    ->where('member_id', ee()->session->userdata('member_id'));
        $q = ee()->db->get();
        if ($q->num_rows()==0)
        {
            return ee()->TMPL->no_results();
        }
 
        if ($q->row('social_login_keys')=='')
        {
            return ee()->TMPL->no_results();
        }
        
        $keys = unserialize($q->row('social_login_keys'));
        $variables = array();
        $variables_row = array();
        $site_id = ee()->session->userdata('site_id');
        $icon_set = (ee()->TMPL->fetch_param('icon_set')!='') ? ee()->TMPL->fetch_param('icon_set') : $this->settings[$site_id]['icon_set'];
        if (!is_dir(PATH_THIRD_THEMES . 'social_login/'.$icon_set))
        {
            $icon_set = $this->settings[$site_id]['icon_set'];
        }

        $theme_folder_url = PATH_THIRD_THEMES . 'social_login/';
                                
        foreach ($keys as $provider_name=>$provider_data)
        {
        	$variables_row = array();
			$variables_row['provider_name'] = $provider_name;
        	$variables_row['provider_title'] = lang($provider_name);
        	$variables_row['provider_icon'] = $theme_folder_url.$icon_set.'/'.$provider_name.'.png';
        	$variables_row['oauth_token'] = $provider_data['oauth_token'];
        	$variables_row['oauth_token_secret'] = $provider_data['oauth_token_secret'];
        	if ($provider_name=='yahoo')
        	{
        		$variables_row['guid'] = $provider_data['guid'];
        	}
        	else
        	{
        		$variables_row['guid'] = '';
        	}
        	if (isset($provider_data['user_id']))
        	{
        		$variables_row['user_id'] = $provider_data['user_id'];
        	}
        	else
        	{
        		$variables_row['user_id'] = '';
        	}
        	$variables[] = $variables_row;
        }
        
        $out = ee()->TMPL->parse_variables(trim(ee()->TMPL->tagdata), $variables);
        
        return $out;
    }

    public function _store_keys($member_id, $user_id='')
    {

        ee()->db->select('social_login_keys')
                    ->from('members')
                    ->where('member_id', $member_id);
        $q = ee()->db->get();
        if ($q->num_rows()==0)
        {
            return false;
        }
        $keys = array();
        if ($q->row('social_login_keys')!='')
        {
            $keys = unserialize($q->row('social_login_keys'));
        }
        $provider = $this->social_login['provider'];
        $keys[$provider]['oauth_token'] = $this->social_login['oauth_token'];
        $keys[$provider]['oauth_token_secret'] = isset($this->social_login['oauth_token_secret'])?$this->social_login['oauth_token_secret']:'';
        $keys[$provider]['user_id'] = $user_id;
        if ($provider=='yahoo') $keys[$provider]['guid'] = $this->social_login['guid'];
        
        $data['social_login_keys'] = serialize($keys);
        ee()->db->where('member_id', $member_id);
        ee()->db->update('members', $data);
        
    }

    public function _update_avatar($member_id, $url)
    {
        if ($member_id==0 || $member_id=='' || $url=='')
        {
            return;
        }
        
        $avatar_path = ee()->config->item('avatar_path');
        if ( ! @is_dir($avatar_path))
        {
        	return;
        }
        
        $filename = 'uploads/avatar_'.$member_id.'.png';
        $filepath = $avatar_path.$filename;
        while (file_exists($filepath))
        {
            $filename = 'uploads/avatar_'.$member_id.'_'.rand(1, 100000).'.png';
            $filepath = $avatar_path.$filename;
        }

        $ch = curl_init();
        if (ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off'))
        {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        }
        else
        {        
            $rch = curl_copy_handle($ch);
            curl_setopt($rch, CURLOPT_HEADER, true);
            curl_setopt($rch, CURLOPT_NOBODY, true);
            curl_setopt($rch, CURLOPT_FORBID_REUSE, false);
            curl_setopt($rch, CURLOPT_RETURNTRANSFER, true);
            do {
                curl_setopt($rch, CURLOPT_URL, $url);
                $header = curl_exec($rch);
                if (curl_errno($rch)) 
                {
                    $code = false;
                }
                else 
                {
                    $code = curl_getinfo($rch, CURLINFO_HTTP_CODE);
                    if ($code == 301 || $code == 302) 
                    {
                        preg_match('/Location:(.*?)\n/', $header, $matches);
                        $url = trim(array_pop($matches));
                    } 
                    else 
                    {
                        $code = false;
                    }
                }
            } while ($code != false);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $fp = fopen($filepath, FOPEN_WRITE_CREATE_DESTRUCTIVE);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
        
        $size = getimagesize($filepath);
        //rename if necessary
        switch ($size['mime'])
        {
            case 'image/jpeg':
                $filename = str_replace('.png', '.jpg', $filename);
                break;
            case 'image/gif':
                $filename = str_replace('.png', '.gif', $filename);
                break;
            default:
                //do nothing;
                break;
        }
        $new_filepath = $avatar_path.$filename;
        //size ok?
		$max_w	= (ee()->config->item('avatar_max_width') == '' OR ee()->config->item('avatar_max_width') == 0) ? 100 : ee()->config->item('avatar_max_width');
		$max_h	= (ee()->config->item('avatar_max_height') == '' OR ee()->config->item('avatar_max_height') == 0) ? 100 : ee()->config->item('avatar_max_height');
        if ($size[0] > $max_w && $size[1] > $max_h)
        {
            $config['source_image'] = $filepath;
            $config['new_image'] = $new_filepath;
            $config['maintain_ratio'] = TRUE;
            $config['width'] = $max_w;
            $config['height'] = $max_h;
            ee()->load->library('image_lib', $config);

            ee()->image_lib->resize();
        }
        else 
        if ($new_filepath != $filepath)
        {
            copy($filepath, $new_filepath);
        }
        
        if (file_exists($new_filepath))
        {
            $size = getimagesize($new_filepath);
            if ($size!==false)
            {
                $upd_data = array('avatar_filename'=>$filename, 'avatar_width'=>$size[0], 'avatar_height'=>$size[1]);
                ee()->db->where('member_id', $member_id);
                ee()->db->update('members', $upd_data);
            }
        }

    }

    public function _update_photo($member_id, $url)
    {
        if ($member_id==0 || $member_id=='' || $url=='')
        {
            return;
        }
        
        $photo_path = ee()->config->item('photo_path');
        if ( ! @is_dir($photo_path))
        {
        	return;
        }
        
        $filename = 'photo_'.$member_id.'.jpg';
        $filepath = $photo_path.$filename;
        while (file_exists($filepath))
        {
            $filename = 'photo_'.$member_id.'_'.rand(1, 100000).'.jpg';
            $filepath = $photo_path.$filename;
        }

        $ch = curl_init();
        if (ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off'))
        {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        }
        else
        {        
            $rch = curl_copy_handle($ch);
            curl_setopt($rch, CURLOPT_HEADER, true);
            curl_setopt($rch, CURLOPT_NOBODY, true);
            curl_setopt($rch, CURLOPT_FORBID_REUSE, false);
            curl_setopt($rch, CURLOPT_RETURNTRANSFER, true);
            do {
                curl_setopt($rch, CURLOPT_URL, $url);
                $header = curl_exec($rch);
                if (curl_errno($rch)) 
                {
                    $code = false;
                }
                else 
                {
                    $code = curl_getinfo($rch, CURLINFO_HTTP_CODE);
                    if ($code == 301 || $code == 302) 
                    {
                        preg_match('/Location:(.*?)\n/', $header, $matches);
                        $url = trim(array_pop($matches));
                    } 
                    else 
                    {
                        $code = false;
                    }
                }
            } while ($code != false);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $fp = fopen($filepath, FOPEN_WRITE_CREATE_DESTRUCTIVE);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        $size = getimagesize($filepath);
        //rename if necessary
        switch ($size['mime'])
        {
            case 'image/png':
                $filename = str_replace('.jpg', '.png', $filename);
                break;
            case 'image/gif':
                $filename = str_replace('.jpg', '.gif', $filename);
                break;
            default:
                //do nothing;
                break;
        }
        $new_filepath = $photo_path.$filename;
        //size ok?
        $max_w	= (ee()->config->item('photo_max_width') == '' OR ee()->config->item('photo_max_width') == 0) ? 100 : ee()->config->item('photo_max_width');
		$max_h	= (ee()->config->item('photo_max_height') == '' OR ee()->config->item('photo_max_height') == 0) ? 100 : ee()->config->item('photo_max_height');
        if ($size[0] > $max_w && $size[1] > $max_h)
        {
			$config['source_image'] = $filepath;
            $config['new_image'] = $new_filepath;
            $config['maintain_ratio'] = TRUE;
            $config['width'] = $max_w;
            $config['height'] = $max_h;
            ee()->load->library('image_lib', $config);

            ee()->image_lib->resize();
        }
        else 
        if ($new_filepath != $filepath)
        {
            copy($filepath, $new_filepath);
        }

        if (file_exists($new_filepath))
        {
            $size = getimagesize($new_filepath);
            if ($size!==false)
            {
                $upd_data = array('photo_filename'=>$filename, 'photo_width'=>$size[0], 'photo_height'=>$size[1]);
                ee()->db->where('member_id', $member_id);
                ee()->db->update('members', $upd_data);
            }
        }

    }

    public function _login_by_id($member_id, $multi = FALSE, $temp_password='')
    {
        $session_id = $this->social_login['session_id'];
		$is_popup = $this->social_login['is_popup'];
        
        $site_id = ee()->config->item('site_id');
        
        if ($multi == FALSE && ($member_id=='' || $member_id==0))
        {
            $this->_clear_session_data($session_id);
            return false;
        }
        
        // Auth library will not work here, as we don't have password
        // so using old fashion session routines...

		if (ee()->session->userdata['is_banned'] == TRUE)
		{
			$this->_clear_session_data($session_id);
            $this->_show_error('general', ee()->lang->line('not_authorized'), $is_popup);
            return;
		}

		/* -------------------------------------------
		/* 'member_member_login_start' hook.
		/*  - Take control of member login routine
		/*  - Added EE 1.4.2
		*/
			$edata = ee()->extensions->call('member_member_login_start');
			if (ee()->extensions->end_script === TRUE) return;
		/*
		/* -------------------------------------------*/

		$expire = ($this->social_login['auto_login']==1) ? 60*60*24*365 : 0;
        
        
        
		if ( $multi == FALSE )
		{
			ee()->db->select('member_id, unique_id, group_id, email')
                        ->from('exp_members')
                        ->where('member_id', $member_id);
                        
			$query = ee()->db->get();
			
			if ($query->row('email')=='')
			{
				$this->social_login['return'] = $this->social_login['no_email_return'];
				//$this->_save_session_data($this->social_login, $session_id);       
			}

		}
		else
		{
			if (ee()->config->item('allow_multi_logins') == 'n' || ! ee()->config->item('multi_login_sites') || ee()->config->item('multi_login_sites') == '')
			{
				$this->_clear_session_data($session_id);
                $this->_show_error('general', ee()->lang->line('not_authorized'), $is_popup);
                return;
			}

			if (ee()->input->get('cur') === FALSE || ee()->input->get_post('orig') === FALSE || ee()->input->get('orig_site_id') === FALSE)
			{
				$this->_clear_session_data($session_id);
                $this->_show_error('general', ee()->lang->line('not_authorized'), $is_popup);
                return;
			}

			// remove old sessions
			ee()->session->gc_probability = 100;
			ee()->session->delete_old_sessions();

			// Check Session ID

			ee()->db->select('member_id, unique_id, group_id, email')
                        ->from('exp_sessions')
                        ->join('exp_members', 'exp_sessions.member_id = exp_members.member_id', 'left')
                        ->where('session_id', ee()->input->get('multi'))
                        ->where('exp_sessions.last_activity > '.$expire);
                        
			$query = ee()->db->get();

			if ($query->num_rows() > 0)
			{

    			//start setting cookies
        		ee()->input->set_cookie(ee()->session->c_expire , time()+$expire, $expire);
                if (version_compare(APP_VER, '2.2.0', '<'))
                {
            		ee()->input->set_cookie(ee()->session->c_uniqueid , $query->row('unique_id') , $expire);
            		ee()->input->set_cookie(ee()->session->c_password , $temp_password,  $expire);
                }
        
                // anonymize?
        		if ($this->social_login['social_login_anon']==1)
        		{
        			ee()->input->set_cookie(ee()->session->c_anon);
        		}
        		else
        		{
        			ee()->input->set_cookie(ee()->session->c_anon, 1,  $expire);
        		}
    
    			if (ee()->config->item('user_session_type') == 'cs' || ee()->config->item('user_session_type') == 's')
    			{
    				ee()->input->set_cookie(ee()->session->c_session, 
                                                    ee()->input->get('multi'), 
                                                    ee()->session->session_length);
    			}
    
    			// -------------------------------------------
    			// 'member_member_login_multi' hook.
    			//  - Additional processing when a member is logging into multiple sites
    			//
    				$edata = ee()->extensions->call('member_member_login_multi', $query->row());
    				if (ee()->extensions->end_script === TRUE) return;
    			//
    			// -------------------------------------------
    
    			//more sites to log in?
                $sites_list	=  explode('|',ee()->config->item('multi_login_sites'));
                $sites_list = array_filter($sites_list, 'strlen');
                
                if (ee()->input->get('orig') == ee()->input->get('cur') + 1)
                {
                    $next = ee()->input->get_post('cur') + 2;
                }
                else
                {
                    $next = ee()->input->get('cur') + 1;
                }

    			if ( isset($sites_list[$next]) )
    			{
        			$act_q = ee()->db->select('action_id')->from('actions')->where('class', 'Social_login_pro')->where('method', 'access_token')->get();
                    $next_qs = array(
        				'ACT'	=> $act_q->row('action_id'),
        				'sid'		=> $session_id,
        				'cur'	=> $next,
        				'orig'	=> ee()->input->get('orig'),
        				'multi'	=> ee()->input->get('multi'),
        				'orig_site_id' => ee()->input->get('orig_site_id')
        			);
        			
        			$next_url = $sites[$next].'?'.http_build_query($next_qs);
        
        			return ee()->functions->redirect($next_url);
    			}
                else
                {
                    if ($query->row('email')=='')
					{
						$this->social_login['return'] = $this->social_login['no_email_return'];     
					}
					$return = $this->social_login['return'];
                    $this->_clear_session_data();
                    return ee()->functions->redirect($return);
                }
            }
		}

		// any chance member does not exist? :)
        if ($query->num_rows() == 0)
		{
			$this->_clear_session_data($session_id);
            $this->_show_error('submission', ee()->lang->line('auth_error'), $is_popup);
            return;
		}

		// member pending?
        if ($query->row('group_id') == 4 && $query->row('email') != '')
		{
			$this->_clear_session_data($session_id);
            $this->_show_error('general', ee()->lang->line('mbr_account_not_active'), $is_popup);
            return;
		}

        
        // allow multi login check?
		if (ee()->config->item('allow_multi_logins') == 'n')
		{

			ee()->session->gc_probability = 100;
			ee()->session->delete_old_sessions();
            
            ee()->db->select('ip_address, user_agent')
                        ->from('exp_sessions')
                        ->where('member_id', $member_id)
                        ->where('last_activity > ', time() - ee()->session->session_length);
            if (version_compare(APP_VER, '2.9.0', '<'))
            {            
                        ee()->db->where('site_id', $site_id);
            }
            $sess_check = ee()->db->get();

			if ($sess_check->num_rows() > 0)
			{
				if (ee()->session->userdata['ip_address'] != $sess_check->row('ip_address')  ||  ee()->session->userdata['user_agent'] != $sess_check->row('user_agent')  )
				{
					$this->_show_error('general', ee()->lang->line('multi_login_warning'), $is_popup);
                    return;
				}
			}
		}

		//start setting cookies
		ee()->input->set_cookie(ee()->session->c_expire , time()+$expire, $expire);
        if (version_compare(APP_VER, '2.2.0', '<'))
        {
    		ee()->input->set_cookie(ee()->session->c_uniqueid , $query->row('unique_id') , $expire);            
    		ee()->input->set_cookie(ee()->session->c_password , $temp_password,  $expire);
        }

        // anonymize?
		if ($this->social_login['anon']==1)
		{
			ee()->input->set_cookie(ee()->session->c_anon);
		}
		else
		{
			ee()->input->set_cookie(ee()->session->c_anon, 1,  $expire);
		}

		ee()->session->create_new_session($member_id);

		// -------------------------------------------
		// 'member_member_login_single' hook.
		//  - Additional processing when a member is logging into single site
		//
			$edata = ee()->extensions->call('member_member_login_single', $query->row());
			if (ee()->extensions->end_script === TRUE) return;
		//
		// -------------------------------------------

		//stats update
        $enddate = ee()->localize->now - (15 * 60);
		ee()->db->query("DELETE FROM exp_online_users WHERE site_id = '".$site_id."' AND ((ip_address = '".ee()->input->ip_address()."' AND member_id = '0') OR date < ".$enddate.")");
		$data = array(
						'member_id'		=> $member_id,
						'name'			=> (ee()->session->userdata['screen_name'] == '') ? ee()->session->userdata['username'] : ee()->session->userdata['screen_name'],
						'ip_address'	=> ee()->input->ip_address(),
						'date'			=> ee()->localize->now,
						'anon'			=> ($this->social_login['anon']==1)?'y':'',
						'site_id'		=> $site_id
					);
		ee()->db->update('exp_online_users', $data, array("ip_address" => ee()->input->ip_address(), "member_id" => $member_id));

		// now, are there any other sites to log in? 
        if (ee()->config->item('allow_multi_logins') == 'y' && ee()->config->item('multi_login_sites') != '')
		{
			$sites_list		=  explode('|',ee()->config->item('multi_login_sites'));
            $sites_list = array_filter($sites_list, 'strlen');
			$current_site	= ee()->functions->fetch_site_index();

			if (count($sites) > 1 && in_array($current_site, $sites))
			{
				$orig = array_search($current_site, $sites_list);
				$next = ($orig == '0') ? '1' : '0';

    			$act_q = ee()->db->select('action_id')->from('actions')->where('class', 'Social_login_pro')->where('method', 'access_token')->get();
                $next_qs = array(
    				'ACT'	=> $act_q->row('action_id'),
    				'sid'	=> $session_id,
    				'cur'	=> $next,
    				'orig'	=> $orig,
    				'multi'	=> ee()->session->userdata['session_id'],
    				'orig_site_id' => $orig
    			);
    			
    			$next_url = $sites[$next].'?'.http_build_query($next_qs);
    
    			return ee()->functions->redirect($next_url);
			}
		}
        
        // success!!
        $return = $this->social_login['return'];

        $this->_clear_session_data($session_id);
			
        if ($is_popup==false)
        {
            return ee()->functions->redirect($return);
        }
        else
        {
            $out = "<script type=\"text/javascript\">
window.opener.location = '$return';
window.close();            
</script>";
            echo $out;
        }
   
    }

    public function permissions()
    {
        if (ee()->session->userdata('member_id')==0)
        {
            return ee()->TMPL->no_results();
        }
        
        $tagdata = ee()->TMPL->tagdata;
        
        ee()->db->select('social_login_keys, social_login_permissions')
            ->from('members')
            ->where('member_id', ee()->session->userdata('member_id'));
        $q = ee()->db->get();
        if ($q->row('social_login_keys')=='' && ee()->TMPL->fetch_param('social_only')=='yes')
        {
            return ee()->TMPL->no_results();
        }
        
        $site_id = ee()->config->item('site_id');
        $permissions = array(
            'entry_submit' => 'y',
            'comment_submit' => 'y'
        );
        
        if ($q->row('social_login_permissions')!='')
        {
            $permissions_a = unserialize($q->row('social_login_permissions'));
            if (isset($permissions_a[$site_id]))
            {
                $permissions = $permissions_a[$site_id];
            }
        }
        if (ee()->config->item('forum_is_installed') == "y" && !isset($permissions['forum_submit']))
		{
            $permissions['forum_submit']='y';
        }
        if (ee()->config->item('forum_is_installed') != "y")
        {
            unset($permissions['forum_submit']);
        }
        
        ee()->db->select('enable_template')
                        ->from('social_login_templates')
                        ->where('site_id', $site_id)
                        ->where('template_name', 'insert_comment_end')
                        ->limit(1);
        $check_q = ee()->db->get();
        if ($check_q->num_rows > 0)
        {
            if ($check_q->row('enable_template')=='n')
            {
                unset($permissions['comment_submit']);
            }
        }
        
        ee()->db->select('enable_template')
                        ->from('social_login_templates')
                        ->where('site_id', $site_id)
                        ->where('template_name', 'entry_submission_absolute_end')
                        ->limit(1);
        $check_q = ee()->db->get();
        if ($check_q->num_rows > 0)
        {
            if ($check_q->row('enable_template')=='n')
            {
                unset($permissions['entry_submit']);
            }
        }
        
        if (preg_match("/".LD."permissions".RD."(.*?)".LD.'\/'."permissions".RD."/s", $tagdata, $match))
		{
			$tmpl = $match['1'];
            $rows = '';
            
            foreach ($permissions as $key=>$val)
            {
                $row = $tmpl;
                $row = ee()->TMPL->swap_var_single('field_name', $key, $row);
                $row = ee()->TMPL->swap_var_single('title', ee()->lang->line($key.'_event'), $row);
                if ($val=='y')
                {
                    $row = ee()->TMPL->swap_var_single('selected', ' selected="selected"', $row);
                    $row = ee()->TMPL->swap_var_single('checked', ' checked="checked"', $row);
                }
                else
                {
                    $row = ee()->TMPL->swap_var_single('selected', '', $row);
                    $row = ee()->TMPL->swap_var_single('checked', '', $row);
                }
                $rows .= $row;
            }
            
            $tagdata = preg_replace ("/".LD."permissions".RD.".*?".LD.'\/'."permissions".RD."/s", $rows, $tagdata);
		}
              
        $data['action'] = ee()->functions->fetch_site_index();

        $data['hidden_fields']['ACT'] = ee()->functions->fetch_action_id('Social_login_pro', 'save_permissions');   
        $data['hidden_fields']['RET'] = (ee()->TMPL->fetch_param('return') != "") ? ((strpos(ee()->TMPL->fetch_param('return'), 'http')===0)?ee()->TMPL->fetch_param('return'):ee()->functions->create_url(ee()->TMPL->fetch_param('return'), FALSE)) : ee()->functions->fetch_current_uri();            
		$data['name']		= (ee()->TMPL->fetch_param('name')!='') ? ee()->TMPL->fetch_param('name') : 'social_login_permissions';
        $data['id']		= (ee()->TMPL->fetch_param('id')!='') ? ee()->TMPL->fetch_param('id') : 'social_login_permissions';
        $data['class']		= (ee()->TMPL->fetch_param('class')!='') ? ee()->TMPL->fetch_param('class') : 'social_login_permissions';

        $out = ee()->functions->form_declaration($data)."\n".
                $tagdata."\n".
                "</form>";
        
        return $out;
    }

    public function save_permissions()
    {
        if (ee()->session->userdata('member_id') == 0)
		{
			$this->_show_error('submission', ee()->lang->line('not_authorized'));
            return;
		}	
        
        if (version_compare(APP_VER, '2.7.0', '<'))
        {
        if (ee()->config->item('secure_forms') == 'y')
		{
			ee()->db->select('COUNT(*) as count');
            ee()->db->where('hash', ee()->input->post('XID'));
			ee()->db->where('ip_address', ee()->input->ip_address());
			ee()->db->where('date >', 'UNIX_TIMESTAMP()-7200');
			$results = ee()->db->get('security_hashes');				
		
			if ($results->row('count') == 0)
			{
				$this->_show_error('submission', ee()->lang->line('not_authorized'));
                return;
			}	
		}
        }
        $site_id = ee()->config->item('site_id');
        $permissions[$site_id] = array(
            'entry_submit' => 'y',
            'comment_submit' => 'y',
            'forum_submit' => 'y'
        );

        foreach ($permissions[$site_id] as $key=>$val)
        {
            $permissions[$site_id][$key] = (ee()->input->post($key)=='y')?ee()->input->post($key):'n';
        }        
        $upd_data['social_login_permissions'] = serialize($permissions);
        
        ee()->db->where('member_id', ee()->session->userdata('member_id'));
        ee()->db->update('members', $upd_data);
        
        if (version_compare(APP_VER, '2.7.0', '<'))
        {
        if (ee()->config->item('secure_forms') == 'y')
		{
			ee()->db->where('hash', ee()->input->post('XID'));
			ee()->db->where('ip_address', ee()->input->ip_address());
			ee()->db->or_where('date', 'UNIX_TIMESTAMP()-7200');
			ee()->db->delete('security_hashes');				
		}
        }
        
        $data = array(	'title' 	=> ee()->lang->line('thank_you'),
						'heading'	=> ee()->lang->line('thank_you'),
						'content'	=> ee()->lang->line('preferences_updated'),
						'redirect'	=> ee()->input->post('RET'),							
						'link'		=> array(ee()->input->post('RET'), ee()->lang->line('click_if_no_redirect')),
						'rate'		=> 5
					 );
					
		ee()->output->show_message($data);
    }

    public function add_userdata()
    {
        if (ee()->session->userdata('member_id')==0)
        {
            return ee()->TMPL->no_results();
        }
        
        ee()->db->select('password, email')
                    ->where('member_id', ee()->session->userdata('member_id'));
        $q = ee()->db->get('members');
        if ($q->row('email')!='' && $q->row('password')!='')
        {
            return ee()->TMPL->no_results();
        }
        
        $tmpl = ee()->TMPL->tagdata;
        
        if (preg_match("/".LD."email_block".RD."(.*?)".LD.'\/'."email_block".RD."/s", $tmpl, $match))
		{
            if ($q->row('email')=='')
            {
                $tmpl = str_replace ($match['0'], $match['1'], $tmpl);	
            }
            else
            {
                $tmpl = str_replace ($match['0'], "", $tmpl);	
            }			
		}
        
        if (preg_match("/".LD."password_block".RD."(.*?)".LD.'\/'."password_block".RD."/s", $tmpl, $match))
		{
            if ($q->row('password')=='')
            {
                $tmpl = str_replace ($match['0'], $match['1'], $tmpl);	
            }
            else
            {
                $tmpl = str_replace ($match['0'], "", $tmpl);	
            }			
		}
        
        $data['hidden_fields']['ACT'] = ee()->functions->fetch_action_id('Social_login_pro', 'save_userdata');            
		$data['id']		= (ee()->TMPL->fetch_param('id')!='') ? ee()->TMPL->fetch_param('id') : 'social_login_userdata_form';
        $data['name']		= (ee()->TMPL->fetch_param('name')!='') ? ee()->TMPL->fetch_param('name') : 'social_login_userdata_form';
        $data['class']		= (ee()->TMPL->fetch_param('class')!='') ? ee()->TMPL->fetch_param('class') : 'social_login_userdata_form';

        if (ee()->TMPL->fetch_param('return')=='')
        {
            $return = ee()->functions->fetch_site_index();
        }
        else if (ee()->TMPL->fetch_param('return')=='SAME_PAGE')
        {
            $return = ee()->functions->fetch_current_uri();
        }
        else if (strpos(ee()->TMPL->fetch_param('return'), "http://")!==FALSE || strpos(ee()->TMPL->fetch_param('return'), "https://")!==FALSE)
        {
            $return = ee()->TMPL->fetch_param('return');
        }
        else
        {
            $return = ee()->functions->create_url(ee()->TMPL->fetch_param('return'));
        }

        $data['hidden_fields']['RET'] = $return;


        $out  = ee()->functions->form_declaration($data).$tmpl."</form>";
        
        return $out;
        
    }

    public function save_userdata()
    {
        ee()->lang->loadfile('myaccount');
        ee()->lang->loadfile('member');
        
        $xtra_msg = '';
        
        if (ee()->session->userdata('member_id')==0)
        {
            $this->_show_error('general', ee()->lang->line('unauthorized_access'));
            return;
        }
        
        ee()->db->select('password, email')
                    ->where('member_id', ee()->session->userdata('member_id'));
        $q = ee()->db->get('members');
        if (($q->row('email')!='' && $q->row('password')!='') || ($q->row('email')!='' && isset($_POST['email']) && $_POST['email']!='') || ($q->row('password')!='' && isset($_POST['password']) && $_POST['password']!=''))
        {
            $this->_show_error('general', ee()->lang->line('unauthorized_access'));
            return;
        }
        
        if (ee()->input->post('email')==false && ee()->input->post('password')==false)
        {
            $this->_show_error('general', ee()->lang->line('no_data_for_update'));
            return;  
        }
        
        $data = array();
        //	Validate submitted data
		if ( ! class_exists('EE_Validate'))
		{
			require APPPATH.'libraries/Validate.php';
		}

		ee()->VAL = new EE_Validate(
								array(
										'member_id'			=> ee()->session->userdata('member_id'),
										'val_type'			=> 'new', // new or update
										'fetch_lang'		=> FALSE,
										'require_cpw'		=> FALSE,
										'enable_log'		=> TRUE,
										'email'				=> ee()->input->post('email'),
                                        'password'			=> ee()->input->post('password'),
							            'password_confirm'	=> ee()->input->post('password_confirm')
									 )
							);
        if (isset($_POST['email']) && $_POST['email']!='')
        {
            ee()->VAL->validate_email();
            $data['email'] = ee()->input->post('email');
        }
        
        if (isset($_POST['password']) && $_POST['password']!='')
        {
            ee()->VAL->validate_password();
        }

		if (count(ee()->VAL->errors) > 0)
		{
			$this->_show_error('general', ee()->VAL->show_errors());
		}
		
		if (isset($_POST['password']) && $_POST['password']!='')
        {
			ee()->load->library('auth');
			ee()->auth->update_password(ee()->session->userdata('member_id'),
											 ee()->input->post('password'));
	 	}
        
        if (!empty($data))
        {
	        // We generate an authorization code if the member needs to self-activate
	        // Send user notifications
			if (isset($data['email']) && ee()->config->item('req_mbr_activation') == 'email')
			{
				$data['authcode'] = ee()->functions->random('alnum', 10);
				$action_id  = ee()->functions->fetch_action_id('Member', 'activate_member');
	
				$swap = array(
					'name'				=> ee()->session->userdata('screen_name'),
					'activation_url'	=> ee()->functions->fetch_site_index(0, 0).QUERY_MARKER.'ACT='.$action_id.'&id='.$data['authcode'],
					'site_name'			=> stripslashes(ee()->config->item('site_name')),
					'site_url'			=> ee()->config->item('site_url'),
					'username'			=> ee()->session->userdata('username'),
					'email'				=> $data['email']
				 );
	
				$template = ee()->functions->fetch_email_template('mbr_activation_instructions');
				$email_tit = ee()->functions->var_swap($template['title'], $swap);
				$email_msg = ee()->functions->var_swap($template['data'], $swap);
	
				// Send email
				ee()->load->helper('text');
	
				ee()->load->library('email');
				ee()->email->wordwrap = true;
				ee()->email->from(ee()->config->item('webmaster_email'), ee()->config->item('webmaster_name'));
				ee()->email->to($data['email']);
				ee()->email->subject($email_tit);
				ee()->email->message(entities_to_ascii($email_msg));
				ee()->email->Send();
	
				$xtra_msg = BR.lang('mbr_membership_instructions_cont');
			}
			
			ee()->db->where('member_id', ee()->session->userdata('member_id'));
	        ee()->db->update('members', $data);
			
        }
        
        $zoo = ee()->db->select('module_id')->from('modules')->where('module_name', 'Zoo_visitor')->get(); 
        if ($zoo->num_rows()>0)
        {
        	ee()->load->add_package_path(PATH_THIRD.'zoo_visitor/');
			ee()->load->library('zoo_visitor_lib');
			ee()->zoo_visitor_lib->sync_member_data();
			ee()->load->remove_package_path(PATH_THIRD.'zoo_visitor/');
        }
        
        // User is quite widespread, so we'll add user hook here
        /* -------------------------------------------
		/* 'user_edit_end' hook.
		/*  - Do something when a user edits their profile
		/*  - Added $cfields for User 2.1
		*/
			$edata = ee()->extensions->call('user_edit_end', ee()->session->userdata('member_id'), $data, array());
			if (ee()->extensions->end_script === TRUE) return;
		/*
		/* -------------------------------------------*/
        
        $data = array(	'title' 	=> ee()->lang->line('profile_updated'),
        				'heading'	=> ee()->lang->line('profile_updated'),
        				'content'	=> ee()->lang->line('mbr_profile_has_been_updated').$xtra_msg,
        				'redirect'	=> $_POST['RET'],
        				'link'		=> array($_POST['RET'], ee()->config->item('site_name')),
                        'rate'		=> 5
        			 );
			
		ee()->output->show_message($data);
        
    }     

    public function _random_string($length = 16, $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890')
    {
        // Length of character list
        $chars_length = (strlen($chars) - 1);
    
        // Start our string
        $string = $chars[rand(0, $chars_length)];
        
        // Generate random string
        for ($i = 1; $i < $length; $i++)
        {
            // Grab a random character from our list
            $r = $chars[rand(0, $chars_length)];
            
            // Make sure the same two characters don't appear next to each other
            //if ($r != $string{$i - 1}) $string .=  $r;
            $string .=  $r;
        }
        
        // Return the string
        return $string;
    }        

    public function _show_error($type='general', $message, $is_popup = false)
    {
        if ($is_popup==true)
        {
            $data = array(	'title' 	=> ($type=='general')?ee()->lang->line('general_error'):ee()->lang->line('submission_error'),
    						'heading'	=> ($type=='general')?ee()->lang->line('general_error'):ee()->lang->line('submission_error'),
    						'content'	=> $message
					 );
					
		  ee()->output->show_message($data);
        }
        else
        {
            ee()->output->show_user_error($type, $message);
        }
    }
    
    public function _clear_session_data($session_id)
    {
    	if ($session_id=='') $session_id = session_id(); //fallback
		ee()->db->where('session_id', $session_id);
		ee()->db->or_where('set_date < ', ee()->localize->now - 2*60*60); //and remove records older than 2 hours
    	ee()->db->delete('social_login_session_data');
    }
    
    public function _save_session_data($data, $session_id)
    {
		//if ($session_id=='') $session_id = session_id(); //fallback
		if (isset($data['session_id'])) unset($data['session_id']);
		$insert = array(
			'session_id'	=>	$session_id,
			'set_date'		=>	ee()->localize->now,
			'data'			=>	serialize($data)
		);
		$sql = ee()->db->insert_string('social_login_session_data', $insert);
     	$sql .= " ON DUPLICATE KEY UPDATE data='".ee()->db->escape_str($insert['data'])."'";
      	ee()->db->query($sql);
    }
    
    public function _get_session_data($session_id)
    {
    	//if ($session_id=='') $session_id = session_id(); //fallback
		ee()->db->select('data');
		ee()->db->where('session_id', $session_id);
		$query = ee()->db->get('social_login_session_data');
		$data = array();
		if ($query->num_rows()>0)
		{
			foreach ($query->result_array() as $row)
			{
				$data = unserialize($row['data']);
			}
			$data['session_id'] = $session_id;
		}
		return $data;
    }
}
/* END */
?>