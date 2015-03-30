<?php

namespace MineBlock\FirstJoin;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;

class FirstJoin extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->loadYml();
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		if(!isset($sub[0])) return false;
		$rm = TextFormat::RED . "Usage: /FirstJoin ";
		$mm = "[FirstJoin] ";
		$ik = $this->isKorean();
		$fj = $this->fj;
		switch(strtolower($sub[0])){
			case "spawn":
			case "s":
			case "스폰":
				if(!$sender instanceof Player){
					$r = $mm . ($ik ? "게임 내에서만 사용해주세요." : "Please run this command in-game");
				}else{
					$fj["Spawn"] = round($x = $sender->x, 2) . ":" . round($y = $sender->y, 1) . ":" . round($z = $sender->z, 2);
					$r = $mm . ($ik ? "첫 스폰 지점을 설정햇습니다. " : "First spawn point is set to ") . floor($x) . ":" . floor($y) . ":" . floor($z);
				}
			break;
			case "message":
			case "m":
				array_shift($sub);
				$fj["Message"] = implode(" ", $sub);
				$r = $mm . ($ik ? "첫 접속 메세지를 설정햇습니다. " : "First Join message is set to ") . $fj["Message"];
			break;
			case "reset":
			case "r":
			case "리셋":
			case "초기화":
				$fj["Joined"] = [];
				$r = $mm . ($ik ? "접속 기록이 초기화되엇습니다." : "Reset the join log");
			break;
			default:
				return false;
			break;
		}
		if(isset($r)) $sender->sendMessage($r);
		if($this->fj !== $fj){
			$this->fj = $fj;
			$this->saveYml();
		}
		return true;
	}

	public function onPlayerJoin(PlayerJoinEvent $event){
		$p = $event->getPlayer();
		if(in_array($n = strtolower($pn = $p->getName()), $this->fj["Joined"])) return;
		$p->sendMessage(str_replace(["%P", "%p"], [$pn, $pn], $this->fj["Message"]));
		$e = explode(":", $this->fj["Spawn"]);
		$p->teleport(new Vector3($e[0], $e[1], $e[2]));
		$this->fj["Joined"][] = $n;
		$this->saveYml();
	}

	public function loadYml(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		$s = $this->getServer()->getDefaultLevel()->getSafeSpawn();
		$this->fj = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "FirstJoin.yml", Config::YAML, ["Spawn" => $s->x . ":" . $s->y . ":" . $s->z, "Message" => "[FirstJoin] Wellcome to this Server : %P", "Joined" => []]))->getAll();
	}

	public function saveYml(){
		$fj = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "FirstJoin.yml", Config::YAML);
		$fj->setAll($this->fj);
		$fj->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}