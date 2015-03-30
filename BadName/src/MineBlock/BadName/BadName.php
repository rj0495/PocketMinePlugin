<?php

namespace MineBlock\BadName;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;

class BadName extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onPlayerJoin(PlayerPreLoginEvent $event){
		if(strpos($n = strtolower($event->getPlayer()->getName()), "\x00") !== false || preg_match('#^[a-zA-Z0-9_]{3,16}$#', $n) == 0 || $n === "" || $n === "rcon" || $n === "console" || $n === "steve" || strlen($n) > 16 || strlen($n) < 3){
			$event->setCancelled();
			$event->setKickMessage("Bad Name");
		}
	}
}