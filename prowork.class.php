<?php
/*
 * Code library for the Prowork API at dev.prowork.me
 *
 * https://github.com/kehers/api.prowork.php
 *
 * @author Opeyemi Obembe (@kehers) <ray@prowork.me>
 * @version 0.9.1
 */
 
require 'prowork.exceptions.php';

class Prowork {

	private $_token;
	private $_error;
	private $_email;
	private $_password;
	private $_apikey;
	public $_api_root = "http://api.prowork.me/";

	/*
	 * Constructor
	 *
	 * In its basic form: new Prowork(APIKEY);
	 *  In this form, note that to perform any api call
	 *  requires login(EMAIL, PASSWORD) first
	 * If you have saved the authentication token 
	 *  from previous login, use
	 * Prowork(APIKEY, TOKEN, EMAIL, PASSWORD)
	 * The email and password parameters are for relogin in case
	 *  the token has expired. If they are missing,
	 *  there won't be a relogin action
	*/	
	public function __construct($apikey, $token = '', $email = '',
			$password = '') {
		$this->_apikey = $apikey;
		$this->_email = $email;
		$this->_password = $password;
		$this->setToken($token);
	}
	
	/*
	 * Helpers
	*/
	
	/*
	 * Get authentication token
	 *
	 * @returns token from login
	 * You can save this so that you don't re-login user later
	 *  but rather setToken(TOKEN) or init class with token:
	 *  new Prowork(APIKEY, TOKEN)
	*/
	public function getToken() {
		return $this->_token;
	}
	
	/*
	 * Sets authentication token
	*/
	public function setToken($token) {
		$this->_token = $token;
	}
	
	/*
	 * Get last error
	*/
	public function getError() {
		return $this->_error;
	}
	
	/*
	 * Me
	 * Get name, email and user_id of authenticated user
	*/
	public function me() {
		list($status, $response) = $this->get('session/user');

		$json = json_decode($response, true);
		return $json;
	}
	
	/*
	 * Get user avatar
	 * $email: Email of user. If not provided, current user is assumed
	 * $size: Avatar width. Optional, defaults to 48
	*/
	public function getAvatar($size = null, $email = null) {
		$url = 'member/avatar?email=';
		if ($email)
			$url .= $email;
		else
			$url .= $this->_email;
			
		if ((int) $size)
			$url .= '&size='.$size;

		list($status, $response) = $this->get($url);

		$json = json_decode($response, true);

		return $json['avatar'];
	}
	
	/*
	 * Auth/Notification
	 */

