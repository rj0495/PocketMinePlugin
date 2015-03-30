<?php

namespace MineBlock\ChatSwitch;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\event\Listener;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerChatEvent;

class ChatSwitch extends PluginBase implements Listener{

	public function onEnable(){
		$this->loadYml();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		$mm = "[ChatSwitch] ";
		$n = $sender->getName();
		$chat = $this->chat;
		if(!isset($chat[$n])) $chat[$n] = true;
		$chat[$n] = !$chat[$n];
		$sender->sendMessage($mm . ($this->isKorean() ? "채팅을 받" . ($chat[$n] ? "" : "지 않") . "습니다." : ($chat[$n] ? "" : "Not ") . "receive the chat"));
		if($this->chat !== $chat){
			$this->chat = $chat;
			$this->saveYml();
		}
		return true;
	}

	public function onPlayerChat(PlayerChatEvent $event){
		$chat = $this->chat;
		$p = $event->getPlayer();
		$n = $p->getName();
		if(!isset($chat[$n])) $chat[$n] = true;
		if(!$chat[$n]){
			$p->sendMessage("[ChatSwitch] " . ($this->isKorean() ? "당신은 채팅을 받지않습니다." : "You are not receive the chat"));
			$event->setCancelled();
			return;
		}
		$recipients = $event->getRecipients();
		foreach($recipients as $k => $v){
			$n = $v->getName();
			if(!isset($chat[$n])) $chat[$n] = true;
			if(!$chat[$n]) unset($recipients[$k]);
		}
		if($this->chat !== $chat){
			$this->chat = $chat;
			$this->saveYml();
		}
		$event->setRecipients($recipients);
	}

	public function loadYml(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		$this->chat = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "ChatSwitch.yml", Config::YAML))->getAll();
	}

	public function saveYml(){
		$chat = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "ChatSwitch.yml", Config::YAML);
		$chat->setAll($this->chat);
		$chat->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}