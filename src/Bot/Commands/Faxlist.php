<?php

namespace Borealis\Bot\Commands;

use GuzzleHttp\Client;

class Faxlist
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
		if (sizeof($params) < 1 || !(strtolower($params[0]) == "sent" || strtolower($params[0]) == "received"))
		{
			$message->reply("Not enough parametres passed! The proper syntax is: `" . $bot->config->getValue('BOT_PREFIX') . "faxlist " . Faxlist::getParams() . "`");
		}
		else
		{
			$client = new Client(['base_uri' => $bot->config->getValue('WEB_URI')]);
			$result = $client->request('GET', 'query/server/faxlist', [
				'query' => [
					'auth_key' => md5($bot->config->getValue('AUTH_KEY')),
					'method' => strtolower($params[0])
				]
			]);

			$response_arr = json_decode($result->getBody(), TRUE);

			if ($response_arr['status'] != 200 || $response_arr['error'] == TRUE)
			{
				$message->reply("API error while querying server.");
				$bot->logger->addError("API error while executing command", ["command" => "Faxlist", "error_code" => $response_arr['status'] ,"error_msg" => $response_arr['msg']]);
				return;
			}

			if ($response_arr['reply_status'] == "success")
			{
				if ($response_arr['data'])
				{
					$reply = "Faxes {$params[0]} this round:";
					foreach ($response_arr['data'] as $id => $title) {
						$reply .= "\r\n#{$title} - {$title}";
					}
				}
				else
				{
					$reply = "No faxes {$params[0]} this round. Yet.";
				}
				$message->reply($reply);
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
		return ["R_CCIAA"];
	}

	/**
	 * Returns the command's description.
	 *
	 * @return string
	 */
	public static function getDescription()
	{
		return "Gives you a list of faxes with their attached IDs.";
	}

	/**
	 * Returns the helper text for the command's parametres.
	 *
	 * @return string
	 */
	public static function getParams()
	{
		return "<received/sent>";
	}
}
