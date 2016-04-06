<?php

namespace Borealis\API;

class ServerQuery
{
	/**
	 * The host address of the server.
	 *
	 * @var string
	 */
	private $address;

	/**
	 * The port number of the server.
	 *
	 * @var string
	 */
	private $port;

	/**
	 * The socket to the server.
	 *
	 * @var Socket
	 */
	private $socket;

	/**
	 * The server's response.
	 *
	 * @var array
	 */
	public $response;

	/**
	 * The server's reply status.
	 *
	 * @var string
	 */
	public $reply_status;

	/**
	 * The auth key to use in queries.
	 *
	 * @var string
	 */
	private $auth_key;

	/**
	 * Sets up the query object.
	 *
	 * @param string $address
	 * @param string $port
	 * @return void
	 */
	public function setUp($address, $port, $auth_key)
	{
		$this->reply_status = "failed";

		if (!isset($address) || !isset($port))
		{
			throw new Exception("Invalid address or port.");
		}

		$this->address = $address;
		$this->port = $port;

		if (isset($auth_key))
		{
			$this->auth_key = $auth_key;
		}

		$sock = socket_create(AF_INET,  SOCK_STREAM, SOL_TCP);
		if ($sock === FALSE)
		{
			throw new Exception("Error creating socket.");
		}

		if (socket_connect($sock, $this->address, $this->port) === FALSE)
		{
			throw new Exception("Error connecting to host.");
		}

		$this->socket = $sock;
	}

	/**
	 * Queries the server we're connected to.
	 *
	 * @param array query
	 * @param boolean append_auth
	 * @return array
	 */
	public function runQuery($query, $append_auth = FALSE)
	{
		if (!isset($query) || !sizeof($query))
		{
			throw new Exception("Invalid query variable passed.");
		}

		if ($append_auth === TRUE)
		{
			if (!isset($this->auth_key))
			{
				throw new Exception("Auth key required but not set.");
			}

			$query['auth'] = md5($this->auth_key);
		}

		$assembled_query = $this->assembleQuery($query);
		$length = strlen($assembled_query);

		while (TRUE)
		{
			$sent = socket_write($this->socket, $assembled_query, $length);

			if ($sent === FALSE)
			{
				break;
			}

			if ($sent < $length)
			{
				$assembled_query = substr($assembled_query, $sent);

				$length -= $sent;
			}
			else
			{
				break;
			}
		}

		$result = socket_read($this->socket, 10000, PHP_BINARY_READ);
		socket_close($this->socket);

		$this->response = $this->parseResult($result);
	}

	/**
	 * Creates a query to send to the SS13 server.
	 *
	 * @param array $query
	 * @return string
	 */
	private function assembleQuery($query)
	{
		$assembled_query = "";

		foreach ($query as $key => $value)
		{
			if (!isset($value))
			{
				continue;
			}

			$assembled_query .= "&" . $key . "=" . $value;
		}

		return "\x00\x83" . pack("n", strlen($assembled_query) + 6) . "\x00\x00\x00\x00\x00" . $assembled_query . "\x00";
	}

	/**
	 * Parses the response from the server.
	 *
	 * @param string $result
	 * @return array
	 */
	private function parseResult($result)
	{
		$response_arr = [];

		if (!isset($result))
		{
			return $response_arr;
		}

		if ($result[0] == "\x00" || $result[1] == "\x83")
		{
			$sizebytes = unpack('n', $result[2] . $result[3]);
			$size = $sizebytes[1] - 1;

			if ($result[4] == "\x06")
			{
				$unpackstr = "";
				$index = 5;

				while ($size > 0)
				{
					$size--;
					$unpackstr .= $result[$index];
					$index++;
				}

				$unpackstr = str_replace("\x00", "", $unpackstr);

				$data_arr = explode("&", $unpackstr);
				foreach ($data_arr as $chunk)
				{
					$data = explode("=", $chunk);

					if ($data[0] == "reply_status")
					{
						$this->reply_status = $data[1];
						continue;
					}

					if (sizeof($data) == 2)
					{
						$response_arr[$data[0]] = $data[1];
					}
					else
					{
						array_push($response_arr, $data[0]);
					}
				}

				return $response_arr;
			}
		}

		return $response_arr;
	}
}
