<?php

namespace MineBlock\AntiExplosion;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\entity\ExplosionPrimeEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;

class AntiExplosion extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->loadYml();
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		if(!isset($sub[0])) return false;
		$rm = "Usage: /AntiExplosion ";
		$mm = "[AntiExplosion] ";
		$ik = $this->isKorean();
		$ae = $this->ae;
		switch(strtolower($sub[0])){
			case "on":
			case "1":
			case "온":
			case "켜짐":
			case "활성화":
				$ae["Mode"] = 1;
				$r = $mm . ($ik ? "폭발을 방지합니다." : "Now provent the explosion");
			break;
			case "off":
			case "0":
			case "오프":
			case "꺼짐":
			case "비활성화":
				$ae["Mode"] = 0;
				$r = $mm . ($ik ? "폭발을 방지하지않습니다." : "Now not provent the explosion");
			break;
			case "protect":
			case "p":
			case "2":
			case "보호":
			case "프로텍트":
				$ae["Mode"] = 2;
				$r = $mm . ($ik ? "폭발의 블럭파괴를 방지합니다." : "Now provent the explosion's block break");
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
		if($this->ae !== $ae){
			$this->ae = $ae;
			$this->saveYml();
		}
		return true;
	}

	public function onExplosionPrime(ExplosionPrimeEvent $event){
		switch($this->ae["Mode"]){
			case 1:
				$event->setCancelled();
			break;
			case 2:
				$event->setBlockBreaking(false);
			break;
		}
	}

 	public function loadYml(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		$this->ae = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "AntiExplosion.yml", Config::YAML, ["Mode" => 1]))->getAll();
	}

	public function saveYml(){
		$ae = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "AntiExplosion.yml", Config::YAML);
		$ae->setAll($this->ae);
		$ae->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}