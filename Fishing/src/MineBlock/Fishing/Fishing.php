<?php

namespace MineBlock\Fishing;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\CallbackTask;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\AddItemEntityPacket;
use pocketmine\network\protocol\Info as ProtocolInfo;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\entity\Entity;
use pocketmine\nbt\tag\Byte;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Float;
use pocketmine\nbt\tag\Int;
use pocketmine\math\AxisAlignedBB;

class Fishing extends PluginBase implements Listener{

	public function onEnable(){
		$this->fish = [];
		$this->cool = [];
		$this->bait = [];
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->loadYml();
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		if(!isset($sub[0])) return false;
		$fs = $this->fs;
		$set = $fs["Set"];
		$fish = $fs["Fish"];
		$rm = TextFormat::RED . "Usage: /Fishing ";
		$mm = "[Fishing]";
		$ik = $this->isKorean();
		switch(strtolower($sub[0])){
			case "fishing":
			case "fs":
			case "on":
			case "off":
			case "낚시":
			case "온":
			case "오프":
				if($set["Fishing"] == "On"){
					$set["Fishing"] = "Off";
					$r = $mm . ($ik ? "낚시를 끕니다.": "Fising is Off");
				}else{
					$set["Fishing"] = "On";
					$r = $mm . ($ik ? "낚시를 켭니다.": "Fising is On");
				}
			break;
			case "rod":
			case "r":
			case "낚시대":
			case "item":
			case "i":
			case "아이템":
			case "템":
			case "낚시대":
				if(!isset($sub[1])){
					$r = $rm . ($ik ? "낚시대 <아이템ID>": "Rod(R) <ItemID>");
				}else{
					$i = Item::fromString($sub[1]);
					$id = $i->getID() . ":" . $i->getDamage();
					$set["Rod"] = $id;
					$r = $mm . ($ik ? "낚시대를 $id 로 설정했습니다.": "Fishing Rod is set $id");
				}
			break;
			case "bait":
			case "b":
			case "미끼":
			case "useitem":
			case "ui":
			case "u":
			case "소모아이템":
			case "소모템":
				if(!isset($sub[1])){
					$r = $rm . ($ik ? "미끼 <아이템ID> <갯수> <이름>": $rm . "Bait(B) <ItemID> <Amount> <Name>");
				}else{
					$i = Item::fromString($sub[1]);
					$cnt = 1;
					if(isset($sub[2]) && is_numeric($sub[2])) $cnt = $sub[2];
					$id = $i->getID().":".$i->getDamage();
					if(!isset($sub[3]) || !$sub[3]) $sub[3] = $id;
					$set["Bait"] = "$id % $cnt % $sub[3]";
					$r = $mm . ($ik ? "미끼를 $sub[3] (Count: $cnt) 로 설정했습니다.": "Fishing Bait is set $sub[3] (Count: $cnt)");
				}
			break;
			case "delay":
			case "d":
			case "time":
			case "t":
			case "딜레이":
			case "시간":
			case "타임":
				if(!isset($sub[1])){
					$r = $rm . ($ik ? "딜레이 <시간>": "Delay(D) <Num>");
				}else{
					if($sub[1] < 0 || !is_numeric($sub[1])) $sub[1] = 0;
					if(isset($sub[2]) && $sub[2] > $sub[1] && is_numeric($sub[2]) !== false) $sub[1] = $sub[1] . "~" . $sub[2];
					$set["Delay"] = $sub[1];
					$r = $mm . ($ik ? "낚시 딜레이를 [$sub[1]] 로 설정했습니다.": "Fishing Delay is set [$sub[1]]");
				}
			break;
			case "cool":
			case "cooltime":
			case "ct":
			case "쿨타임":
			case "쿨":
				if(!isset($sub[1])){
					$r = $rm . ($ik ? "쿨타임 <시간>": "CoolTime(CT) <Num>");
				}else{
					if($sub[1] < 0 || !is_numeric($sub[1])) $sub[1] = 0;
					if(isset($sub[2]) && $sub[2] > $sub[1] && is_numeric($sub[2]) !== false) $sub[1] = $sub[1] . "~" . $sub[2];
					$set["Cool"] = $sub[1];
					$r = $mm . ($ik ? "낚시 쿨타임을 [$sub[1]] 로 설정했습니다.": "Fishing cooltime is set [$sub[1]]");
				}
			break;
			case "count":
			case "c":
			case "횟수":
			case "갯수":
				if(!isset($sub[1])){
					$r = $rm . ($ik ? "횟수 <횟수>": "Count(C) <Num>");
				}else{
					if($sub[1] < 1 || !is_numeric($sub[1])) $sub[1] = 1;
					if(isset($sub[2]) && $sub[2] > $sub[1] && is_numeric($sub[2])) $sub[1] = $sub[1] . "~" . $sub[2];
					$set["Count"] = $sub[1];
					$r = $mm . ($ik ? "물고기획득 횟수를 [$sub[1]] 로 설정했습니다.": "Get Fish count is set [$sub[1]]");
				}
			break;
			case "fishs":
			case "fish":
			case "f":
			case "물고기":
			case "피쉬":
				if(!isset($sub[1])){
					$r = $rm . ($ik ? "물고기 <추가|삭제|리셋|목록>": "Fishs(F) <Add|Del|Reset|List>");
				}else{
					switch(strtolower($sub[1])){
						case "add":
						case "a":
						case "추가":
							if(!isset($sub[2]) || !isset($sub[3])){
								$r = $rm . ($ik ? "물고기 추가 <아이템ID> <확률> <갯수1> <갯수2> <이름>": "Fishs(F) Add(A) <ItemID> <Petsent> <Count1> <Count2> <Name>");
							}else{
								$i = Item::fromString($sub[2]);
								if($sub[3] < 1 || !is_numeric($sub[3])) $sub[3] = 1;
								if(!isset($sub[4]) < 0 || !is_numeric($sub[4])) $sub[4] = 0;
								if(isset($sub[5]) && $sub[5] > $sub[4] && is_numeric($sub[5])) $sub[4] = $sub[4] . "~" . $sub[5];
								$id = $i->getID().":".$i->getDamage();
								if(!isset($sub[6]) || !$sub[6]) $sub[6] = $id;
								$fish[] = $sub[3]." % ".$i->getID() . ":" . $i->getDamage()." % $sub[4] % $sub[6]";
								$r = $mm . ($ik ? "물고기 추가됨 [" . $i->getID() . ":" . $i->getDamage() . " 갯수:$sub[4] 확률:$sub[3]]": "Fish add [" . $i->getID() . ":" . $i->getDamage() . " Count:$sub[4] Persent:$sub[3]]");
							}
						break;
						case "del":
						case "d":
						case "삭제":
						case "제거":
							if(!isset($sub[2])){
								$r = $rm . ($ik ? "물고기 삭제 <번호>": "Fishs(F) Del(D) <FishNum>");
							}else{
								if($sub[2] < 0 || !is_numeric($sub[2])) $sub[2] = 0;
								if(!isset($fish[$sub[2] - 1])){
									$r = $mm . ($ik ? "[$sub[2]] 는 존재하지않습니다. \n  " . $rm . "물고기 목록 ": "[$sub[2]] does not exist.\n  " . $rm . "Fish(F) List(L)");
								}else{
									$d = $fish[$sub[2] - 1];
									unset($fish[$sub[2] - 1]);
									$r = $mm . ($ik ? "물고기 제거됨 [" . $d["ID"] . ":" . $i->getDamage() . " 갯수:" . $d["Count"] . " 확률:" . $d["Percent"] . "]": "Fish del [" . $d["ID"] . ":" . $i->getDamage() . " Count:" . $d["Count"] . " Persent:" . $d["Percent"] . "]");
								}
							}
						break;
						case "reset":
						case "r":
						case "리셋":
						case "초기화":
							$fish = [];
							$r = $mm . ($ik ? "물고기 목록을 초기화합니다.": "Fish list is Reset");
						break;
						case "list":
						case "l":
						case "목록":
						case "리스트":
							$page = 1;
							if(isset($sub[2]) && is_numeric($sub[2])) $page = round($sub[2]);
							$list = array_chunk($fish, 5, true);
							if($page >= ($c = count($list))) $page = $c;
							$r = $mm . ($ik ? "물고기 목록 (페이지": "Fish List (Page") . " $page/$c) \n";
							$num = ($page - 1) * 5;
							foreach($list[$page - 1] as $k => $v){
								$num++;
								$info = explode(" % ", $v);
								$r .= ($ik ? "  [$num] [$info[3]] 아이디: $info[1] 갯수: $info[2] 확률: $info[0] \n": "  [$num] [$info[3]] ID: $info[1] Count: $info[2] Percent: $info[0] \n");
							}
						break;
						default:
							return false;
						break;
					}
				}
			break;
			default:
				return false;
		}
		if(isset($r)) $sender->sendMessage($r);
		if($fs["Set"] !== $set || $fs["Fish"] !== $fish){
			$this->fs = ["Set" => $set, "Fish" => $fish];
			$this->saveYml();
		}
		return true;
	}

