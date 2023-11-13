<?php

namespace RPurinton\AshDiscord;

use React\Async;

class DiscordClient extends ConfigLoader
{
	private $loop = null;
	private $discord = null;
	private $bunny = null;
	private $bot_id = null;
	private $promptwriter = null;
	private $discord_roles = [];

	function __construct(int $bot_id, string $bot_token)
	{
		parent::__construct();
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

		// Check if the message is from the translator and if so ignore it 
		if ($message->d->author->id == 1073766516803260437) {
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
		$publish_message = json_decode(json_encode($message), true);
		$publish_message["d"]["bot_id"] = $this->bot_id;
		$publish_message["d"]["roles"] = $guild->roles;
		$publish_message["d"]["bot_roles"] = $bot_roles;
		$publish_message["d"]["channel_name"] = $channel->name;
		$publish_message["d"]["channel_topic"] = $channel->topic;
		$microtime = number_format(microtime(true), 6, '.', '');
		$publish_message["d"]["microtime"] = $microtime;
		print_r($publish_message);
		return true;
	}

	private function outbox($message)
	{
		switch ($message["function"]) {
			case "DIE":
				return $this->DIE();
			case "MESSAGE_CREATE":
				return $this->MESSAGE_CREATE($message);
			case "GET_CHANNEL":
				return $this->GET_CHANNEL($message);
			case "START_TYPING":
				return $this->START_TYPING($message);
		}
		return true;
	}

	private function DIE()
	{
		echo ("DiscordClient_{$this->bot_id} STOP cmd received.\n");
		$this->loop->addPeriodicTimer(1, function () {
			die();
		});
		return true;
	}

	private function START_TYPING($message)
	{
		$channel = $this->discord->getChannel($message["channel_id"]);
		if ($channel) $channel->broadcastTyping();
		return true;
	}

	private function GET_CHANNEL($message)
	{
		$guild = $this->discord->guilds[$message["guild_id"]];
		$channel = $guild->channels[$message["channel_id"]];
		$history = Async\await($channel->getMessageHistory(['limit' => 100]));
		$publish_message = $message;
		$publish_message["history"] = $history;
		$publish_message["channel_name"] = $channel->name;
		$publish_message["channel_topic"] = $channel->topic;
		$publish_message["roles"] = $guild->roles;
		foreach ($guild->roles as $key => $value) $this->discord_roles[$key] = $value->name;
		$bot_member = $guild->members[$this->bot_id];
		$bot_roles = [];
		foreach ($bot_member->roles as $role) {
			$bot_roles[] = $role->id;
		}
		$publish_message["bot_roles"] = $bot_roles;
		$this->bunny->publish($message["queue"], $publish_message);
		return true;
	}

	private function MESSAGE_CREATE($message)
	{
		$ignore = isset($message["ignore"]) ? $message["ignore"] : false;
		if (!isset($message["content"]) || strlen($message["content"]) < 2000) {
			Async\await($this->discord->getChannel($message["channel_id"])->sendMessage($this->builder($message)));
			return true;
		}
		$content = $message["content"] . " ";
		$lines = explode("\n", $content);
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
				$message["content"] = $result;
				Async\await($this->discord->getChannel($message["channel_id"])->sendMessage($this->builder($message)));
				$result = "";
			}
		}
		if (strlen($result)) {
			$message["content"] = $result;
			Async\await($this->discord->getChannel($message["channel_id"])->sendMessage($this->builder($message)));
		}
		return true;
	}

	private function builder($message)
	{
		$builder = \Discord\Builders\MessageBuilder::new();
		//if ($this->bot_id == 1143781600807637112) $builder->setTts(true);
		//if ($this->bot_id == 1112146727932268585) $builder->setTts(true);

		if (isset($message["content"])) {
			$builder->setContent($message["content"]);
		}
		if (isset($message["addFileFromContent"])) {
			foreach ($message["addFileFromContent"] as $attachment) {
				$builder->addFileFromContent($attachment["filename"], $attachment["content"]);
			}
		}
		if (isset($message["attachments"])) {
			foreach ($message["attachments"] as $attachment) {
				$embed = new \Discord\Parts\Embed\Embed($this->discord);
				$embed->setURL($attachment["url"]);
				$embed->setImage($attachment["url"]);
				$builder->addEmbed($embed);
			}
		}
		if (isset($message["embeds"])) foreach ($message["embeds"] as $old_embed) {
			if ($old_embed["type"] == "rich") {
				$new_embed = new \Discord\Parts\Embed\Embed($this->discord);
				$new_embed->fill($old_embed);
				$builder->addEmbed($new_embed);
			}
		}
		if (isset($message["mentions"])) {
			$allowed_users = array();
			foreach ($message["mentions"] as $mention) $allowed_users[] = $mention["id"];
			$allowed_mentions["parse"] = array("roles", "everyone");
			$allowed_mentions["users"] = $allowed_users;
			$builder->setAllowedMentions($allowed_mentions);
		}
		return $builder;
	}
}
