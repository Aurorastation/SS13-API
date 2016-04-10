<?php

namespace Borealis\Bot\Commands;

use GuzzleHttp\Client;

class Restartserver
{
	/**
	 * Runs the command.
	 *
	 * @param Message $message
	 * @param array $params
	 * @param Discord $discord
	 * @param Bot $bot
	 * @return void
	 */
	public static function runCommand($message, $params, $discord, $bot)
	{
		$client = new Client(['base_uri' => $bot->config->getValue('WEB_URI')]);
		$result = $client->request('GET', 'query/server/restartserver', [
			'query' => [
				'auth_key' => md5($bot->config->getValue('AUTH_KEY')),
				'admin' => $message->author->name
			]
		]);

		$response_arr = json_decode($result->getBody(), TRUE);

		if ($response_arr['status'] != 200 || $response_arr['error'] == TRUE)
		{
			$message->reply("API error while querying server.");
			$bot->logger->addError("API error while executing command", ["command" => "Restartserver", "error_code" => $response_arr['status'] ,"error_msg" => $response_arr['msg']]);
			return;
		}

		if ($response_arr['reply_status'] == "success")
		{
			$message->reply("Restart successful!");
		}
		else
		{
			$message->reply("The server failed to execute the command.");
		}
	}

	/**
	 * Returns the array of flags required to execute the command.
	 *
	 * @return array
	 */
	public static function getAuth()
	{
		return ["R_ADMIN"];
	}

	/**
	 * Returns the command's description.
	 *
	 * @return string
	 */
	public static function getDescription()
	{
		return "Restarts the SS13 server.";
	}

	/**
	 * Returns the helper text for the command's parametres.
	 *
	 * @return string
	 */
	public static function getParams()
	{
		return "";
	}
}
