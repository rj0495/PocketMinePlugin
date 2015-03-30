<?php

namespace MineBlock\DeleteCrashDump;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;

class DeleteCrashDump extends PluginBase{

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		$mm = "[DeleteCrashDump] ";
		$ik = $this->isKorean();
		$dir = @opendir($path = $this->getServer()->getDataPath());
		$cnt = 0;
		while($open = readdir($dir)){
			if(strpos($open, "CrashDump_") === 0){
				@unlink($path . $open);
				$cnt++;
			}
		}
		$r = $mm . ($ik ? "모든 크래쉬덤프 파일을 제거햇습니다. " : "Delete the all crash dump file") . " [$cnt]";
		if(isset($r)) $sender->sendMessage($r);
		closedir($dir);
		return true;
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}