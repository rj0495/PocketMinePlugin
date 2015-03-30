<?php

namespace MineBlock\SignPacket;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\scheduler\CallbackTask;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\network\protocol\UpdateBlockPacket;
use pocketmine\network\protocol\EntityDataPacket;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Int;
use pocketmine\nbt\tag\String;
use pocketmine\utils\Config;

class SignPacket extends PluginBase implements Listener{

	public $mode;

	public function onEnable(){
		$this->mode = ["particle" => "Particle", "particl" => "Particle", "partic" => "Particle", "parti" => "Particle", "part" => "Particle", "par" => "Particle", "p" => "Particle", "toast" => "Toast", "toas" => "Toast", "toa" => "Toast", "to" => "Toast", "t" => "Toast", "dialog" => "Dialog", "dialo" => "Dialog", "dial" => "Dialog", "dia" => "Dialog", "di" => "Dialog", "d" => "Dialog"];
		$this->update = [];
		$this->lastUpdate = [];
		$this->canUpdate = [];
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, "onTick"]), 5);
	}

	public function onPlayerQuit(PlayerQuitEvent $event){
		unset($this->update[$id = $event->getPlayer()->getID()], $this->canUpdate[$id], $this->isUpdate[$id]);
	}

	public function onPlayerCommandPreprocess(PlayerCommandPreprocessEvent $event){
		if(strpos($msg = $event->getMessage(), "/[<MineBlock>]:") === 0){
			$event->setCancelled();
			$p = $event->getPlayer();
			$id = $p->getID();
			if($this->canUpdate($p) && explode(":", $msg)[1] == "Complete" && $this->isUpdate($p)){
				$b = $p->getLevel()->getBlock($vv = $this->lastUpdate[$id]);
				$pk = new UpdateBlockPacket();
				$pk->x = $vv->x;
				$pk->y = 0;
				$pk->z = $vv->z;
				$pk->block = $b->getID();
				$pk->meta = $b->getDamage();
				$p->dataPacket($pk);
				unset($this->lastUpdate[$id]);
				$p->sendMessage("[SignPacket] Complete || " . $vv->x . ":" . $vv->z . " (" . $c = count($this->update[$id]) . ")");
				array_shift($this->update[$id]);
			}elseif(!$this->canUpdate($p)){
				$this->canUpdate[$id] = true;
				$p->sendMessage("[SignPacket] Enable Sign Packet || " . $p->getName());
			}
		}
	}

	public function onPlayerInteract(PlayerInteractEvent $event){
		$this->sendDialog($p = $event->getPlayer(), "내용테스트", "SignPacket", 20, 125, 255, 0, 0);
	}
	/*
	 * i1 = X:Y:Z i2 = velX:velY:velZ i3 = ParticleType:Size
	 */
	public function sendParticle($player, $type = 2, $x = 0, $y = 0, $z = 0, $vX = 0, $vY = 0, $vZ = 0, $size = 1){
		$this->sendSign($player, "Particle", round($x, 1) . ":" . round($y, 1) . ":" . round($z, 1), round($vX, 1) . ":" . round($vY, 1) . ":" . round($vZ, 1), "$type:" . max($size, 1));
	}
	/*
	 * i1 = Message i2 = Size i3 = A:R:G:B
	 */
	public function sendToast($player, $text = "", $size = 1, $a = 255, $r = 0, $g = 0, $b = 0){
		$this->sendSign($player, "Toast", $text, $size, "$a:$r:$g:$b");
	}
	/*
	 * i1 = Message i2 = Title:Size i3 = A:R:G:B
	 */
	public function sendDialog($player, $text = "", $title = "", $size = 1, $a = 255, $r = 0, $g = 0, $b = 0){
		$this->sendSign($player, "Dialog", $text, "$title:" . max($size, 1), "$a:$r:$g:$b");
	}

	public function sendSign($player, $mode = "", $line1 = "", $line2 = "", $line3 = ""){
		if(!$this->canUpdate($player)) return false;
		if(isset($this->mode[$sm = strtolower($mode)])){
			if(!isset($this->update[$id = $player->getID()])) $this->update[$id] = [];
			$this->update[$id][] = [$player, $this->mode[$sm], $line1, $line2, $line3];
			return true;
		}
		return false;
	}

	public function canUpdate($player){
		return isset($this->canUpdate[$player->getID()]);
	}

	public function isUpdate($player){
		return isset($this->lastUpdate[$player->getID()]);
	}

	public function onTick(){
		foreach($this->update as $id => $list){
			if(!isset($list[0])) continue;
			$info = $list[0];
			$p = $info[0];
			$v = $p->floor();
			$v->y = 0;
			$pk = new UpdateBlockPacket();
			if(isset($this->lastUpdate[$id]) && $this->lastUpdate[$id]->distance($v) >= 1){
				$b = $p->getLevel()->getBlock($vv = $this->lastUpdate[$id]);
				$pk->x = $vv->x;
				$pk->y = 0;
				$pk->z = $vv->z;
				$pk->block = $b->getID();
				$pk->meta = $b->getDamage();
				$p->dataPacket($pk);
			}
			$this->lastUpdate[$id] = $v;
			$pk->x = $v->x;
			$pk->y = 0;
			$pk->z = $v->z;
			$pk->block = 68;
			$pk->meta = 0;
			$p->dataPacket($pk);
			$nbt = new NBT(NBT::LITTLE_ENDIAN);
			$nbt->setData(new Compound("", [new String("id", "Sign"), new String("Text1", $info[1]), new String("Text2", $info[2]), new String("Text3", $info[3]), new String("Text4", $info[4]), new Int("x", $v->x), new Int("y", 0), new Int("z", $v->z)]));
			$pk = new EntityDataPacket();
			$pk->x = $v->x;
			$pk->y = 0;
			$pk->z = $v->z;
			$pk->namedtag = $nbt->write();
			$p->dataPacket($pk);
		}
	}
}