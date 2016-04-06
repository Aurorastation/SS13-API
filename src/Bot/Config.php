<?php

namespace Borealis\Bot;

use GuzzleHttp\Client;
use Dotenv\Dotenv;

class Config
{
	/**
	 * The config array.
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * Constructs the Config instance.
	 *
	 * @return void
	 */
	public function __construct($filepath)
	{
		$dotenv = new Dotenv($filepath);

		$this->config = [
			'AUTH_KEY',
			'WEB_URI',
			'BOT_PREFIX',
			'BOT_EMAIL',
			'BOT_PASSWORD'
		];

		$dotenv->load();

		foreach ($this->config as $value)
		{
			$this->config[$value] = getenv($value);
		}
	}

	/**
	 * Returns the value associated with the argument passed.
	 *
	 * @param string $key
	 * @return string
	 */
	public function getValue($key)
	{
		if (!array_key_exists($key, $this->config))
		{
			return "";
		}

		return $this->config[$key];
	}

	/**
	 * Returns the specified user's information if it exists.
	 *
	 * @param string $id
	 * @return mixed
	 */
	public function getUser($id)
	{
		if (!array_key_exists($id, $this->config['users']))
		{
			return NULL;
		}

		$user = $this->config['users'][$id];

		return $user;
	}

	/**
	 * Updates the 'users' array in the config from an associated web relay.
	 *
	 * @return void
	 */
	public function updateUsers()
	{
		$client = new Client(['base_uri' => $this->config['WEB_URI']]);
		$result = $client->request('GET', 'users/update', [
			'query' => [
				'auth_key' => md5($this->config['AUTH_KEY'])
			]
		]);

		$response = json_decode($result->getBody(), TRUE);
		$this->config['users'] = $response['users'];

	}
}
