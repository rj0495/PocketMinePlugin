<?php

namespace MineBlock\Spawner;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\scheduler\CallbackTask;
use pocketmine\Player;
use pocketmine\item\Item;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\network\protocol\AddMobPacket;
use pocketmine\network\protocol\MovePlayerPacket;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\nbt\tag\Byte;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Float;
use pocketmine\nbt\tag\Int;
use pocketmine\math\AxisAlignedBB;

class Spawner extends PluginBase implements Listener{

	public function onEnable(){
		foreach([10, 11, 12] as $id)
			Block::$creative[] = [383, $id];
		$this->entity = [];
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, "onTick"]), 20);
		$this->loadYml();
	}

	public function onPlayerInteract(PlayerInteractEvent $event){
		$b = $event->getBlock();
		$i = $event->getItem();
		if($b->getID() == 52){
			$event->setCancelled();
			$p = $event->getPlayer();
			$ik = $this->isKorean();
			$d = $i->getDamage();
			if(!$p->hasPermission("spawner.add")){
				$p->sendMessage("[Spawner] " . ($ik ? "권한이 없습니다. " : "You don't have perminssion to add spawner"));
			}else{
				if(isset($this->sn[$pos = $this->getPos($b)])){
					$sid = $this->sn[$pos][0];
					$sc = $this->sn[$pos][1];
					if($i->getID() == 383){
						if($sid == $d){
							$this->sn[$pos][1] += $event->getFace() >= 2 ? 1 : ($sc <= 1 ? 0 : -1);
							if($event->getFace() < 2) $this->despawnSpawner($pos);
							$p->sendMessage("[Spawner] " . ($ik ? "스포너의 동물수를 변경하였습니다. 동물수 : " : "Spawner's animal count changed. Count : ") . "[$sc => " . $this->sn[$pos][1] . "]");
							$this->saveYml();
						}else{
							$this->delSpawner($pos);
							$this->addSpawner($pos, $d);
							$p->sendMessage("[Spawner] " . ($ik ? "스포너를 생성하였습니다. 동물 아이디 : " : "Spawner is created. Animal ID : ") . "$d, " . ($ik ? "동물수 : " : "Count : ") . $sc);
						}
					}else{
						$p->sendMessage("[Spawner] " . ($ik ? "스포너 정보. 동물 아이디 : " : "Spawner Info. Animal ID : ") . "$sid, " . ($ik ? "동물수 : " : "Count : ") . $sc);
					}
				}elseif($i->getID() == 383){
					$this->addSpawner($pos, $d);
					$p->sendMessage("[Spawner] " . ($ik ? "스포너를 생성하였습니다. 동물 아이디 : " : "Spawner is created. Animal ID : ") . "$d, " . ($ik ? "동물수 : 10" : "Count : 10"));
				}
			}
		}
	}

	public function onBlockBreak(BlockBreakEvent $event){
		$b = $event->getBlock();
		if($b->getID() !== 52 || !isset($this->sn[$pos = $this->getPos($b)])) return;
		$event->setCancelled();
		$ik = $this->isKorean();
		$p = $event->getPlayer();
		if($p->hasPermission("spawner.del")){
			$this->delSpawner($pos);
			$p->sendMessage("[Spawner] " . ($ik ? "스포너를 제거하였습니다. " : "Spawner is deleted. "));
		}else{
			$p->sendMessage("[Spawner] " . ($ik ? "권한이 없습니다. " : "You don't have perminssion to add spawner"));
		}
	}

	public function onTick(){
		foreach($this->entity as $k => $v){
			foreach($v as $vk => $e){
				if($e->dead || $e->closed) unset($this->entity[$k][$vk]);
			}
		}
		foreach($this->sn as $k => $v){
			if($v[0] < 10 || $v[0] > 12){
				unset($this->sn[$k]);
				continue;
			}
			if(!isset($this->entity[$k])) $this->entity[$k] = [];
			if(count($this->entity[$k]) >= $v[1] || rand(0, 2) !== 0) continue;
			$e = explode(":", $k);
			if($l = $this->getServer()->getLevelByName($e[3])){
				if($l->getBlock(new Vector3($e[0], $e[1], $e[2]))->getID() == 52){
					$d = true;
					$i = 0;
					while($d == true && $i <= 10){
						$i++;
						$d = false;
						$vec = new Vector3($e[0] + rand(-3, 3), $e[1], $e[2] + rand(-3, 3));
						foreach($this->entity[$k] as $en){
							if($en->distance($vec) <= 1.5){
								$d = true;
								break;
							}
						}
					}
				}else{
					unset($this->sn[$k]);
					continue;
				}
				$pos = $l->getSafeSpawn($vec);
				$this->entity[$k][] = new SpawnerAnimal($pos, $v[0]);
			}else{
				unset($this->sn[$k]);
				continue;
			}
		}
	}

	public function despawnSpawner($pos){
		if(isset($this->entity[$pos])){
			foreach($this->entity[$pos] as $e){
				$e->close();
			}
		}
	}

	public function addSpawner($pos, $id){
		if(!isset($this->sn[$pos])) $this->sn[$pos] = [$id, 10];
		$this->saveYml();
		return true;
	}

	public function delSpawner($pos){
		$this->despawnSpawner($pos);
		unset($this->sn[$pos]);
		$this->saveYml();
	}

	public function getPos($b){
		return $b->x . ":" . $b->y . ":" . $b->z . ":" . $b->getLevel()->getFolderName();
	}

	public function loadYml(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		$this->sn = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "Spawner.yml", Config::YAML))->getAll();
	}

	public function saveYml(){
		ksort($this->sn);
		$sn = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "Spawner.yml", Config::YAML);
		$sn->setAll($this->sn);
		$sn->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}
