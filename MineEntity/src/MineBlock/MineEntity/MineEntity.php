<?php

namespace MineBlock\MineEntity;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\entity\ExplosionPrimeEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\protocol\Info as ProtocolInfo;
use pocketmine\network\protocol\AddMobPacket;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\MovePlayerPacket;
use pocketmine\Player;
use pocketmine\item\Item;
use pocketmine\block\Block;
use pocketmine\level\Position;
use pocketmine\level\Explosion;
use pocketmine\entity\Entity;
use pocketmine\entity\Explosive;
use pocketmine\entity\Animal as PocketMineAnimal;
use pocketmine\nbt\tag\Byte;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Float;
use pocketmine\nbt\tag\Int;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\scheduler\CallbackTask;
use pocketmine\network\protocol\DataPacket;

class MineEntity extends PluginBase implements Listener{

	public function onEnable(){
		foreach([10, 11, 12, 13, 14, 15, 16, 33, 34, 35, 36, 37, 38, 39] as $d)
			Block::$creative[] = [383, $d];
		Block::$creative[] = [328, 0];
		$this->list = [10 => Chicken::class, 11 => Cow::class, 12 => Pig::class, 13 => Sheep::class, 14 => Wolf::class, 15 => Villager::class, 16 => Mooshroom::class, 32 => Zombie::class, 33 => Creeper::class, 34 => Skeleton::class, 35 => Spider::class, 36 => ZombiePigman::class, 37 => Slime::class, 38 => Enderman::class, 39 => Silverfish::class];
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	/**
	 * @priority HIGHEST
	 */
	public function onDataPacketReceive(DataPacketReceiveEvent $event){
		$p = $event->getPlayer();
		$pk = $event->getPacket();
		$p->sendMessage("0x" . dechex($pk->pid()));
		if($p->spawned && !$p->dead && !$p->blocked){
			if($pk->pid() == 162){
				$target = $p->getLevel()->getEntity($pk->target);
				if($target instanceof BaseEntity){
					$this->player[$p->getName()] = [$target, $this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "runAttack"], [$p, $target]), 2)];
					$event->setCancelled();
				}
			}elseif($pk->pid() == 163 && $pk->face == 255){
				if(isset($this->player[$n = $p->getName()])){
					if($this->player[$n][0] instanceof BaseEntity){
						$this->player[$n][0]->onActivate($p->getInventory()->getItemInHand(), $p);
					}
					$this->player[$n][1]->cancel();
					unset($this->player[$n]);
				}
			}
		}
	}

	/**
	 * @priority HIGHEST
	 */
	public function onPlayerInteract(PlayerInteractEvent $event){
		$i = $event->getItem();
		if($event->getFace() !== 255 && !$event->isCancelled()){
			if($i->getID() === 383 && isset($this->list[$d = $i->getDamage()])){
				$event->setCancelled();
				new $this->list[$d]($event->getBlock()->getSide($event->getFace()));
			}elseif($i->getID() == 328){
				new MineCart($event->getBlock()->getSide($event->getFace()));
			}else{
				return;
			}
			$p = $event->getPlayer();
			if(!$p->isCreative()){
				$i->setCount($i->getCount() - 1);
				$p->getInventory()->setItem($player->getInventory()->getHeldItemSlot(), $i);
				$p->getInventory()->sendContents($p);
			}
		}
	}

	public function runAttack($player, $target){
		$player->craftingType = 0;
		$item = $player->getInventory()->getItemInHand();
		$damageTable = [Item::WOODEN_SWORD => 4, Item::GOLD_SWORD => 4, Item::STONE_SWORD => 5, Item::IRON_SWORD => 6, Item::DIAMOND_SWORD => 7, Item::WOODEN_AXE => 3, Item::GOLD_AXE => 3, Item::STONE_AXE => 3, Item::IRON_AXE => 5, Item::DIAMOND_AXE => 6, Item::WOODEN_PICKAXE => 2, Item::GOLD_PICKAXE => 2, Item::STONE_PICKAXE => 3, Item::IRON_PICKAXE => 4, Item::DIAMOND_PICKAXE => 5, Item::WOODEN_SHOVEL => 1, Item::GOLD_SHOVEL => 1, Item::STONE_SHOVEL => 2, Item::IRON_SHOVEL => 3, Item::DIAMOND_SHOVEL => 4];
		$damage = [EntityDamageEvent::MODIFIER_BASE => isset($damageTable[$item->getID()]) ? $damageTable[$item->getID()] : 1];
		$ev = new EntityDamageByEntityEvent($player, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage);
		if($player->distance($target) > 8) $ev->setCancelled();
		$target->attack($ev->getFinalDamage(), $ev);
		if($ev->isCancelled()){
			if($player->isSurvival()) $player->getInventory()->sendContents($player);
		}elseif($item->isTool() and $player->isSurvival()){
			if($item->useOn($target) and $item->getDamage() >= $item->getMaxDurability()){
				$player->getInventory()->setItemInHand(Item::get(Item::AIR, 0, 1), $player);
			}else{
				$player->getInventory()->setItemInHand($item, $player);
			}
			$player->getInventory()->sendContents($player);
		}
	}
}
class BaseEntity extends PocketMineAnimal{

