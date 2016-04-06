<?php

namespace Borealis\Bot\Commands;

use GuzzleHttp\Client;

class Playernotes
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
			$message->reply("Not enough parametres passed! The proper syntax is: `" . $bot->config->getValue('prefix') . "playernotes " . Playernotes::getParams() . "`");
		}
		else
		{
			$client = new Client(['base_uri' => $bot->config->getValue('WEB_URI')]);
			$result = $client->request('GET', 'query/database/playernotes', [
				'query' => [
					'auth_key' => md5($bot->config->getValue('AUTH_KEY')),
					'player' => strtolower($params[0])
				]
			]);

			$response_arr = json_decode($result->getBody(), TRUE);

			if ($response_arr['status'] != 200 || $response_arr['error'] == TRUE)
			{
				$message->reply("API error while querying database.");
				$bot->logger->addError("API error while executing command", ["command" => "Playernotes", "error_code" => $response_arr['status'] ,"error_msg" => $response_arr['msg']]);
				return;
			}

			if ($response_arr['reply_status'] == "success")
			{
				$notes = $response_arr['data'];

				if (sizeof($notes))
				{
					$use_pm = sizeof($notes) > 5;

					$initial_message = "Displaying notes issued to {$params[0]}:";
					if ($use_pm == TRUE)
					{
						$message->author->sendMessage($initial_message);
						$message->reply("Check your PMs.");
					}
					else
					{
						$message->reply($initial_message);
					}

					foreach ($notes as $note) {
						if ($use_pm == TRUE)
						{
							$message->author->sendMessage($note);
						}
						else
						{
							$message->reply($note);
						}
					}
				}
				else
				{
					$reply = "No notes tied to the given ckey.";
					$message->reply($reply);
				}
			}
			else
			{
				$message->reply("The server responded with a failed query.");
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
		return "Looks through the database for a specific player's notes. PMs them if there are more than 5.";
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
