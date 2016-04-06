# BOREALISbot2
Second version of the BOREALIS bot, with a PHP Slim lightweight API included.

## The Bot
The bot runs using the [DiscordPHP library by TeamReflex](https://github.com/teamreflex/DiscordPHP).

#### Main Features
The bot can query a PHP Slim API in order to gain access to information from either within the game itself, or from the associated database if no direct interface with the server is necessary.
It also utilizes the ss13_admin table for handing out user authorization. Note that all locked commands are useless without that set up, and the API set up.

### Setup
Make sure you've ran `composer install` in the root directory. Read a guide on composer to figure out what it does and how it works.

To set up the bot, create a `.env` file in the root directory. Copy the contents from the `.env.example` file into that file, and give proper values to the the following fields:
* AUTH_KEY - the authentication get passed into every GET request, used to confirm identify and validate requests
* WEB_URI - the root URL where the attached Slim API is hosted from
* BOT_EMAIL - the email used by the bot to log in (notice: this bot does not use the token system)
* BOT_PASSWORD - the password used by the bot to log in
* BOT_PREFIX - a letter (preferably) which all commands have to be prefixed with in order to call them up

Once those are done, simply run the `run_bot.php` file through whichever means are available for your OS.

## The API
The API utilizes the [PHP Slim](http://www.slimframework.com/) framework. Specifically, subversions 2.3, in order to enable the usage of the [slim-json-api](https://github.com/entomb/slim-json-api) middleware.

### Main Features
This API is what acts as an interface between the game/database, and the bot. Instead of directly querying either, it sends a request to the API and uses the data that the API returns. Because the API needs to be accessible by the SS13 server as well, it primarily uses GET requests. All routes are secured via an authentication key which is passed as the `auth_key` parametre in the request itself.

It will also send nudges to the bot whenever a specific get request is made. These nudges use a database field for storing data, as returning large amounts of data via BYOND's `world.Export()` proc is finnicky. Instead, a simple ID is passed, and the related data is pulled from the database by the bot upon receiving the nudge. Effective structure: server sends a query to the API via GET, API sends a nudge via socket to the bot, the bot sends a query to the API via GET, the API returns data from the database and the bot then processes it.

### Setup
Make sure you've ran `composer install` in the root directory. Read a guide on composer to figure out what it does and how it works.

Note that the public root directory must be `public/`. The index.php file is located there.

To set up the API, create a `.env` file in the root directory. Copy the contents from the `.env.example` file into that file, and give proper values to the the following fields:
* AUTH_KEY - the authentication get passed into every GET request, used to confirm identify and validate requests
* DB_HOST - the address/URL of the database address (do not append the port)
* DB\_NAME - the name of the database wherein the various ss13\_ tables are housed
* DB_USER - the name of the database user to log in with
* DB_PASS - the password with which to log in to the database

The next 4 keys are optional. They will simply determine whether or not the API can be hooked up to the server, or the bot. Note that the bot can easily handle errors to an API without a connected server.
* SS13_HOST - the address of the SS13 server to query as needed
* SS13_PORT - the port of the SS13 server to query as needed
* BOT_HOST - the address of the bot
* BOT_PORT - the port of the bot

Once done, all of your routes should be accessible and usable.
