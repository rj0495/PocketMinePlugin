<?php

namespace MineBlock\Gun;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\protocol\Info as ProtocolInfo;
use pocketmine\network\protocol\ExplodePacket;
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

class Gun extends PluginBase implements Listener{

	public function onEnable(){
		$this->cool = [];
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->loadYml();
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		if(!isset($sub[0])) return false;
		$g = $this->g;
		$rm = TextFormat::RED . "Usage: /Gun ";
		$mm = "[Gun]";
		$ik = $this->isKorean();
		switch(strtolower($sub[0])){
			case "gun":
			case "g":
			case "총":
			case "item":
			case "i":
			case "아이템":
			case "템":
				if(!isset($sub[1])){
					$r = $rm . ($ik ? "총 <아이템ID>" : "Gun(G) <ItemID>");
				}else{
					$i = Item::fromString($sub[1]);
					$id = $i->getID() . ":" . $i->getDamage();
					$g["Gun"] = $id;
					$r = $mm . ($ik ? "총을 $id 로 설정했습니다." : "Gun is set $id");
				}
			break;
			case "bullet":
			case "b":
			case "총알":
			case "탄알":
			case "useitem":
			case "ui":
			case "u":
			case "소모아이템":
			case "소모템":
				if(!isset($sub[1])){
					$r = $rm . ($ik ? "총알 <아이템ID> <갯수> <이름>" : $rm . "Bullet(B) <ItemID> <Amount> <Name>");
				}else{
					$i = Item::fromString($sub[1]);
					$cnt = 1;
					if(isset($sub[2]) && is_numeric($sub[2])) $cnt = $sub[2];
					$id = $i->getID() . ":" . $i->getDamage();
					if(!isset($sub[3]) || !$sub[3]) $sub[3] = $id;
					$g["Bullet"] = "$id % $cnt % $sub[3]";
					$r = $mm . ($ik ? "총알을 $sub[3] (Count: $cnt) 로 설정했습니다." : "Bullet is set $sub[3] (Count: $cnt)");
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
					$r = $mm . ($ik ? "낚시 쿨타임을 [$sub[1]] 로 설정했습니다." : "Gun cooltime is set [$sub[1]]");
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
		$m = ($ik ? "[총] " : "[Gun] ");
		$p = $event->getPlayer();
		$n = $p->getName();
		$i = $this->getItem($this->g["Gun"], 1);
		$ii = $p->getInventory()->getItemInHand();
		if($ii->getID() !== $i->getID() || $ii->getDamage() !== $i->getDamage()) return;
		if(!isset($this->cool[$n])) $this->cool[$n] = 0;
		$c = microtime(true) - $this->cool[$n];
		if($c < 0){
			$m .= $ik ? "쿨타임 :" . round(-$c, 1) . " 초" : "Cool : " . round($c * 100) / -100 . " sec";
		}elseif(($iv = $this->checkInven($p)) !== true){
			$m .= $ik ? "당신은" . $iv[0] . "(" . $iv[1] . "개) 를 가지고있지않습니다. : " . $iv[2] . "개" : "You Don't have " . $iv[0] . "($iv[1]) You have : " . $iv[2];
		}else{
			$c = explode("~", $this->g["Cool"]);
			$this->cool[$n] = microtime(true) + rand($c[0], isset($c[1]) ? $c[1] : $c[0]);
			$pk = new ExplodePacket();
			$pk->x = ($x = $p->x);
			$pk->y = ($y = $p->y + $p->getEyeHeight());
			$pk->z = ($z = $p->z);
			$pk->radius = 2;
			$pk->records = [];
			$this->getServer()->broadcastPacket($p->getLevel()->getUsingChunk($x >> 4, $z >> 4), $pk);
			$nbt = new Compound("", ["Pos" => new Enum("Pos", [new Double("", $p->x), new Double("", $p->y + $p->getEyeHeight() - 0.2), new Double("", $p->z)]), "Motion" => new Enum("Motion", [new Double("", -sin($p->getyaw() / 180 * M_PI) * cos($p->getPitch() / 180 * M_PI)), new Double("", -sin($p->getPitch() / 180 * M_PI)), new Double("", cos($p->getyaw() / 180 * M_PI) * cos($p->getPitch() / 180 * M_PI))]), "Rotation" => new Enum("Rotation", [new Float("", $p->getyaw()), new Float("", $p->getPitch())])]);
			$bullet = new Bullet($p->chunk, $nbt, $p);
			$bullet->spawnToAll();
			return;
		}
		if(isset($m)) $p->sendMessage($m);
		$event->setCancelled();
	}

	public function checkInven($p){
		$ui = explode(" % ", $this->g["Bullet"]);
		$i = $this->getItem($ui[0], $c = $ui[1]);
		$cnt = 0;
		$inv = $p->getInventory();
		foreach($inv->getContents() as $item){
			if($item->equals($i, true)) $cnt += $item->getCount();
			if($cnt >= $c) break;
		}
		if($cnt < $c){
			return [$ui[2], $c, $cnt];
		}else{
			$inv->removeItem($i);
			return true;
		}
	}

	public function getItem($id = 0, $cnt = 0){
		$id = explode(":", $id);
		return Item::get($id[0], isset($id[1]) ? $id[1] : 0, $cnt);
	}

	public function loadYml(){
		@mkdir($this->path = ($this->getServer()->getDataPath() . "/plugins/! MineBlock/"));
		$this->g = (new Config($this->file = $this->path . "Gun.yml", Config::YAML, ["Gun" => "104:0", "Bullet" => "175:0 % 1 % Bullet", "Cool" => "3"]))->getAll();
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
class Bullet extends Projectile{

	protected $gravity = 0;

	protected $drag = 0.01;

	public function __construct(FullChunk $chunk, Compound $nbt, Entity $shootingEntity = null){
		$this->shootingEntity = $shootingEntity;
		parent::__construct($chunk, $nbt);
	}

	protected function initEntity(){}

	public function saveNBT(){}

	public function canCollideWith(Entity $entity){
		return false;
	}

	public function onUpdate($currentTick){
		if($this->closed){return false;}
		$this->timings->startTiming();
		$hasUpdate = parent::onUpdate($currentTick);
		$bb = new AxisAlignedBB($x = $this->x - 0.5, $y = $this->y, $z = $this->z - 0.5, $x + 1, $y + 1, $z + 1);
		if($this->age > 100 || count($this->level->getCollisionBlocks($bb = $this->getBoundingBox())) > 0){
			$this->kill();
			$this->close();
			$hasUpdate = true;
		}else{
			$se = $this->shootingEntity;
			$list = $this->server->getOnlinePlayers();
			foreach($list as $p){
				if($p !== $se && $bb->intersectsWith($p->getBoundingBox())){
					$armorTable = [Item::LEATHER_CAP => 1, Item::LEATHER_TUNIC => 3, Item::LEATHER_PANTS => 2, Item::LEATHER_BOOTS => 1, Item::CHAIN_HELMET => 1, Item::CHAIN_CHESTPLATE => 5, Item::CHAIN_LEGGINGS => 4, Item::CHAIN_BOOTS => 1, Item::GOLD_HELMET => 1, Item::GOLD_CHESTPLATE => 5, Item::GOLD_LEGGINGS => 3, Item::GOLD_BOOTS => 1, Item::IRON_HELMET => 2, Item::IRON_CHESTPLATE => 6, Item::IRON_LEGGINGS => 5, Item::IRON_BOOTS => 2, Item::DIAMOND_HELMET => 3, Item::DIAMOND_CHESTPLATE => 8, Item::DIAMOND_LEGGINGS => 6, Item::DIAMOND_BOOTS => 3];
					$points = 0;
					foreach($p->getInventory()->getArmorContents() as $index => $armor){
						if(isset($armorTable[$armor->getID()])){
							$points += $armorTable[$armor->getID()];
						}
					}
					$p->knockBack($se, $finalDamage = 5 - floor(5 * $points * 0.04), sin($yaw = atan2($p->x - $se->x, $p->z - $se->z)), cos($yaw), 0.4);
					$p->attack($finalDamage);
					$this->kill();
					$this->close();
					$hasUpdate = true;
					break;
				}
			}
		}
		$this->timings->stopTiming();
		return $hasUpdate;
	}

	public function spawnTo(Player $player){}
}