class SpawnerAnimal extends Living{

	public $nid = 10;

	public $width = 1;

	public $length = 1;

	public $height = 1;

	protected $gravity = 0.25;

	protected $speed = 0.125;

	public $canCollide = true;

	public $target;

	public function __construct(Vector3 $pos, $id){
		parent::__construct($pos->getLevel()->getChunk($pos->x >> 4, $pos->z >> 4), new Compound("", ["Pos" => new Enum("Pos", [new Double("", $pos->x + 0.5), new Double("", $pos->y), new Double("", $pos->z + 0.5)]), "Motion" => new Enum("Motion", [new Double("", 0), new Double("", 0), new Double("", 0)]), "Rotation" => new Enum("Rotation", [new Float("", rand(-100, 100)), new Float("", rand(-2, 2))])]));
		$this->target = null;
		$this->pos = $pos;
		$this->nid = $id;
		parent::spawnToAll();
		parent::setHealth($id == 10 ? 10 : 20);
		parent::setMaxHealth($id == 10 ? 10 : 20);
	}

	public function getName(){
		$typeTable = [10 => "Chicken", 11 => "Cow", 12 => "Pig"];
		return isset($typeTable[$this->nid]) ? $typeTable[$this->nid] : "SpawnerAnimal";
	}

	public function getData(){
		$flags = 0;
		$flags |= $this->fireTicks > 0 ? 1 : 0;
		return [0 => ["type" => 0, "value" => $flags], 1 => ["type" => 1, "value" => $this->airTicks], 16 => ["type" => 0, "value" => 0], 17 => ["type" => 6, "value" => [0, 0, 0]]];
	}

	public function getDrops(){
		$drops = [];
		if($this->closed) return $drops;
		switch($this->nid){
			case 10:
				$drops[] = Item::get($this->fireTicks > 0 ? 366 : 365, 0, 1);
				if(($r = rand(0, 2)) > 0) $drops[] = Item::get(288, 0, $r);
			break;
			case 11:
				$drops[] = Item::get($this->fireTicks > 0 ? 364 : 363, 0, 1);
				if(($r = rand(0, 2)) > 0) $drops[] = Item::get(334, 0, $r);
			break;
			case 12:
				$drops[] = Item::get($this->fireTicks > 0 ? 320 : 319, 0, 1);
			break;
		}
		return $drops;
	}

	protected function initEntity(){}

	public function saveNBT(){}

	public function canCollideWith(Entity $entity){
		return true;
	}

	public function onUpdate($currentTick){
		if($this->closed) return false;
		else $this->entityBaseTick();
		return true;
	}

	public function spawnTo(Player $player){
		$pk = new AddMobPacket();
		$pk->type = $this->nid;
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