	/*
	 * Login user
	 *
	 * @params User email and password
	 * @returns User id
	 * Doc: http://dev.prowork.me/accounts-login
	*/
	public function login($email, $password) {
		$this->_email = $this->escape($email);
		$this->_password = $this->escape($password);
		
		if (!$this->_email || !$this->_password) {
			throw new MissingParameterException("Missing
				credentials.");
		}
		
		list($status, $response) = $this->post('session/get', array(
				'email' => $this->_email,
				'password' => $this->_password,
				'api_key' => $this->_apikey
			));
		
		$json = json_decode($response, true);
		
		// Login failed
		if ($status != 200) {
			$this->_error = $json['error'];
			
			return false;
		}
		
		// Logged in
		$this->setToken($json['token']);
		// If you ever need the token, see getToken()
		return $json['user_id'];
	}
	
	/*
	 * Register user
	 *
	 * @params User email and password
	 * @returns User id
	 * Doc: http://dev.prowork.me/accounts-register
	*/
	public function register($email, $password) {
		$email = $this->escape($email);
		$password = $this->escape($password);
		
		if (!$email || !$password) {
			throw new MissingParameterException("Missing credentials.");
		}
		
		list($status, $response) = $this->post('session/register', 
			array(
				'email' => $email,
				'password' => $password
			)
		);
		
		$json = json_decode($response, true);
		
		// Registration failed
		if ($status == 403) {
			$this->_error = $json['error'];
			
			return false;
		}
		
		// Successful
		$this->_token = $json['token'];
		return $json['user_id'];
	}

	/*
	 * Notification count
	 * Doc: http://dev.prowork.me/accounts-notifications
	*/
	public function notificationCount() {
		list($status, $response) = $this->get('session/notifications');
		
		$json = json_decode($response, true);
		return $json['notifications'];
	}

	/*
	 * Activities 
	 *   This is a poll implementation.
	 *   <s>Working on a push architecture already</s>. See pushSubscribe()
	 * read: Return read activities. Limit 50
	 * Doc: http://dev.prowork.me/accounts-activities
	*/
	public function activities($read = false) {
		list($status, $response) = $read ? $this->get('session/get_activities?read=1')
			: $this->get('session/get_activities');
		
		$array = json_decode($response, true);
		return $array;
	}
	
	/*
	 * Subscribe to push
	 * $url: Url push notifications will be sent to
	 * Doc: http://dev.prowork.me/push-subscribe
	*/
	public function pushSubscribe($url, $verifier) {
		if (!$url || !$verifier) {
			throw new MissingParameterException("Missing parameter.");
		}
		
		list($status, $response) = $this->post('notifications/subscribe', array(
				'url' => $url,
				'verifier' => $verifier,
				'token' => $this->_token,
				'api_key' => $this->_apikey
			));
		
		$json = json_decode($response, true);
		if ($status != 200) {
			$this->_error = $json['error'];
			
			return false;
		}
		
		return $json['status'] ? true : false;
	}
	
	/*
	 * Unsubscribe from push
	 * Doc: http://dev.prowork.me/push-unsubscribe
	*/
	public function pushUnsubscribe() {		
		list($status, $response) = $this->get('notifications/unsubscribe?api_key='.$this->_apikey);
		
		$json = json_decode($response, true);
		if ($status != 200) {
			$this->_error = $json['error'];
			
			return false;
		}
		
		return $json['status'] ? true : false;
	}

	/*
	 * Projects
	 */

	/*
	 * Get projects user belongs to
	 * @returns: array of [project] id, member_count, task_count, name, description,
     *	  admin [member id], created_date
	 * Doc: http://dev.prowork.me/projects-get
	*/
	public function getProjects() {
		list($status, $response) = $this->get('projects/get');
		
		$array = json_decode($response, true);
		return $array;
	}

	/*
	 * Get info about a single project
	 * $project_id : ID of project to get.
	 * @returns : [project] id, member_count, task_count, name, description,
     *	  admin [member id], created_date
	 * Doc: http://dev.prowork.me/project-get
	*/	
	public function getProject($project_id) {
		$project_id = (int) $project_id;
		
		if (!$project_id) {
			throw new MissingParameterException("Missing
				parameter: project id.");
		}
		
		list($status, $response) = $this->get('project/get?project_id='.$project_id);
		
		$array = json_decode($response, true);
		return $array;
	}
	
	/*
	 * Create a new prowork project
	 * $title: Project title
	 * $description: Project description. Optional
	 * @returns Project id
	 * Doc: http://dev.prowork.me/projects-new
	*/
	public function newProject($title, $description = null) {
	
		if (!$title) {
			throw new MissingParameterException("Missing
				parameter: project title.");
		}
		
		$array = array(
				'title' => $this->escape($title),
				'token' => $this->_token
			);
		if($description) {
			$array['description'] = $this->escape($description);
		}
		list($status, $response) = $this->post('project/new', $array);
		$json = json_decode($response, true);
		
		if ($status != 200) {
			$this->_error = $json['error'];
			
			return false;
		}
		
		return $json['id'];
	}

	/*
	 * Get Project Activities
	 * $project_id : ID of project to get. See getProjects()
	 * $read : return read activities. Defaults to no
	 * @returns : array of [member] name, action [performed] and date
	 * Doc: http://dev.prowork.me/project-get-activities
	*/
	public function projectActivities($project_id, $read = false) {
		$project_id = (int) $project_id;
		
		if (!$project_id) {
			throw new MissingParameterException("Missing
				parameter: project id.");
		}
		
		list($status, $response) = $read ? $this->get('project/activities?project_id='.$project_id.'&read=1')
			: $this->get('project/activities?project_id='.$project_id);
		
		$array = json_decode($response, true);
		return $array;
	}

	/*
	 * Delete project
	 * $project_id : ID of project
	 * @returns : true or false depending on status
	 * Doc: http://dev.prowork.me/project-delete
	*/	
	public function deleteProject($project_id) {
		$project_id = (int) $project_id;
		
		if (!$project_id) {
			throw new MissingParameterException("Missing
				parameter: project id.");
		}
		
		list($status, $response) = $this->get('project/delete?task_id='.$project_id);
		
		$json = json_decode($response, true);
		if ($status != 200) {
			$this->_error = $json['error'];
			
			return false;
		}
		
		return $json['status'] ? true : false;
	}
	
	/*
	 * Project Members
	 */

	/*
	 * Add project members
	 * $project_id : ID of project
	 * $emails : Array of prospective members' email
	 * @returns : true or false depending on status
	 * Doc: http://dev.prowork.me/project-members-add
	*/	
	public function addProjectMembers($project_id, $emails) {
		$project_id = (int) $project_id;
		$emails = $this->escape(implode(',', $emails));
		
		if (!$project_id || !$emails) {
			throw new MissingParameterException("Missing
				parameter.");
		}
		
		list($status, $response) = $this->post('project/members_add', array(
				'project_id' => $project_id,
				'emails' => $emails,
				'token' => $this->_token
			));
		
		$json = json_decode($response, true);
		if ($status != 200) {
			$this->_error = $json['error'];
			
			return false;
		}
		
		return $json['status'] ? true : false;
	}
	
	/*
	 * Get Project Members
	 * $project_id : ID of project.
	 * @returns : arrays of member_id, [member] name, avatar, role
	 * Doc: http://dev.prowork.me/project-members-get
	*/
	public function getProjectMembers($project_id) {
		$project_id = (int) $project_id;
		
		if (!$project_id) {
			throw new MissingParameterException("Missing
				parameter: project id.");
		}
		
		list($status, $response) = $this->get('project/members_get?project_id='.$project_id);
		
		$array = json_decode($response, true);
		return $array;
	}
	
	/*
	 * Remove Project Member
	 * $project_id : ID of project
	 * $member_id : ID of member
	 * @returns : true or false depending on status
	 * Doc: http://dev.prowork.me/project-members-remove
	*/
	public function removeProjectMember($project_id, $member_id) {
		$project_id = (int) $project_id;
		$member_id = (int) $member_id;
		
		if (!$project_id || !$member_id) {
			throw new MissingParameterException("Missing
				parameter.");
		}
		
		list($status, $response) = $this->get('project/members_remove?project_id='.$project_id.'&member_id='.$member_id);
		
		$json = json_decode($response, true);
		if ($status != 200) {
			$this->_error = $json['error'];
			
			return false;
		}
		
		return $json['status'] ? true : false;
	}
	
	/*
	 *
	 * Tasks
	 */
	 
	/*
	 * Get Project Tasks
	 * $project_id : ID of project
	 * @returns : arrays of task_id, member_id (member ids seperated by comma), title,
	 *   start [date], duration (ignore), date (due), done, notes, srt (sort index),
	 *	 status (ignore)
	 * Doc: http://dev.prowork.me/tasks-get
	*/
	public function getTasks($project_id) {
		$project_id = (int) $project_id;
		
		if (!$project_id) {
			throw new MissingParameterException("Missing
				parameter: project id.");
		}
		
		list($status, $response) = $this->get('tasks/get?project_id='.$project_id);
		
		$array = json_decode($response, true);
		return $array;
	}
	
	/*
	 * Get a Project Task
	 * $project_id : ID of project
	 * $task_id : ID of task
	 * @returns : see getTasks()
	 * Doc: http://dev.prowork.me/task-get
	*/
	public function getTask($project_id, $task_id) {
		$project_id = (int) $project_id;
		$task_id = (int) $task_id;
		
		if (!$project_id || !$task_id) {
			throw new MissingParameterException("Missing
				parameter.");
		}
		
		list($status, $response) = $this->get('task/get?project_id='.$project_id.'&task_id='.$task_id);
		
		$array = json_decode($response, true);
		return $array;
	}
	
	/*
	 * Add a new task
	 * $project_id: Project id
	 * $title: Task title
	 * $date: Due date. Optional
	 * @returns Task id
	 * Doc: http://dev.prowork.me/task-new
	*/
	public function newTask($project_id, $title, $date = null) {
		$project_id = (int) $project_id;
		
		if (!$title || !$project_id) {
			throw new MissingParameterException("Missing parameter.");
		}
		
		$array = array(
				'project_id' => $project_id,
				'title' => $this->escape($title),
				'token' => $this->_token
			);
		if($date)
			$array['date'] = $date;
			
		list($status, $response) = $this->post('task/new', $array);
		$json = json_decode($response, true);
		
		if ($status != 200) {
			$this->_error = $json['error'];
			
			return false;
		}
		
		return $json['id'];
	}
	
	/*
	 * Delete task
	 * $project_id : ID of project
	 * $task_id : ID of task
	 * @returns : true or false depending on status
	 * Doc: http://dev.prowork.me/task-delete
	*/	
	public function deleteTask($project_id, $task_id) {
		$project_id = (int) $project_id;
		$task_id = (int) $task_id;
		
		if (!$project_id || !$task_id) {
			throw new MissingParameterException("Missing
				parameter.");
		}
		
		list($status, $response) = $this->get('task/delete?project_id='.$project_id.'&task_id='.$task_id);
		
		$json = json_decode($response, true);
		if ($status != 200) {
			$this->_error = $json['error'];
			
			return false;
		}
		
		return $json['status'] ? true : false;
	}
	
	/*
	 * Change task status
	 * $project_id : ID of project
	 * $task_id : ID of task
	 * $done : 1 to completed or 0 to uncompleted
	 * @returns : true or false depending on status
	 * Doc: http://dev.prowork.me/task-set-status
	*/	
	public function setTaskStatus($project_id, $task_id, $done = 0) {
		$project_id = (int) $project_id;
		$task_id = (int) $task_id;
		
		if (!$project_id || !$task_id) {
			throw new MissingParameterException("Missing
				parameter.");
		}
		
		$url = 'task/set_status?project_id='.$project_id.'&task_id='.$task_id.'&done=';
		$url .= $done ? '1' : '0';
		list($status, $response) = $this->get($url);
		$json = json_decode($response, true);
		
		if ($status == 200 || $status == 304) {
			return true;
		}
		
		$this->_error = $json['error'];
		return false;
	}	
	
	/*
	 * Tasks Memebers
	 */
	 
	/*
	 * Assign members to task
	 * $project_id: Project id
	 * $task_id: Task id
	 * $member_ids: Array of member ids to assign
	 * @returns Array of member ids
	 * Doc: http://dev.prowork.me/task-new
	*/
	public function assignMembers($project_id, $task_id, $member_ids) {
		$project_id = (int) $project_id;
		$task_id = (int) $task_id;
		$member_ids = $this->escape(implode(',', $member_ids));
		
		if (!$task_id || !$project_id || !$member_ids) {
			throw new MissingParameterException("Missing parameter.");
		}
		
		list($status, $response) = $this->post('task/members_add',  array(
				'project_id' => $project_id,
				'task_id' => $task_id,
				'member_ids' => $member_ids,
				'token' => $this->_token
			));
		$json = json_decode($response, true);
		
		if ($status != 200) {
			$this->_error = $json['error'];
			
			return false;
		}
		
		return explode(',', $json['ids']);
	}
	
	/*
	 * Remove task members
	 * $project_id : ID of project
	 * $task_id : ID of task
	 * $member_id : ID of member
	 * @returns : true or false depending on status
	 * Doc: http://dev.prowork.me/task-member-remove
	*/	
	public function removeTaskMember($project_id, $task_id, $member_id) {
		$project_id = (int) $project_id;
		$task_id = (int) $task_id;
		
		if (!$project_id || !$task_id || $member_id) {
			throw new MissingParameterException("Missing
				parameter.");
		}
		
		list($status, $response) = $this->get('task/member_remove?project_id='.$project_id.'&task_id='.$task_id.'&member_id='.$member_id);
		
		$json = json_decode($response, true);
		if ($status != 200) {
			$this->_error = $json['error'];
			
			return false;
		}
		
		return $json['status'] ? true : false;
	}
	
	/*
	 * Private methods
	 */
	
	/*
	 * escape
	 *   Simple input filter. Real filtering is done on server
	 *   $input : the input
	 */
	private function escape($input) {
		$input = trim($input);
		
        if (get_magic_quotes_gpc ()) {
            $input = stripslashes($input);
        }
		
		return $input;
	}
	
	private function get($url) {
		$parse = parse_url($url);
		$url .= $parse['query'] ? '&' : '?';
		return $this->http($url.'token='.$this->_token);
	}
	
	private function post($url, $data) {
		return $this->http($url, $data);
	}
	
    /**
     * HTTP request handler
	 *
	 * $url: url to post or get
	 * $data: post data for POST
	 * @returns array of http status and response
     */
	private function http($url, $data = null) {
		// print_r($data); #debug
		
		$ch = curl_init();
		
		$callurl = $this->_api_root.$url;
		// echo $callurl; #debug
		
		curl_setopt($ch, CURLOPT_URL, $callurl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		
		if (isset($data)) {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}
		
		$response = curl_exec($ch);
		$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		
		// Session expired, relogin
		if ($status == 410) {	
			$this->login($this->_email, $this->_password);
			list($status, $response) = $this->http($url, $data);
		}
		
		//echo $response; #debug
		return array($status, $response);
	}
}
?>