	public $width = 1;

	public $length = 1;

	public $height = 1;

	public $canCollide = true;

	public $target = null;

	public $moveTarget = null;

	public $moveTick = 0;

	public $knockBackXZ = null;

	public $attackTime = 0;

	public $isRiding = false;

	public $rider = null;

	protected $gravity = 0.25;

	protected $speed = 0.25;

	protected $type = 0;

	public function __construct(Position $pos){
		parent::__construct($pos->getLevel()->getChunk($pos->x >> 4, $pos->z >> 4), new Compound("", ["Pos" => new Enum("Pos", [new Double("", $pos->x), new Double("", $pos->y), new Double("", $pos->z)]), "Motion" => new Enum("Motion", [new Double("", 0), new Double("", 0), new Double("", 0)]), "Rotation" => new Enum("Rotation", [new Float("", 0), new Float("", 0)]), "Data" => new Byte("Data", 0)]));
		parent::spawnToAll();
	}

	public function getName(){
		return "BaseEntity";
	}

	public function getData(){
		$flags = 0;
		$flags |= $this->fireTicks > 0 ? 1 : 0;
		$d = [0 => ["type" => 0, "value" => $flags], 1 => ["type" => 1, "value" => $this->airTicks], 16 => ["type" => 0, "value" => 0], 17 => ["type" => 6, "value" => [0, 0, 0]]];
		return $d;
	}

	public function getDrops(){
		return [];
	}

	protected function initEntity(){}

	public function saveNBT(){}

	public function getBoundingBox(){
		$this->boundingBox = new AxisAlignedBB($x = $this->x - $this->width / 2, $y = $this->y, $z = $this->z - $this->length / 2, $x + $this->width, $y + $this->height, $z + $this->length);
		return $this->boundingBox;
	}

	public function updateMove(Vector3 $vec){
		$this->x = $vec->x;
		$this->y = $vec->y;
		$this->z = $vec->z;
		$pk = new MovePlayerPacket();
		$pk->eid = $this->id;
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		$pk->yaw = $this->yaw;
		$pk->pitch = $this->pitch;
		$pk->bodyYaw = $this->yaw;
		$this->server->broadcastPacket($this->hasSpawned, $pk);
	}

