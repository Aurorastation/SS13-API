<?php

namespace Borealis\Bot\Commands;

use GuzzleHttp\Client;

class Adminpm
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
			$message->reply("Not enough parametres passed! The proper syntax is: `" . $bot->config->getValue('BOT_PREFIX') . "adminpm " . Adminpm::getParams() . "`");
			return;
		}
		else
		{
			$ckey = strtolower($params[0]);

			$params = array_shift($params);
			$msg = implode(" ", $params);

			$client = new Client(['base_uri' => $bot->config->getValue('WEB_URI')]);
			$result = $client->request('GET', 'query/server/adminpm', [
				'query' => [
					'auth_key' => md5($bot->config->getValue('AUTH_KEY')),
					'player' => $ckey,
					'admin' => $message->author->getUsernameAttribute(),
					'msg' => $msg
				]
			]);

			$response_arr = json_decode($result->getBody(), TRUE);

			if ($response_arr['status'] != 200 || $response_arr['error'] == TRUE)
			{
				$message->reply("API error while querying server.");
				$bot->logger->addError("API error while executing command", ["command" => "Announceserver", "error_code" => $response_arr['status'] ,"error_msg" => $response_arr['msg']]);
				return;
			}

			if ($response_arr['reply_status'] == "success")
			{
				$message->reply("Message sent!");
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
		return "PMs the player specified.";
	}

	/**
	 * Returns the helper text for the command's parametres.
	 *
	 * @return string
	 */
	public static function getParams()
	{
		return "<ckey> <message follows here>";
	}
}
