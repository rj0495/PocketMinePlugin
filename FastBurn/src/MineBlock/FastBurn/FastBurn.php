<?php

namespace MineBlock\FastBurn;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\inventory\FurnaceBurnEvent;
use pocketmine\event\inventory\FurnaceSmeltEvent;
use pocketmine\scheduler\CallbackTask;

class FastBurn extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->furnace = [];
	}

	public function onFuranceBurn(FurnaceBurnEvent $event){
		$this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this,"runSmelt"], [$event->getFurnace()]), 2);
	}

	public function onFuranceSmelt(FurnaceSmeltEvent $event){
		$this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this,"runSmelt"], [$event->getFurnace()]), 2);
 	}


	public function runSmelt($furnace){
		$inv = $furnace->getInventory();
		$fuel = $inv->getFuel();
		$raw = $inv->getSmelting();
		$product = $inv->getResult();
		$smelt = $this->getServer()->getCraftingManager()->matchFurnaceRecipe($raw);
		if($smelt !== null && $raw->getCount() > 0 && (($smelt->getResult()->equals($product, true) && $product->getCount() < $product->getMaxStackSize()) || $product->getId() === 0)){
			$furnace->namedtag["BurnTime"] -= 200;
			$furnace->namedtag["CookTime"] = 200;
		}else{
			$furnace->namedtag["BurnTime"] = 0;
			$furnace->namedtag["CookTime"] = 0;
		}
	}
}