	public function onUpdate($currentTick){
		if($this->closed == true){
			return false;
		}else{
			$this->entityBaseTick();
			if(!$this->target instanceof Player || $this->target->dead || $this->getLevel() !== $this->target->getLevel() || $this->target->distance($this) >= 20){
				$this->target = null;
			}
			$target = $this->target;
			if($this->isRiding && $this->rider !== null){
				$target = $this->add(-sin((($yaw = $this->rider->getYaw()) == 0 ? 0.1 : $yaw) / 180 * M_PI) * cos((($pitch = $this->rider->getPitch()) == 0 ? 0.1 : $pitch) / 180 * M_PI) * 5, -sin(($pitch == 0 ? 0.1 : $pitch) / 180 * M_PI), cos(($yaw == 0 ? 0.1 : $yaw) / 180 * M_PI) * cos(($pitch == 0 ? 0.1 : $pitch) / 180 * M_PI) * 5);
				foreach($this->hasSpawned as $p){
					$pk = new SetRidePacket();
					$pk->a = $this->rider === $p ? 0 : $this->rider->getID();
					$pk->b = $this->id;
					$p->dataPacket($pk);
				}
				$property = (new \ReflectionClass("\\pocketmine\\Player"))->getProperty("inAirTicks");
				$property->setAccessible(true);
				$property->setValue($this->rider, 0);
			}
			if($target == null){
				if($this->moveTick <= 0){
					$this->moveTarget = null;
					$this->moveTick = 40;
					$this->updateMove($this);
					return true;
				}else{
					$this->moveTick--;
					$this->moveTarget = $this->moveTarget == null ? $this->add(rand(0, 5) * (rand(0, 1) == 0 ? 1 : -1), 0, rand(0, -5) * (rand(0, 1) == 0 ? 1 : -1)) : $this->moveTarget;
					$target = $this->moveTarget;
				}
			}else{
				$this->moveTarget = null;
				$this->moveTick = 0;
			}
			if($this->attackTime > 0 && $this->knockBackXZ !== null){
				$target = $this->add($this->knockBackXZ[0], $this->y, $this->knockBackXZ[1]);
				$isMove = true;
				if($this->attackTime >= 5) $y = $this->gravity;
			}elseif($this->knockBackXZ !== null){
				$this->knockBackXZ = null;
				$this->updateMove($this);
				return true;
			}
			if($target == null){
				$this->updateMove($this);
				return true;
			}
			$dis = sqrt(pow($dZ = $target->z - $this->z, 2) + pow($dX = $target->x - $this->x, 2));
			$bb = clone $this->getBoundingBox();
			$tickDiff = max(1, $currentTick - $this->lastUpdate);
			$this->lastUpdate = $currentTick;
			$hasUpdate = $this->entityBaseTick($tickDiff);
			$onGround = count($this->level->getCollisionBlocks($bb->offset(0, -$this->gravity, 0))) > 0;
			$x = cos($at2 = atan2($dZ, $dX)) * $this->speed;
			$z = sin($at2) * $this->speed;
			if(!isset($y)) $y = 0;
			$bb->offset(0, $this->gravity, 0);
			$isJump = count($this->level->getCollisionBlocks($bb->grow(0, 0, 0)->offset($x, 1, $z))) <= 0;
			if(count($this->level->getCollisionBlocks($bb->grow(0, 0, 0)->offset(0, 0, $z))) > 0){
				$z = 0;
				if($isJump) $y += $this->gravity;
			}
			if(count($this->level->getCollisionBlocks($bb->grow(0, 0, 0)->offset($x, 0, 0))) > 0){
				$x = 0;
				if($isJump) $y += $this->gravity;
			}
			if(!is_array($this->knockBackXZ) && !$this->isRiding && $this->rider == null && $dis < 2.5){
				$x = 0;
				$z = 0;
			}
			if(($n = floor($this->y) - $this->y) < $this->gravity && $n > 0) $y -= $n;
			if($y == 0 && !$onGround) $y -= $this->gravity;
			$block = $this->level->getBlock($this->add($vec = new Vector3($x, $y, $z)));
			if($block->hasEntityCollision()){
				$block->addVelocityToEntity($this, $vec2 = $vec->add(0, $this->gravity, 0));
				$vec->x = ($vec->x + $vec2->x * 0.5) * 0.2;
				$vec->y = ($vec->y + $vec2->y * 0.5);
				$vec->z = ($vec->z + $vec2->z * 0.5) * 0.2;
			}
			if(count($this->level->getCollisionBlocks($bb->offset(0, -0.01, 0))) <= 0) $y -= 0.01;
			if(!is_array($this->knockBackXZ)){
				$this->yaw = atan2($dX, $dZ) / M_PI * -180;
				$dY = $this->type == 10 ? $this->y - $target->y : $target->y - $this->y;
				$dXZ = sqrt(pow($dX, 2) + pow($dZ, 2) + 0.1);
				$this->pitch = atan($dY / $dXZ) / M_PI * -180;
			}
			$this->updateMove($this->distance($target) <= 2 ? $this : $this->add($vec)); // new Vector3(($this->boundingBox->minX + $this->boundingBox->maxX - $this->drag) * 0.5, ($this->boundingBox->minY + $this->boundingBox->maxY) * 0.5 - $this->height * 0.5, ($this->boundingBox->minZ + $this->boundingBox->maxZ - $this->drag) / 2)));
			$this->onGround = $onGround;
		}
		return true;
	}

