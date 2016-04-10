<?php

namespace Borealis\Bot\Commands;

use GuzzleHttp\Client;

class Muteplayer
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
		if (sizeof($params) < 1)
		{
			$message->reply("Not enough parametres passed! The proper syntax is: `" . $bot->config->getValue('BOT_PREFIX') . "muteplayer " . Muteplayer::getParams() . "`");
			return;
		}
		else
		{
			$client = new Client(['base_uri' => $bot->config->getValue('WEB_URI')]);
			$result = $client->request('GET', 'query/server/muteplayer', [
				'query' => [
					'auth_key' => md5($bot->config->getValue('AUTH_KEY')),
					'player' => strtolower($params[0])
				]
			]);

			$response_arr = json_decode($result->getBody(), TRUE);

			if ($response_arr['status'] != 200 || $response_arr['error'] == TRUE)
			{
				$message->reply("API error while querying server.");
				$bot->logger->addError("API error while executing command", ["command" => "Muteplayer", "error_code" => $response_arr['status'] ,"error_msg" => $response_arr['msg']]);
				return;
			}

			if ($response_arr['reply_status'] == "success")
			{
				if (isset($response_arr['data']['muted']))
				{
					$message->reply("{$params[0]} is now {$response_arr['data']['muted']}.");
				}
				else
				{
					$message->reply("No player with that ckey found on the server.");
				}
			}
			else
			{
				$message->reply("The server failed to execute the command.");
			}
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
		return "Mutes the specified player from responding to discord messages.";
	}

	/**
	 * Returns the helper text for the command's parametres.
	 *
	 * @return string
	 */
	public static function getParams()
	{
		return "<ckey>";
	}
}
