<?php

namespace MineBlock\TpsLog;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\CallbackTask;

class TpsLog extends PluginBase{

	public function onEnable(){
		$this->loadYml();
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, "onTick"]), 1200);
	}

	public function onTick(){
		$log = "TPS:" . $this->getServer()->getTicksPerSecond() . " || Load:" . $this->getServer()->getTickUsage() . "% || Ram:" . round((memory_get_usage() / 1024) / 1024, 2) . "/" . round((memory_get_usage(true) / 1024) / 1024, 2) . "MB";
		$this->getServer()->getLogger()->info("TpsLog\n" . str_replace("||", "\n[" . date("Y.m.d.H.i.s", time()) . "][TPS] ", $log));
		$this->tl[date("Y.m.d.H.i.s", time())] = $log;
		$this->saveYml();
	}

	public function loadYml(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		$this->tl = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "TpsLog.yml", Config::YAML))->getAll();
	}

	public function saveYml(){
		$tl = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "TpsLog.yml", Config::YAML);
		$tl->setAll($this->tl);
		$tl->save();
	}

	public function isKorean(){
		return (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
	}
}