<?php

namespace MineBlock\ArmorDefense;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\Config;
use pocketmine\item\Item;
use pocketmine\Player;

class ArmorDefense extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		$ad = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "ArmorDefense.yml", Config::YAML, [Item::LEATHER_CAP => 1, Item::LEATHER_TUNIC => 3, Item::LEATHER_PANTS => 2, Item::LEATHER_BOOTS => 1, Item::CHAIN_HELMET => 1, Item::CHAIN_CHESTPLATE => 5, Item::CHAIN_LEGGINGS => 4, Item::CHAIN_BOOTS => 1, Item::GOLD_HELMET => 1, Item::GOLD_CHESTPLATE => 5, Item::GOLD_LEGGINGS => 3, Item::GOLD_BOOTS => 1, Item::IRON_HELMET => 2, Item::IRON_CHESTPLATE => 6, Item::IRON_LEGGINGS => 5, Item::IRON_BOOTS => 2, Item::DIAMOND_HELMET => 3, Item::DIAMOND_CHESTPLATE => 8, Item::DIAMOND_LEGGINGS => 6, Item::DIAMOND_BOOTS => 3]);
		$this->armorTable = $ad->getAll();
	}

	/**
	 * @priority HIGHTEST
	 */
 	public function onEntityDamage(EntityDamageEvent $event){
		if(($p = $event->getEntity()) instanceof Player && $event instanceof EntityDamageByEntityEvent){
			$defense = 0;
			foreach($p->getInventory()->getArmorContents() as $index => $armor){
				if(isset($this->armorTable[$id = $armor->getID()])){
					$defense += $this->armorTable[$id];
				}
			}
			$event->setDamage(max(-floor($event->getDamage(EntityDamageEvent::MODIFIER_BASE) * $defense * 0.04), -$event->getDamage(EntityDamageEvent::MODIFIER_BASE)), EntityDamageEvent::MODIFIER_ARMOR);
		}
	}
}