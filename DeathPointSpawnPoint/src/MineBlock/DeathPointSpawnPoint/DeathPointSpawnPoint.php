<?php

namespace MineBlock\DeathPointSpawnPoint;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;

class DeathPointSpawnPoint extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onPlayerDeath(PlayerDeathEvent $event){
		$p = $event->getEntity();
		$pos = $event->getEntity()->getPosition();
		if($pos->y <= 0) $pos->add(0, -$pos->y, 0);
		$pos->add(0, 1, 0);
		$p->setSpawn($pos);
	}
}