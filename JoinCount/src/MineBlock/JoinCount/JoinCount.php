<?php

namespace MineBlock\JoinCount;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerJoinEvent;

class JoinCount extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->loadYml();
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		if(!isset($sub[0])) return false;
		if(isset($sub[1])) $sub[1] = strtolower($sub[1]);
		$jc = $this->jc;
		$rm = TextFormat::RED . "Usage: /JoinCount ";
		$mm = "[JoinCount] ";
		$ik = $this->isKorean();
		switch(strtolower($sub[0])){
			case "view":
			case "v":
			case "보기":
				if(!isset($sub[1])){
					$r = $rm . ($ik ? "보기 <플레이어명>" : "View(V) <PlayerName>");
				}elseif(!isset($jc[$sub[1]])){
					$r = $mm . $sub[1] . ($ik ? "는 잘못된 플레이어명입니다." : " is invalid player");
				}else{
					$r = $mm . $sub[1] . ($ik ? "' 접속횟수 : " : "' Join Count : ") . $jc[$sub[1]];
				}
			break;
			case "rank":
			case "r":
			case "랭크":
			case "랭킹":
			case "순위":
			case "목록":
				$page = 1;
				if(isset($sub[1]) && is_numeric($sub[1])) $page = max(floor($sub[1]), 1);
				$list = array_chunk($jc, 5, true);
				if($page >= ($c = count($list))) $page = $c;
				$r = $mm . ($ik ? "접속횟수 랭킹 (페이지" : "JoinCount Rank (Page") . " $page/$c) \n";
				$num = ($page - 1) * 5;
				if($c > 0){
					foreach($list[$page - 1] as $k => $v){
						$num++;
						$r .= "  [$num] $k : [$v]\n";
					}
				}
			break;
			case "clear":
			case "c":
			case "초기화":
			case "클리어":
				if(!isset($sub[1])){
					$r = $rm . ($ik ? "클리어 <플레이어명>" : "Clear(C) <PlayerName>");
				}elseif(!isset($jc[$sub[1]])){
					$r = $mm . $sub[1] . ($ik ? "는 잘못된 플레이어명입니다." : " is invalid player");
				}else{
					$jc[$sub[1]] = 0;
					$r = $mm . $sub[1] . ($ik ? "' 접속횟수를 초기화 합니다." : "' Join Count is Reset");
				}
			break;
			case "allclear":
			case "ac":
			case "전체초기화":
			case "전체클리어":
				$jc = [];
				foreach($this->getServer()->getOnlinePlayers() as $p){
					if(!isset($this->jc[strtolower($p->getName())])) $this->jc[strtolower($p->getName())] = 1;
				}
				$r = $mm . ($ik ? "모든 접속횟수를 초기화합니다." : "Clear the All Join Count");
			break;
			default:
				return false;
			break;
		}
		if(isset($r)) $sender->sendMessage($r);
		if($this->jc !== $jc){
			$this->jc = $jc;
			$this->saveYml();
		}
		return true;
	}

	public function onPlayerJoin(PlayerJoinEvent $event){
		$this->joinLog($event);
	}

	public function joinLog($event){
		if(!isset($this->jc[strtolower($event->getPlayer()->getName())])) $this->jc[strtolower($event->getPlayer()->getName())] = 0;
		$this->jc[strtolower($event->getPlayer()->getName())]++;
		$this->saveYml();
	}

	public function loadYml(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		$this->jc = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "JoinCount.yml", Config::YAML))->getAll();
	}

	public function saveYml(){
		asort($this->jc);
		$jc = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "JoinCount.yml", Config::YAML);
		$jc->setAll($this->jc);
		$jc->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}