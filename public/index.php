<?php

require '../vendor/autoload.php';

use Slim\Slim;
use Dotenv\Dotenv;
use BorealisAPI\ServerQuery;

$dotenv = new Dotenv("../");
$dotenv->load();

$app = new Slim();

$app->view(new \JsonApiView());
$app->add(new \JsonApiMiddleware());

/**
 * Middleware: request verification.
 */
function verifyRequest()
{
	$app = Slim::getInstance();

	$key = $app->request()->params('auth_key');

	if (!isset($key))
	{
		$app->render(403);
	}

	if (strcmp(md5(getenv('AUTH_KEY')), $key) !== 0)
	{
		$app->render(403);
	}
}

/**
 * Helper: sets up a PDO object that's connected to the game's database.
 *
 * @return PDO
 */
function setupDbh()
{
	$app = Slim::getInstance();
	try {
		$db_host = getenv('DB_HOST');
		$db_name = getenv('DB_NAME');
		$dbh = new PDO("mysql:host={$db_host}:3306;dbname={$db_name}", getenv('DB_USER'), getenv('DB_PASS'));
		$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		return $dbh;
	}
	catch (PDOException $e)
	{
		$app->render(500);
	}
}

/**
 * Helper: Parse's an integer (bitmask) and detects certain ranks.
 *
 * @param integer $bitfield
 * @return array
 */
function parseAuth($bitfield)
{
	$possible_auths = [
		2 => "R_ADMIN",
		8192 => "R_MOD",
		32768 => "R_CCIAA"
	];

	$auths = array();

	foreach ($possible_auths as $flag => $value)
	{
		if ($bitfield & $flag)
		{
			array_push($auths, $value);
		}
	}

	return $auths;
}

/**
 * Route: GET /users
 *
 * requires @param auth_key for verification
 */
$app->get('/users', 'verifyRequest', function () use ($app) {
	$dbh = setupDbh();

	$stmt = $dbh->prepare("SELECT discord_id, ckey, flags FROM ss13_admin WHERE discord_id IS NOT NULL AND rank != 'Removed'");
	$stmt->execute();

	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$users = [];
	foreach ($rows as $user)
	{
		$users[$user['discord_id']] = [
			'auth' => parseAuth($user['flags']),
			'ckey' => $user['ckey']
		];
	}

	$app->render(200, ["users" => $users]);

	$dbh = NULL;
});

/**
 * Route: PUT /users
 *
 * requires @param auth_key for verification
 * requires @param ckey for binding
 * requires @param discord_id for binding
 */
$app->put('/users', 'verifyRequest', function () use ($app) {
	$params = $app->request->put();

	$dbh = setupDbh();

	if (empty($params['ckey']) || empty($params['discord_id']))
	{
		$app->render(500, ["error_msg" => "Bad params sent."]);
	}

	$stmt = $dbh->prepare("SELECT count(ckey) AS found_count FROM ss13_admin WHERE ckey = :ckey AND rank != 'Removed'");
	$stmt->execute([":ckey" => $params['ckey']]);
	$match = $stmt->fetch(PDO::FETCH_ASSOC);

	if ($match['found_count'] != 1)
	{
		$app->render(200, ["error_msg" => "No active admin with given ckey found"]);
	}

	$stmt = $dbh->prepare("UPDATE ss13_admin SET discord_id = :discord_id WHERE ckey = :ckey");
	$stmt->execute([":discord_id" => $params["discord_id"], ":ckey" => $params["ckey"]]);

	$app->render(200);
});

/**
 * Route: DELETE /users
 *
 * requires @param auth_key for verification
 * requires @param ckey OR discord_id for removal
 */
$app->delete('/users', 'verifyRequest', function () use ($app) {
	$params = $app->request->delete();

	if (array_key_exists("discord_id", $params))
	{
		$sql_select = "SELECT id FROM ss13_admin WHERE discord_id = :discord_id";
		$sql_update = "UPDATE ss13_admin SET discord_id = NULL WHERE id = :id";
		$sql_params = [":discord_id" => $params["discord_id"]];
	}
	else if (array_key_exists("ckey", $params))
	{
		$sql_select = "SELECT id FROM ss13_admin WHERE ckey = :ckey";
		$sql_update = "UPDATE ss13_admin SET discord_id = NULL WHERE id = :id";
		$sql_params = [":ckey" => $params["ckey"]];
	}
	else
	{
		$app->render(500, ["error_msg" => "Bad params sent."]);
	}

	$dbh = setupDbh();

	$stmt = $dbh->prepare($sql_select);
	$stmt->execute($sql_params);
	$match = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!sizeof($match) || !$match["id"])
	{
		$app->render(200, ["error_msg" => "No matching entry found."]);
	}

	$sql_params[":id"] = $match["id"];

	$stmt = $dbh->prepare($sql_update);
	$stmt->execute($sql_params);

	$app->render(200);
});