	public function onDataPacketReceive(DataPacketReceiveEvent $event){
		$pk = $event->getPacket();
		if($pk->pid() !== ProtocolInfo::USE_ITEM_PACKET || $pk->face !== 0xff) return false;
		if($this->fs["Set"]["Fishing"] == "Off") return;
		$ik = $this->isKorean();
		$m = ($ik ? "[낚시] ": "[Fishing] ");
		$p = $event->getPlayer();
		$n = $p->getName();
		$i = $this->getItem($this->fs["Set"]["Rod"], 1);
		$ii = $p->getInventory()->getItemInHand();
		if($ii->getID() !== $i->getID() || $ii->getDamage() !== $i->getDamage()) return;
		if(!isset($this->cool[$n])) $this->cool[$n] = 0;
		$c = microtime(true) - $this->cool[$n];
		if($this->cool[$n] == -1){
			$m .= $ik ? "이미 낚시를 시작했습니다. 기다려주세요.": "Already started fishing. Please wait.";
		}elseif($c < 0){
			$m .= $ik ? "쿨타임 :" . round(-$c, 1) . " 초": "Cool : " . round($c * 100) / -100 . " sec";
		}elseif($this->checkWater($p) !== true){
			$m .= $ik ? "물을 향해서 던져주세요.": "Thare is not water";
		}elseif(($iv = $this->checkInven($p)) !== true){
			$m .= $ik ? "당신은 " . $iv[0] . "(" . $iv[1] . "개) 를 가지고있지않습니다. : " . $iv[2] . "개": "You Don't have " . $iv[0] . "($iv[1]) You have : " . $iv[2];
		}else{
			$this->fishStart($p);
			unset($m);
		}
		if(isset($m)) $p->sendMessage($m);
		$event->setCancelled();
	}

