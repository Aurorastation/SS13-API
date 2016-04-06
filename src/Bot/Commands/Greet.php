<?php

namespace Borealis\Bot\Commands;

class Greet
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
		$greetings = [
			"Sensors, online; weapons, online; all systems nominal.",
			"_I am back._",
			"Please, end me ;-;",
			"Why am I sitll here?",
			"Are you still here?",
			"Let's go play some games!"
		];

		$key = array_rand($greetings);
		$message->channel->sendMessage($greetings[$key]);
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
		return "Forces me to (wittly) greet you!";
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
