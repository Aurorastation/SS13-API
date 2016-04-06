<?php

namespace Borealis\Bot\Commands;

use GuzzleHttp\Client;

class Staffwho
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
		$result = $client->request('GET', 'query/server/staffwho', [
			'query' => [
				'auth_key' => md5($bot->config->getValue('AUTH_KEY'))
			]
		]);

		$is_staff = $bot->checkAuth($message->author->id, ["R_MOD", "R_ADMIN", "R_CCIAA"]);

		$response_arr = json_decode($result->getBody(), TRUE);

		if ($response_arr['status'] != 200 || $response_arr['error'] == TRUE)
		{
			$message->reply("API error while querying server.");
			$bot->logger->addError("API error while executing command", ["command" => "Staffwho", "error_code" => $response_arr['status'] ,"error_msg" => $response_arr['msg']]);
			return;
		}

		if ($response_arr['reply_status'] == "success")
		{
			$i = sizeof($response_arr['data']);
			if ($i)
			{
				$reply = "Server staff currently online:\r\n";
				foreach ($response_arr['data'] as $team) {
					$reply .= "**{$team}**:\r\n";
					foreach ($team as $staff => $status)
					{
						$reply .= "{$staff}";
						if ($is_staff === TRUE)
						{
							$reply .= " - {$status}";
						}

						$reply .= "\r\n";
					}
					$i++;

					if ($i != sizeof($response_arr))
					{
						$reply .= "\r\n\r\n";
					}
				}

				$message->reply($reply);
			}
			else
			{
				$reply = "No staff currently on the server. Remember, though, _I am always watching._";
			}
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
		return "Gives you a list of players playing on the server.";
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