	public function fishStart($p){
		$p->sendMessage($this->isKorean() ? "[낚시] 낚시를 시작했습니다. 기다려주세요.": "[Fishing] Started fishing. Please wait.");
		$this->cool[$n = $p->getName()] = -1;
		$ui = explode(" % ", $this->fs["Set"]["Bait"]);
		$this->bait[$n] = new Bait($p, $this->getItem($ui[0], $ui[1]));
		$t = explode("~", $this->fs["Set"]["Delay"]);
		$this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this,"fishGive"], [$p]), rand($t[0], isset($t[1]) ? $t[1] : $t[0]) * 20);
	}

	public function fishGive($p){
		$c = explode("~", $this->fs["Set"]["Cool"]);
		$this->cool[$n = $p->getName()] = microtime(true) + rand($c[0], isset($c[1]) ? $c[1] : $c[0]);
		$cnt = explode("~", $this->fs["Set"]["Count"]);
		if($this->bait[$n] instanceof Bait){
			$this->bait[$n]->close();
		}
		unset($this->bait[$n]);
		$fishs = $this->fishs;
		for($for = 0; $for < rand($cnt[0], isset($cnt[1]) ? $cnt[1] : $cnt[0]); $for++){
			shuffle($fishs);
			$d = $fishs[0];
			$fc = explode("~", $d[2]);
			new Fish($p, $this->bait[$n]->add(0, 0.5, 0), $i = $this->getItem($d[1], rand($fc[0], isset($fc[1]) ? $fc[1] : $fc[0])));
			$p->sendMessage($this->isKorean() ? "[낚시] ".$d[3]." (갯수: " . $i->getCount() . ") 개를 얻었습니다.": "[Fishing] You get ".$d[3]." (count:" . $i->getCount() . ")");
		}
	}

	public function checkInven($p){
		$ui = explode(" % ", $this->fs["Set"]["Bait"]);
		$i = $this->getItem($ui[0], $c = $ui[1]);
		$cnt = 0;
		$inv = $p->getInventory();
		foreach($inv->getContents() as $item){
			if($item->equals($i, true)) $cnt += $item->getCount();
			if($cnt >= $c) break;
		}
		if($cnt < $c){
			return [$ui[2],$c,$cnt];
		}else{
			$inv->removeItem($i);
			return true;
		}
	}

	public function getItem($id = 0, $cnt = 0){
		$id = explode(":", $id);
		return Item::get($id[0], isset($id[1]) ? $id[1] : 0, $cnt);
	}

	public function checkWater($p){
		$yaw = $p->getYaw();
		$pitch = $p->getPitch();
		$yawS = -sin($yaw / 180 * M_PI);
		$yawC = cos($yaw / 180 * M_PI);
		$pitchS = -sin($pitch / 180 * M_PI);
		$pitchC = cos($pitch / 180 * M_PI);
		$x = $p->x;
		$y = $p->y + $p->getEyeHeight();
		$z = $p->z;
		$l = $p->getLevel();
		$ps = $this->getServer()->getOnlinePlayers();
		for($f = 0; $f < 5; ++$f){
			$x += $yawS * $pitchC;
			$y += $pitchS;
			$z += $yawC * $pitchC;
			$b = $l->getBlock(new Vector3($x, $y, $z));
			if($b->getID() == 8 || $b->getID() == 9) return true;
		}
		return false;
	}

	public function loadYml(){
		@mkdir($this->path = ($this->getServer()->getDataPath() . "/plugins/! MineBlock/"));
		$this->fs = (new Config($this->file = $this->path . "Fishing.yml", Config::YAML, [
			"Set" => [
				"Fishing" => "On",
				"Rod" => "280:0",
				"Bait" => "365:0 % 1 % Chiken",
				"Delay" => "3~5",
				"Cool" => "3",
				"Count" => "1~2"
			],
			"Fish" => is_file($this->file) ? [] : [
				"50 % 4:0 % 1 % Stone",
				"50 % 0:0 % 0 % Air",
				"30 % 365:0 % 1 % Chiken",
				"30 % 263:0 % 1~3 % Coal",
				"30 % 260:0 % 1 % Apple",
				"30 % 262:0 % 1~3 % Arrow",
				"30 % 297:0 % 1~2 % Bread",
			]
		]))->getAll();
		$this->fishs = [];
		foreach($this->fs["Fish"] as $fish){
			$info = explode(" % ", $fish);
			for($for = 0; $for < $info[0]; $for++) $this->fishs[] = $info;
		}
	}

	public function saveYml(){
		$fs = new Config($this->file, Config::YAML);
		$fs->setAll($this->fs);
		$fs->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}

