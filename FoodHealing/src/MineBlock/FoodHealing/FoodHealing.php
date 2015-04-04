<?php

namespace MineBlock\FoodHealing;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\network\protocol\EntityEventPacket;
use pocketmine\network\protocol\Info as ProtocolInfo;
use pocketmine\utils\Config;
use pocketmine\item\Item;
use pocketmine\Player;

class FoodHealing extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		$fh = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "FoodHealing.yml", Config::YAML, [Item::APPLE => 4, Item::MUSHROOM_STEW => 10, Item::BEETROOT_SOUP => 10, Item::BREAD => 5, Item::RAW_PORKCHOP => 3, Item::COOKED_PORKCHOP => 8, Item::RAW_BEEF => 3, Item::STEAK => 8, Item::COOKED_CHICKEN => 6, Item::RAW_CHICKEN => 2, Item::MELON_SLICE => 2, Item::GOLDEN_APPLE => 10, Item::PUMPKIN_PIE => 8, Item::CARROT => 4, Item::POTATO => 1, Item::BAKED_POTATO => 6]);
		$this->foodTable = $fh->getAll();
	}

	public function onDataPacketReceive(DataPacketReceiveEvent $event){
		$pk = $event->getPacket();
		$p = $event->getPlayer();
		if($pk->pid() == ProtocolInfo::ENTITY_EVENT_PACKET && $pk->event == 9){
			$i = $p->getInventory()->getItemInHand();
			if($p->getHealth() < $p->getMaxHealth() && isset($this->foodTable[$i->getID()])){
				$event->setCancelled();
				$this->getServer()->getPluginManager()->callEvent($ev = new PlayerItemConsumeEvent($p, $i));
				if($ev->isCancelled()){
					$p->getInventory()->sendContents($p);
					break;
				}
				$pk = new EntityEventPacket();
				$pk->eid = 0;
				$pk->event = 9;
				$p->dataPacket($pk);
				$pk->eid = $p->getId();
				$this->getServer()->broadcastPacket($p->getViewers(), $pk);
				$amount = $this->foodTable[$i->getID()];
				$this->getServer()->getPluginManager()->callEvent($ev = new EntityRegainHealthEvent($p, $amount, EntityRegainHealthEvent::CAUSE_EATING));
				if(!$ev->isCancelled()){
//					if($ev->getAmount() <= 0){
						$p->heal($ev->getAmount(), $ev);
//					}else{
//						$p->attack($ev->getAmount());
//					}
				}
				--$i->count;
				$p->getInventory()->setItemInHand($i, $p);
				if($i->getID() === Item::MUSHROOM_STEW or $i->getID() === Item::BEETROOT_SOUP){
					$p->getInventory()->addItem(Item::get(Item::BOWL, 0, 1), $p);
				}
			}
		}
	}
}