<?php

namespace MineBlock\AntiPvpCheat;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\Player;

class AntiPvpCheat extends PluginBase implements Listener{

	public function onEnable(){
		$this->move = [];
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onEntityDamage(EntityDamageEvent $event){
		if($p = $event->getEntity() instanceof Player && $event instanceof EntityDamageByEntityEvent && $d = $event->getDamager() instanceof Player){
			$l = $d->getLocation();
			if(!isset($this->move[$n = $d->getName()])) $this->move[$n] = $l;
			$m = $this->move[$n];
			if($p->distance($d) > 5 || ($m->x == $l->x && $m->y == $l->y && $m->z == $l->z && $m->getYaw() == $l->getYaw() && $m->getPitch() == $l->getPitch())) $event->setCancelled(false);
		}
	}
}