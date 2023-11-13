<?php

namespace RPurinton\template;

require_once(__DIR__ . "/ConfigLoader.php");

class BunnyAsyncClient extends ConfigLoader
{
	private $queue = null;
	private $client = null;
	private $channel = null;
	private $callback = null;

	function __construct($loop, $queue, $callback)
	{
		parent::__construct();
		$this->queue = $queue;
		$this->callback = $callback;
		$client = new \Bunny\Async\Client($loop, $this->config["bunny"]);
		$client->connect()->then($this->getChannel(...))->then($this->consume(...));
	}

	private function getChannel($client)
	{
		$this->client = $client;
		return $client->channel();
	}

	private function consume($channel)
	{
		$this->channel = $channel;
		$channel->qos(0, 1);
		$channel->consume($this->process(...), $this->queue);
	}

	private function close()
	{
		$this->channel->close();
	}

	private function process($message, $channel, $client)
	{
		if (($this->callback)(json_decode($message->content, true))) $channel->ack($message);
		else $channel->nack($message);
	}

	public function publish($queue, $data)
	{
		$this->channel->publish(json_encode($data, JSON_PRETTY_PRINT), [], '', $queue);
	}
}
