<?php

namespace Borealis\Bot\Commands;

class Addchannel
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
			$message->reply("Not enough parametres passed! The proper syntax is: `" . $bot->config->getValue('BOT_PREFIX') . "addchannel " . Addchannel::getParams() . "`");
		}
		else
		{
			if ($bot->addChannel($params[0], $message->getChannelAttribute()) === TRUE)
			{
				$message->reply("Channel added to group {$params[0]}!");
				$bot->logger->addNotice("Added channel to group", ["group" => $params[0], "userID" => $message->author->id]);
			}
			else
			{
				$message->reply("Adding channel failed!");
				$bot->logger->addNotice("Failed to add channel to group", ["group" => $params[0], "userID" => $message->author->id]);
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
		return ["R_ADMIN"];
	}

	/**
	 * Returns the command's description.
	 *
	 * @return string
	 */
	public static function getDescription()
	{
		return "Adds an entry into my stored channel array. Messages designated to that channel group will be echoed in the new channel.";
	}

	/**
	 * Returns the helper text for the command's parametres.
	 *
	 * @return string
	 */
	public static function getParams()
	{
		return "<CHANNEL_MOD/CHANNEL_ADMIN/CHANNEL_CCIAA/CHANNEL_LOBBY>";
	}
}
