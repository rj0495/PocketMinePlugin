<?php

namespace MineBlock\ItemPet;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\item\Item as Item;
use pocketmine\math\Vector3;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\AddItemEntityPacket;
use pocketmine\Player;
use pocketmine\entity\Entity;
use pocketmine\nbt\tag\Byte;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Float;
use pocketmine\nbt\tag\Int;
use pocketmine\math\AxisAlignedBB;

class ItemPet extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		// Entity::registerEntity(ItemPetEntity::class);
		// Entity::registerEntity(ItemPetItemEntity::class);
		$this->player = [];
	}

	public function onPlayerMove(PlayerMoveEvent $event){
		if(!isset($this->player[$n = strtolower($event->getPlayer()->getName())]) || $this->player[$n]->dead){
			$this->player[$n] = new ItemPetEntity($event->getPlayer(), 1.5, 0, 0.4);
		}
	}
}
class ItemPetEntity extends Entity{
	const NETWORK_ID = 66;

	public $width = 0.25;

	public $length = 0.25;

	public $height = 0.25;

	public $stepHeight = 0.5;

	protected $gravity = 0.25;

	protected $speed = 0.125;

	protected $blockId = 65;

	protected $damage;

	public $canCollide = false;

	public $target;

	public $player;

	public $isFallow = true;

	public $jumpTick = 0;

	public $in = 0;

	public $isHead = false;

	public $targetVec;

	public function __construct(Entity $target, $distance = 0, $tailCount = 0, $tailDistance = null){
		parent::__construct($target->getLevel()->getChunk($target->x >> 4, $target->z >> 4), new Compound("", ["Pos" => new Enum("Pos", [new Double("", $target->x), new Double("", $target->y + 0.5), new Double("", $target->z)]), "Motion" => new Enum("Motion", [new Double("", 0), new Double("", 0), new Double("", 0)]), "Rotation" => new Enum("Rotation", [new Float("", 0), new Float("", 0)]), "TileID" => new Int("TileID", 1), "Data" => new Byte("Data", 0)]));
		$this->isHead = $target instanceof Player;
		$this->target = $target;
		$this->player = $target instanceof Player ? $target : ($target instanceof ItemPetEntity && $target->player instanceof Player ? $target->player : null);
		$this->distance = $target instanceof Player || $tailDistance == null ? $distance : $tailDistance;
		if($tailCount > 0) new ItemPetEntity($this, $distance, $tailCount - 1, $tailDistance);
		parent::spawnToAll();
		$this->item = new ItemPetItemEntity($this);
	}

	protected function initEntity(){}

	public function saveNBT(){}

	public function canCollideWith(Entity $entity){
		return false;
	}

	public function getBoundingBox(){
		$this->boundingBox = new AxisAlignedBB($x = $this->x - $this->width / 2, $y = $this->y - $this->stepHeight, $z = $this->z - $this->length / 2, $x + $this->width, $y + $this->height, $z + $this->length);
		return $this->boundingBox;
	}

	public function updateMove(Vector3 $vec){
		$this->x = $vec->x;
		$this->y = $vec->y;
		$this->z = $vec->z;
		foreach($this->hasSpawned as $player){
			$player->addEntityMovement($this->getId(), $this->x, $this->y, $this->z, 0, 0);
		}
	}

	public function onUpdate($currentTick){
		if($this->closed == true || $this->dead == true || ($target = $this->target) == null || $target->dead || $this->player instanceof Player && !$this->player->spawned){
			if(!$this->closed) parent::close();
			if(!$this->item->closed) $this->item->close();
			$this->dead = true;
			return false;
		}else{
			$dis = sqrt(pow($dZ = $target->z - $this->z, 2) + pow($dX = $target->x - $this->x, 2));
			if($this->isHead){
				$bb = clone $this->getBoundingBox();
				$this->timings->startTiming();
				$tickDiff = max(1, $currentTick - $this->lastUpdate);
				$this->lastUpdate = $currentTick;
				$hasUpdate = $this->entityBaseTick($tickDiff);
				if($this->getLevel()->getFolderName() !== $target->getLevel()->getFolderName()) $this->teleport($target);
				$onGround = count($this->level->getCollisionBlocks($bb->offset(0, -$this->gravity, 0))) > 0;
				$x = cos($at2 = atan2($dZ, $dX)) * $this->speed;
				$z = sin($at2) * $this->speed;
				$y = 0;
				$bb->offset(0, $this->gravity, 0);
				$isJump = count($this->level->getCollisionBlocks($bb->grow(0, 0, 0)->offset($x, 1, $z))) <= 0;
				if(count($this->level->getCollisionBlocks($bb->grow(0, 0, 0)->offset(0, 0, $z))) > 0){
					$z = 0;
					if($isJump) $y = $this->gravity;
				}
				if(count($this->level->getCollisionBlocks($bb->grow(0, 0, 0)->offset($x, 0, 0))) > 0){
					$x = 0;
					if($isJump) $y = $this->gravity;
				}
				if(!$this->isFallow || $dis < $this->distance){
					$x = 0;
					$z = 0;
				}
				if($this->isFallow && $dis > 50){
					$this->updateMove($target);
					if($target instanceof Player) $target->sendMessage("[ItemPet] 같이가요 ");
					return true;
				}elseif(!$isJump && $target->y > $this->y - ($target instanceof Player ? 0.5 : 0)){
					if($this->jumpTick <= 0) $this->jumpTick = 40;
					elseif($this->jumpTick > 36) $y = $this->gravity;
				}
				if($this->jumpTick > 0) $this->jumpTick--;
				if(($n = floor($this->y) - $this->y) < $this->gravity && $n > 0) $y = -$n;
				if($y == 0 && !$onGround) $y = -$this->gravity;
				$block = $this->level->getBlock($this->add($vec = new Vector3($x, $y, $z)));
				if($block->hasEntityCollision()){
					$block->addVelocityToEntity($this, $vec2 = $vec->add(0, $this->gravity, 0));
					$vec->x = ($vec->x + $vec2->x / 2) / 5;
					$vec->y = ($vec->y + $vec2->y / 2);
					$vec->z = ($vec->z + $vec2->z / 2) / 5;
				}
				if(count($this->level->getCollisionBlocks($bb->offset(0, -0.01, 0))) <= 0) $y -= 0.01;
				$this->updateMove($vec->add(new Vector3(($this->boundingBox->minX + $this->boundingBox->maxX - $this->drag) / 2, ($this->boundingBox->minY + $this->boundingBox->maxY) / 2, ($this->boundingBox->minZ + $this->boundingBox->maxZ - $this->drag) / 2)));
				$this->onGround = $onGround;
			}else{
				$x = cos($at2 = atan2($dZ, $dX)) * $this->distance;
				$z = sin($at2) * $this->distance;
				$this->updateMove($target->add(-$x, 0, -$z));
			}
		}
		return true;
	}

