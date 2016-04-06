<?php

namespace Borealis\Bot\Commands;

use GuzzleHttp\Client;

class Players
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
		$result = $client->request('GET', 'query/server/playercount', [
			'query' => [
				'auth_key' => md5($bot->config->getValue('AUTH_KEY'))
			]
		]);

		$response_arr = json_decode($result->getBody(), TRUE);

		if ($response_arr['status'] != 200 || $response_arr['error'] == TRUE)
		{
			$message->reply("API error while querying server.");
			$bot->logger->addError("API error while executing command", ["command" => "Players", "error_code" => $response_arr['status'] ,"error_msg" => $response_arr['msg']]);
			return;
		}

		if ($response_arr['reply_status'] == "success")
		{
			$message->reply("There are {$response_arr['data']['playercount']} players currently on the server!");
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
		return "Queries the server and figures out how many folks are playing.";
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
