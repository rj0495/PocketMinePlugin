<?php

namespace MineBlock\ViewLocation;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\Player;

class ViewLocation extends PluginBase{

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		$rm = "Usage: /ViewLocation ";
		$mm = "[ViewLocation] ";
		$ik = $this->isKorean();
		if(isset($sub[0])){
			$p = $this->getServer()->getPlayer($sub[0]);
			if($p) $sender->sendMessage($mm . $p->getName() . ($ik ? " 님의 좌표" : "\'s Location") . "::  X: " . $p->getfloorX() . " Y: " . $p->getfloorY() . " Z:" . $p->getfloorZ());
			else $sender->sendMessage($ik ? $mm . "$sub[0] 는 잘못된 플레이어명입니다." : $mm . "$sub[0] is invalid player");
		}else{
			$sender->sendMessage($ik ? $rm . "<플레이어명>" : $rm . "<PlayerName>");
			if($sender instanceof Player) $sender->sendMessage($mm . ($ik ? " 본인의 좌표" : "\'s Location") . "::  X: " . $sender->getfloorX() . " Y: " . $sender->getfloorY() . " Z:" . $sender->getfloorZ());
		}
		return true;
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}