# The BOREALIS (support) API
This is a lightweight PHP based API, made to support external services for an SS13 server. Primarily, the goal is to facilitate communication between the server, and external programs. (Such as chat bots, monitoring programs, etcetera.) As added support for chat bots, some routes permit access to the game server's database as well.

## Dependencies
The API utilizes the [PHP Slim](http://www.slimframework.com/) framework. Specifically, subversions 2.3, in order to enable the usage of the [slim-json-api](https://github.com/entomb/slim-json-api) middleware.
Used as well is [PHPdotenv](https://github.com/vlucas/phpdotenv), in order to read the .env files.

## Main Features
This API is what acts as an interface between the game/database, and external services. Instead of direct communication between, potentially multiple, services, authorized applications may sends a request to this API, and once the request is validated, the API will return an appropriate response. Because the API needs to be accessible by the SS13 server as well, it primarily uses GET requests. However, for operations that are to be done by more capable libraries, PUT, DELETE, and POST routes also exist.

As necessary, the bot will return data from the server's databases if at all possible, or query the server. Note that routes involving direct server queries may time out/return an error, should you catch the game server during a restart or a crash.

All routes require an authentication key to be passed, in order for the request to be serviced. For all requests, the key is a 32 character long MD5 hash of a password. Should authentication fail, error code 403 is returned. For GET requests, the authentication key must be passed as the 'auth_key' parameter. For PUT, DELETE, and POST requests, the 'auth_key' parameter must be included in the request body. (Note that data must be sent in the "x-www-form-urlencoded" fashion.)