/**
 * Route: GET /channels
 *
 * requires @param auth_key for verification
 */
$app->get('/channels', 'verifyRequest', function () use ($app) {
	$dbh = setupDbh();

	$stmt = $dbh->prepare("SELECT channel_group, channel_id FROM discord_channels");
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$channels = [];

	foreach ($rows as $channel)
	{
		if (empty($channels[$channel['channel_group']]))
		{
			$channels[$channel['channel_group']] = [];
		}
		array_push($channels[$channel['channel_group']], $channel['channel_id']);
	}

	$app->render(200, ["channels" => $channels]);
});

/**
 * Route: PUT /channels
 *
 * requires @param auth_key for verification
 * requires @param channel_id for binding
 * requires @param channel_group for binding
 */
$app->put('/channels', 'verifyRequest', function () use ($app) {
	$params = $app->request->put();

	$dbh = setupDbh();

	if (empty($params['channel_id']) || empty($params['channel_group']))
	{
		$app->render(500, ["error_msg" => "Bad params sent."]);
	}

	/*
	$stmt = $dbh->prepare("SELECT count(distinct channel_group) AS found_count FROM discord_channels WHERE channel_group = :channel_group");
	$stmt->execute([":channel_group" => $params['channel_group']]);
	$match = $stmt->fetch(PDO::FETCH_ASSOC);

	if ($match['found_count'] != 1)
	{
		$app->render(200, ["error_msg" => "Invalid channel group."]);
	}
	*/

	$stmt = $dbh->prepare("SELECT count(*) AS found_count FROM discord_channels WHERE channel_group = :channel_group AND channel_id = :channel_id");
	$stmt->execute([":channel_group" => $params['channel_group'], ":channel_id" => $params['channel_id']]);
	$match = $stmt->fetch(PDO::FETCH_ASSOC);

	if ($match['found_count'] != 0)
	{
		$app->render(200, ["error_msg" => "Channel is already bound to that group."]);
	}

	$stmt = $dbh->prepare("INSERT INTO discord_channels (channel_group, channel_id) VALUES (:channel_group, :channel_id)");
	$stmt->execute([":channel_group" => $params['channel_group'], ":channel_id" => $params['channel_id']]);

	$app->render(200);
});

/**
 * Route: DELETE /channels
 *
 * requires @param auth_key for verification
 * requires @param channel_id for deletion
 * requires @param channel_group for deletion
 */
$app->delete('/channels', 'verifyRequest', function () use ($app) {
	$params = $app->request->delete();

	$dbh = setupDbh();

	if (empty($params['channel_id']) || empty($params['channel_group']))
	{
		$app->render(500, ["error_msg" => "Bad params sent."]);
	}

	$stmt = $dbh->prepare("SELECT id FROM discord_channels WHERE channel_group = :channel_group AND channel_id = :channel_id");
	$stmt->execute([":channel_group" => $params['channel_group'], ":channel_id" => $params['channel_id']]);
	$match = $stmt->fetch(PDO::FETCH_ASSOC);

	if (empty($match['id']))
	{
		$app->render(200, ["error_msg" => "Channel not found."]);
	}

	$stmt = $dbh->prepare("DELETE FROM discord_channels WHERE id = :id");
	$stmt->execute([":id" => $match['id']]);

	$app->render(200);
});

/**
 * Route: /query/database/:question
 *
 * requires @param auth_key for verification
 * requires @param :question for usage
 */