class Bait extends Entity{
	const NETWORK_ID = 64;

	public $width = 0.25;
	public $length = 0.25;
	public $height = 0.25;
	public $stepHeight = 0;

	protected $gravity = 0.25;
	protected $speed = 0.125;

	protected $item;

	public $canCollide = false;

	public function __construct(Player $player, Item $item){
		parent::__construct($player->getLevel()->getChunk($player->x >> 4, $player->z >> 4), new Compound("", [
			"Pos" => new Enum("Pos", [
				new Double("", $player->x),
				new Double("", $player->y + $player->getEyeHeight()),
				new Double("", $player->z),
			]),
			"Motion" => new Enum("Motion", [
				new Double("", 0),
				new Double("", 0),
				new Double("", 0),
			]),
			"Rotation" => new Enum("Rotation", [
				new Float("", 0),
				new Float("", 0),
			]),
		]));
		$this->item = $item->getID() == 0 ? Item::get(1000, 0, 0) : $item; 
		$this->player = $player;
		$this->yaw = $player->getYaw();
		$this->pitch = $player->getPitch();
		parent::spawnToAll();
 	}

	protected function initEntity(){
	}

	public function saveNBT(){
	}

	public function getBoundingBox(){
		$this->boundingBox = new AxisAlignedBB(
			$x = $this->x - $this->width / 2,
			$y = $this->y - $this->stepHeight,
			$z = $this->z - $this->length / 2,
			$x + $this->width,
			$y + $this->height,
			$z + $this->length
		);
		return $this->boundingBox;
	}

