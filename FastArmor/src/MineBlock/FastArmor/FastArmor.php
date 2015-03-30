<?php

namespace MineBlock\FastArmor;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\protocol\Info as ProtocolInfo;
use pocketmine\item\Item;

class FastArmor extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->armorTable = [Item::LEATHER_CAP => 0, Item::LEATHER_TUNIC => 1, Item::LEATHER_PANTS => 2, Item::LEATHER_BOOTS => 3, Item::CHAIN_HELMET => 0, Item::CHAIN_CHESTPLATE => 1, Item::CHAIN_LEGGINGS => 2, Item::CHAIN_BOOTS => 3, Item::GOLD_HELMET => 0, Item::GOLD_CHESTPLATE => 1, Item::GOLD_LEGGINGS => 2, Item::GOLD_BOOTS => 3, Item::IRON_HELMET => 0, Item::IRON_CHESTPLATE => 1, Item::IRON_LEGGINGS => 2, Item::IRON_BOOTS => 3, Item::DIAMOND_HELMET => 0, Item::DIAMOND_CHESTPLATE => 1, Item::DIAMOND_LEGGINGS => 2, Item::DIAMOND_BOOTS => 3];
	}

	public function onDataPacketReceive(DataPacketReceiveEvent $event){
		$pk = $event->getPacket();
		if($pk->pid() !== ProtocolInfo::USE_ITEM_PACKET || $pk->face !== 0xff) return false;
		$p = $event->getPlayer();
		$inv = $p->getInventory();
		$i = $inv->getItemInHand();
		if(isset($this->armorTable[$id = $i->getID()])){
			$ai = $inv->getArmorItem($type = $this->armorTable[$id]);
			$inv->setArmorItem($type, $i, $p);
			$inv->setItem($inv->getHeldItemSlot(), $ai);
			$inv->sendContents($p);
			$inv->sendArmorContents($p);
		}
	}
}