<?php

namespace Borealis\Bot\Commands;

use GuzzleHttp\Client;

class Playerinfo
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
		if (sizeof($params) < 2)
		{
			$message->reply("Not enough parametres passed! The proper syntax is: `" . $bot->config->getValue('BOT_PREFIX') . "playerinfo " . Playerinfo::getParams() . "`");
			return;
		}

		switch (strtolower($params[1])) {
			case 'database':
				$query_target = "query/database/playerinfo";
				break;

			case 'server':
				$query_target = "query/server/playerinfo";
				break;
			}

		if (isset($query_target))
		{
			$client = new Client(['base_uri' => $bot->config->getValue('WEB_URI')]);
			$result = $client->request('GET', $query_target, [
				'query' => [
					'auth_key' => md5($bot->config->getValue('AUTH_KEY')),
					'player' => strtolower($params[0])
				]
			]);

			$response_arr = json_decode($result->getBody(), TRUE);

			if ($response_arr['status'] != 200 || $response_arr['error'] == TRUE)
			{
				$message->reply("API error while executing query.");
				$bot->logger->addError("API error while executing command", ["command" => "Playerinfo", "error_code" => $response_arr['status'] ,"error_msg" => $response_arr['msg']]);
				return;
			}

			if ($response_arr['reply_status'] == "success")
			{
				if ($response_arr['data']['found'] == TRUE)
				{
					unset($response_arr['data']['found']);

					$reply = "Information regarding the {$params[0]}, retreived from the {$params[1]}:";
					foreach ($response_arr['data'] as $field => $data) {
						$reply .= "\r\n{$field}: {$data}";
					}
				}
				else
				{
					$reply = "No players with this ckey found";
				}
				$message->reply($reply);
			}
			else
			{
				$message->reply("The server responded with a failed query.");
			}
		}
		else
		{
			$message->reply("Second parametre passed incorrect. It has to be either ´database´ or ´server´.");
		}
	}

	/**
	 * Returns the array of flags required to execute the command.
	 *
	 * @return array
	 */
	public static function getAuth()
	{
		return ["R_MOD", "R_ADMIN"];
	}

	/**
	 * Returns the command's description.
	 *
	 * @return string
	 */
	public static function getDescription()
	{
		return "Queries either the database or the game for a given player's information.";
	}

	/**
	 * Returns the helper text for the command's parametres.
	 *
	 * @return string
	 */
	public static function getParams()
	{
		return "<ckey> <database/game>";
	}
}