	public function onActivate(Item $item, Player $player = null){
		$this->isRiding = !$this->isRiding;
		$player->sendMessage($this->isRiding ? "True" : "False");
		if($this->isRiding && $this->rider == null){
			$this->rider = $player;
		}
	}

	public function attack($damage, $source = EntityDamageEvent::CAUSE_MAGIC){
		if(!$this->isRiding || $this->rider == null){
			parent::attack($damage, $source);
		}
	}

	public function knockBack(Entity $attacker, $damage, $x, $z, $base = 0.4){
		if(!$this->isRiding && $this->rider == null){
			$this->knockBackXZ = [$x, $z];
			parent::knockBack($attacker, $damage, $x, $z, $base);
		}
	}

	public function spawnTo(Player $player){
		$pk = new AddMobPacket();
		$pk->type = $this->type;
		$pk->eid = $this->id;
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		$pk->yaw = $this->yaw;
		$pk->pitch = $this->pitch;
		$pk->metadata = $this->getData();
		$player->dataPacket($pk);
		parent::spawnTo($player);
	}
}
class Animal extends BaseEntity{

	public $feed = null;

	public $target = null;

	public function getName(){
		return "Animal";
	}

	public function onUpdate($currentTick){
		if($this->closed == true){
			return false;
		}else{
			if(!$this->isRiding && $this->feed !== null && ($this->target == null || $this->target->dead || $this->getLevel() !== $this->target->getLevel() || $this->target->distance($this) >= 20 || $this->target->getInventory()->getItemInHand()->getID() !== $this->feed)){
				$this->target = null;
				foreach($this->hasSpawned as $p){
					if($p instanceof Player && !$p->dead || $this->level == $p->getLevel() && $p->distance($this) < 20){
						if($p->getInventory()->getItemInHand()->getID() == $this->feed){
							$this->target = $p;
							break;
						}
					}
				}
			}
			parent::onUpdate($currentTick);
		}
	}
}
class Chicken extends Animal{

	protected $type = 10;

	public $feed = 295;

	protected function initEntity(){
		$this->setMaxHealth(10);
	}

	public function getDrops(){
		$drops = [];
		$drops[] = Item::get($this->fireTicks > 0 ? 366 : 365, 0, 1);
		return $drops;
	}

	public function getName(){
		return "Chicken";
	}

	public function onUpdate($currentTick){
		if($this->closed == true){
			return false;
		}else{
			if(rand(0, 600) == 0){
				$this->level->dropItem($this, Item::get(344, 0, 1));
			}
			parent::onUpdate($currentTick);
		}
	}
}
class Cow extends Animal{

	protected $type = 11;

	public $feed = 296;

	public function getDrops(){
		$drops = [];
		$drops[] = Item::get($this->fireTicks > 0 ? 364 : 363, 0, 1);
		return $drops;
	}

	public function getName(){
		return "Cow";
	}

	public function onActivate(Item $item, Player $player = null){
		if($item->getID() == 325 && $item->getDamage() == 0){
			$player->getInventory()->setItem($player->getInventory()->getHeldItemSlot(), Item::get(325, 1, 1));
			$player->getInventory()->sendContents($player);
		}
	}
}
class Pig extends Animal{

	protected $type = 12;

	public $feed = 319;

	public function getName(){
		return "Pig";
	}

	public function getDrops(){
		$drops = [];
		$drops[] = Item::get($this->fireTicks > 0 ? 320 : 319, 0, 1);
		return $drops;
	}
}
class Sheep extends Animal{
	const WHITE = 0;
	const ORANGE = 1;
	const MMAGENTA = 2;
	const LIGHT_BLUE = 3;
	const YELLOW = 4;
	const LIME = 5;
	const PINK = 6;
	const GRAY = 7;
	const LIGHT_GRAY = 8;
	const CYAN = 9;
	const PURPLE = 10;
	const BLUE = 11;
	const BROWN = 12;
	const GREEN = 13;
	const RED = 14;
	const BLACK = 15;

	protected $type = 13;

