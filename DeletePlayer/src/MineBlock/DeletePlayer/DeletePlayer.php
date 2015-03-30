<?php

namespace MineBlock\DeletePlayer;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\Player;

class DeletePlayer extends PluginBase{

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		if(!isset($sub[0]) || !$sub[0]) return false;
		$mm = "[DeletePlayer] ";
		$ik = $this->isKorean();
		$path = $this->getServer()->getDataPath() . "players/";
		if(!$p = $this->getServer()->getPlayer($sub[0])) $p = $this->getServer()->getOfflinePlayer($sub[0]);
		if(!file_exists($path . strtolower($sub[0]) . ".dat")){
			$r = $mm . $sub[0] . ($ik ? "는 잘못된 플레이어명입니다." : " is invalid player");
		}else{
			$n = strtolower($p->getName());
			if($p instanceof Player) $p->close($m = ($ik ? "플레이어 데이터 제거됨" : "Player data is delete"), $m);
			@unlink($path . "$n.dat");
			$r = $mm . ($ik ? "$n 님의 데이터를 제거햇습니다." : "Delete the $n's data");
		}
		if(isset($r)) $sender->sendMessage($r);
		return true;
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}