<?php

namespace RPurinton\template;

require_once(__DIR__ . "/SqlClient.php");
require_once(__DIR__ . "/BunnyClient.php");
require_once(__DIR__ . "/BunnyAsyncClient.php");

class InboxHandler extends ConfigLoader
{
	private $sql = null;
	private $bunny = null;

	function __construct()
	{
		parent::__construct();
		$this->sql = new SqlClient();
		$loop = \React\EventLoop\Loop::get();
		$this->bunny = new BunnyAsyncClient($loop, "template_inbox", $this->process(...));
		$loop->run();
	}

	private function process($message)
	{
		switch ($message["t"]) {
			case "MESSAGE_CREATE":
				return $this->MESSAGE_CREATE($message["d"]);
			case "MESSAGE_UPDATE":
				return $this->MESSAGE_UPDATE($message["d"]);
			case "MESSAGE_DELETE":
				return $this->MESSAGE_DELETE($message["d"]);
		}
		return true;
	}

	private function MESSAGE_CREATE($message)
	{
		return true;
	}

	private function MESSAGE_UPDATE($message)
	{
		return true;
	}

	private function MESSAGE_DELETE($message)
	{
		return true;
	}

	private function reply($message, $reply)
	{
		$reply["command-reply"] = true;
		$reply["function"] = "MESSAGE_REPLY";
		$reply["reply_to"] = $message["id"];
		$reply["channel_id"] = $message["channel_id"];
		$this->bunny->publish("template_outbox", $reply);
		return true;
	}
}
