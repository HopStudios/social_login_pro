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
 File: upd.social_login_pro.php
-----------------------------------------------------
 Purpose: Integration of EE membership with social networks
=====================================================
*/

if ( ! defined('BASEPATH'))
{
    exit('Invalid file request');
}

require_once PATH_THIRD.'social_login_pro/config.php';

class Social_login_pro_upd {

    public $version = SOCIAL_LOGIN_PRO_ADDON_VERSION;

    public function __construct() { 
        // nothing
    } 

    public function install() { 
        
        ee()->load->dbforge(); 
        
        //----------------------------------------
		// EXP_MODULES
		// The settings column, Ellislab should have put this one in long ago.
		// No need for a seperate preferences table for each module.
		//----------------------------------------
		if (ee()->db->field_exists('settings', 'modules') == FALSE)
		{
			ee()->dbforge->add_column('modules', array('settings' => array('type' => 'TEXT') ) );
		}
        
        if (ee()->db->field_exists('social_login_keys', 'members') == FALSE)
		{
			ee()->dbforge->add_column('members', array('social_login_keys' => array('type' => 'TEXT') ) );
		}
        
        if (ee()->db->field_exists('social_login_permissions', 'members') == FALSE)
		{
			ee()->dbforge->add_column('members', array('social_login_permissions' => array('type' => 'TEXT') ) );
		}
        
        ee()->db->select('module_id'); 
        $query = ee()->db->get_where('modules', array('module_name' => 'Social_login')); 
        if ($query->num_rows() > 0)
        {
            //this is Basic to Pro upgrade
            
            $data = array( 'module_name' => 'Social_login_pro' , 'module_version' => $this->version); 
            ee()->db->where('module_name', 'Social_login');
            ee()->db->update('modules', $data); 
            
            $data = array( 'class' => 'Social_login_pro'); 
            ee()->db->where('class', 'Social_login');
            ee()->db->update('actions', $data); 

        }
        else
        {
         
            $settings = array();
            
            $data = array( 'module_name' => 'Social_login_pro' , 'module_version' => $this->version, 'has_cp_backend' => 'y', 'settings'=> serialize($settings) ); 
            ee()->db->insert('modules', $data); 
            
            $data = array( 'class' => 'Social_login_pro' , 'method' => 'request_token' ); 
            ee()->db->insert('actions', $data); 
            
            $data = array( 'class' => 'Social_login_pro' , 'method' => 'access_token' ); 
            ee()->db->insert('actions', $data); 
            
        }
        
        ee()->db->select('action_id'); 
        $query = ee()->db->get_where('actions', array('class' => 'Social_login_pro',  'method' => 'save_userdata')); 
        if ($query->num_rows()==0)
        {
            $data = array( 'class' => 'Social_login_pro' , 'method' => 'save_userdata' ); 
            ee()->db->insert('actions', $data); 
        }
        
        $data = array( 'class' => 'Social_login_pro' , 'method' => 'access_token_loggedin' ); 
        ee()->db->insert('actions', $data); 
        
        $data = array( 'class' => 'Social_login_pro' , 'method' => 'save_permissions' ); 
        ee()->db->insert('actions', $data); 
        
        //install Shorteen
        ee()->db->select('module_id'); 
        $query = ee()->db->get_where('modules', array('module_name' => 'Shorteen')); 
        if ($query->num_rows() == 0)
        {
            $settings = array();
            $data = array( 'module_name' => 'Shorteen' , 'module_version' => '0.4.0', 'has_cp_backend' => 'y', 'settings'=> serialize($settings) ); 
            ee()->db->insert('modules', $data); 
            
            $data = array( 'class' => 'Shorteen' , 'method' => 'process' ); 
            ee()->db->insert('actions', $data); 
            
            ee()->db->query("CREATE TABLE IF NOT EXISTS `exp_shorteen` (
              `id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
              `service` varchar(20) NOT NULL,
              `url` varchar(255) NOT NULL,
              `shorturl` varchar(128) NOT NULL,
              `created` INT( 10 ) NOT NULL ,
              KEY `service` (`service`,`url`)
            )");
        }
        
        $fields = array(
			'template_id'		=> array('type' => 'INT',		'unsigned' => TRUE, 'auto_increment' => TRUE),
			'site_id'			=> array('type' => 'INT',		'unsigned' => TRUE, 'default' => 1),
			//'channel_id'		=> array('type' => 'INT',		'unsigned' => TRUE, 'default' => 0),
			'enable_template'	=> array('type' => 'CHAR',		'constraint'=> 1,	'default' => 'y'),
			'template_name'		=> array('type' => 'VARCHAR',	'constraint'=> 50,	'default' => ''),
			'template_data'		=> array('type' => 'TEXT'),
			//'template_link'		=> array('type' => 'VARCHAR',	'constraint'=> 255,	'default' => '')
		);

		ee()->dbforge->add_field($fields);
		ee()->dbforge->add_key('template_id', TRUE);
		ee()->dbforge->add_key('site_id');
		//ee()->dbforge->add_key('channel_id');
		ee()->dbforge->add_key('template_name');
		ee()->dbforge->create_table('social_login_templates', TRUE);
        
        //exp_social_login_session_data
        $fields = array(
			'session_id'	=> array('type' => 'VARCHAR',	'constraint'=> 250,	'default' => ''),
			'set_date'	    => array('type' => 'INT',		'unsigned' => TRUE, 'default' => 0),
			'data'			=> array('type' => 'TEXT')
		);

		ee()->dbforge->add_field($fields);
		ee()->dbforge->add_key('session_id', TRUE);
		ee()->dbforge->add_key('set_date');
		ee()->dbforge->create_table('social_login_session_data', TRUE);
        
        return TRUE; 
        
    }

    public function uninstall() { 

        ee()->db->select('module_id'); 
        $query = ee()->db->get_where('modules', array('module_name' => 'Social_login_pro')); 
        
        ee()->db->where('module_id', $query->row('module_id')); 
        ee()->db->delete('module_member_groups'); 
        
        ee()->db->where('module_name', 'Social_login_pro'); 
        ee()->db->delete('modules'); 
        
        ee()->db->where('class', 'Social_login_pro'); 
        ee()->db->delete('actions'); 
        
        ee()->db->query("DROP TABLE ".ee()->db->dbprefix."social_login_templates");
        ee()->db->query("DROP TABLE ".ee()->db->dbprefix."social_login_session_data");
        
        return TRUE; 
    }

    public function update($current='') { 
        if ($current < 0.2) 
        { 
            $data = array( 'class' => 'Social_login_pro' , 'method' => 'save_userdata' ); 
            ee()->db->insert('actions', $data); 
        } 
        if ($current < 0.4) 
        { 
            $sql = "SELECT screen_name FROM exp_members GROUP BY screen_name HAVING COUNT(screen_name) > 1";
            $q = ee()->db->query($sql);
            if ($q->num_rows() > 0)
            {
                $this->_update_screen_names($q);
            }
        } 
        if ($current < 0.6) 
        { 
            ee()->db->query("CREATE TABLE IF NOT EXISTS `exp_shorteen` (
              `id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
              `service` varchar(20) NOT NULL,
              `url` varchar(255) NOT NULL,
              `shorturl` varchar(128) NOT NULL,
              `created` INT( 10 ) NOT NULL ,
              KEY `service` (`service`,`url`)
            )");
        } 
        
        if ($current < 1.4)
        {
        	ee()->load->dbforge(); 
			
			//exp_social_login_session_data
	        $fields = array(
				'session_id'	=> array('type' => 'VARCHAR',	'constraint'=> 250,	'default' => ''),
				'set_date'	    => array('type' => 'INT',		'unsigned' => TRUE, 'default' => 0),
				'data'			=> array('type' => 'TEXT',		'default' => '')
			);
	
			ee()->dbforge->add_field($fields);
			ee()->dbforge->add_key('session_id', TRUE);
			ee()->dbforge->add_key('set_date');
			ee()->dbforge->create_table('social_login_session_data', TRUE);
        }
        
        return TRUE; 
    }

    public function _update_screen_names($q)
    {
        foreach ($q->result_array() as $row)
        {
            $i = 0;
            $sql = "SELECT member_id FROM exp_members WHERE screen_name='".ee()->db->escape_str($row['screen_name'])."' ORDER BY join_date ASC";
            $q2 = ee()->db->query($sql);
            foreach ($q2->result_array() as $row2)
            {
                if ($i>0)
                {
                    $data['screen_name'] = $row['screen_name']." ".$i;
                    ee()->db->where('member_id', $row2['member_id']);
                    ee()->db->update('members', $data); 
                }
                $i++;
            }
        }

        $sql = "SELECT screen_name FROM exp_members GROUP BY screen_name HAVING COUNT(screen_name) > 1";
        $q3 = ee()->db->query($sql);
        if ($q3->num_rows() > 0)
        {
            $this->_update_screen_names($q3);
        }
    }
}
/* END */
?>