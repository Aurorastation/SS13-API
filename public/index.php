<?php

require '../vendor/autoload.php';

use Slim\Slim;
use Dotenv\Dotenv;
use Borealis\API\ServerQuery;

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

	$key = $app->request()->get('auth_key');

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
 * Route: /users/update
 *
 * requires @param auth_key for verification
 */
$app->get('/users/update', 'verifyRequest', function () use ($app) {
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
			$stmt->execute([":ckey" => $params['player']]);
			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

			$notes = array();

			foreach ($rows as $note)
			{
				array_push($notes, implode(" || ", $note));
			}

			$response = $notes;

			break;

		case 'playerinfo':
			$stmt = $dbh->prepare("SELECT firstseen, lastseen, lastadminrank FROM ss13_player WHERE ckey = :ckey");
			$stmt->execute([":ckey" => $params['player']]);
			$data = $stmt->fetch(PDO::FETCH_ASSOC);

			$response['found'] = TRUE;

			if (!isset($data['firstseen']))
			{
				$response['found'] = FALSE;
			}

			$response['First seen'] = $data['firstseen'];
			$response['Last seen'] = $data['lastseen'];
			$response['Rank'] = $data['lastadminrank'];

			$stmt = $dbh->prepare("SELECT count(*) as note_count FROM ss13_notes WHERE ckey = :ckey AND visible = '1'");
			$stmt->execute([":ckey" => $params['player']]);
			$data = $stmt->fetch(PDO::FETCH_ASSOC);

			$response['Notes'] = $data['note_count'];

			$stmt = $dbh->prepare("SELECT count(*) as warning_count FROM ss13_warnings WHERE ckey = :ckey AND visible = 1");
			$stmt->execute([":ckey" => $params['player']]);
			$data = $stmt->fetch(PDO::FETCH_ASSOC);

			$response['Warnings'] = $data['warning_count'];

			$stmt = $dbh->prepare("SELECT count(*) as active_bans FROM ss13_ban WHERE ckey = :ckey AND (bantype = 'PERMABAN'  OR (bantype = 'TEMPBAN' AND expiration_time > Now())) AND unbanned IS NULL");
			$stmt->execute([":ckey" => $params['player']]);
			$data = $stmt->fetch(PDO::FETCH_ASSOC);

			if ($data['active_bans'] > 0)
			{
				$response['Is banned'] = "Yes";
			}
			else
			{
				$response['Is banned'] = "No";
			}

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
	$bot_port = getenv("BOT_PORT");
	socket_connect($socket, $bot_address, $bot_port);

	$message = "nudge=" . $message_id;
	$message .= "?auth_key=" . md5(getenv('AUTH_KEY'));
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

	if (!isset($message_id))
	{
		$app->render(500);
	}

	$dbh = setupDbh();

	$stmt = $dbh->prepare("SELECT key, channel, content FROM ss13_bot_cache WHERE id = :id");
	$stmt->execute([":id" => $message_id]);

	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$response = [];
	foreach ($rows as $row)
	{
		$response['nudge'][$row['key']] = [
			"channel" => $row['channel'],
			"content" => $row['content']
		];
	}

	$app->render(200, $response);
});

$app->run();