	public $feed = 296;

	private $sheared = true;

	private $color = 0;

	public function initEntity(){
		$this->sheared = true;
		$this->color = rand(0, 3) !== 0 ? 0 : mt_rand(1, 15);
	}

	public function getName(){
		return "Sheep";
	}

	public function onUpdate($currentTick){
		if($this->closed == true){
			return false;
		}else{
			if($this->sheared == false && rand(0, 600) == 0){
				$b = $this->level->getBlock($this->add(0, -0.5, 0));
				if($b->getID() == 2){
					$this->level->setBlock($b, Block::get(3, 0));
					$this->sheared = true;
					$this->sendMetadata($this->hasSpawned);
				}
			}
			parent::onUpdate($currentTick);
		}
	}

	public function getColor(){
		return $this->color;
	}

	public function setColor($color){
		if(!is_numeric($color) || $color < 0 || $color > 15) return false;
		$this->color = floor($color);
		$this->sendMetadata($this->hasSpawned);
	}

	public function getDrops(){
		$drops = [];
		if($this->sheared == true) $drops[] = Item::get(35, $this->color & 0x0F, 1);
		return $drops;
	}

	public function getData(){
		$flags = 0;
		$flags |= $this->fireTicks > 0 ? 1 : 0;
		return [0 => ["type" => 0, "value" => $flags], 1 => ["type" => 1, "value" => $this->airTicks], 16 => ["type" => 0, "value" => (($this->sheared == true ? 0 : 1) << 4) | ($this->color & 0x0F)], 17 => ["type" => 6, "value" => [0, 0, 0]]];
	}

	public function onActivate(Item $item, Player $player = null){
		if($item->getID() == 351){
			$colorTable = [Sheep::WHITE, Sheep::ORANGE, Sheep::MMAGENTA, Sheep::LIGHT_BLUE, Sheep::YELLOW, Sheep::LIME, Sheep::PINK, Sheep::GRAY, Sheep::LIGHT_GRAY, Sheep::CYAN, Sheep::PURPLE, Sheep::BLUE, Sheep::BROWN, Sheep::GREEN, Sheep::RED, Sheep::BLACK];
			if(isset($colorTabel[$d = 15 - $item->getDamage()])){
				$this->setColor($colorTable[$d]);
				if($player->isSurvival()){
					$item->setCount($item->getCount - 1);
					$player->getInventory()->setItem($player->getInventory()->getHeldItemSlot(), $item);
					$player->getInventory()->sendContents($player);
				}
			}
		}elseif($item->getID() == 359 && $this->sheared == true){
			foreach($this->getDrops() as $item){
				$this->level->dropItem($this, $item);
				if(rand(0, 1) == 0) $this->level->dropItem($this, $item);
			}
			$this->sheared = false;
			$this->sendMetadata($this->hasSpawned);
			if($player->isSurvival()){
				if($item->useOn($target) and $item->getDamage() >= $item->getMaxDurability()){
					$player->getInventory()->setItemInHand(Item::get(Item::AIR, 0, 1), $player);
				}else{
					$player->getInventory()->setItemInHand($item, $player);
				}
				$player->getInventory()->sendContents($player);
			}
		}
	}
}
class Wolf extends Animal{

	protected $type = 14;

	public $feed = 352;

	public function getName(){
		return "Wolf";
	}
}
class Villager extends Animal{

	protected $type = 15;

	public $feed = 388;

	public function getName(){
		return "Villager";
	}
}
class Mooshroom extends Animal{

	protected $type = 16;

	public $feed = 296;

	public function getDrops(){
		$drops = [];
		if(($r = rand(0, 2)) >= 1) $drops[] = Item::get(40, 0, $r);
		return $drops;
	}

	public function getName(){
		return "Mooshroom";
	}

	public function onActivate(Item $item, Player $player = null){
		if($item->getID() == 359){
			foreach($this->getDrops() as $item){
				$this->level->dropItem($this, $item);
				if(rand(0, 1) == 0) $this->level->dropItem($this, $item);
			}
			$this->close((new Cow($this))->setRotation($this->yaw, $this->pitch));
			if($player->isSurvival()){
				if($item->useOn($target) and $item->getDamage() >= $item->getMaxDurability()){
					$player->getInventory()->setItemInHand(Item::get(Item::AIR, 0, 1), $player);
				}else{
					$player->getInventory()->setItemInHand($item, $player);
				}
				$player->getInventory()->sendContents($player);
			}
		}
	}
}
class Monster extends BaseEntity{

