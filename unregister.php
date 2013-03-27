<?php
$token = $_POST['token'];
$reg_id = $_POST['reg_id'];

require 'constants.php';
require 'prowork.class.php';

$prowork = new Prowork($apikey);
$prowork->setToken($token);

if ($prowork->me()) {
	try {
		$db = new Mongo();
			
		// Unregister for prowork's push
		if ($prowork->pushUnsubscribe($apikey)) {
			// Successful
			$db->notifications->androidRoster->remove(array('id' => $reg_id));
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