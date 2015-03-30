<?php

namespace MineBlock\Reboot;

use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\CallbackTask;

class Reboot extends PluginBase{

	public function onEnable(){
		$this->time = 18;
		$this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new CallbackTask([$this, "onTick"]), 20 * 60 * 30 - 20 * 18, 20);
	}

	public function onTick(){
		if($this->time == 18) $this->getServer()->broadcastMessage("/•[MineFarm] 저희 마인팜 서버는  30분마다 자동으로 리붓됩니다.");
		elseif($this->time == 17) $this->getServer()->broadcastMessage("/•[MineFarm] 창고가 가끔씩 증발하는 경우가 있으니, 주의하시기 바랍니다.");
		elseif($this->time == 16) $this->getServer()->broadcastMessage("/•[MineFarm] 혹시모를 불상사를 방지하기위해 15초의 시간을 드립니다.");
		else $this->getServer()->broadcastMessage("/•[MineFarm] 서버가 " . $this->time . "초 후에 리붓됩니다.");
		$this->time--;
		if($this->time < 0){
			foreach($this->getServer()->getLevels() as $l){
				$l->save(true);
				foreach($l->getPlayers() as $p){
					$p->save();
					$p->kick("Server is Auto Reboot");
				}
				foreach($l->getEntities() as $e)
					$e->saveNBT();
				foreach($l->getTiles() as $t)
					$t->saveNBT();
			}
			$this->getServer()->shutdown();
		}
	}
}