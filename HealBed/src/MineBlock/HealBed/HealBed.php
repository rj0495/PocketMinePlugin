<?php

namespace MineBlock\HealBed;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerBedEnterEvent;
use pocketmine\event\player\PlayerBedLeaveEvent;
use pocketmine\Player;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\nbt\tag\Byte;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Float;
use pocketmine\nbt\tag\Int;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\network\protocol\MovePlayerPacket;
use pocketmine\inventory\PlayerInventory;
use pocketmine\network\protocol\MessagePacket;

class HealBed extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->player = [];
	}

	/**
	 * @priority HIGHEST
	 */
	public function onPlayerInteract(PlayerInteractEvent $event){
		if($event->isCancelled()) return;
		$p = $event->getPlayer();
		$b = $event->getBlock();
		if($b->getID() !== 26) return;
		$event->setCancelled();
		$xTabel = [3 => 1, 1 => -1];
		$b = $b->getSide(5, isset($xTabel[$dmg = $b->getDamage()]) ? $xTabel[$dmg] : 0);
		$zTabel = [0 => 1, 2 => -1];
		$b = $b->getSide(3, isset($zTabel[$dmg]) ? $zTabel[$dmg] : 0);
		$this->getServer()->getPluginManager()->callEvent($ev = new PlayerBedEnterEvent($p, $b));
		if($ev->isCancelled()) return;
		$property = (new \ReflectionClass("\\pocketmine\\Player"))->getProperty("sleeping");
		$property->setAccessible(true);
		foreach($p->getLevel()->getNearbyEntities($p->getBoundingBox()->grow(2, 1, 2), $p) as $pl){
			if($pl instanceof Player && $pl->isSleeping()){
				if($b->distance($property->getValue($pl)) <= 0.1){
					$p->sendMessage("This bed is occupied");
					return;
				}
			}
		}
		$property->setValue($p, $b);
		$p->teleport($b->add(0.5, 0.5, 0.5));
		$p->sendMetadata($p->getViewers());
		$p->sendMetadata($p);
	}

	public function onPlayerBedEnter(PlayerBedEnterEvent $event){
		$p = $event->getPlayer();
		if(!isset($this->player[$n = $p->getName()]) || $this->player[$n]->closed){
			$this->player[$n] = new Player4NameTag($p);
		}
	}

	public function onPlayerBedLeave(PlayerBedLeaveEvent $event){
		$p = $event->getPlayer();
		if(isset($this->player[$n = $p->getName()])){
			if(!$this->player[$n]->closed) $this->player[$n]->close();
			unset($this->player[$n]);
		}
	}
}
class Player4NameTag extends Human{

	public $player;

	public $name = "";

	public $healTick = 0;

	public function __construct($player){
		parent::__construct($player->getLevel()->getChunk($player->x >> 4, $player->z >> 4), new Compound("", ["Pos" => new Enum("Pos", [new Double("", 0), new Double("", 0), new Double("", 0)]), "Motion" => new Enum("Motion", [new Double("", 0), new Double("", 0), new Double("", 0)]), "Rotation" => new Enum("Rotation", [new Float("", 0), new Float("", 0)])]));
		$this->player = $player;
		$this->inventory = new PlayerInventory($this);
	}

	protected function initEntity(){}

	public function saveNBT(){}

	public function canCollideWith(Entity $entity){
		return false;
	}

	public function onUpdate($currentTick){
		$p = $this->player;
		if($this->closed == true || $this->dead == true || !$p instanceof Player || !$p->isSleeping()){
			if(!$this->closed) parent::close();
			$this->player = false;
			return false;
		}else{
			if($this->healTick >= 60){
				$p->heal(1);
				$this->healTick = 0;
			}
			$this->healTick++;
			$name = "\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n";
			$name .= "Healing...";
			if($this->healTick < 55){
				$name .= "\n    " . (3 - floor($this->healTick * 0.05)) . "...";
				$name .= ["-", "\\", ".|", "/"][floor($this->healTick * 0.5) % 4];
			}
			$property = (new \ReflectionClass("\\pocketmine\\Player"))->getProperty("sleeping");
			$property->setAccessible(true);
			$b = $this->getLevel()->getBlock($property->getValue($p));
			$xTabel = [1 => 2, 3 => -2, 9 => 2, 11 => -2];
			$x = isset($xTabel[$dmg = $b->getDamage()]) ? $xTabel[$dmg] : 0.5;
			$zTabel = [0 => -2, 2 => 2, 8 => -2, 10 => 2];
			$z = isset($zTabel[$dmg]) ? $zTabel[$dmg] : 0.5;
			$this->x = $b->x + $x . $this->y = $p->y + 19;
			$this->z = $b->z + $z;
			if($this->nameTag !== $name){
				$this->nameTag = $name;
				$this->despawnFrom($p);
			}
			$this->spawnTo($p);
			$pk = new MovePlayerPacket();
			$pk->eid = $this->id;
			$pk->x = $this->x;
			$pk->y = $this->y;
			$pk->z = $this->z;
			$pk->yaw = 0;
			$pk->bodyYaw = 0;
			$pk->pitch = 0;
			$p->getServer()->broadcastPacket($this->hasSpawned, $pk);
		}
		return true;
	}

	public function getInventory(){
		return $this->player->getInventory();
	}

	public function getName(){
		return $this->nameTag;
	}

	public function getData(){
		return [];
	}

	public function getDrops(){
		return [];
	}

	public function attack($damage, $source = EntityDamageEvent::CAUSE_MAGIC){}

	public function heal($amount, $source = EntityRegainHealthEvent::CAUSE_MAGIC){}

	public function despawnFrom(Player $player){
		parent::despawnFrom($player);
	}

	public function spawnTo(Player $player){
		parent::spawnTo($player);
	}
}