	public $target = null;

	public function getName(){
		return "Monster";
	}

	public function onUpdate($currentTick){
		if($this->closed == true){
			return false;
		}else{
			if(!$this->isRiding && $this->target == null || $this->target->dead || $this->getLevel() !== $this->target->getLevel() || $this->target->distance($this) >= 20){
				$this->target = null;
				foreach($this->hasSpawned as $p){
					if($p instanceof Player && !$p->dead || $this->getLevel()->getFolderName() == $p->getLevel()->getFolderName() && $p->distance($this) < 20){
						$this->target = $p;
						break;
					}
				}
			}
			parent::onUpdate($currentTick);
		}
	}

	public function attack($damage, $source = EntityDamageEvent::CAUSE_MAGIC){
		if($source instanceof EntityDamageEvent && $source instanceof EntityDamageByEntityEvent) $this->target = $source->getDamager();
		parent::attack($damage, $source);
	}
}
class Zombie extends Monster{

	protected $type = 32;

	public function getDrops(){
		$drops = [];
		if(($r = rand(0, 2)) >= 1) $drops[] = Item::get(288, 0, $r);
		return $drops;
	}

	public function getName(){
		return "Zombie";
	}

	public function onUpdate($currentTick){
		if($this->closed == true){
			return false;
		}else{
			parent::onUpdate($currentTick);
			if(($time = $this->level->getTime() % 24000) < 14000 || $time > 23000) $this->setOnFire(3);
		}
	}
}
class Creeper extends Monster implements Explosive{

	protected $type = 33;

	public $explodeTick = 0;

	public function explode(){
		$this->server->getPluginManager()->callEvent($ev = new ExplosionPrimeEvent($this, 4));
		if(!$ev->isCancelled()){
			$explosion = new Explosion($this, $ev->getForce(), $this);
			if($ev->isBlockBreaking()){
				$explosion->explodeA();
			}
			$explosion->explodeB();
		}
	}

	public function getDrops(){
		$drops = [];
		if(!isset($this->isExplode) && ($r = rand(0, 2)) >= 1) $drops[] = Item::get(289, 0, $r);
		return $drops;
	}

	public function getData(){
		$flags = 0;
		$flags |= $this->fireTicks > 0 ? 1 : 0;
		return [0 => ["type" => 0, "value" => $flags], 1 => ["type" => 1, "value" => $this->airTicks], 16 => ["type" => 0, "value" => rand(0, 1)], 17 => ["type" => 6, "value" => [0, 0, 0]]];
	}

	public function getName(){
		return "Creeper";
	}

	public function onUpdate($currentTick){
		if($this->closed == true){
			return false;
		}else{
			parent::onUpdate($currentTick);
			if($this->explodeTick >= 60){
				$this->isExplode = true;
				$this->explode();
				$this->close();
				return false;
			}elseif($this->explodeTick >= 40){
				$this->speed = 0;
				$this->explodeTick++;
			}elseif($this->target == null || $this->distance($this->target) >= 5){
				$this->explodeTick = 0;
				return true;
			}else{
				$this->explodeTick++;
				$this->sendMetadata($this->hasSpawned);
			}
		}
	}
}
class Skeleton extends Monster{

	protected $type = 34;

	public function getDrops(){
		$drops = [];
		if(($r = rand(0, 2)) >= 1) $drops[] = Item::get(352, 0, $r);
		if(($r = rand(0, 2)) >= 1) $drops[] = Item::get(262, 0, $r);
		return $drops;
	}

	public function getName(){
		return "Skeleton";
	}

	public function onUpdate($currentTick){
		if($this->closed == true){
			return false;
		}else{
			parent::onUpdate($currentTick);
			if(($time = $this->level->getTime() % 24000) < 14000 || $time > 23000) $this->setOnFire(3);
		}
	}
}
class Spider extends Monster{

	protected $type = 35;

	public function getDrops(){
		$drops = [];
		if(($r = rand(0, 2)) >= 1) $drops[] = Item::get(287, 0, $r);
		return $drops;
	}

