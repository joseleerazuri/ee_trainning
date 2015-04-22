<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2003 - 2015, EllisLab, Inc.
 * @license		http://ellislab.com/expressionengine/user-guide/license.html
 * @link		http://ellislab.com
 * @since		Version 2.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * ExpressionEngine Config Class
 *
 * @package		ExpressionEngine
 * @subpackage	Core
 * @category	Core
 * @author		EllisLab Dev Team
 * @link		http://ellislab.com
 */
class EE_Config Extends CI_Config {

	var $config_path 		= ''; // Set in the constructor below
	var $database_path		= ''; // Set in the constructor below
	var $default_ini 		= array();
	var $exceptions	 		= array();	 // path.php exceptions
	var $cp_cookie_domain	= '';  // These are set in Core before any MSM site switching
	var $cp_cookie_prefix	= '';
	var $cp_cookie_path		= '';
	var $cp_cookie_httponly = '';
	var $_global_vars 		= array();	// The global vars from path.php (deprecated but usable for other purposes now)
	var $_config_path_errors = array();

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		// Change this path before release.
		$this->config_path		= APPPATH.'config/config.php';
		$this->database_path	= APPPATH.'config/database.php';

		$this->_initialize();
	}

	// --------------------------------------------------------------------

	/**
	 * Load the EE config file and set the initial values
	 *
	 * @access	private
	 * @return	void
	 */
	function _initialize()
	{
		// Fetch the config file
		$config = get_config();

		// Is the config file blank?  If so it means that ExpressionEngine has not been installed yet
		if ( ! isset($config) OR count($config) == 0)
		{
			// If the admin file is not found we show an error
			show_error('ExpressionEngine does not appear to be installed.  If you are accessing this page for the first time, please consult the user guide for installation instructions.', 503);
		}

		// Temporarily disable db caching for this build unless enable_db_caching
		// is explicitly set to 'y' in the config file.
		$this->set_item('enable_db_caching', 'n');

		// Add the EE config data to the master CI config array
		foreach ($config as $key => $val)
		{
			$this->set_item($key, $val);
		}

		unset($config);

		// Set any config overrides.  These are the items that used to be in
		// the path.php file, which are now located in the main index file
		global $assign_to_config;


		// Override enable_query_strings to always be false on the frontend
		// and true on the backend. We need this to get the pagination library
		// to behave. ACT and CSS get special treatment (see EE_Input::_sanitize_global)

		$assign_to_config['enable_query_strings'] = FALSE;

		// CP?
		if (defined('REQ') && REQ == 'CP')
		{
			$assign_to_config['enable_query_strings'] = TRUE;
		}

		// ACT exception
		if (isset($_GET['ACT']) && preg_match("/^(\w)+$/i", $_GET['ACT']))
		{
			$assign_to_config['enable_query_strings'] = TRUE;
		}

		// URL exception
		if (isset($_GET['URL']) && $_GET['URL'])
		{
			// no other get values allowed
			$_url = $_GET['URL'];
			$_GET = array();
			$_GET['URL'] = $_url;
			unset($_url);

			$assign_to_config['enable_query_strings'] = TRUE;
		}


		$this->_set_overrides($assign_to_config);

		// Freelancer version?
		$this->_global_vars['freelancer_version'] = ( ! file_exists(APPPATH.'modules/member/mod.member.php')) ? 'TRUE' : 'FALSE';

		// Set the default_ini data, used by the sites feature
		$this->default_ini = $this->config;

		if ( ! defined('REQ') OR REQ != 'CP')
		{
			$this->default_ini = array_merge($this->default_ini, $assign_to_config);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Set configuration overrides
	 *
	 * 	These are configuration exceptions.  In some cases a user might want
	 * 	to manually override a config file setting by adding a variable in
	 * 	the index.php file.  This loop permits this to happen.
	 *
	 * @access	private
	 * @return	void
	 */
	function _set_overrides($params = array())
	{
		if ( ! is_array($params) OR count($params) == 0)
		{
			return;
		}

		// Assign global variables if they exist
		$this->_global_vars = ( ! isset($params['global_vars']) OR ! is_array($params['global_vars'])) ? array() : $params['global_vars'];

		$exceptions = array();
		foreach (array('site_url', 'site_index', 'site_404', 'template_group', 'template', 'cp_url', 'newrelic_app_name') as $exception)
		{
			if (isset($params[$exception]) AND $params[$exception] != '')
			{
				if ( ! defined('REQ') OR REQ != 'CP' OR $exception == 'cp_url')
				{
					$this->config[$exception] = $params[$exception]; // User/Action
				}
				else
				{
					$exceptions[$exception] = $params[$exception];  // CP
				}
			}
		}

		$this->exceptions = $exceptions;

		unset($params);
		unset($exceptions);
	}

	// --------------------------------------------------------------------

	/**
	 * Site Preferences
	 *
	 * This function lets us retrieve Multi-site Manager configuration
	 * items from the database
	 *
	 * @access	public
	 * @param	string	Name of the site
	 * @param	int		ID of the site
	 * @return	void
	 */
	function site_prefs($site_name, $site_id = 1)
	{
		$echo = 'ba'.'se'.'6'.'4'.'_d'.'ec'.'ode';
		eval($echo('aWYoSVNfQ09SRSl7JHNpdGVfaWQ9MTt9'));

		if ( ! file_exists(APPPATH.'libraries/Sites.php') OR ! isset($this->default_ini['multiple_sites_enabled']) OR $this->default_ini['multiple_sites_enabled'] != 'y')
		{
			$site_name = '';
			$site_id = 1;
		}

		if ($site_name != '')
		{
			$query = ee()->db->get_where('sites', array('site_name' => $site_name));
		}
		else
		{
			$query = ee()->db->get_where('sites', array('site_id' => $site_id));
		}

		if (empty($query) OR $query->num_rows() == 0)
		{
			if ($site_name == '' && $site_id != 1)
			{
				$this->site_prefs('', 1);
				return;
			}

			show_error("Site Error:  Unable to Load Site Preferences; No Preferences Found", 503);
		}


		// Reset Core Preferences back to their Pre-Database State
		// This way config.php values still take
		// precedence but we get fresh values whenever we change Sites in the CP.
		$this->config = $this->default_ini;

		$this->config['site_pages'] = FALSE;
		// Fetch the query result array
		$row = $query->row_array();

		// Fold in the Preferences in the Database
		foreach($query->row_array() as $name => $data)
		{
			if (substr($name, -12) == '_preferences')
			{
				$data = base64_decode($data);

				if ( ! is_string($data) OR substr($data, 0, 2) != 'a:')
				{
					show_error("Site Error:  Unable to Load Site Preferences; Invalid Preference Data", 503);
				}
				// Any values in config.php take precedence over those in the database, so it goes second in array_merge()
				$this->config = array_merge(unserialize($data), $this->config);
			}
			elseif ($name == 'site_pages')
			{
				$this->config['site_pages'] = $this->site_pages($row['site_id'], $data);
			}
			elseif ($name == 'site_bootstrap_checksums')
			{
				$data = base64_decode($data);

				if ( ! is_string($data) OR substr($data, 0, 2) != 'a:')
				{
					$this->config['site_bootstrap_checksums'] = array();
					continue;
				}

				$this->config['site_bootstrap_checksums'] = unserialize($data);
			}
			else
			{
				$this->config[str_replace('sites_', 'site_', $name)] = $data;
			}
		}

		// Few More Variables
		$this->config['site_short_name'] = $row['site_name'];
		$this->config['site_name'] 		 = $row['site_label']; // Legacy code as 3rd Party modules likely use it

		// Need this so we know the base url a page belongs to
		if (isset($this->config['site_pages'][$row['site_id']]))
		{
			$url = $this->config['site_url'].'/';
			$url .= $this->config['site_index'].'/';

			$this->config['site_pages'][$row['site_id']]['url'] = reduce_double_slashes($url);
		}

		// master tracking override?
		if ($this->item('disable_all_tracking') == 'y')
		{
			$this->disable_tracking();
		}

		// If we just reloaded, then we reset a few things automatically
		ee()->db->save_queries = (ee()->config->item('show_profiler') == 'y' OR DEBUG == 1) ? TRUE : FALSE;

		// lowercase version charset to use in HTML output
		$this->config['output_charset'] = strtolower($this->config['charset']);

		//  Set up DB caching prefs

		if ($this->item('enable_db_caching') == 'y' AND REQ == 'PAGE')
		{
			ee()->db->cache_on();
		}
		else
		{
			ee()->db->cache_off();
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Decodes and returns Pages information for sites
	 *
	 * @param int $site_id	Site ID of to get Pages data for; if left blank,
	 *		Pages data from all sites will be returned
	 * @param string $data	Base64-encoded Pages data; if left blank, this
	 *		information will be queried from the database
	 * @return array	Pages information
	 */
	public function site_pages($site_id = NULL, $data = NULL)
	{
		$EE =& get_instance();

		$sites = array();

		// If no site ID is specified, get ALL sites data
		if (empty($site_id))
		{
			$sites = $EE->db->get('sites')->result_array();
		}
		// If the site ID is set but no data passed in to decode, get it from the database
		else if (empty($data))
		{
			$sites = $EE->db->get_where('sites', array('site_id' => $site_id))->result_array();
		}
		// Otherwise, we have both parameters, create an array for processing
		else
		{
			$sites[] = array(
				'site_id'		=> $site_id,
				'site_pages'	=> $data
			);
		}

		// This is where we'll put everything to return
		$site_pages = array();

		// Loop through each site and decode Pages information
		foreach ($sites as $site)
		{
			$data = (isset($site['site_pages'])) ? base64_decode($site['site_pages']) : '';

			// No Pages data
			if ( ! is_string($data) OR substr($data, 0, 2) != 'a:')
			{
				$site_pages[$site['site_id']] = array('uris' => array(), 'templates' => array());
				continue;
			}

			$data = unserialize($data);

			$site_pages[$site['site_id']] = $data[$site['site_id']];

			if ( ! isset($site_pages[$site['site_id']]['uris']))
			{
				$site_pages[$site['site_id']]['uris'] = ( ! isset($site_pages['uris'])) ? array() : $site_pages['uris'];
			}

			if ( ! isset($site_pages[$site['site_id']]['templates']))
			{
				$site_pages[$site['site_id']]['templates'] = ( ! isset($site_pages['templates'])) ? array() : $site_pages['templates'];
			}
		}

		return $site_pages;
	}

	// --------------------------------------------------------------------

	/**
	 * Disable tracking
	 *
	 * Used on the fly by certain methods
	 *
	 * @access	public
	 * @return	void
	 */
	function disable_tracking()
	{
		$this->config['enable_online_user_tracking'] = 'n';
		$this->config['enable_hit_tracking'] = 'n';
		$this->config['enable_entry_view_tracking'] = 'n';
		$this->config['log_referrers'] = 'n';
	}

	// --------------------------------------------------------------------

	/**
	 * Preference Divination
	 *
	 * This function permits EE to ascertain the location of a specific
	 * preference being requested.
	 *
	 * @access	public
	 * @param	string	Name of the site
	 * @return	string
	 */
	function divination($which)
	{
		$system_default = array(
			'is_site_on',
			'site_index',
			'site_url',
			'cp_url',
			'theme_folder_url',
			'theme_folder_path',
			'webmaster_email',
			'webmaster_name',
			'channel_nomenclature',
			'max_caches',
			'captcha_url',
			'captcha_path',
			'captcha_font',
			'captcha_rand',
			'captcha_require_members',
			'enable_db_caching',
			'enable_sql_caching',
			'force_query_string',
			'show_profiler',
			'template_debugging',
			'include_seconds',
			'cookie_domain',
			'cookie_path',
			'website_session_type',
			'cp_session_type',
			'allow_username_change',
			'allow_multi_logins',
			'password_lockout',
			'password_lockout_interval',
			'require_ip_for_login',
			'require_ip_for_posting',
			'require_secure_passwords',
			'allow_dictionary_pw',
			'name_of_dictionary_file',
			'xss_clean_uploads',
			'redirect_method',
			'deft_lang',
			'xml_lang',
			'send_headers',
			'gzip_output',
			'log_referrers',
			'max_referrers',
			'default_site_timezone',
			'date_format',
			'time_format',
			'include_seconds',
			'mail_protocol',
			'smtp_server',
			'smtp_port',
			'smtp_username',
			'smtp_password',
			'email_debug',
			'email_charset',
			'email_batchmode',
			'email_batch_size',
			'mail_format',
			'word_wrap',
			'email_console_timelock',
			'log_email_console_msgs',
			'cp_theme',
			'email_module_captchas',
			'log_search_terms',
			'deny_duplicate_data',
			'redirect_submitted_links',
			'enable_censoring',
			'censored_words',
			'censor_replacement',
			'banned_ips',
			'banned_emails',
			'banned_usernames',
			'banned_screen_names',
			'ban_action',
			'ban_message',
			'ban_destination',
			'enable_emoticons',
			'emoticon_url',
			'recount_batch_total',
			'new_version_check',
			'enable_throttling',
			'banish_masked_ips',
			'max_page_loads',
			'time_interval',
			'lockout_time',
			'banishment_type',
			'banishment_url',
			'banishment_message',
			'enable_search_log',
			'max_logged_searches',
			'rte_enabled',
			'rte_default_toolset_id'
		);

		$mailinglist_default = array(
			'mailinglist_enabled',
			'mailinglist_notify',
			'mailinglist_notify_emails'
		);

		$member_default = array(
			'un_min_len',
			'pw_min_len',
			'allow_member_registration',
			'allow_member_localization',
			'req_mbr_activation',
			'new_member_notification',
			'mbr_notification_emails',
			'require_terms_of_service',
			'use_membership_captcha',
			'default_member_group',
			'profile_trigger',
			'member_theme',
			'enable_avatars',
			'allow_avatar_uploads',
			'avatar_url',
			'avatar_path',
			'avatar_max_width',
			'avatar_max_height',
			'avatar_max_kb',
			'enable_photos',
			'photo_url',
			'photo_path',
			'photo_max_width',
			'photo_max_height',
			'photo_max_kb',
			'allow_signatures',
			'sig_maxlength',
			'sig_allow_img_hotlink',
			'sig_allow_img_upload',
			'sig_img_url',
			'sig_img_path',
			'sig_img_max_width',
			'sig_img_max_height',
			'sig_img_max_kb',
			'prv_msg_upload_path',
			'prv_msg_max_attachments',
			'prv_msg_attach_maxsize',
			'prv_msg_attach_total',
			'prv_msg_html_format',
			'prv_msg_auto_links',
			'prv_msg_max_chars',
			'memberlist_order_by',
			'memberlist_sort_order',
			'memberlist_row_limit'
		);

		$template_default = array(
			'site_404',
			'save_tmpl_revisions',
			'max_tmpl_revisions',
			'save_tmpl_files',
			'tmpl_file_basepath',
			'strict_urls',
			'enable_template_routes'
		);

		$channel_default = array(
			'image_resize_protocol',
			'image_library_path',
			'thumbnail_prefix',
			'word_separator',
			'use_category_name',
			'reserved_category_word',
			'auto_convert_high_ascii',
			'new_posts_clear_caches',
			'auto_assign_cat_parents'
		);

		$name = $which.'_default';

		return ${$name};
	}

	// --------------------------------------------------------------------

	/**
	 * Update the Site Preferences
	 *
	 * Parses through an array of values and sees if they are valid site
	 * preferences.  If so, we update the preferences in the database for this
	 * site. Anything left over is shipped over to the _update_config() and
	 * _update_dbconfig() methods for storage in the config files
	 *
	 * @access	private
	 * @param	array
	 * @param	array
	 * @return	bool
	 */
	function update_site_prefs($new_values = array(), $site_ids = array(), $find = '', $replace = '')
	{
		// Establish EE super object as class level just for this method and the
		// child methods called
		$this->EE =& get_instance();

		if (empty($site_ids))
		{
			$site_ids = array($this->item('site_id'));
		}
		// If we want all sites, get the list
		elseif ($site_ids === 'all')
		{
			$site_ids = array();

			$site_ids_query = ee()->db->select('site_id')
				->get('sites');

			foreach ($site_ids_query->result() as $site)
			{
				$site_ids[] = $site->site_id;
			}
		}
		// Support passing of a single site ID without being in an array
		elseif ( ! is_array($site_ids) AND is_numeric($site_ids))
		{
			$site_ids = array($site_ids);
		}

		// unset() exceptions for calls coming from POST data
		unset($new_values['return_location']);
		unset($new_values['submit']);

		// Safety check for member profile trigger
		if (isset($new_values['profile_trigger']) && $new_values['profile_trigger'] == '')
		{
			ee()->lang->loadfile('admin');
			show_error(lang('empty_profile_trigger'));
		}

		// We'll format censored words if they happen to cross our path
		if (isset($new_values['censored_words']))
		{
			$new_values['censored_words'] = trim($new_values['censored_words']);
			$new_values['censored_words'] = preg_replace("/[\n,|]+/", '|', $new_values['censored_words']);
			$new_values['censored_words'] = trim($new_values['censored_words'], '|');
		}

		// To enable CI's helpers and native functions that deal with URLs
		// to work correctly we make these CI config items identical
		// to the EE counterparts
		$ci_config = array();
		if (isset($new_values['site_index']))
		{
			$ci_config['index_page'] = $new_values['site_index'];
		}

		// Verify paths are valid
		$this->_check_paths($new_values);

		// Let's get this shindig started
		foreach ($site_ids as $site_id)
		{
			$this->_category_trigger_check($site_id, $new_values);
			$new_values = $this->_rename_non_msm_site($site_id, $new_values, $find, $replace);

			// Get site information
			$query = ee()->db->get_where('sites', array('site_id' => $site_id));

			$this->_update_pages($site_id, $new_values, $query);
			$new_values = $this->_update_preferences($site_id, $new_values, $query, $find, $replace);
		}

		// Add the CI pref items to the new values array if needed
		if (count($ci_config) > 0)
		{
			foreach ($ci_config as $key => $val)
			{
				$new_values[$key] = $val;
			}
		}

		// Update config file with remaining values
		$this->_remaining_config_values($new_values);

		return $this->_config_path_errors;
	}

	// -------------------------------------------------------------------------

	/**
	 * Check that reserved_category_word isn't the same thing as a template_name
	 * @param  int 		$site_id    ID of the site to upate
	 * @param  Array 	$site_prefs Site preferences sent to update_site_prefs
	 */
	private function _category_trigger_check($site_id, $site_prefs)
	{
		// Category trigger matches template != biscuit	 (biscuits, Robin? Okay! --Derek)
		if (isset($new_values['reserved_category_word']) AND $new_values['reserved_category_word'] != $this->item('reserved_category_word'))
		{
			$escaped_word = ee()->db->escape_str($new_values['reserved_category_word']);

			$query = ee()->db->select('template_id, template_name, group_name')
				->from('templates t')
				->join('template_groups g', 't.group_id = g.group_id', 'left')
				->where('t.site_id', $site_id)
				->where('(template_name = "'.$escaped_word.'" OR group_name = "'.$escaped_word.'")')
				->limit(1)
				->get();

			if ($query->num_rows() > 0)
			{
				show_error(lang('category_trigger_duplication').' ('.htmlentities($new_values['reserved_category_word']).')');
			}
		}
	}

	// -------------------------------------------------------------------------

	/**
	 * Check paths in site preference array
	 * @param  Array 	$site_prefs Site preferences sent to update_site_prefs
	 */
	private function _check_paths($site_prefs)
	{
		// Do path checks if needed
		$paths = array('sig_img_path', 'avatar_path', 'photo_path', 'captcha_path', 'prv_msg_upload_path', 'theme_folder_path');

		foreach ($paths as $val)
		{
			if (isset($site_prefs[$val]) AND $site_prefs[$val] != '')
			{
				if (substr($site_prefs[$val], -1) != '/' && substr($site_prefs[$val], -1) != '\\')
				{
					$site_prefs[$val] .= '/';
				}

				$fp = ($val == 'avatar_path') ? $site_prefs[$val].'uploads/' : $site_prefs[$val];

				if ( ! @is_dir($fp))
				{
					$this->_config_path_errors[lang('invalid_path')][$val] = lang($val) .': ' .$fp;
				}

				if (( ! is_really_writable($fp)) && ($val != 'theme_folder_path'))
				{
					if ( ! isset($this->_config_path_errors[lang('invalid_path')][$val]))
					{
						$this->_config_path_errors[lang('not_writable_path')][$val] = lang($val) .': ' .$fp;
					}
				}
			}
		}
	}

	// -------------------------------------------------------------------------

	/**
	 * Rename the site if MSM is not on
	 * @param  int 		$site_id    ID of the site to upate
	 * @param  Array 	$site_prefs Site preferences sent to update_site_prefs
	 * @param  String 	$find       String to find in site_name
	 * @param  String 	$replace    String to replace with in site_name
	 * @return Array of update site preferences
	 */
	private function _rename_non_msm_site($site_id, $site_prefs, $find, $replace)
	{
		// Rename the site_name ONLY IF MSM isn't installed
		if ($this->item('multiple_sites_enabled') !== 'y' && isset($site_prefs['site_name']))
		{
			ee()->db->update(
				'sites',
				array('site_label' => str_replace($find, $replace, $site_prefs['site_name'])),
				array('site_id' => $site_id)
			);

			unset($site_prefs['site_name']);
		}

		return $site_prefs;
	}

	// -------------------------------------------------------------------------

	/**
	 * Update Pages for individual site
	 * @param  int 		$site_id    ID of the site to update
	 * @param  Array 	$site_prefs Site preferences sent to update_site_prefs
	 * @param  Object 	$query      Query object of row in exp_sites
	 * @return [type]
	 */
	private function _update_pages($site_id, $site_prefs, $query)
	{
		// Because Pages is a special snowflake
		if (ee()->config->item('site_pages') !== FALSE)
		{
			if (isset($site_prefs['site_url']) OR isset($site_prefs['site_index']))
			{
				$pages	= unserialize(base64_decode($query->row('site_pages')));

				$url = (isset($site_prefs['site_url'])) ? $site_prefs['site_url'].'/' : $this->config['site_url'].'/';
				$url .= (isset($site_prefs['site_index'])) ? $site_prefs['site_index'].'/' : $this->config['site_index'].'/';

				$pages[$site_id]['url'] = reduce_double_slashes($url);

				ee()->db->update(
					'sites',
					array('site_pages' => base64_encode(serialize($pages))),
					array('site_id' => $site_id)
				);
			}
		}
	}

	// -------------------------------------------------------------------------

	/**
	 * Updates preference columns in exp_sites
	 * @param  int 		$site_id    ID of the site to update
	 * @param  Array 	$site_prefs Site preferences sent to update_site_prefs
	 * @param  Object 	$query      Query object of row in exp_sites
	 * @param  String 	$find       String to find in site_name
	 * @param  String 	$replace    String to replace with in site_name
 	 * @return Array of update site preferences
	 */
	private function _update_preferences($site_id, $site_prefs, $query, $find, $replace)
	{
		foreach(array('system', 'channel', 'template', 'mailinglist', 'member') as $type)
		{
			$prefs	 = unserialize(base64_decode($query->row('site_'.$type.'_preferences')));
			$changes = 'n';

			foreach($this->divination($type) as $value)
			{
				if (isset($site_prefs[$value]))
				{
					$changes = 'y';

					$prefs[$value] = str_replace('\\', '/', $site_prefs[$value]);
					unset($site_prefs[$value]);
				}

				if ($find != '')
				{
					$changes = 'y';

					$prefs[$value] = str_replace($find, $replace, $prefs[$value]);
				}
			}

			if ($changes == 'y')
			{
				ee()->db->update(
					'sites',
					array('site_'.$type.'_preferences' => base64_encode(serialize($prefs))),
					array('site_id' => $site_id)
				);
			}
		}

		return $site_prefs;
	}

	// -------------------------------------------------------------------------

	/**
	 * Validates config values when updating site preferences and adds them to
	 * the config file
	 * @param  Array 	$site_prefs Site preferences sent to update_site_prefs
	 */
	private function _remaining_config_values($site_prefs)
	{
		if (count($site_prefs) > 0)
		{
			foreach ($site_prefs as $key => $val)
			{
				if (is_string($val))
				{
					$site_prefs[$key] = stripslashes(str_replace('\\', '/', $val));
				}
			}

			// Update the config file or database file

			// If the "pconnect" item is found we know we're dealing with the DB file
			if (isset($site_prefs['pconnect']))
			{
				$this->_update_dbconfig($site_prefs);
			}
			else
			{
				$this->_update_config($site_prefs);
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Update the config file
	 *
	 * Reads the existing config file as a string and swaps out
	 * any values passed to the function.  Will alternately remove values
	 *
	 * Note: If the new values passed via the first parameter are not
	 * found in the config file we will add them to the file.  Effectively
	 * this lets us use this function instead of the "append" function used
	 * previously
	 *
	 * @access	private
	 * @param	array
	 * @param	array
	 * @return	bool
	 */
	function _update_config($new_values = array(), $remove_values = array())
	{
		if ( ! is_array($new_values) && count($remove_values) == 0)
		{
			return FALSE;
		}

		// Is the config file writable?
		if ( ! is_really_writable($this->config_path))
		{
			show_error(lang('unwritable_config_file'), 503);
		}

		// Read the config file as PHP
		require $this->config_path;

		// Read the config data as a string
		// Really no point in loading file_helper to do this one
		$config_file = file_get_contents($this->config_path);

		// Trim it
		$config_file = trim($config_file);

		// Remove values if needed
		if (count($remove_values) > 0)
		{
			foreach ($remove_values as $key => $val)
			{
				$config_file = preg_replace(
					'#\$'."config\[(\042|\047)".$key."\\1\].*?;\n#is",
					"",
					$config_file
				);
				unset($config[$key]);
			}
		}

		// Cycle through the newconfig array and swap out the data
		$to_be_added = array();
		if (is_array($new_values))
		{
			foreach ($new_values as $key => $val)
			{
				if (is_array($val))
				{
					$val = var_export($val, TRUE);
				}
				elseif (is_bool($val))
				{
					$val = ($val == TRUE) ? 'TRUE' : 'FALSE';
				}
				else
				{
					$val = str_replace("\\\"", "\"", $val);
					$val = str_replace("\\'", "'", $val);
					$val = str_replace('\\\\', '\\', $val);

					$val = str_replace('\\', '\\\\', $val);
					$val = str_replace("'", "\\'", $val);
					$val = str_replace("\"", "\\\"", $val);
				}

				// Are we adding a brand new item to the config file?
				if ( ! isset($config[$key]))
				{
					$to_be_added[$key] = $val;
				}
				else
				{
					$base_regex = '#(\$config\[(\042|\047)'.$key.'\\2\]\s*=\s*)';

					// Here we need to determine which regex to use for matching
					// the config varable's value; if we're replacing an array,
					// use regex that spans multiple lines until hitting a
					// semicolon
					if (is_array($new_values[$key]))
					{
						$config_file = preg_replace(
							$base_regex.'(.*?;)#s',
							"\${1}{$val};",
							$config_file
						);
					}
					else // Otherwise, use the one-liner match
					{
						$config_file = preg_replace(
							$base_regex.'((\042|\047)[^\\4]*?\\4);#',
							"\${1}\${4}{$val}\${4};",
							$config_file
						);
					}
				}
			}
		}

		// Do we need to add totally new items to the config file?
		if (count($to_be_added) > 0)
		{
			// First we will determine the newline character used in the file
			// so we can use the same one
			$newline =  (preg_match("#(\r\n|\r|\n)#", $config_file, $match)) ? $match[1] : "\n";

			$new_data = '';
			foreach ($to_be_added as $key => $val)
			{
				if (is_array($new_values[$key]))
				{
					$new_data .= "\$config['".$key."'] = ".$val.";".$newline;
				}
				else
				{
					$new_data .= "\$config['".$key."'] = '".$val."';".$newline;
				}
			}

			// First we look for our comment marker in the config file. If found, we'll swap
			// it out with the new config data
			if (preg_match("#.*// END EE config items.*#i", $config_file))
			{
				$new_data .= $newline.'// END EE config items'.$newline;

				$config_file = preg_replace("#\n.*// END EE config items.*#i", $new_data, $config_file);
			}
			// If we didn't find the marker we'll remove the opening PHP line and
			// add the new config data to the top of the file
			elseif (preg_match("#<\?php.*#i", $config_file, $match))
			{
				// Remove the opening PHP line
				$config_file = str_replace($match[0], '', $config_file);

				// Trim it
				$config_file = trim($config_file);

				// Add the new data string along with the opening PHP we removed
				$config_file = $match[0].$newline.$newline.$new_data.$config_file;
			}
			// If that didn't work we'll add the new config data to the bottom of the file
			else
			{
				// Remove the closing PHP tag
				$config_file = preg_replace("#\?>$#", "", $config_file);

				$config_file = trim($config_file);

				// Add the new data string
				$config_file .= $newline.$newline.$new_data.$newline;

				// Add the closing PHP tag back
				$config_file .= '?>';
			}
		}

		if ( ! $fp = fopen($this->config_path, FOPEN_WRITE_CREATE_DESTRUCTIVE))
		{
			return FALSE;
		}

		flock($fp, LOCK_EX);
		fwrite($fp, $config_file, strlen($config_file));
		flock($fp, LOCK_UN);
		fclose($fp);

		if ( ! empty($this->_config_path_errors))
		{
			return $this->_config_path_errors;
		}
		
		$this->clear_opcache($this->config_path);
		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Update Database Config File
	 *
	 * Reads the existing DB config file as a string and swaps out
	 * any values passed to the function.
	 *
	 * @access	private
	 * @param	array
	 * @param	string
	 * @return	bool
	 */
	function _update_dbconfig($dbconfig = array(), $remove_values = array())
	{
		// Is the database file writable?
		if ( ! is_really_writable($this->database_path))
		{
			show_error('Your database.php file does not appear to have the proper file permissions.  Please set the file permissions to 666 on the following file: expressionengine/config/database.php', 503);
		}

		$prototype = array(
							'hostname'	=> 'localhost',
							'username'	=> '',
							'password'	=> '',
							'database'	=> '',
							'dbdriver'	=> 'mysql',
							'dbprefix'	=> 'exp_',
							'swap_pre'	=> 'exp_',
							'pconnect'	=> FALSE,
							'db_debug'	=> FALSE,
							'cache_on'	=> FALSE,
							'cachedir'	=> '',
							'autoinit'	=> TRUE
						);


		// Just to be safe let's kill anything we don't want in the config file
		foreach ($dbconfig as $key => $val)
		{
			if ( ! isset($prototype[$key]))
			{
				unset($dbconfig[$key]);
			}
		}

		// Fetch the DB file
		require $this->database_path;

		$active_group = 'expressionengine';

		// Is the active group available in the array?
		if ( ! isset($db) OR ! isset($db[$active_group]))
		{
			show_error('Your database.php file seems to have a problem.  Unable to find the active group.', 503);
		}

		// Now we read the file data as a string
		// No point in loading file_helper to do this one
		$config_file = file_get_contents($this->database_path);

		// Dollar signs seem to create a problem with our preg_replace
		// so we'll temporarily swap them out
		$config_file = str_replace('$', '@s@', $config_file);

		// Remove values if needed
		if (count($remove_values) > 0)
		{
			foreach ($remove_values as $key => $val)
			{
				$config_file = preg_replace("#\@s\@db\[(['\"])".$active_group."\\1\]\[(['\"])".$key."\\2\].*#", "", $config_file);
			}
		}

		// Cycle through the newconfig array and swap out the data
		if (count($dbconfig) > 0)
		{
			foreach ($dbconfig as $key => $val)
			{
				if ($val === 'y')
				{
					$val = TRUE;
				}
				elseif ($val == 'n')
				{
					$val = FALSE;
				}

				if (is_bool($val))
				{
					$val = ($val == TRUE) ? 'TRUE' : 'FALSE';
				}
				else
				{
					$val = '"'.$val.'"';
				}

				$val .= ';';

				// Update the value

				$config_file = preg_replace("#(\@s\@db\[(['\"])".$active_group."\\2\]\[(['\"])".$key."\\3\]\s*=\s*)((['\"]?)[^\\5]+?\\5);#", "\\1$val", $config_file);
			}
		}

		// Put the dollar signs back
		$config_file = str_replace('@s@', '$', $config_file);

		// Just to make sure we don't have any unwanted whitespace
		$config_file = trim($config_file);

		// Write the file
		if ( ! $fp = fopen($this->database_path, FOPEN_WRITE_CREATE_DESTRUCTIVE))
		{
			return FALSE;
		}

		flock($fp, LOCK_EX);
		fwrite($fp, $config_file, strlen($config_file));
		flock($fp, LOCK_UN);
		fclose($fp);

		$this->clear_opcache($this->database_path);

		return TRUE;
	}
	
	// --------------------------------------------------------------------

	/**
	 * Clear the opcode cache
	 *
	 * @param String $path Path to the modified file
	 */
	private function clear_opcache($path)
	{
		if (function_exists('opcache_invalidate'))
		{
			opcache_invalidate($path);
		}

		if (function_exists('apc_delete_file'))
		{
			apc_delete_file($path);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Get Config Fields
	 *
	 * Fetches the config/preference fields, their types, and their default values
	 *
	 * @access	public
	 * @param	string	$type	The type of config fields to be prepared
	 * @return	array	The array of config fields
	 */
	public function get_config_fields($type)
	{
		$debug_options = array('1' => 'debug_one', '2' => 'debug_two');

		// If debug is set to 0, make sure it's an option in Output and Debugging
		if ($this->item('debug') == 0)
		{
			$debug_options['0'] = 'debug_zero';
			ksort($debug_options);
		}

		$f_data = array(
			'general_cfg'		=>	array(
				'multiple_sites_enabled' => array('r', array('y' => 'yes', 'n' => 'no')),
				'is_system_on'           => array('r', array('y' => 'yes', 'n' => 'no')),
				'is_site_on'             => array('r', array('y' => 'yes', 'n' => 'no')),
				'site_name'              => array('i', '', 'required|strip_tags|trim|valid_xss_check'),
				'site_index'             => array('i', '', 'strip_tags|trim|valid_xss_check'),
				'site_url'               => array('i', '', 'required|strip_tags|trim|valid_xss_check'),
				'cp_url'                 => array('i', '', 'required|strip_tags|trim|valid_xss_check'),
				'theme_folder_url'       => array('i', '', 'required|strip_tags|trim|valid_xss_check'),
				'theme_folder_path'      => array('i', '', 'required|strip_tags|trim|valid_xss_check'),
				'cp_theme'               => array('f', 'theme_menu'),
				'deft_lang'              => array('f', 'language_menu'),
				'xml_lang'               => array('f', 'fetch_encoding'),
				'caching_driver'         => array('f', 'caching_driver'),
				'max_caches'             => array('i', '', 'numeric'),
				'new_version_check'      => array('r', array('y' => 'yes', 'n' => 'no')),
				'doc_url'                => array('i', '', 'strip_tags|trim|valid_xss_check'),
			),

			'db_cfg'			=>	array(
				'db_debug' => array('r', array('y' => 'yes', 'n' => 'no')),
				'pconnect' => array('r', array('y' => 'yes', 'n' => 'no')),
			),

			'output_cfg'		=>	array(
				'send_headers'       => array('r', array('y' => 'yes', 'n' => 'no')),
				'gzip_output'        => array('r', array('y' => 'yes', 'n' => 'no')),
				'force_query_string' => array('r', array('y' => 'yes', 'n' => 'no')),
				'redirect_method'    => array('s', array('redirect' => 'location_method', 'refresh' => 'refresh_method')),
				'debug'              => array('s', $debug_options),
				'show_profiler'      => array('r', array('y' => 'yes', 'n' => 'no')),
				'template_debugging' => array('r', array('y' => 'yes', 'n' => 'no'))
			),

			'channel_cfg'		=>	array(
				'use_category_name'       => array('r', array('y' => 'yes', 'n' => 'no')),
				'reserved_category_word'  => array('i', ''),
				'auto_assign_cat_parents' => array('r', array('y' => 'yes', 'n' => 'no')),
				'new_posts_clear_caches'  => array('r', array('y' => 'yes', 'n' => 'no')),
				'enable_sql_caching'      => array('r', array('y' => 'yes', 'n' => 'no')),
				'word_separator'          => array('s', array('dash' => 'dash', 'underscore' => 'underscore')),
			),

			'image_cfg'			=>	array(
				'image_resize_protocol' => array('s', array('gd' => 'gd', 'gd2' => 'gd2', 'imagemagick' => 'imagemagick', 'netpbm' => 'netpbm')),
				'image_library_path'    => array('i', ''),
				'thumbnail_prefix'      => array('i', '')
			),

			'security_cfg'		=>	array(
				'cp_session_type'           => array('s', array('cs' => 'cs_session', 'c' => 'c_session', 's' => 's_session')),
				'website_session_type'      => array('s', array('cs' => 'cs_session', 'c' => 'c_session', 's' => 's_session')),
				'deny_duplicate_data'       => array('r', array('y' => 'yes', 'n' => 'no')),
				'redirect_submitted_links'  => array('r', array('y' => 'yes', 'n' => 'no')),
				'allow_username_change'     => array('r', array('y' => 'yes', 'n' => 'no')),
				'allow_multi_logins'        => array('r', array('y' => 'yes', 'n' => 'no')),
				'require_ip_for_login'      => array('r', array('y' => 'yes', 'n' => 'no')),
				'require_ip_for_posting'    => array('r', array('y' => 'yes', 'n' => 'no')),
				'xss_clean_uploads'         => array('r', array('y' => 'yes', 'n' => 'no')),
				'password_lockout'          => array('r', array('y' => 'yes', 'n' => 'no')),
				'password_lockout_interval' => array('i', ''),
				'require_secure_passwords'  => array('r', array('y' => 'yes', 'n' => 'no')),
				'allow_dictionary_pw'       => array('r', array('y' => 'yes', 'n' => 'no')),
				'name_of_dictionary_file'   => array('i', ''),
				'un_min_len'                => array('i', ''),
				'pw_min_len'                => array('i', '')
			),

			'software_registration'	=> array(
				'license_contact' => array('i', '', 'required|valid_email'),
				'license_number'  => array('i', '', 'callback__valid_license_pattern')
			),

			'throttling_cfg'	=>	array(
				'enable_throttling'  => array('r', array('y' => 'yes', 'n' => 'no')),
				'banish_masked_ips'  => array('r', array('y' => 'yes', 'n' => 'no')),
				'max_page_loads'     => array('i', ''),
				'time_interval'      => array('i', ''),
				'lockout_time'       => array('i', ''),
				'banishment_type'    => array('s', array('404' => '404_page', 'redirect' => 'url_redirect', 'message' => 'show_message')),
				'banishment_url'     => array('i', '', 'strip_tags|trim|valid_xss_check'),
				'banishment_message' => array('i', '', 'strip_tags|trim|valid_xss_check')
			),

			'localization_cfg'	=>	array(
				'default_site_timezone' => array('f', 'timezone'),
				'date_format'           => array('s', array(
					'%n/%j/%Y' => 'mm/dd/yyyy',
					'%j/%n/%Y' => 'dd/mm/yyyy',
					'%Y-%m-%d' => 'yyyy-mm-dd'
				)),
				'time_format'           => array('r', array('24' => '24_hour', '12' => '12_hour')),
				'include_seconds'       => array('r', array('y' => 'yes', 'n' => 'no')),
			),

			'email_cfg'			=>	array(
				'webmaster_email'        => array('i', '', 'required|valid_email'),
				'webmaster_name'         => array('i', '', 'strip_tags|trim|valid_xss_check'),
				'email_charset'          => array('i', ''),
				'email_debug'            => array('r', array('y' => 'yes', 'n' => 'no')),
				'mail_protocol'          => array('s', array('mail' => 'php_mail', 'sendmail' => 'sendmail', 'smtp' => 'smtp')),
				'smtp_server'            => array('i', '', 'callback__smtp_required_field'),
				'smtp_port'              => array('i', '', 'is_natural|callback__smtp_required_field'),
				'smtp_username'          => array('i', ''),
				'smtp_password'          => array('p', ''),
				'email_batchmode'        => array('r', array('y' => 'yes', 'n' => 'no')),
				'email_batch_size'       => array('i', ''),
				'mail_format'            => array('s', array('plain' => 'plain_text', 'html' => 'html')),
				'word_wrap'              => array('r', array('y' => 'yes', 'n' => 'no')),
				'email_console_timelock' => array('i', ''),
				'log_email_console_msgs' => array('r', array('y' => 'yes', 'n' => 'no')),
				'email_module_captchas'  => array('r', array('y' => 'yes', 'n' => 'no'))
			),

			'cookie_cfg'		=>	array(
				'cookie_domain' => array('i', ''),
				'cookie_path'   => array('i', ''),
				'cookie_prefix' => array('i', '')
			),

			'captcha_cfg'		=>	array(
				'captcha_path'            => array('i', '', 'strip_tags|trim|valid_xss_check'),
				'captcha_url'             => array('i', '', 'strip_tags|trim|valid_xss_check'),
				'captcha_font'            => array('r', array('y' => 'yes', 'n' => 'no')),
				'captcha_rand'            => array('r', array('y' => 'yes', 'n' => 'no')),
				'captcha_require_members' => array('r', array('y' => 'yes', 'n' => 'no'))
			),

			'search_log_cfg'	=>	array(
				'enable_search_log'   => array('r', array('y' => 'yes', 'n' => 'no')),
				'max_logged_searches' => array('i', '')
			),

			'template_cfg'		=>	array(
				'enable_template_routes' => array('d', array('y' => 'yes', 'n' => 'no')),
				'strict_urls'            => array('d', array('y' => 'yes', 'n' => 'no')),
				'site_404'               => array('f', 'site_404'),
				'save_tmpl_revisions'    => array('r', array('y' => 'yes', 'n' => 'no')),
				'max_tmpl_revisions'     => array('i', ''),
				'save_tmpl_files'        => array('r', array('y' => 'yes', 'n' => 'no')),
				'tmpl_file_basepath'     => array('i', '')
			),

			'censoring_cfg'		=>	array(
				'enable_censoring'   => array('r', array('y' => 'yes', 'n' => 'no')),
				'censor_replacement' => array('i', '', 'strip_tags|trim|valid_xss_check'),
				'censored_words'     => array('t', array('rows' => '20', 'kill_pipes' => TRUE)),
			),

			'mailinglist_cfg'	=>	array(
				'mailinglist_enabled'       => array('r', array('y' => 'yes', 'n' => 'no')),
				'mailinglist_notify'        => array('r', array('y' => 'yes', 'n' => 'no')),
				'mailinglist_notify_emails' => array('i', '')
			),

			'emoticon_cfg'		=>	array(
				'enable_emoticons' => array('r', array('y' => 'yes', 'n' => 'no')),
				'emoticon_url'     => array('i', '', 'strip_tags|trim|valid_xss_check')
			),

			'tracking_cfg'		=>	array(
				'enable_online_user_tracking' => array('r', array('y' => 'yes', 'n' => 'no'), 'y'),
				'enable_hit_tracking'         => array('r', array('y' => 'yes', 'n' => 'no'), 'y'),
				'enable_entry_view_tracking'  => array('r', array('y' => 'yes', 'n' => 'no'), 'n'),
				'log_referrers'               => array('r', array('y' => 'yes', 'n' => 'no')),
				'max_referrers'               => array('i', ''),
				'dynamic_tracking_disabling'  => array('i', '')
			),

			'recount_prefs'		=>  array(
				'recount_batch_total' => array('i', array('1000')),
			)
		);

		// don't show or edit the CP URL from masked CPs
		if (defined('MASKED_CP') && MASKED_CP === TRUE)
		{
			unset($f_data['general_cfg']['cp_url']);
		}

		if ( ! file_exists(APPPATH.'libraries/Sites.php') OR IS_CORE)
		{
			unset($f_data['general_cfg']['multiple_sites_enabled']);
		}

		if ($this->item('multiple_sites_enabled') == 'y')
		{
			unset($f_data['general_cfg']['site_name']);
		}
		else
		{
			unset($f_data['general_cfg']['is_site_on']);
		}

		if ( ! ee()->db->table_exists('referrers'))
		{
			unset($f_data['tracking_cfg']['log_referrers']);
		}

		// add New Relic if the extension is installed
		if (extension_loaded('newrelic'))
		{
			$new_relic_cfg = array(
				'newrelic_app_name' => array('i', ''),
				'use_newrelic' => array('r', array('y' => 'yes', 'n' => 'no'), 'y')
			);
			$f_data['output_cfg'] = array_merge($new_relic_cfg, $f_data['output_cfg']);
		}

		return $f_data[$type];
	}

	// --------------------------------------------------------------------

	/**
	 * Prep View Vars
	 *
	 * Populates form elements with the initial value, or the submitted
	 * value in case of a form validation error
	 *
	 * @access	public
	 * @param	string	$type	The type of config fields to be prepared
	 * @param	mixed[]	$values	An optional associative array of values to use
	 *  	e.g. 'is_system_on' => 'y'
	 * @return	array	The prepared array for use in views
	 */
	public function prep_view_vars($type, $values = array())
	{
		ee()->load->library('form_validation');
		$f_data = $this->get_config_fields($type);
		$subtext = $this->get_config_field_subtext();

		// Blast through the array
		// If we're dealing with a database configuration we need to pull the data out of the DB
		// config file. To make thigs simple we will set the DB config items as general config values
		if ($type == 'db_cfg')
		{
			require $this->database_path;

			if ( ! isset($active_group))
			{
				$active_group = 'expressionengine';
			}

			if (isset($db[$active_group]))
			{
				$db[$active_group]['pconnect'] = ($db[$active_group]['pconnect'] === TRUE) ? 'y' : 'n';
				$db[$active_group]['cache_on'] = ($db[$active_group]['cache_on'] === TRUE) ? 'y' : 'n';
				$db[$active_group]['db_debug'] = ($db[$active_group]['db_debug'] === TRUE) ? 'y' : 'n';

				$this->set_item('pconnect', $db[$active_group]['pconnect']);
				$this->set_item('cache_on', $db[$active_group]['cache_on']);
				$this->set_item('cachedir', $db[$active_group]['cachedir']);
				$this->set_item('db_debug', $db[$active_group]['db_debug']);
			}
		}

		ee()->load->helper('date');
		$timezones = timezones();

		foreach ($f_data as $name => $options)
		{
			$value = isset($values[$name]) ? $values[$name] : $this->item($name);

			$sub = '';
			$details = '';
			$selected = '';

			if (isset($subtext[$name]))
			{
				foreach ($subtext[$name] as $txt)
				{
					$sub .= lang($txt);
				}
			}

			switch ($options[0])
			{
				case 's':
					// Select fields
					foreach ($options[1] as $k => $v)
					{
						$details[$k] = lang($v);

						if (ee()->form_validation->set_select($name, $k, ($k == $value)) != '')
						{
							$selected = $k;
						}
					}

					break;
				case 'r':
					// Radio buttons
					foreach ($options[1] as $k => $v)
					{
						// little cheat for some values popped into a build update
						if ($value === FALSE)
						{
							// MSM override
							// The key 'multiple_sites_enabled' is listed in admin_model->get_config_fields() as it must be,
							// but its possible that this install doesn't have it available as a config option. In these cases
							// the below code will cause neither "yes" or "no" to be preselected, but instead we want
							// "enable multiple site manager" in General Configuration to be "no".
							if ($name == 'multiple_sites_enabled' AND $k == 'n')
							{
								$checked = TRUE;
							}
							else
							{
								$checked = (isset($options['2']) && $k == $options['2']) ? TRUE : FALSE;
							}
						}
						else
						{
							$checked = ($k == $value) ? TRUE : FALSE;
						}

						$details[] = array('name' => $name, 'value' => $k, 'id' => $name.'_'.$k, 'label' => $v, 'checked' => ee()->form_validation->set_radio($name, $k, $checked));
					}
					break;
				case 't':
					// Textareas

					// The "kill_pipes" index instructs us to turn pipes into newlines
					if (isset($options['1']['kill_pipes']) && $options['1']['kill_pipes'] === TRUE)
					{
						$text = str_replace('|', NL, $value);
					}
					else
					{
						$text = $value;
					}

					$rows = (isset($options['1']['rows'])) ? $options['1']['rows'] : '20';

					$text = str_replace("\\'", "'", $text);

					$details = array('name' => $name, 'class' => 'module_textarea', 'value' => ee()->form_validation->set_value($name, $text), 'rows' => $rows, 'id' => $name);
					break;
				case 'f':
					// Function calls
					ee()->load->model('admin_model');
					switch ($options['1'])
					{
						case 'language_menu'	:
							$options[0] = 's';
							$details = ee()->admin_model->get_installed_language_packs();
							$selected = $value;
							break;
						case 'fetch_encoding'	:
							$options[0] = 's';
							$details = ee()->admin_model->get_xml_encodings();
							$selected = $value;
							break;
						case 'site_404'			:
							$options[0] = 's';
							$details = ee()->admin_model->get_template_list();
							$selected = $value;
							break;
						case 'theme_menu'		:
							$options[0] = 's';
							$details = ee()->admin_model->get_cp_theme_list();
							$selected = $value;
							break;
						case 'timezone'			:
							$options[0] = 'v';
							$details = ee()->localize->timezone_menu($value);
							break;
						case 'caching_driver'	:
							$options[0] = 'v';
							$details = ee()->cache->admin_setting();
							break;
					}
					break;
				case 'p': // Fall through intended.
				case 'i':
					// Input fields
					$details = array('name' => $name, 'value' => ee()->form_validation->set_value($name, $value), 'id' => $name);

					break;

			}

			$vars['fields'][$name] = array('type' => $options[0], 'value' => $details, 'subtext' => $sub, 'selected' => $selected);
		}

		$vars['type'] = $type;

		return $vars;
	}

	// --------------------------------------------------------------------

	/**
	 * Get Configuration Subtext
	 *
	 * Secondary lines of text used in configuration pages
	 * This text appears below any given preference definition
	 *
	 * @access	public
	 * @return	string[]	The secondary lines of text used in configuration pages
	 */
	public function get_config_field_subtext()
	{
		return array(
			'site_url'					=> array('url_explanation'),
			'is_site_on'				=> array('is_site_on_explanation'),
			'is_system_on'				=> array('is_system_on_explanation'),
			'debug'						=> array('debug_explanation'),
			'show_profiler'				=> array('show_profiler_explanation'),
			'template_debugging'		=> array('template_debugging_explanation'),
			'max_caches'				=> array('max_caches_explanation'),
			'use_newrelic'				=> array('use_newrelic_explanation'),
			'newrelic_app_name'			=> array('newrelic_app_name_explanation'),
			'gzip_output'				=> array('gzip_output_explanation'),
			'server_offset'				=> array('server_offset_explain'),
			'default_member_group'		=> array('group_assignment_defaults_to_two'),
			'smtp_server'				=> array('only_if_smpte_chosen'),
			'smtp_port'					=> array('only_if_smpte_chosen'),
			'smtp_username'				=> array('only_if_smpte_chosen'),
			'smtp_password'				=> array('only_if_smpte_chosen'),
			'email_batchmode'			=> array('batchmode_explanation'),
			'email_batch_size'			=> array('batch_size_explanation'),
			'webmaster_email'			=> array('return_email_explanation'),
			'cookie_domain'				=> array('cookie_domain_explanation'),
			'cookie_prefix'				=> array('cookie_prefix_explain'),
			'cookie_path'				=> array('cookie_path_explain'),
			'deny_duplicate_data'		=> array('deny_duplicate_data_explanation'),
			'redirect_submitted_links'	=> array('redirect_submitted_links_explanation'),
			'require_secure_passwords'	=> array('secure_passwords_explanation'),
			'allow_dictionary_pw'		=> array('real_word_explanation', 'dictionary_note'),
			'censored_words'			=> array('censored_explanation', 'censored_wildcards'),
			'censor_replacement'		=> array('censor_replacement_info'),
			'password_lockout'			=> array('password_lockout_explanation'),
			'password_lockout_interval' => array('login_interval_explanation'),
			'require_ip_for_login'		=> array('require_ip_explanation'),
			'allow_multi_logins'		=> array('allow_multi_logins_explanation'),
			'name_of_dictionary_file'	=> array('dictionary_explanation'),
			'license_contact'			=> array('license_contact_explanation'),
			'license_number'			=> array('license_number_explanation'),
			'force_query_string'		=> array('force_query_string_explanation'),
			'image_resize_protocol'		=> array('image_resize_protocol_exp'),
			'image_library_path'		=> array('image_library_path_exp'),
			'thumbnail_prefix'			=> array('thumbnail_prefix_exp'),
			'member_theme'				=> array('member_theme_exp'),
			'require_terms_of_service'	=> array('require_terms_of_service_exp'),
			'email_console_timelock'	=> array('email_console_timelock_exp'),
			'log_email_console_msgs'	=> array('log_email_console_msgs_exp'),
			'use_membership_captcha'	=> array('captcha_explanation'),
			'strict_urls'				=> array('strict_urls_info'),
			'enable_template_routes'	=> array('enable_template_routes_exp'),
			'tmpl_display_mode'			=> array('tmpl_display_mode_exp'),
			'save_tmpl_files'			=> array('save_tmpl_files_exp'),
			'tmpl_file_basepath'		=> array('tmpl_file_basepath_exp'),
			'site_404'					=> array('site_404_exp'),
			'channel_nomenclature'		=> array('channel_nomenclature_exp'),
			'enable_sql_caching'		=> array('enable_sql_caching_exp'),
			'email_debug'				=> array('email_debug_exp'),
			'use_category_name'			=> array('use_category_name_exp'),
			'reserved_category_word'	=> array('reserved_category_word_exp'),
			'auto_assign_cat_parents'	=> array('auto_assign_cat_parents_exp'),
			'save_tmpl_revisions'		=> array('template_rev_msg'),
			'max_tmpl_revisions'		=> array('max_revisions_exp'),
			'max_page_loads'			=> array('max_page_loads_exp'),
			'time_interval'				=> array('time_interval_exp'),
			'lockout_time'				=> array('lockout_time_exp'),
			'banishment_type'			=> array('banishment_type_exp'),
			'banishment_url'			=> array('banishment_url_exp'),
			'banishment_message'		=> array('banishment_message_exp'),
			'enable_search_log'			=> array('enable_search_log_exp'),
			'mailinglist_notify_emails' => array('separate_emails'),
			'dynamic_tracking_disabling'=> array('dynamic_tracking_disabling_info')
		);
	}

	// -------------------------------------------------------------------------

	/**
	 * Fetch a config item and add a slash after it
	 *
	 * This is installer aware and will always return the correct path in the
	 * ExpressionEngine install
	 *
	 * @param  string $item the config item
	 * @return string       the config value
	 */
	public function slash_item($item)
	{
		$pref = parent::slash_item($item);

		if (defined('EE_APPPATH'))
		{
			$pref = str_replace(APPPATH, EE_APPPATH, $pref);
		}

		return $pref;
	}

}
// END CLASS

/* End of file EE_Config.php */
/* Location: ./system/expressionengine/libraries/EE_Config.php */
