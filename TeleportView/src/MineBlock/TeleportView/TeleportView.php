<?php

namespace MineBlock\TeleportView;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\level\Position;
use pocketmine\utils\Config;

class TeleportView extends PluginBase{

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		$mm = "[TeleportView] ";
		if($sender->getName() == "CONSOLE"){
			$sender->sendMessage($mm . ($this->isKorean() ? "게임내에서만 사용가능합니다." : "Please run this command in-game"));
			return true;
		}
		$yaw = $sender->getYaw();
		$pitch = $sender->getPitch();
		$yawS = -sin($yaw / 180 * M_PI);
		$yawC = cos($yaw / 180 * M_PI);
		$pitchS = -sin($pitch / 180 * M_PI);
		$pitchC = cos($pitch / 180 * M_PI);
		$x = $sender->x;
		$y = $sender->y + $sender->getEyeHeight();
		$z = $sender->z;
		$l = $sender->getLevel();
		$ps = $this->getServer()->getOnlinePlayers();
		for($f = 0; $f < 50; ++$f){
			$x += $yawS * $pitchC;
			$y += $pitchS;
			$z += $yawC * $pitchC;
			$b = $l->getBlock(new Position($x, $y, $z, $l));
			if($b->isSolid()) break;
			if($f >= 50){
				$sender->sendMessage($mm . ($this->isKorean() ? "타겟 블럭이 너무 멉니다." : "TargetBlock is too far"));
				return true;
			}
		}
		$sender->teleport(new Position($x, $y, $z, $sender->getLevel()));
		return true;
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}