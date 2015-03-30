<?php

namespace MineBlock\ChatMute;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerChatEvent;

class ChatMute extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->loadYml();
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		if(!isset($sub[0])) return false;
		$rm = "Usage: /ChatMute ";
		$mm = "[ChatMute] ";
		$ik = $this->isKorean();
		$mute = $this->mute;
		switch(strtolower($sub[0])){
			case "mute":
			case "m":
			case "추가":
			case "차단":
			case "음소거":
				if(!isset($sub[1])){
					$sender->sendMessage($rm . ($ik ? "음소거 <플레이어명>" : "Mute(M) <PlayerName>"));
					return true;
				}else{
					if(!$player = $this->getServer()->getPlayer($sub[1])){
						$r = $mm . $sub[1] . ($ik ? " 는 잘못된 플레이어명입니다." : "is invalid player");
					}else{
						$n = $player->getName();
						$mutes = $mute["Mute"];
						if(isset($mutes[$n])){
							unset($mutes[$n]);
							$r = $mm . $n . ($ik ? "의 음소거를 해제합니다." : " has UnMute");
						}else{
							$mutes[$n] = true;
							$r = $mm . $n . ($ik ? "의 음소거를 설정합니다." : " has Mute");
						}
						$mute["Mute"] = $mutes;
					}
				}
			break;
			case "allmute":
			case "a":
			case "전체추가":
			case "전체차단":
			case "전체음소거":
				if($mute["AllMute"]){
					$mute["AllMute"] = false;
					$m = $mm . ($ik ? "모든 채팅 음소거를 해제합니다." : "AllMute Off");
				}else{
					$mute["AllMute"] = true;
					$m = $mm . ($ik ? "모든 채팅 음소거를 설정합니다." : "AllMute On");
				}
			break;
			default:
				return false;
			break;
		}
		if(isset($r)){
			$sender->sendMessage($r);
		}elseif(isset($m)){
			$this->getServer()->broadcastMessage($m);
		}
		if($this->mute !== $mute){
			$this->mute = $mute;
			$this->saveYml();
		}
		return true;
	}

	public function onPlayerChat(PlayerChatEvent $event){
		if($event->isCancelled()) return;
		$p = $event->getPlayer();
		$m = "[ChatMute] ";
		$ik = $this->isKorean();
		if($this->mute["AllMute"] && !$p->hasPermission("chatmute.chat")){
			$p->sendMessage($m . ($ik ? "모든 채팅 음소거 상태입니다.." : "All Mute"));
			$event->setCancelled();
		}else{
			$n = $p->getName();
			if(isset($this->mute["Mute"][$n])){
				$p->sendMessage($m . ($ik ? "당신은 채팅 음소거 상태입니다." : "ChatMute"));
				$event->setCancelled();
			}
		}
	}

	public function loadYml(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		$this->mute = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "ChatMute.yml", Config::YAML, ["AllMute" => false, "Mute" => []]))->getAll();
	}

	public function saveYml(){
		$mute = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "ChatMute.yml", Config::YAML);
		$mute->setAll($this->mute);
		$mute->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}