$app->get('/query/database/:question', 'verifyRequest', function ($question) use ($app) {
	$params = $app->request->get();

	$dbh = setupDbh();

	$response = [];

	switch($question)
	{
		case 'playernotes':
			$stmt = $dbh->prepare("SELECT adddate, a_ckey, content FROM ss13_notes WHERE visible = 1 AND ckey = :ckey");
			$stmt->execute([":ckey" => $params['ckey']]);
			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

			$notes = array();

			foreach ($rows as $note)
			{
				array_push($notes, implode(" || ", $note));
			}

			$response = $notes;

			break;

		case 'playerinfo':
			$stmt = $dbh->prepare("SELECT DATE_FORMAT(firstseen, '%d-%m-%Y') AS time_firstseen, DATE_FORMAT(lastseen, '%d-%m-%Y') AS time_lastseen, lastadminrank FROM ss13_player WHERE ckey = :ckey");
			$stmt->execute([":ckey" => $params['ckey']]);
			$data = $stmt->fetch(PDO::FETCH_ASSOC);

			$response['found'] = TRUE;

			if (!isset($data['time_firstseen']))
			{
				$response['found'] = FALSE;
			}

			$response['First seen'] = $data['time_firstseen'];
			$response['Last seen'] = $data['time_lastseen'];
			$response['Rank'] = $data['lastadminrank'];

			$stmt = $dbh->prepare("SELECT count(*) as note_count FROM ss13_notes WHERE ckey = :ckey AND visible = '1'");
			$stmt->execute([":ckey" => $params['ckey']]);
			$data = $stmt->fetch(PDO::FETCH_ASSOC);

			$response['Notes'] = $data['note_count'];

			$stmt = $dbh->prepare("SELECT count(*) as warning_count FROM ss13_warnings WHERE ckey = :ckey AND visible = 1");
			$stmt->execute([":ckey" => $params['ckey']]);
			$data = $stmt->fetch(PDO::FETCH_ASSOC);

			$response['Warnings'] = $data['warning_count'];

			$stmt = $dbh->prepare("SELECT count(*) as active_bans FROM ss13_ban WHERE ckey = :ckey AND (bantype = 'PERMABAN'  OR (bantype = 'TEMPBAN' AND expiration_time > Now())) AND unbanned IS NULL");
			$stmt->execute([":ckey" => $params['ckey']]);
			$data = $stmt->fetch(PDO::FETCH_ASSOC);

			if ($data['active_bans'] > 0)
			{
				$response['Is banned'] = "Yes";
			}
			else
			{
				$response['Is banned'] = "No";
			}

			// Gotta help Python out. HNNNNRG.
			$response['sort_order'] = ['First seen', 'Last seen', 'Rank', 'Notes', 'Warnings', 'Is banned'];

			break;

		default:
			$app->render(500);

			break;
	}

	$app->render(200, ["reply_status" => "success", "data" => $response]);

	$db = NULL;

});

/**
 * Route: /query/server/:question
 *
 * requires @param auth_key for verification
 * requires @param :question for usage
 */
$app->get('/query/server/:question', 'verifyRequest', function ($question) use ($app) {
	$params = $app->request->get();
	$params['query'] = $question;

	$ss13 = new ServerQuery();
	try {
		$ss13->setUp(getenv('SS13_HOST'), getenv('SS13_PORT'), getenv('AUTH_KEY'));
		$ss13->runQuery($params);
		$response['data'] = $ss13->response;

		$response['reply_status'] = $ss13->reply_status;

		// Fetch the actual content from the DB
		// The rest (sentby and title) are returned via socket
		if ($question == "faxget" && $response['reply_status'] == "success")
		{
			$dbh = setupDbh();

			$stmt = $dbh->prepare("SELECT content FROM ss13_bot_cache WHERE id = :id");
			$stmt->execute([":id" => $ss13->response['data']['message_id']]);
			$data = $stmt->fetch(PDO::FETCH_ASSOC);

			$response['data']['content'] = $data['content'];
		}

		$app->render(200, $response);

	} catch (Exception $e) {
		$app->render(500);
	}
});

/**
 * Route: /nudge/send
 *
 * requires @param auth_key for verification
 * requires @param message_id for relaying the nudge
 */
$app->get('/nudge/send', 'verifyRequest', function () use ($app) {
	$message_id = $app->request->get('message_id');

	if (!isset($message_id))
	{
		$app->render(500);
	}

	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

	$bot_address = getenv("BOT_HOST");
	$bot_port = intval(getenv("BOT_PORT"));

	if (!socket_connect($socket, $bot_address, $bot_port))
	{
		$app->render(500, ["error_msg" => "Socket timed out.", "address" => $bot_address, "port" => $bot_port]);
	}

	$message = "message_id=" . $message_id;
	$message .= "&auth_key=" . md5(getenv('AUTH_KEY'));
	socket_write($socket, $message, strlen($message));

	socket_close($socket);

	$app->render(200);
});

/**
 * Route: /nudge/receive
 *
 * requires @param auth_key for verification
 * requires @param message_id for relaying the nudge
 */
$app->get('/nudge/receive', 'verifyRequest', function () use ($app) {
	$message_id = $app->request->get('message_id');

	if (empty($message_id))
	{
		$app->render(500);
	}

	$dbh = setupDbh();

	$stmt = $dbh->prepare("SELECT msg_key, channel, content FROM ss13_bot_cache WHERE id = :id");
	$stmt->execute([":id" => $message_id]);

	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$response = [];
	foreach ($rows as $row)
	{
		$response['nudge'][$row['msg_key']] = [
			"channel" => $row['channel'],
			"content" => $row['content']
		];
	}

	$app->render(200, $response);
});

$app->run();
