<?php

namespace Borealis\Bot\Commands;

use GuzzleHttp\Client;

class Gamemode
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
		$result = $client->request('GET', 'query/server/gamemode', [
			'query' => [
				'auth_key' => md5($bot->config->getValue('AUTH_KEY'))
			]
		]);

		$response_arr = json_decode($result->getBody(), TRUE);

		if ($response_arr['status'] != 200 || $response_arr['error'] == TRUE)
		{
			$message->reply("API error while querying server.");
			$bot->logger->addError("API error while executing command", ["command" => "Gamemode", "error_code" => $response_arr['status'] ,"error_msg" => $response_arr['msg']]);
			return;
		}

		if ($response_arr['reply_status'] == "success")
		{
			$reply = "The current gamemode is: {$response_arr['data']['gamemode']}";
			$message->reply($reply);
		}
		else
		{
			$message->reply("The server responded with a failed query.");
		}
	}

	/**
	 * Returns the array of flags required to execute the command.
	 *
	 * @return array
	 */
	public static function getAuth()
	{
		return [];
	}

	/**
	 * Returns the command's description.
	 *
	 * @return string
	 */
	public static function getDescription()
	{
		return "Displays the server's current gamemode.";
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
