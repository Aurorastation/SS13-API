<?php

namespace Borealis\Bot\Commands;

class Help
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
		$reply = "```\r\n-----------BOREALIS Directives-----------\r\n";

		$user = $bot->config->getUser($message->author->id);
		$prefix = $bot->config->getValue('BOT_PREFIX');

		foreach ($bot->getCommands() as $command => $data)
		{
			$is_authed = TRUE;
			if (sizeof($data['auth']))
			{
				$is_authed = FALSE;
				foreach ($data['auth'] as $perm)
				{
					if (!$user || !sizeof($user))
					{
						break;
					}
					if (in_array($perm, $user['auth']))
					{
						$is_authed = TRUE;
						break;
					}
				}
			}

			if ($is_authed == TRUE)
			{
				$reply .= "[+] {$prefix}{$command}";
				if (!empty($data['params']))
				{
					$reply .= " " . $data['params'];
				}

				$reply .= " - {$data['description']}\r\n";
			}
		}

		$reply .= "-----------------------------------------\r\n```";

		$message->author->sendMessage($reply);

		$message->reply("I've PM-d you with the list of commands available to you!");
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
		return "Used to display this message!";
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