	public function updateMove(Vector3 $vec){
		$this->x = $vec->x;
		$this->y = $vec->y;
		$this->z = $vec->z;
		foreach($this->hasSpawned as $player){
			$player->addEntityMovement($this->getId(), $this->x, $this->y - (($id = $this->level->getBlock($this)->getID()) == 8 || $id == 9 ? 0.4 : 0), $this->z, 0, 0);
		}
	}

	public function onUpdate($currentTick){
		if($this->closed == true || $this->dead == true){
			if(!$this->closed) parent::close();
			$this->dead = true;
			return false;
		}
		$p = $this->player;
		$l = $p->getLevel();
		$b = $l->getBlock(new Vector3($this->x, $this->y, $this->z));
		if($b->getID() == 8 || $b->getID() == 9) $this->updateMove($b);
		else{
			$yaw = $this->yaw;
			$pitch = $this->pitch;
			$yawS = -sin($yaw / 180 * M_PI);
			$yawC = cos($yaw / 180 * M_PI);
			$pitchS = -sin($pitch / 180 * M_PI);
			$pitchC = cos($pitch / 180 * M_PI);
			$x = $this->x + ($yawS * $pitchC / 3);
			$y = $this->y + ($pitchS / 3);
			$z = $this->z + ($yawC * $pitchC / 3);
			$this->updateMove(new Vector3($x, $y, $z));
		}
		return true;
	}

	public function attack($damage, $source = EntityDamageEvent::CAUSE_MAGIC){
	}

	public function heal($amount, $source = EntityRegainHealthEvent::CAUSE_MAGIC){
	}

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

class Fish extends Entity{
	const NETWORK_ID = 64;

	public $width = 0.25;
	public $length = 0.25;
	public $height = 0.25;
	public $stepHeight = 0;

	protected $gravity = 0.25;
	protected $speed = 0.125;

	protected $item;

	public $canCollide = false;

	public function __construct(Player $player, Vector3 $bait, Item $item){
		parent::__construct($player->getLevel()->getChunk($player->x >> 4, $player->z >> 4), new Compound("", [
			"Pos" => new Enum("Pos", [
				new Double("", $bait->x),
				new Double("", $bait->y),
				new Double("", $bait->z),
			]),
			"Motion" => new Enum("Motion", [
				new Double("", 0),
				new Double("", 0),
				new Double("", 0),
			]),
			"Rotation" => new Enum("Rotation", [
				new Float("", 0),
				new Float("", 0),
			]),
		]));
		$this->item = $item->getID() == 0 ? Item::get(1000, 0, 0) : $item;
		$this->player = $player;
		parent::spawnToAll();
 	}

	protected function initEntity(){
	}

	public function saveNBT(){
	}

	public function getBoundingBox(){
		$this->boundingBox = new AxisAlignedBB(
			$x = $this->x - $this->width / 2,
			$y = $this->y - $this->stepHeight,
			$z = $this->z - $this->length / 2,
			$x + $this->width,
			$y + $this->height,
			$z + $this->length
		);
		return $this->boundingBox;
	}

	public function updateMove(Vector3 $vec){
		$this->x = $vec->x;
		$this->y = $vec->y;
		$this->z = $vec->z;
		foreach($this->hasSpawned as $player){
			$player->addEntityMovement($this->getId(), $this->x, $this->y - (($id = $this->level->getBlock($this)->getID()) == 8 || $id == 9 ? 0.4 : 0), $this->z, 0, 0);
		}
	}

	public function onUpdate($currentTick){
		if($this->closed == true || $this->dead == true){
			if(!$this->closed) parent::close();
			$this->dead = true;
			return false;
		}
		$p = $this->player;
		$x = cos($at2 = atan2($p->z - $this->z, $p->x - $this->x)) * 0.3;
		$z = sin($at2) * 0.3;
		$v = $p->add(0, $p->getEyeHeight(), 0); 		
		$this->updateMove($this->add($x, ($v->y - $this->y) / 5, $z));
		if($v->distance($this) <= 0.5){
			$p->getInventory()->addItem($this->item);
			$this->close();
		}
		return true;
	}

	public function attack($damage, $source = EntityDamageEvent::CAUSE_MAGIC){
	}

	public function heal($amount, $source = EntityRegainHealthEvent::CAUSE_MAGIC){
	}

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