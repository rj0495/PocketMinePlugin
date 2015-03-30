<?php

namespace MineBlock\AntiExplosionBreak;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\entity\ExplosionPrimeEvent;

class AntiExplosionBreak extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onExplosionPrime(ExplosionPrimeEvent $event){
		$event->setBlockBreaking(false);
	}
}