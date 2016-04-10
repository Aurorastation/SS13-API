<?php

ini_set('max_execution_time', 0);
ini_set('memory_limit', '200M');

use Borealis\Bot\Bot;
use Borealis\Bot\Config;
use Discord\Discord;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

require 'vendor/autoload.php';

try {
	$logger = new Logger('BOREALIS');
	$logger->pushHandler(new StreamHandler('borealis.log', Logger::NOTICE));

	$logger->addNotice("Service started.");
	$bot = new Bot(__DIR__, $logger);
} catch (\Exception $e) {
	print("Error while starting bot:");
	print($e->getMessage());
	$logger->addAlert("Critical exception during start-up", ["error" => $e->getMessage()]);
	die(1);
}


try {
	$bot->addCommand('addchannel', \Borealis\Bot\Commands\Addchannel::class);
	$bot->addCommand('adminpm', \Borealis\Bot\Commands\Adminpm::class);
	$bot->addCommand('announceserver', \Borealis\Bot\Commands\Announceserver::class);
	$bot->addCommand('faxget', \Borealis\Bot\Commands\Faxget::class);
	$bot->addCommand('faxlist', \Borealis\Bot\Commands\Faxlist::class);
	$bot->addCommand('gamemode', \Borealis\Bot\Commands\Gamemode::class);
	$bot->addCommand('help', \Borealis\Bot\Commands\Help::class);
	$bot->addCommand('greet', \Borealis\Bot\Commands\Greet::class);
	$bot->addCommand('muteplayer', \Borealis\Bot\Commands\Muteplayer::class);
	$bot->addCommand('playerinfo', \Borealis\Bot\Commands\Playerinfo::class);
	$bot->addCommand('playernotes', \Borealis\Bot\Commands\Playernotes::class);
	$bot->addCommand('players', \Borealis\Bot\Commands\Players::class);
	$bot->addCommand('restartserver', \Borealis\Bot\Commands\Restartserver::class);
	$bot->addCommand('staffwho', \Borealis\Bot\Commands\Staffwho::class);
	$bot->addCommand('who', \Borealis\Bot\Commands\Who::class);
	$bot->addCommand('addchannel', \Borealis\Bot\Commands\Addchannel::class);
	$bot->addCommand('updateusers', \Borealis\Bot\Command\Updateusers::class);
	$bot->addCommand('whoami', \Borealis\Bot\command\Whoami::class);
	$bot->addCommand('cats', \Borealis\Bot\Commands\Cats::class);
	$bot->addCommand('penguins', \Borealis\Bot\Commands\Penguins::class);
} catch (\Exception $e) {
	print("Error while initing commands:");
	print($e->getMessage());
	$logger->addAlert("Critical exception while adding commands", ["error" => $e->getMessage()]);
	die(1);
}

$bot->start();
