<?php

namespace RPurinton\AshDiscord;

require_once(__DIR__ . "/vendor/autoload.php");

use React\Async;

class DiscordClient
{
	private $loop = null;
	private $discord = null;
	private $bot_id = null;
	private $admins = [
		"363853952749404162" => "Russell",
		"450271229526540298" => "Leon",
		"675464584580169762" => "Matu",
		"259826523433861121" => "Alexander",
		"890623495518715904" => "Espen",
		"979467395238342717" => "Ryo",
		"1104434656159465652" => "Victor",
	];

	function __construct(int $bot_id, string $bot_token)
	{
		$this->bot_id = $bot_id;
		$this->loop = \React\EventLoop\Loop::get();
		$discord_config["token"] = $bot_token;
		$discord_config["loop"] = $this->loop;
		$this->discord = new \Discord\Discord($discord_config);
		$this->discord->on("ready", $this->ready(...));
		$this->discord->run();
	}

	private function ready()
	{
		$this->discord->on("raw", $this->inbox(...));
		$pretty_name = shell_exec("cat /etc/os-release | grep PRETTY_NAME | cut -d '=' -f 2 | sed 's/\"//g'");
		$activity = $this->discord->factory(\Discord\Parts\User\Activity::class, [
			'name' => $pretty_name,
			'type' => \Discord\Parts\User\Activity::TYPE_PLAYING
		]);
		$this->discord->updatePresence($activity);
		echo ("bot_id: " . $this->bot_id . " is Ready!\n");
	}

	private function inbox($message, $discord)
	{
		print_r($message);
		if ($message->op == 11) {
			return;
		}
		if ($message->t != "MESSAGE_CREATE") {
			return true; // Skip processing the message
		}

		// Check if the message is from the bot itself
		if ($message->d->author->id == $this->bot_id) {
			return true; // Skip processing the message
		}

		// Check if the message is not from an admin ignore
		if (!isset($this->admins[$message->d->author->id])) {
			return true; // Skip processing the message
		}

		// Check if the message starts with a !
		if (substr($message->d->content, 0, 1) == "!") {
			return true; // Skip processing the message
		}

		if (isset($message->d->referenced_message)) {
			if ($message->d->referenced_message->author->id == $this->bot_id) {
				$relevant = true;
			}
		}

		$guild = $discord->guilds[$message->d->guild_id];
		$channel = $guild->channels[$message->d->channel_id];
		$bot_member = $guild->members[$this->bot_id];

		$bot_roles = [];
		foreach ($bot_member->roles as $role) {
			$bot_roles[] = $role->id;
		}

		foreach ($message->d->mention_roles as $role_id) {
			if (in_array($role_id, $bot_roles)) {
				$relevant = true;
			}
		}
		if (strpos($message->d->content, "<@{$this->bot_id}>") !== false) {
			$relevant = true;
		}

		if (!$relevant) {
			return true; // Skip processing the message
		}
		$in_content = $message->d->content;
		$in_content = str_replace("<@{$this->bot_id}>", "ash", $in_content);
		$in_content = $this->admins[$message->d->author->id] . " says: " . $in_content;
		$in_content = escapeshellarg($in_content);
		$cmd = "ash /m $in_content";
		while (strpos($cmd, "  ") !== false) $cmd = str_replace("  ", " ", $cmd);
		$guild = $this->discord->guilds[$message->d->guild_id];
		$channel = $guild->channels[$message->d->channel_id];
		$channel->broadcastTyping();
		chdir(trim(shell_exec("echo ~")));
		$result = shell_exec($cmd);
		$this->MESSAGE_CREATE($channel, $result);
		return true;
	}

	private function MESSAGE_CREATE($channel, $result)
	{

		if (strlen($result) < 2000) {
			$channel->sendMessage($result);
			return true;
		}
		$result = $result . " ";
		$lines = explode("\n", $result);
		$mode = "by_line";
		$result = "";
		while (count($lines)) {
			$line = array_shift($lines);
			if (strlen($line) > 2000) {
				$sentences = explode(". ", $line);
				$mode = "by_sentence";
				foreach ($lines as $line) {
					$sentences[] = $line;
				}
				$lines = $sentences;
				$line = array_shift($lines);
			}
			if (strlen($line) > 2000) {
				$words = explode(" ", $line);
				$mode = "by_word";
				foreach ($lines as $line) {
					$words[] = $line;
				}
				$lines = $words;
				$line = array_shift($lines);
			}
			if (strlen($line) > 2000) {
				$chars = str_split($line);
				$mode = "by_char";
				foreach ($lines as $line) {
					$chars[] = $line;
				}
				$lines = $chars;
				$line = array_shift($lines);
			}
			$old_result = $result;
			switch ($mode) {
				case "by_char":
					$result .= $line;
					break;
				case "by_word":
					$result .= $line . " ";
					break;
				case "by_sentence":
					$result .= $line . ". ";
					break;
				case "by_line":
					$result .= $line . "\n";
					break;
			}
			if (strlen($result) > 2000) {
				$result = $old_result;
				array_unshift($lines, $line);
				// if last char of result is a space then remove it
				if (substr($result, -1) == " ") $result = substr($result, 0, -1);
				$channel->sendMessage($result);
				$result = "";
			}
		}
		if (strlen(trim($result))) $channel->sendMessage(trim($result));
		return true;
	}
}
