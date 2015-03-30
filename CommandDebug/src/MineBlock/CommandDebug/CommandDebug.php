<?php
namespace MineBlock\CommandDebug;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerCommandPreprocessEvent;

class CommandDebug extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->loadYml();
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		if(!isset($sub[0])) return false;
		$ik = $this->isKorean();
		$mm = "[CommandDebug] ";
		$rm = TextFormat::RED . "Usage: /CommandDebug";
		$cd = $this->cd;
		switch(strtolower($sub[0])){
			case "add":
			case "a":
			case "추가":
				if(!isset($sub[1]) || !$sub[1]){
					$r = $rm . ($ik ? "추가 <명령어>": "Add(A) <Command>");
				}else{
					$c = strtolower($sub[1]);
					if(!in_array($c, $cd)) $cd[] = $c;
					$r = $mm . ($ik ? " 추가됨 ": "Add") . " : $c";
				}
			break;
			case "del":
			case "d":
			case "삭제":
			case "제거":
				if(!isset($sub[1])){
					$r = $rm . ($ik ? "제거 <명령어>": "Del(D) <Command>");
				}else{
					$c = strtolower($sub[1]);
					if(!in_array($c, $cd)){
						$r = " [$c] " . ($ik ? "목록에 존재하지 않습니다.\n $rm 목록 ": "does not exist.\n $rm List(L)");
					}else{
						foreach($cd as $k => $v){
							if($v == $c){
								unset($cd[$k]);
								$r = $mm . ($ik ? " 제거됨 ": "Del") . " : $c";
								break;
							}
						}
					}
				}
			break;
			case "reset":
			case "r":
			case "리셋":
			case "초기화":
				$cd = [];
				$r = $mm . ($ik ? " 리셋됨.": " Reset");
			break;
			case "list":
			case "l":
			case "목록":
			case "리스트":
				$page = 1;
				if(isset($sub[1]) && is_numeric($sub[1])) $page = max(floor($sub[1]), 1);
				$list = array_chunk($cd, 5, true);
				if($page >= ($c = count($list))) $page = $c;
				$r = $mm . ($ik ? "숨김 목록 (페이지": "Hide List (Page") . " $page/$c) \n";
				$num = ($page - 1) * 5;
				if($c > 0){
					foreach($list[$page - 1] as $v){
						$num++;
						$r .= "  [$num] $v\n";
					}
				}
			break;
			default:
				return false;
			break;
		}
		if(isset($r)) $sender->sendMessage($r);
		if($this->cd !== $cd){
			$this->cd = $cd;
			$this->saveYml();
		}
		return true;
	}

/**
	* @priority HIGHEST
	*/
	public function onPlayerCommandPreprocess(PlayerCommandPreprocessEvent $event){
		if(!$event->isCancelled() && strpos($c = $event->getMessage(), "/") === 0 && !in_array($c = strtolower(explode(" ", $cmd = substr($c, 1))[0]), $this->cd) && $this->getServer()->getCommandMap()->getCommand($c)) $this->getLogger()->info(TextFormat::YELLOW.$event->getPlayer()->getName().($event->getPlayer()->isOp() ? TextFormat::RED : TextFormat::BLUE)." : $cmd");
	}

	public function loadYml(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		$this->cd = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "CommandDebug.yml", Config::YAML, is_file($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "CommandDebug.yml") ? [] : ["stop","list","help","login","register","say","me","give","tp"]))->getAll();
	}

	public function saveYml(){
		sort($this->cd);
		$cd = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "CommandDebug.yml", Config::YAML);
		$cd->setAll($this->cd);
		$cd->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}