	public function getName(){
		return "Spider";
	}
}
class ZombiePigman extends Monster{

	protected $type = 36;

	public function getName(){
		return "ZombiePigman";
	}
}
class Slime extends Monster{

	protected $type = 37;

	private $size = 0;

	public $jumpTick = 0;

	public function initEntity(){
		if($this->size < 1 || $thos->size > 3) $this->size = rand(1, 3);
		$this->setMaxHealth([0, 5, 10, 20][$this->size]);
	}

	public function getSize(){
		return $this->size;
	}

	public function setSize($size){
		if(!is_numeric($size) || $size < 1 || $size > 3) return false;
		$this->size = floor($size);
		$this->sendMetadata($this->hasSpawned);
	}

	public function getDrops(){
		$drops = [];
		if($this->size == 1 && rand(0, 10) !== 0) $drops[] = Item::get(341, 0, rand(1, 2));
		return $drops;
	}

	public function getData(){
		$flags = 0;
		$flags |= $this->fireTicks > 0 ? 1 : 0;
		return [0 => ["type" => 0, "value" => $flags], 1 => ["type" => 1, "value" => $this->airTicks], 16 => ["type" => 0, "value" => $this->size * 1.5], 17 => ["type" => 6, "value" => [0, 0, 0]]];
	}

	public function getName(){
		return "Slime";
	}

	public function onUpdate($currentTick){
		if($this->closed == true){
			return false;
		}else{
			parent::onUpdate($currentTick);
			if($this->target == null){
				$this->jumpTick = 0;
				return;
			}elseif($this->jumpTick > 0){
				$this->jumpTick--;
			}elseif(rand(0, 10) == 0){
				$this->jumpTick = 15;
				$this->knockBack($this, 0, abs($dX = $this->target->x - $this->x) > 1 ? ($dX > 0 ? 0.3 : -0.3) : 0, abs($dZ = $this->target->z - $this->z) > 1 ? ($dZ > 0 ? 0.3 : -0.3) : 0, 0.4);
			}
		}
	}

	public function kill(){
		parent::kill();
		$this->dead = true;
		if($this->size > 1) $this->server->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "division"], []), 20);
	}

	public function division(){
		if(!$this->dead){
			$this->kill();
		}elseif($this->size > 1){
			for($xz = 0; $xz < 2; $xz++){
				$e = new Slime(Position::fromObject($this->add($xz % 2, 0, $xz), $this->level));
				$e->setSize($this->size - 1);
			}
		}
	}
}
class Enderman extends Monster{

	protected $type = 38;

	public function getName(){
		return "Enderman";
	}
}
class Silverfish extends Monster{

	protected $type = 39;

	public function getName(){
		return "Silverfish";
	}
}
class MineCart extends BaseEntity{

	public $width = 1;

	public $length = 1;

	public $height = 1;

	public $canCollide = true;

	public $isRiding = true;

	protected $gravity = 0.25;

	protected $speed = 0.25;

	protected $type = 84;

	public function __construct(Position $pos){
		parent::__construct(Position::fromObject($pos->add(0, 1, 0), $pos->getLevel()));
	}

	public function updateMove(Vector3 $vec){
		$this->x = $vec->x;
		$this->y = $vec->y;
		$this->z = $vec->z;
		foreach($this->hasSpawned as $player){
			$player->addEntityMovement($this->getId(), $this->x, $this->y, $this->z, $this->yaw, $this->pitch);
		}
	}

	public function getName(){
		return "MineCart";
	}

	public function spawnTo(Player $player){
		$pk = new AddEntityPacket();
		$pk->type = $this->type;
		$pk->eid = $this->id;
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		$pk->yaw = $this->yaw;
		$pk->pitch = $this->pitch;
		$pk->metadata = $this->getData();
		$player->dataPacket($pk);
		parent::spawnTo($player);
	}
}
class SetRidePacket extends DataPacket{

	public static $pool = [];

	public static $next = 0;

	public $a;

	public $b;

	public function pid(){
		return 0xa9;
	}

	public function decode(){}

	public function encode(){
		$this->reset();
		$this->putInt($this->a);
		$this->putInt($this->b);
	}
}