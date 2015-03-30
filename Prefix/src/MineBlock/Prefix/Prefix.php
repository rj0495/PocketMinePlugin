<?php

namespace MineBlock\Prefix;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\CallbackTask;

class Prefix extends PluginBase{

	public function onEnable(){
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, "onTick"]), 100);
		$this->loadYml();
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		if(!(isset($sub[1]) && $sub[0] && $sub[1])) return false;
		$mm = "[Prefix] ";
		$pf = $this->pf["Player"];
		$ik = $this->isKorean();
		$n = strtolower(array_Shift($sub));
		$prefix = implode(" ", $sub);
		if(!isset($pf[$n]) && ($p = $this->getServer()->getPlayer($n)) == null){
			$r = $mm . $n . ($ik ? "는 잘못된 플레이어명입니다." : " is invalid player");
		}else{
			if(($p = $this->getServer()->getPlayer($n)) !== null) $n = strtolower($p->getName());
			$r = $mm . $n . ($ik ? "' 칭호 : " : "' Prefix : ") . $prefix;
			$pf[$n] = $prefix;
		}
		if(isset($r)) $sender->sendMessage($r);
		if($this->pf["Player"] !== $pf){
			$this->pf["Player"] = $pf;
			$this->saveYml();
		}
		return true;
	}

	public function onTick(){
		foreach($this->getServer()->getOnlinePlayers() as $p){
			$c = false;
			$n = $p->getName();
			$sn = strtolower($n);
			if(!isset($this->pf["Player"][$sn])) $this->pf["Player"][$sn] = $this->pf["Default"];
			if($this->pf["Player"][$sn] == "") continue;
			$prefix = str_replace(["%prefix", "%name"], [$this->pf["Player"][$sn], $p->getName()], $this->pf["Format"]);
			if($p->getDisplayName() !== $prefix){
				$p->setDisplayName($prefix);
				$c = true;
			}
			if(strpos($p->getNameTag(), $prefix) === false){
				$r = $p->setNameTag($prefix);
				$c = true;
			}
			if($c) $p->sendMessage("[Prefix] " . ($this->isKorean() ? " 당신의 칭호가 $prefix 으로 변경되엇습니다." : "Your frefix is change to $prefix"));
		}
	}

	public function loadYml(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		$this->pf = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "Prefix.yml", Config::YAML, ["Format" => "[%prefix] %name", "Default" => "User", "Player" => []]))->getAll();
	}

	public function saveYml(){
		ksort($this->pf);
		$pf = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "Prefix.yml", Config::YAML, []);
		$pf->setAll($this->pf);
		$pf->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}