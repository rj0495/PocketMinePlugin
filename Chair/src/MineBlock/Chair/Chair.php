<?php

namespace MineBlock\Chair;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\protocol\AddMobPacket;
use pocketmine\network\protocol\MovePlayerPacket;
use pocketmine\Player;
use pocketmine\block\Block;
use pocketmine\entity\Living;
use pocketmine\nbt\tag\Byte;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Float;
use pocketmine\nbt\tag\Int;
use pocketmine\scheduler\CallbackTask;
use pocketmine\network\protocol\DataPacket;

class Chair extends PluginBase implements Listener{

	public function onEnable(){
		$this->player = [];
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$property = (new \ReflectionClass("\\pocketmine\\Server"))->getProperty("mainInterface");
		$property->setAccessible(true);
		$property->getValue($this->getServer())->registerPacket(0xb9, PlayerInputPacket::class);
	}

	/**
	 * @priority HIGHEST
	 */
	public function onDataPacketReceive(DataPacketReceiveEvent $event){
		$p = $event->getPlayer();
		$pk = $event->getPacket();
		if($pk instanceof PlayerInputPacket && isset($this->player[$n = $p->getName()]) && !$this->player[$n]->closed && !$this->player[$n]->isRide){
			$this->player[$n]->isRide = true;
		}
	}

	/**
	 * @priority HIGHEST
	 */
	public function onPlayerInteract(PlayerInteractEvent $event){
		$b = $event->getBlock();
		if(!$event->isCancelled() && !$event->getItem()->isPlaceable() && (!isset($this->player[$n = $event->getPlayer()->getName()]) || $this->player[$n]->closed) && in_array($b->getID(), [53, 67, 108, 109, 114, 128, 134, 135, 136, 163, 164]) && $b->getDamage() <= 4){
			$event->setCancelled();
			$this->player[$n] = new ChairEntity($b, $event->getPlayer());
		}
	}
}
class ChairEntity extends Living{

	public $block, $rider, $isRide;

	public function __construct(Block $block, Player $player){
		$vec = $block->add(0.5 + [-0.25, 0.25, 0, 0][$d = $block->getDamage()], 0.6, 0.5 + [0, 0, -0.25, 0.25][$d = $block->getDamage()]);
		parent::__construct($block->getLevel()->getChunk($block->x >> 4, $block->z >> 4), new Compound("", ["Pos" => new Enum("Pos", [new Double("", $vec->x), new Double("", $vec->y), new Double("", $vec->z)]), "Motion" => new Enum("Motion", [new Double("", 0), new Double("", 0), new Double("", 0)]), "Rotation" => new Enum("Rotation", [new Float("", 0), new Float("", 0)])]));
		$this->block = $block;
		$this->rider = $player;
		$this->isRide = false;
		$this->property = (new \ReflectionClass("\\pocketmine\\Player"))->getProperty("inAirTicks");
		$this->property->setAccessible(true);
		parent::spawnToAll();
	}

	public function getName(){
		return "ChairEntity";
	}

	public function getData(){
		return [];
	}

	protected function initEntity(){}

	public function saveNBT(){}

	public function onUpdate($currentTick){
		if($this->closed == true || $this->rider->dead || $this->getLevel() !== $this->rider->getLevel()){
			if(!$this->closed) $this->close();
			return false;
		}else{
			$this->property->setValue($this->rider, 0);
			$this->yaw = $this->rider->getYaw();
			$this->pitch = $this->rider->getPitch();
			$pk = new MovePlayerPacket();
			$pk->eid = $this->id;
			$pk->x = $this->x;
			$pk->y = $this->y;
			$pk->z = $this->z;
			$pk->yaw = $this->yaw;
			$pk->pitch = $this->pitch;
			$pk->bodyYaw = $pk->yaw;
			$pk2 = new SetRidePacket();
			$pk2->rider = $this->rider->getID();
			$pk2->riding = $this->id;
			$dis = sqrt(pow($dZ = $this->rider->z - $this->z, 2) + pow($dX = $this->rider->x - $this->x, 2));
			if(!$this->isRide && $dis > 0.1){
				$this->rider->teleport($this);
			}elseif($this->isRide && $dis > 0.1){
				$this->close();
				return false;
			}
			foreach($this->hasSpawned as $p){
				if($this->isRide && $this->rider === $p) continue;
				$p->directDataPacket($pk);
				$pk2->rider = $this->rider === $p ? 0 : $this->rider->getID();
				$p->directDataPacket($pk2);
			}
		}
		return true;
	}

	public function spawnTo(Player $player){
		$pk = new AddMobPacket();
		$pk->type = 37;
		$pk->eid = $this->id;
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		$pk->yaw = 0;
		$pk->pitch = 0;
		$pk->metadata = [16 => ["type" => 0, "value" => 0]];
		$player->dataPacket($pk);
		parent::spawnTo($player);
	}
}
class SetRidePacket extends DataPacket{

	public static $pool = [];

	public static $next = 0;

	public $rider;

	public $riding;

	public function pid(){
		return 0xa9;
	}

	public function decode(){}

	public function encode(){
		$this->reset();
		$this->putInt($this->rider);
		$this->putInt($this->riding);
	}
}
class PlayerInputPacket extends DataPacket{

	public static $pool = [];

	public static $next = 0;

	public function pid(){
		return 0xb9;
	}

	public function decode(){
	}

	public function encode(){
	}
}