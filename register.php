<?php
$token = $_POST['token'];
$reg_id = $_POST['reg_id'];

require 'constants.php';
require 'prowork.class.php';

$prowork = new Prowork($apikey);
$prowork->setToken($token);

if ($user = $prowork->me()) {
	// Map reg_id and user_id together
	try {
		$db = new Mongo();
			
		// Register for prowork's push
		if ($prowork->pushSubscribe($push_url, $verifier)) {
			// Successful
			$db->notifications->androidRoster->update(array(
					'user' => $user['member_id'],
					'id' => $reg_id
				), array(
					'user' => $user['member_id'],
					'id' => $reg_id
				), array('upsert' => true));
			echo json_encode(array(
					'status' => 'done'
				));
			exit;
		}
	}
	catch(MongoConnectionException $e) {
		echo json_encode(array(
				'error' => 'Internal db error. Try again later.'
			));
	}
}

echo json_encode(array(
		'error' => $prowork->getError()
	));
?>