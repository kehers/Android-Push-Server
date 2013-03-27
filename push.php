<?php
require 'constants.php';

// Verify post is from prowork
if ($_POST['verifier'] != $verifier)
	exit;
	
// Get reg ids of receiver and prepare message
$user = $_POST['receiver_id'];
try {
	$db = new Mongo();
	$regids = array();
	$regdbids = array();
	$r = $db->notifications->androidRoster->find(array('user' => $user));
	foreach ($r as $v) {
		$regids[] = $v['id'];
		$regdbids[] = $v['_id'];
	}
	
	// Process messages
	switch($_POST['object']) {
		case 'p':
			$title = 'Project';
			$message = '"'.$_POST['project_name'].'"';
			if ($_POST['type'] == 1)
				$message .=  ' created';
			else if ($_POST['type'] == 2)
				$message .=  ' updated';
			else
				$message .=  ' removed';
			break;
		case 't':
			$title = 'Task';
			$message = $_POST['object_name'];
			if ($_POST['type'] == 1)
				$message .=  ' added to project: "'.$_POST['project_name'].'"';
			else if ($_POST['type'] == 2)
				$message .=  ' updated to uncompleted';
			else if ($_POST['type'] == 3)
				$message .=  ' updated to completed';
			else
				$message .= ' removed from project: "'.$_POST['project_name'].'"';
		break;
		case 'f':
			$title = 'File';
			if ($_POST['type'] == 1)
				$message = $_POST['object_name'].' added to project: "'.$_POST['project_name'].'"';
			else
				$message = $_POST['object_name'].' removed from project: "'.$_POST['project_name'].'"';
		break;
		case 'm':
			$title = 'Project Member';
			if ($_POST['type'] == 1) {
				if ($_POST['parent'] == 'p')
					$message = $_POST['object_name'].' added to project: "'.$_POST['project_name'].'"';
				else if ($_POST['parent'] == 't')
					$message = $_POST['object_name'].' assigned to task: "'.$_POST['parent_name'].'"';
			}
			else {
				if ($_POST['parent'] == 'p')
					$message = $_POST['object_name'].' removed from project: "'.$_POST['project_name'].'"';
				else if ($_POST['parent'] == 't')
					$message = $_POST['object_name'].' removed from task: "'.$_POST['parent_name'].'"';
		   }
		break;
		case 'n':
			$title = 'Note';
			if ($_POST['type'] == 1)
				$message = '"'.$_POST['parent_name'].'" has a new note';
		   else 
				$message = 'Note deleted from the task: "'.$_POST['parent_name'].'"';
		break;
		case 'dm':
			$title = 'Message';
			$message = $_POST['parent_name'].' sent you a new message: "'.$_POST['object_name'].'"';
		break;
	}
	
	$payload = json_encode(array(
				'data' => array('title'=>$title, 'message'=>$message),
				'registration_ids' => $regids
			)
		);
}
catch(MongoConnectionException $e) {
	exit;
}

// Send to Google gcm server
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $google_gcm_server);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Content-Type: application/json',
		'Authorization: key='.$authkey
)); 
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
$response = curl_exec($ch);
$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Process result
if ($response) {
	$response = json_decode(strtolower($response), true);
	// Are there failed deliveries?
	if ($response['failure'] == 0 && 
		$response['canonical_ids'] == 0) {
		// No failed delivery. All is settled
		exit;
	}
	
	// Yes. Process them
	if ($response['results']) {
		foreach ($response['results'] as $k => $result) {
			// There is an error
			if ($result['error']) {
				$error = $result['error'];
				if ($error == 'mismatchsenderid' || $error == 'invalidregistration'
						|| $error == 'notregistered') {
					// Unrecoverable. Remove from db
					$r = $db->notifications->androidRoster->remove(array('_id' => $regdbids[$k]));
				}
			}
			// There is a need to update reg id
			else if ($result['registration_id']) {
				$r = $db->notifications->androidRoster->update(array('_id' => $regdbids[$k]),
						array('$set' => array('id' => $result['registration_id']))
					);
			}
		}
	}
}
?>