	public function getData(){
		return [];
	}

	public function attack($damage, $source = EntityDamageEvent::CAUSE_MAGIC){
		if($source instanceof EntityDamageEvent && $source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if(!$player = $this->player) return;
			if($player->getName() == $damager->getName()){
				$this->item->attack($damage, $source);
			}else{
				$damager->sendMessage("[BlockPet] 제 주인은 " . $player->getName() . "님입니다. 때리지마요 !");
				$player->sendMessage("[BlockPet] " . $damager->getName() . "님이 저 때려요 !");
			}
		}
	}

	public function heal($amount, $source = EntityRegainHealthEvent::CAUSE_MAGIC){}

	public function spawnTo(Player $player){
		$pk = new AddEntityPacket();
		$pk->type = ItemPetEntity::NETWORK_ID;
		$pk->eid = $this->id;
		$pk->x = $this->x;
		$pk->y = $this->y + $this->stepHeight;
		$pk->z = $this->z;
		$pk->did = -($this->blockId | $this->damage << 0x10);
		$player->dataPacket($pk);
		parent::spawnTo($player);
	}
}
class ItemPetItemEntity extends Entity{
	const NETWORK_ID = 64;

	public $width = 0.25;

	public $length = 0.25;

	public $height = 0.25;

	public $stepHeight = 0;

	protected $gravity = 0.25;

	protected $speed = 0.125;

	protected $item;

	public $block;

	public $canCollide = false;

	public function __construct(Entity $target){
		parent::__construct($target->getLevel()->getChunk($target->x >> 4, $target->z >> 4), new Compound("", ["Pos" => new Enum("Pos", [new Double("", $target->x), new Double("", $target->y + 0.5), new Double("", $target->z)]), "Motion" => new Enum("Motion", [new Double("", 0), new Double("", 0), new Double("", 0)]), "Rotation" => new Enum("Rotation", [new Float("", 0), new Float("", 0)]), "TileID" => new Int("TileID", 1), "Data" => new Byte("Data", 0)]));
		$this->item = Item::get(1, 0, 1);
		$this->block = $target;
		parent::spawnToAll();
	}

	protected function initEntity(){}

	public function saveNBT(){}

	public function getBoundingBox(){
		$this->boundingBox = new AxisAlignedBB($x = $this->x - $this->width / 2, $y = $this->y - $this->stepHeight, $z = $this->z - $this->length / 2, $x + $this->width, $y + $this->height, $z + $this->length);
		return $this->boundingBox;
	}

	public function updateMove(Vector3 $vec){
		$this->x = $vec->x;
		$this->y = $vec->y;
		$this->z = $vec->z;
		foreach($this->hasSpawned as $player){
			$player->addEntityMovement($this->getId(), $this->x, $this->y, $this->z, 0, 0);
		}
	}

	public function onUpdate($currentTick){
		if($this->closed == true || $this->dead == true || $this->block == null || $this->block->dead || $this->block->closed){
			if(!$this->closed) parent::close();
			if(!$this->block->closed) $this->block->close();
			$this->dead = true;
			return false;
		}
		$this->updateMove($this->block->add(0, -0.35, 0));
		return true;
	}

	public function attack($damage, $source = EntityDamageEvent::CAUSE_MAGIC){
		if($source instanceof EntityDamageEvent && $source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if(!$this->item->equals($i = $damager->getInventory()->getItemInHand(), true)){
				$i->setCount(1);
				$this->item = $i;
				$this->despawnFromAll();
				if(!$this->dead){
					$this->spawnToAll();
				}
				$damager->sendMessage("[BlockPet] 블럭 변신 !");
			}else{
				$this->block->isFallow = !$this->block->isFallow;
				$damager->sendMessage("[BlockPet] " . ($this->block->isFallow ? "잘 따라다닐게요 !" : "여기서 기다릴게요 !"));
			}
		}
	}

	public function heal($amount, $source = EntityRegainHealthEvent::CAUSE_MAGIC){}

	public function getData(){
		return [];
	}

	public function canCollideWith(Entity $entity){
		return false;
	}

	public function spawnTo(Player $player){
		$pk = new AddItemEntityPacket();
		$pk->eid = $this->id;
		$pk->x = $this->x;
		$pk->y = $this->y + $this->stepHeight;
		$pk->z = $this->z;
		$pk->yaw = $this->yaw;
		$pk->pitch = $this->pitch;
		$pk->roll = 0;
		$pk->item = $this->item;
		$player->dataPacket($pk);
		parent::spawnTo($player);
	}
}