<?php

namespace MineBlock\Grenade;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\entity\ExplosionPrimeEvent;
use pocketmine\network\protocol\AddItemEntityPacket;
use pocketmine\network\protocol\Info as ProtocolInfo;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\entity\Living;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Float;
use pocketmine\entity\Entity;
use pocketmine\entity\Projectile;
use pocketmine\level\format\FullChunk;
use pocketmine\level\Explosion;

class Grenade extends PluginBase implements Listener{

	public function onEnable(){
		$this->cool = [];
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->loadYml();
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		if(!isset($sub[0])) return false;
		$g = $this->g;
		$rm = TextFormat::RED . "Usage: /Grenade ";
		$mm = "[Grenade]";
		$ik = $this->isKorean();
		switch(strtolower($sub[0])){
			case "gun":
			case "g":
			case "수류탄":
			case "item":
			case "i":
			case "아이템":
			case "템":
				if(!isset($sub[1])){
					$r = $rm . ($ik ? "수류탄 <아이템ID>" : "Grenade(G) <ItemID>");
				}else{
					$i = Item::fromString($sub[1]);
					$id = $i->getID() . ":" . $i->getDamage();
					$g["Grenade"] = $id;
					$r = $mm . ($ik ? "수류탄을 $id 로 설정했습니다." : "Grenade is set $id");
				}
			break;
			case "cool":
			case "cooltime":
			case "ct":
			case "쿨타임":
			case "쿨":
				if(!isset($sub[1])){
					$r = $rm . ($ik ? "쿨타임 <시간>" : "CoolTime(CT) <Num>");
				}else{
					if($sub[1] < 0 || !is_numeric($sub[1])) $sub[1] = 0;
					if(isset($sub[2]) && $sub[2] > $sub[1] && is_numeric($sub[2]) !== false) $sub[1] = $sub[1] . "~" . $sub[2];
					$g["Cool"] = $sub[1];
					$r = $mm . ($ik ? "낚시 쿨타임을 [$sub[1]] 로 설정했습니다." : "Grenade cooltime is set [$sub[1]]");
				}
			break;
			default:
				return false;
			break;
		}
		if(isset($r)) $sender->sendMessage($r);
		if($this != $g){
			$this->g = $g;
			$this->saveYml();
		}
		return true;
	}

	public function onDataPacketReceive(DataPacketReceiveEvent $event){
		$pk = $event->getPacket();
		if($pk->pid() !== ProtocolInfo::USE_ITEM_PACKET || $pk->face !== 0xff) return false;
		$ik = $this->isKorean();
		$m = ($ik ? "[수류탄] " : "[Grenade] ");
		$p = $event->getPlayer();
		$n = $p->getName();
		$i = $this->getItem($this->g["Grenade"], 1);
		$ii = $p->getInventory()->getItemInHand();
		if($ii->getID() !== $i->getID() || $ii->getDamage() !== $i->getDamage()) return;
		if(!isset($this->cool[$n])) $this->cool[$n] = 0;
		$c = microtime(true) - $this->cool[$n];
		if($c < 0){
			$m .= $ik ? "쿨타임 :" . round(-$c, 1) . " 초" : "Cool : " . round($c * 100) / -100 . " sec";
		}else{
			$c = explode("~", $this->g["Cool"]);
			$this->cool[$n] = microtime(true) + rand($c[0], isset($c[1]) ? $c[1] : $c[0]);
			$p->getInventory()->removeItem($i);
			$this->getServer()->broadcastPacket($p->getLevel()->getUsingChunk($p->x >> 4, $p->z >> 4), $pk);
			$nbt = new Compound("", ["Pos" => new Enum("Pos", [new Double("", $p->x), new Double("", $p->y + $p->getEyeHeight() - 0.2), new Double("", $p->z)]), "Motion" => new Enum("Motion", [new Double("", -sin($p->getyaw() / 180 * M_PI) * cos($p->getPitch() / 180 * M_PI)), new Double("", -sin($p->getPitch() / 180 * M_PI)), new Double("", cos($p->getyaw() / 180 * M_PI) * cos($p->getPitch() / 180 * M_PI))]), "Rotation" => new Enum("Rotation", [new Float("", $p->getyaw()), new Float("", $p->getPitch())])]);
			$grenade = new GrenadeEntity($p->chunk, $nbt, $p, $i);
			$grenade->spawnToAll();
			return;
		}
		if(isset($m)) $p->sendMessage($m);
		$event->setCancelled();
	}

	public function getItem($id = 0, $cnt = 0){
		$id = explode(":", $id);
		return Item::get($id[0], isset($id[1]) ? $id[1] : 0, $cnt);
	}

	public function loadYml(){
		@mkdir($this->path = ($this->getServer()->getDataPath() . "/plugins/! MineBlock/"));
		$this->g = (new Config($this->file = $this->path . "Grenade.yml", Config::YAML, ["Grenade" => "341:0", "Cool" => "3"]))->getAll();
	}

	public function saveYml(){
		$g = new Config($this->file, Config::YAML);
		$g->setAll($this->g);
		$g->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}
class GrenadeEntity extends Projectile{
	const NETWORK_ID = 64;

	protected $gravity = 0.03;

	protected $drag = 0.01;

	public function __construct(FullChunk $chunk, Compound $nbt, Entity $shootingEntity = null, Item $item){
		$this->shootingEntity = $shootingEntity;
		$this->item = $item;
		parent::__construct($chunk, $nbt);
	}

	protected function initEntity(){}

	public function saveNBT(){}

	public function canCollideWith(Entity $entity){
		return false;
	}

	public function explode(){
		$this->server->getPluginManager()->callEvent($ev = new ExplosionPrimeEvent($this, 3));
		if(!$ev->isCancelled()){
			$explosion = new Explosion($this, $ev->getForce(), $this);
			if($ev->isBlockBreaking()){
				$explosion->explodeA();
			}
			$explosion->explodeB();
		}
	}

	public function onUpdate($currentTick){
		if($this->closed){return false;}
		$this->timings->startTiming();
		$hasUpdate = parent::onUpdate($currentTick);
		$bb = new AxisAlignedBB($x = $this->x - 0.5, $y = $this->y, $z = $this->z - 0.5, $x + 1, $y + 1, $z + 1);
		if($this->age > 100){
			$this->kill();
			$this->explode();
			$this->close();
			$hasUpdate = true;
		}elseif(count($this->level->getCollisionBlocks($bb = $this->getBoundingBox())) > 0){
			if($this->motionX < 0) $this->motionX = -$this->motionX * 0.5;
			if($this->motionY < 0) $this->motionY = -$this->motionY * 0.1;
			if($this->motionZ < 0) $this->motionZ = -$this->motionZ * 0.5;
		}
		$this->timings->stopTiming();
		return $hasUpdate;
	}

	public function spawnTo(Player $player){
		$pk = new AddItemEntityPacket();
		$pk->eid = $this->id;
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		$pk->yaw = $this->yaw;
		$pk->pitch = $this->pitch;
		$pk->roll = 0;
		$pk->item = $this->item;
		$player->dataPacket($pk);
		parent::spawnTo($player);
	}
}