<?php

namespace MineBlock\ArrowShot;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\protocol\Info as ProtocolInfo;
use pocketmine\item\Item;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Float;
use pocketmine\entity\Arrow;
use pocketmine\event\entity\EntityShootBowEvent;

class ArrowShot extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onDataPacketReceive(DataPacketReceiveEvent $event){
		$pk = $event->getPacket();
		if($pk->pid() !== ProtocolInfo::USE_ITEM_PACKET || $pk->face !== 0xff) return false;
		$p = $event->getPlayer();
		$inv = $p->getInventory();
		$i = $inv->getItemInHand();
		if($i->getID() == 262){
			$nbt = new Compound("", ["Pos" => new Enum("Pos", [new Double("", $p->x), new Double("", $p->y + $p->getEyeHeight()), new Double("", $p->z)]), "Motion" => new Enum("Motion", [new Double("", -sin($p->getyaw() / 180 * M_PI) * cos($p->getPitch() / 180 * M_PI)), new Double("", -sin($p->getPitch() / 180 * M_PI)), new Double("", cos($p->getyaw() / 180 * M_PI) * cos($p->getPitch() / 180 * M_PI))]), "Rotation" => new Enum("Rotation", [new Float("", $p->getyaw()), new Float("", $p->getPitch())])]);
			$arrow = new Arrow($p->chunk, $nbt, $p);
			$ev = new EntityShootBowEvent($p, Item::get(264, 0, 0), $arrow, 1.5);
			$this->getServer(0)->getPluginManager()->callEvent($ev);
			if($ev->isCancelled()){
				$arrow->kill();
			}else{
				$i->setCount($i->getCount() - 1);
				$inv->setItem($inv->getHeldItemSlot(), $i);
				$arrow->spawnToAll();
			}
		}
	}
}