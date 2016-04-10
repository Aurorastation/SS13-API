<?php

namespace Borealis\Bot\Commands;

use GuzzleHttp\Client;

class Faxget
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
		if (sizeof($params) < 2 || !(strtolower($params[0]) == "sent" || strtolower($params[0]) == "received") || !is_numeric($params[1]))
		{
			$message->reply("Not enough parametres passed! The proper syntax is: `" . $bot->config->getValue('BOT_PREFIX') . "faxget " . Faxget::getParams() . "`");
			return;
		}
		else
		{
			$client = new Client(['base_uri' => $bot->config->getValue('WEB_URI')]);
			$result = $client->request('GET', 'query/server/faxget', [
				'query' => [
					'auth_key' => md5($bot->config->getValue('AUTH_KEY')),
					'method' => strtolower($params[0]),
					'id' => $params[1]
				]
			]);

			$response_arr = json_decode($result->getBody(), TRUE);

			if ($response_arr['status'] != 200 || $response_arr['error'] == TRUE)
			{
				$message->reply("API error while querying server.");
				$bot->logger->addError("API error while executing command", ["command" => "Faxget", "error_code" => $response_arr['status'] ,"error_msg" => $response_arr['msg']]);
				return;
			}

			if ($response_arr['reply_status'] == "success")
			{
				if ($response_arr['data'])
				{
					$message->channel->sendMessage("Displaying {$params[0]} fax #{$params[1]}.\r\n");
					$message->channel->sendMessage("Sent by {$response_arr['data']['sentby']}, titled ´{$response_arr['data']['title']}´:\r\n");

					$content = $response_arr['data']['content'];

					while (TRUE)
					{
						$size = strlen($content);
						$offset = 2000;

						if ($size <= 2000)
						{
							array_push($chunks, $content);
							break;
						}

						$pos = strpos($strrev($content), " ", $size - $offset);

						$to_send = substr($content, 0, $pos);
						array_push($chunks, $content);

						$content = substr($content, $pos);
					}

					foreach ($chunks as $message)
					{
						foreach ($channels as $channel)
						{
							$mesasge->channel->sendMessage($message);
						}
					}
				}
				else
				{
					$reply = "No such fax found.";
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
		return "<received/sent> <fax ID number>";
	}
}
