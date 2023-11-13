<?php

namespace RPurinton\template;

require_once(__DIR__ . "/ConfigLoader.php");

class BunnyClient extends ConfigLoader
{
	function __construct($requestQueue, $request, $callbackQueue, $callback, $timeout = 10)
	{
		parent::__construct();
		$client = new \Bunny\Client($this->config["bunny"]);
		$client->connect();
		$channel = $client->channel();
		$channel->qos(0, 1);
		$channel->queueDeclare($callbackQueue);
		$channel->publish(json_encode($request, JSON_PRETTY_PRINT), [], '', $requestQueue);
		$message = null;
		$retry = 0;
		while ($message == null && $retry < $timeout * 10) {
			$message = $channel->get($callbackQueue);
			if ($message == null) usleep(100000);
		}
		if ($message != null) {
			if (($callback)(json_decode($message->content, true))) $channel->ack($message);
			else $channel->nack($message);
		} else (($callback)(array("timeout")));
		$channel->queueDelete($callbackQueue);
	}
}
