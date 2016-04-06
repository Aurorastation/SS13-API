<?php

namespace Borealis\Bot;

use Borealis\Bot\Config;
use Discord\Discord;
use Discord\WebSockets\Event;
use Discord\WebSockets\WebSocket;
use Discord\Parts\Channel\Channel;
use React\Socket\Server;
use GuzzleHttp\Client;

class Bot
{
	/**
	 * The Discord instance.
	 *
	 * @var Discord
	 */
	protected $discord;

	/**
	 * The Discord WebSocket instance.
	 *
	 * @var WebSocket
	 */
	protected $websocket;

	/**
	 * The list of commands.
	 *
	 * @var array
	 */
	protected $commands = [];

	/**
	 * The list of channels set.
	 *
	 * @var array
	 */
	protected $channels = [];

	/**
	 * The config instance.
	 *
	 * @var Config
	 */
	public $config;

	/**
	 * The Monolog logger.
	 *
	 * @var Logger
	 */
	public $logger;

	/**
	 * The websocket instance through for receiving nudges.
	 *
	 * @var Server
	 */
	protected $nudge;

	/**
	 * Constructor for the Bot.
	 *
	 * @param string $config_filename
	 * @return void
	 */
	public function __construct($config_filename, $logger)
	{
		$this->config = new Config($config_filename);

		$this->discord = new Discord($this->config->getValue('BOT_EMAIL'), $this->config->getValue('BOT_PASSWORD'));
		$this->websocket = new WebSocket($this->discord);

		$this->config->updateUsers();

		$this->channels = [
			"CHANNEL_ADMIN" => [],
			"CHANNEL_MOD" => [],
			"CHANNEL_CCIAA" => [],
			"CHANNEL_LOBBY" => [],
			"CHANNEL_ANNOUNCE" => []
		];

		$this->logger = $logger;
		$this->logger->addNotice("Bot constructed");
	}

	/**
	 * Starts the Bot.
	 *
	 * @return void
	 */
	public function start()
	{

		// Command handling.
		$this->websocket->on(Event::MESSAGE_CREATE, function ($message, $discord, $new) {
			$words = explode(' ', $message->content);

			// Safety checks for the time being.
			if (sizeof($words) && array_key_exists($words, 0))
			{
				foreach ($this->commands as $command => $data)
				{
					if ($words[0] == $this->config->getValue('BOT_PREFIX') . $command)
					{
						array_shift($words);

						$is_authed = $this->checkAuth($message->author->id, $data['auth']);

						if ($is_authed === TRUE)
						{
							try
							{
								$data['class']::runCommand($message, $words, $new, $this);
							}
							catch (\Exception $e)
							{
								try
								{
									$this->logger->addError("Error while executing command", ["command" => $command, "error" => $e->getMessage()]);
									$message->reply("There was an error while executing the command.");
								}
								catch (\Exception $e2)
								{
									$this->logger->addAlert("Error while executing command", ["error" => $e2->getMessage()]);
								}
							}
						}
						else
						{
							try
							{
								$message->reply("You are not authorized to use this command.");
							}
							catch (\Exception $e)
							{
								$this->logger->addAlert("Discord error while replying to message", ["error" => $e->getMessage()]);
							}
						}
					}
				}
			}
		});

		// ready event.
		$this->websocket->on('ready', function ($discord) {
			$discord->updatePresence($this->websocket, "aboard the NSS Aurora", FALSE);
		});

		// Update the config's users.
		// Ran every 86 400 seconds (24 hours).
		$callUpdateUsers = function () {
			$this->config->updateUsers();
		};
		$this->websocket->loop->addPeriodicTimer(86400, $callUpdateUsers);

		// Set up the nudge listener.
		// Orders a check whenever a connection from a specific address is identified with the proper auth key.
		$this->nudge = new Server($this->websocket->loop);
		$this->nudge->on('connection', function ($conn) {
			$conn->on('data', function ($data) use ($conn)
			{
				$parsed_data = [];
				foreach (explode($data, "?") as $field) {
					$field = explode($field);
					$parsed_data[$field[0]] = $field[1];
				}

				if (isset($parsed_data['auth_key']) && strcmp($parsed_data['auth_key'], md5($this->config->getValue('AUTH_KEY'))) === 0)
				{
					$this->logger->addNotice("Data received from whitelisted host", ["host" => $conn->getRemoteAddress()]);
					$this->handleNudge($parsed_data['nudge']);
				}
				else
				{
					$this->logger->addWarning("Data received from non-whitelisted host. Connection closed", ["host" => $conn->getRemoteAddress()]);
					$conn->close();
				}
			});
		});
		$this->nudge->listen(4000);

		$this->logger->addNotice("Bot started");
		$this->websocket->run();
	}

	/**
	 * Adds a command.
	 *
	 * @param string $command
	 * @param string $class
	 * @return void
	 */
	public function addCommand($command, $class)
	{
		$this->commands[$command] = [
			'class' => $class,
			'auth' => $class::getAuth(),
			'description' => $class::getDescription(),
			'params' => $class::getParams()
		];
		$this->logger->addNotice("Added command", ["command" => $command]);
	}

	/**
	 * Returns the array of commands.
	 *
	 * @return array
	 */
	public function getCommands()
	{
		return $this->commands;
	}

	/**
	 * Adds a channel object to the related string.
	 *
	 * @param string $channel_str
	 * @param Channel $channel_obj
	 * @return boolean
	 */
	public function addChannel($channel_str, $channel_obj)
	{
		if (array_key_exists($channel_str, $this->channels))
		{
			array_push($this->channels[$channel_str], $channel_obj);
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Checks if a user has one of the rights required of them.
	 *
	 * @param string $user_id
	 * @param array $auth
	 * @return boolean
	 */
	public function checkAuth($user_id, $auth)
	{
		if (!isset($auth) || !sizeof($auth))
		{
			return TRUE;
		}

		$user = $this->config->getUser($user_id);

		if (!$user || !sizeof($user))
		{
			return FALSE;
		}

		foreach ($auth as $perm)
		{
			if (in_array($perm, $user['auth']))
			{
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Gets the information about a nudge from the API and forwards its contents as needed.
	 *
	 * @param integer $nudge_id
	 * @return void
	 */
	private function handleNudge($nudge_id)
	{
		if (!isset($nudge_id))
		{
			$this->logger->addWarning("No ID sent with a nudge");
			return;
		}

		$client = new Client(['base_uri' => $this->config->getValue('WEB_URI')]);
		$result = $client->request('GET', 'nudge/receive', [
			'query' => [
				'auth_key' => md5($this->config->getValue('AUTH_KEY')),
				'message_id' => $nudge_id
			]
		]);

		$response_arr = json_decode($result->getBody(), TRUE);

		if ($response_arr['status'] == 200)
		{
			foreach ($response_arr['nudge'] as $key => $value)
			{
				$this->forwardMessage($value['channel'], $value['content']);
			}
		}
	}

	/**
	 * Forwards a message to a given channel, splits the message as necessary.
	 *
	 * @param string $channel_str
	 * @param string $content
	 * @return void
	 */
	private function forwardMessage($channel_str, $content)
	{
		if (!isset($channel_str) || !sizeof($this->channels[$channel_str]))
		{
			return;
		}

		$channels = $this->channels[$channel_str];
		$chunks = [];

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
				$channel->sendMessage($message);
			}
		}
	}
}
