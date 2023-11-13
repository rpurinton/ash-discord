<?php

namespace RPurinton\template;

require_once(__DIR__ . "/ConfigLoader.php");

class ThreadManager extends ConfigLoader
{
	private $threads = null;
	private $functions = null;
	private $futures = null;

	function __construct()
	{
		parent::__construct();
		if ($this->config["template"]["StartDiscord"]) {
			$key = "DiscordClient";
			$this->threads[$key] = new \parallel\Runtime(__DIR__ . "/DiscordClient.php");
			$this->functions[$key] = function () {
				new DiscordClient;
			};
			$this->futures[$key] = $this->threads[$key]->run($this->functions[$key]);
		}
		for ($id = 0; $id < $this->config["template"]["InboxHandlers"]; $id++) {
			$key = "InboxHandler$id";
			$this->threads[$key] = new \parallel\Runtime(__DIR__ . "/InboxHandler.php");
			$this->functions[$key] = function () {
				new InboxHandler;
			};
			$this->futures[$key] = $this->threads[$key]->run($this->functions[$key]);
		}
		while (true) {
			sleep(5);
			foreach ($this->futures as $key => $future) if ($future->done()) $this->futures[$key] = $this->threads[$key]->run($this->functions[$key]);
		}
	}

	function __destruct()
	{
		foreach ($this->threads as $thread) $thread->kill();
	}
}
