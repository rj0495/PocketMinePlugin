<?php

namespace MineBlock\BurnTime;

use pocketmine\plugin\PluginBase;
use pocketmine\inventory\Fuel;
use pocketmine\utils\Config;

class BurnTime extends PluginBase{

	public function onEnable(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		$bt = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "BurnTime.yml", Config::YAML, Fuel::$duration);
		Fuel::$duration = $bt->getAll();
	}
}