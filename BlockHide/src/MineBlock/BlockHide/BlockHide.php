<?php

namespace MineBlock\BlockHide;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\CallbackTask;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\Config;
use pocketmine\level\Position;
use pocketmine\item\Item;
use pocketmine\block\Block;
use pocketmine\Player;
use pocketmine\entity\Human;
use pocketmine\inventory\PlayerInventory;
use pocketmine\network\protocol\MovePlayerPacket;
use pocketmine\nbt\tag\Byte;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Float;
use pocketmine\nbt\tag\Int;

class BlockHide extends PluginBase implements Listener{
	public $set, $back;

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->loadYml();
		$this->set = ["Start" => 0, "Tagger" => false];
		$this->touch = [];
		$this->back = [];
		$this->damageTable = [Item::WOODEN_SWORD => 4, Item::GOLD_SWORD => 4, Item::STONE_SWORD => 5, Item::IRON_SWORD => 6, Item::DIAMOND_SWORD => 7, Item::WOODEN_AXE => 3, Item::GOLD_AXE => 3, Item::STONE_AXE => 3, Item::IRON_AXE => 5, Item::DIAMOND_AXE => 6, Item::WOODEN_PICKAXE => 2, Item::GOLD_PICKAXE => 2, Item::STONE_PICKAXE => 3, Item::IRON_PICKAXE => 4, Item::DIAMOND_PICKAXE => 5, Item::WOODEN_SHOVEL => 1, Item::GOLD_SHOVEL => 1, Item::STONE_SHOVEL => 2, Item::IRON_SHOVEL => 3, Item::DIAMOND_SHOVEL => 4];
		$this->armorTable = [Item::LEATHER_CAP => 1, Item::LEATHER_TUNIC => 3, Item::LEATHER_PANTS => 2, Item::LEATHER_BOOTS => 1, Item::CHAIN_HELMET => 1, Item::CHAIN_CHESTPLATE => 5, Item::CHAIN_LEGGINGS => 4, Item::CHAIN_BOOTS => 1, Item::GOLD_HELMET => 1, Item::GOLD_CHESTPLATE => 5, Item::GOLD_LEGGINGS => 3, Item::GOLD_BOOTS => 1, Item::IRON_HELMET => 2, Item::IRON_CHESTPLATE => 6, Item::IRON_LEGGINGS => 5, Item::IRON_BOOTS => 2, Item::DIAMOND_HELMET => 3, Item::DIAMOND_CHESTPLATE => 8, Item::DIAMOND_LEGGINGS => 6, Item::DIAMOND_BOOTS => 3];
		$this->armorType = [[], [], [], []];
		$type = 0;
		foreach($this->armorTable as $k => $v){
			if($type >= 4) $type = 0;
			$this->armorType[$type][] = $k;
			$type++;
		}
		$this->player = [];
	}

	public function onDisable(){
		foreach($this->getServer()->getOnlinePlayers() as $p)
			$this->backupPlayer($p);
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		if(!isset($sub[0])) return false;
		$rm = TextFormat::RED . "Usage: /BloclHide ";
		$mm = "[BlockHide] ";
		$ik = $this->isKorean();
		$bh = $this->bh;
		switch(strtolower($sub[0])){
			case "start":
			case "s":
			case "시작":
				$ps = $this->getServer()->getOnlinePlayers();
				if($this->set["Start"] !== 0){
					$r = $mm . ($ik ? "게임이 이미 시작되었습니다" : "Game has already started");
				}elseif(count($ps) < 2){
					$r = $mm . ($ik ? "플레이어가 너무 적습니다." : "Too few players.");
				}else{
					$this->set = ["Start" => 0, "Tagger" => $ps[array_rand($ps)]];
					$this->gameWait();
					$r = $mm . ($ik ? "게임을 시작합니다." : "Game Start");
				}
			break;
			case "stop":
			case "st":
			case "중지":
			case "종료":
				if($this->set["Start"] == 0){
					$r = $mm . ($ik ? "게임이 시작되지 않앗습니다." : "Game has not started yet");
				}else{
					$this->gameStop(0);
				}
			break;
			case "time":
			case "t":
			case "시간":
				if(!isset($sub[1]) || !is_numeric($sub[1]) || is_numeric($sub[0]) && $sub[0] <= 1){
					$r = $rm . ($ik ? "시간 <시간>" : "Time <Time>");
				}else{
					$bh["Time"] = floor($sub[1]);
					$r = $mm . ($ik ? "게임 시간이 " . floor($sub[1]) . "초로 설정되엇습니다." : "Game time is set to " . floor($sub[1]));
				}
			break;
			default:
				return false;
			break;
		}
		if(isset($r)) $sender->sendMessage($r);
		if($this->bh !== $bh){
			$this->bh = $bh;
			$this->saveYml();
		}
		return true;
	}

	public function onBlockBreak(BlockBreakEvent $event){
		$this->blockEvent($event);
	}

	public function onBlockPlace(BlockPlaceEvent $event){
		$this->blockEvent($event);
	}

	public function onPlayerInteract(PlayerInteractEvent $event){
		if($event->getFace() !== 255) $this->blockEvent($event);
	}

	public function onPlayerDropItem(PlayerDropItemEvent $event){
		if($this->set["Start"] !== 0) $event->setCancelled();
	}

	public function onPlayerRespawn(PlayerRespawnEvent $event){
		if($this->set["Start"] !== 0){
			$event->setRespawnPosition($this->set["Pos"]);
		}
		$this->player[$event->getPlayer()->getName()] = new Player4NameTag($event->getPlayer(), $this);
	}

	public function onPlayerJoin(PlayerJoinEvent $event){
		if($this->set["Start"] !== 0){
			$p = $event->getPlayer();
			$this->back[$n = $p->getName()] = [];
			$back = $this->back[$n];
			$back["Player"] = $p;
			$back["Pos"] = $p->getPosition();
			$p->teleport($this->set["Pos"]);
			$back["MHP"] = $p->getMaxHealth();
			$p->setMaxHealth(20);
			$back["HP"] = $p->getHealth();
			$p->setHealth(20);
			$back["Inv"] = $p->getInventory()->getContents();
			$back["Arm"] = $p->getInventory()->getArmorContents();
			$p->getInventory()->setContents([]);
			$p->getInventory()->setArmorContents([]);
			$p->getInventory()->sendArmorContents($this->getServer()->getOnlinePlayers());
			$back["Life"] = false;
			$back["Hide"] = false;
			$back["Block"] = false;
			$this->back[$n] = $back;
			$p->sendMessage("[BlockHide] " . ($this->isKorean() ? "이미 게임이 시작되엇습니다. 당신은 게임오버입니다." : "Game is already started. You are game over"));
		}
	}

	public function onPlayerQuit(PlayerQuitEvent $event){
		if($this->set["Start"] !== 0){
			$this->backupPlayer($p = $event->getPlayer());
			if($this->set["Tagger"] instanceof Player && $this->set["Tagger"]->getName() == $p->getName()) $this->gameStop($this->isKorean() ? "술래 퇴장" : "Tagger Quit");
			else{
				$cnt = 0;
				foreach($this->back as $back)
					if($back["Life"]) $cnt++;
				if($cnt < 2) $this->gameStop($this->isKorean() ? "플레이어 퇴장" : "Player Quit");
				else $this->showPlayer($p);
			}
		}
		$this->player[$event->getPlayer()->getName()]->close();
	}

	public function onPlayerDeath(PlayerDeathEvent $event){
		if($this->set["Start"] !== 0){
			$event->setDeathMessage("");
			$event->setKeepInventory(true);
			$p = $event->getEntity();
			$back = $this->back[$n = $p->getName()];
			if($back["Hide"]) $this->showPlayer($p);
			$back["Hide"] = false;
			if(($b = $back["Block"]) instanceof Block) $b->getLevel()->setBlock($b, $b);
			$back["Block"] = false;
			$back["Life"] = false;
			$this->back[$n] = $back;
			if($this->set["Tagger"]->getName() == $p->getName()) $this->gameStop($this->isKorean() ? "술래 사망" : "Tagger Dead");
			else{
				$cnt = 0;
				foreach($this->back as $back)
					if($back["Life"]) $cnt++;
				$this->getServer()->broadCastMessage("[BlockHide] " . $p->getName() . ($this->isKorean() ? "님이 탈락하셨습니다. 플레이어 : " : " is Game Over. Players : ") . $cnt);
				if($cnt < 2) $this->gameStop($this->isKorean() ? "술래 승리" : "Tagger Win");
				else{
					$this->hidePlayer($p);
					$this->back[$n]["Hide"] = true;
					$p->setHealth(20);
					$p->getInventory()->clearAll();
				}
			}
		}
	}

	public function onPlayerMove(PlayerMoveEvent $event){
		$p = $event->getPlayer();
		if($this->set["Start"] > 0){
			if($this->set["Start"] == 1){
				if(!$this->back[$n = $p->getName()]["Life"]){
					if(!$this->back[$n]["Hide"]) $this->hidePlayer($p);
					if($p->dead && $p->getHealth() >= 1) $p->dead = false;
				}elseif($this->set["Tagger"]->getName() == $p->getName()){
					$event->setCancelled();
				}
			}
			if($this->set["Tagger"]->getName() != $p->getName()){
				$p->onGround = true;
				$back = $this->back[$n = $p->getName()];
				if($back["Hide"] && $back["Block"] instanceof Block && $back["Block"]->distance($p->floor()) > 0.5){
					$this->showPlayer($p);
					$back["Hide"] = false;
					$hb = $back["Block"];
					$hb->getLevel()->setBlock($hb, $hb);
					$back["Block"] = false;
					$back["Player"]->sendMessage("[BlockHide] " . ($this->isKorean() ? "숨기 풀림" : "Hide cancelled"));
				}
				$map = [[105, 120, 120, 135], [120, 135, 105, 120], [135, 150, 120, 135], [120, 135, 135, 150]][$this->set["Map"]];
				$to = $event->getTo();
				if($to->x < $map[0] || $to->x > $map[1] + 1 || $to->z < $map[2] || $to->z > $map[3] + 1){
					$event->setCancelled();
				}
				$this->back[$n] = $back;
			}
		}
	}

	public function blockEvent($event){
		$b = $event->getBlock();
		$event->setCancelled();
		if($this->bh["Protect"] && !$this->set["Start"]) $event->setCancelled();
		$p = $event->getPlayer();
		$n = $p->getName();
		$ik = $this->isKorean();
		if($this->set["Start"] !== 0 && !$this->back[$n]["Life"]){
			$event->setCancelled();
			if(!$event instanceof PlayerInteractEvent) $p->sendMessage("[BlockHide] " . ($ik ? "당신은 게임 오버입니다." : "You are is game over"));
		}elseif($event instanceof PlayerInteractEvent){
			if(isset($this->touch[$n])){
				$this->bh[$this->touch[$n] . "Pos"] = floor($b->x) . ":" . floor($b->y) . ":" . floor($b->z);
				$this->saveYml();
				$p->sendMessage("[BlockHide] " . ($ik ? "시작 지점을 설정햇습니다. " : "Start point is set to ") . $this->bh[$this->touch[$n] . "Pos"]);
				unset($this->touch[$n]);
				$event->setCancelled();
				if($event->getItem()->isPlaceable()) $this->place[$n] = true;
			}elseif($this->set["Start"] !== 0){
				if($event->getItem()->getID() !== 332) $event->setCancelled();
				if($this->set["Tagger"]->getName() == $p->getName() && !$this->back[$n]["Hide"]){
					$attack = true;
					foreach($this->back as $n => $back){
						if($back["Hide"] && $back["Block"] instanceof Block && $back["Block"]->distance($b) <= 0.5){
							$this->showPlayer($p);
							$back["Hide"] = false;
							$hb = $back["Block"];
							$hb->getLevel()->setBlock($hb, $hb);
							$back["Block"] = false;
							$d = $back["Player"];
							$i = $event->getItem();
							$damage = isset($this->damageTable[$i->getID()]) ? $this->damageTable[$i->getID()] : 2;
							$points = 0;
							foreach($d->getInventory()->getArmorContents() as $index => $armor){
								if(isset($this->armorTable[$armor->getID()])){
									$points += $this->armorTable[$armor->getID()];
								}
							}
							$this->showPlayer($back["Player"]);
							$d->knockBack($p, $finalDamage = $damage - floor($damage * $points * 0.04), sin($yaw = atan2($d->x - $p->x, $d->z - $p->z)), cos($yaw), 0.4);
							$d->attack($finalDamage);
							$d->sendMessage("[BlockHide] " . ($ik ? "숨기 풀림" : "Hide cancelled"));
							$p->heal(2);
							$attack = false;
						}
						$this->back[$n] = $back;
					}
					if($attack){
						if($p->getHealth() <= 1) $this->gameStop("[BlockHide] " . ($this->isKorean() ? "술래 사망" : "Tagger Dead"));
						else $p->attack(1);
					}
				}else{
					$gb = $b->getLevel()->getBlock($p->floor()->add(0, -1, 0));
					if(!$gb->isSolid()){
						$p->sendMessage("[BlockHide] " . ($ik ? "이 블럭에는 숨을수 없습니다." : "You can't hide this block"));
					}elseif($this->back[$n]["Hide"]){
						$p->sendMessage("[BlockHide] " . ($this->isKorean() ? "이미 숨엇습니다." : "Already Hide"));
					}else{
						$this->back[$n]["Block"] = $b->getLevel()->getBlock($hb = $gb->add(0, 1, 0));
						$gb->getLevel()->setBlock($hb, $gb);
						$this->back[$n]["Hide"] = true;
						$p->teleport($hb->add(0.5, 0, 0.5));
						$this->hidePlayer($p);
						$p->sendMessage("[BlockHide] " . ($ik ? "숨음" : "Hide"));
					}
				}
			}
		}elseif($this->set["Start"] !== 0) $event->setCancelled();
	}

	public function onEntityDamage(EntityDamageEvent $event){
		if($this->set["Start"] <= 1) $event->setCancelled();
		if(($p = $event->getEntity()) instanceof Player && $event instanceof EntityDamageByEntityEvent && ($d = $event->getDamager()) instanceof Player && $this->set["Start"] >= 1){
			if($this->set["Tagger"]->getName() !== $p->getName() && $this->set["Tagger"]->getName() !== $d->getName() || !$this->back[$d->getName()]["Life"]){
				$event->setCancelled();
				return;
			}
			if($p->getHealth() <= $event->getFinalDamage()){
				$d->heal(5);
				$event->setCancelled();
				$back = $this->back[$n = $p->getName()];
				if($back["Hide"]) $this->showPlayer($p);
				$back["Hide"] = false;
				if(($b = $back["Block"]) instanceof Block) $b->getLevel()->setBlock($b, $b);
				$back["Block"] = false;
				$back["Life"] = false;
				$this->back[$n] = $back;
				if($this->set["Tagger"]->getName() == $p->getName()) $this->gameStop($this->isKorean() ? "술래 사망" : "Tagger Dead");
				else{
					$cnt = 0;
					foreach($this->back as $back)
						if(isset($back["Life"]) && $back["Life"]) $cnt++;
					$this->getServer()->broadCastMessage("[BlockHide] " . $p->getName() . ($this->isKorean() ? "님이 탈락하셨습니다. 플레이어 : " : " is Game Over. Players : ") . $cnt);
					if($cnt < 2) $this->gameStop($this->isKorean() ? "술래 승리" : "Tagger Win");
					else{
						$this->hidePlayer($p);
						$this->back[$n]["Hide"] = true;
						$p->setHealth(20);
						$p->getInventory()->clearAll();
					}
				}
			}
		}
	}

	public function gameWait(){
		if(!$level = $this->getServer()->getLevelByName($this->bh["World"])){
			$level = $this->getServer()->getDefaultLevel();
		}
		$bb = ["빨강", "노랑", "초록", "보라"][$r = rand(0, 3)];
		$this->set["Map"] = $r;
		if($this->bh["Pos"]){
			$pos = explode(":", $this->bh["Pos"]);
			$playerPos = new Position($pos[0], $pos[1], $pos[2], $level);
		}else{
			$playerPos = new Position([113, 127, 142, 127][$r], 5, [127, 113, 127, 142][$r], $level);
		}
		if($this->bh["TaggerPos"]){
			$pos = explode(":", $this->bh["TaggerPos"]);
			$taggerPos = new Position($pos[0], $pos[1], $pos[2], $level);
		}else{
			$taggerPos = $playerPos;
		}
		$this->back = [];
		$taggerInv = [];
		$taggerArm = [$a = Item::get(0, 0, 0), $a, $a, $a];
		foreach($this->bh["Item"]["Tagger"] as $k => $v){
			$e = explode(":", $v);
			$i = Item::get($e[0], $e[1], $e[2]);
			for($f = 0; $f < 4; $f++){
				if($taggerArm[$f]->getID() == 0 && in_array($i->getID(), $this->armorType[$f])){
					$taggerArm[$f] = $i;
					unset($i);
					break;
				}
			}
			if(isset($i)) $taggerInv[] = $i;
		}
		$playerInv = [];
		$playerArm = [$a, $a, $a, $a];
		foreach($this->bh["Item"]["Player"] as $k => $v){
			$e = explode(":", $v);
			$i = Item::get($e[0], $e[1], $e[2]);
			for($f = 0; $f < 4; $f++){
				if($playerArm[$f]->getID() == 0 && in_array($i->getID(), $this->armorType[$f])){
					$playerArm[$f] = $i;
					unset($i);
					break;
				}
			}
			if(isset($i)) $playerInv[] = $i;
		}
		foreach(($ps = $this->getServer()->getOnlinePlayers()) as $p){
			$this->back[$n = $p->getName()] = [];
			$back = $this->back[$n];
			$back["Player"] = $p;
			$back["Pos"] = $p->getPosition();
			$p->teleport($this->set["Tagger"]->getName() == $p->getName() ? $taggerPos : $playerPos);
			$back["MHP"] = $p->getMaxHealth();
			$p->setMaxHealth(20);
			$back["HP"] = $p->getHealth();
			$p->setHealth(20);
			$back["Inv"] = $p->getInventory()->getContents();
			$back["Arm"] = $p->getInventory()->getArmorContents();
			$p->getInventory()->setContents($this->set["Tagger"]->getName() == $p->getName() ? $taggerInv : $playerInv);
			$p->getInventory()->setArmorContents($this->set["Tagger"]->getName() == $p->getName() ? $taggerArm : $playerArm);
			$p->getInventory()->sendArmorContents($ps);
			$back["Life"] = true;
			$back["Hide"] = false;
			$back["Block"] = false;
			$this->back[$n] = $back;
		}
		$this->set["Pos"] = $playerPos;
		$this->set["Start"] = 1;
		$this->set["Time"] = time(true) + 30;
		$this->getServer()->broadCastMessage("[BlockHideTag] " . ($this->isKorean() ? "게임이 준비되었습니다. \n    시간 : " . $this->bh["Time"] . "초 \n    술래 :" . $this->set["Tagger"]->getName() . "\n 맵 색 : " . $bb : "Game is ready \n     Time: " . $this->bh["Time"] . "sec \n     Tagger:" . $this->set["Tagger"]->getName()));
		for($i = 0; $i <= 5; $i++)
			$this->addSchedule($this->getServer(), "broadCastMessage", ["[BlockHide] " . ($this->isKorean() ? "" . (30 - $i * 5) . "초 후에 게임이 시작됩니다." : "Game start to : " . (30 - $i * 5) . " seconds")], $i * 100);
		$this->addSchedule($this, "gameStart", [], 600);
		for($i = 0; $i < 10; $i++)
			$this->addSchedule($this->getServer(), "broadCastMessage", ["[BlockHide] " . ($this->isKorean() ? "" . (10 - $i) . "초 후에 게임이 종료됩니다." : "Game stop to : " . (10 - $i) . " seconds")], ($this->bh["Time"] + $i + 30) * 20);
		$this->addSchedule($this, "gameStop", [$this->isKorean() ? "시간종료, 술래패배" : "TimeOut, TaggerLose"], ($this->bh["Time"] + 40) * 20);
		$this->randomBlock(true);
	}

	public function gameStart(){
		$this->set["Start"] = 2;
		$this->set["Tagger"]->teleport($this->set["Pos"]);
		$this->getServer()->broadCastMessage("[BlockHideTag] " . ($this->isKorean() ? "게임이 시작되었습니다." : "Game is start"));
		$this->set["Time"] = time(true) + $this->bh["Time"];
	}

	public function gameStop($cause){
		$this->getServer()->broadCastMessage("[BlockHide] " . ($this->isKorean() ? "게임이 종료되엇습니다." : "Game is stoped") . " : $cause");
		foreach($this->scheduleList as $id)
			$this->getServer()->getScheduler()->cancelTask($id);
		foreach($this->getServer()->getOnlinePlayers() as $p)
			$this->backupPlayer($p);
		$this->randomBlock(false);
		$this->set = ["Start" => 0, "Tagger" => false];
		$this->scheduleList = [];
		$this->back = [];
	}

	public function backupPlayer($p){
		if(!isset($this->back[$n = $p->getName()])) return;
		$back = $this->back[$n];
		$p->teleport($back["Pos"]);
		$p->setMaxHealth($back["MHP"]);
		$p->setHealth($back["HP"]);
		$p->getInventory()->clearAll();
		$p->getInventory()->setContents($back["Inv"]);
		$p->getInventory()->setArmorContents($back["Arm"]);
		$p->getInventory()->sendArmorContents($p);
		if($back["Hide"]) $this->showPlayer($p);
		if(($b = $back["Block"]) instanceof Block) $b->getLevel()->setBlock($b, $b);
		unset($this->back[$n]);
		$p->dead = $p->getHealth() == 0;
	}

	public function addSchedule($class = false, $function = false, $array = [], $time = 0){
		if(!$class || !$function || !is_array($array) || !is_numeric($time)) return false;
		$task = $this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$class, $function], $array), $time);
		$this->scheduleList[] = $task->getTaskId();
	}

	public function hidePlayer($p){
		$this->back[$p->getName()]["Hide"] = true;
		foreach($this->getServer()->getOnlinePlayers() as $pl){
			if(isset($this->back[$n = $pl->getName()]) && isset($this->back[$n]["Life"]) && $this->back[$n]["Life"]) $pl->hidePlayer($p);
		}
	}

	public function showPlayer($p){
		$this->back[$p->getName()]["Hide"] = false;
		foreach($this->getServer()->getOnlinePlayers() as $pl)
			$pl->showPlayer($p);
	}

	public function loadYml(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		$this->bh = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "BlockHide.yml", Config::YAML, ["Time" => 60, "Pos" => false, "TaggerPos" => "128:1000:128", "World" => false, "Protect" => false, "Item" => ["Tagger" => ["310:0:1", "311:0:1", "312:0:1", "313:0:1", "276:0:1"], "Player" => ["332:0:255", "298:0:1", "299:0:1", "300:0:1", "301:0:1"]]]))->getAll();
	}

	public function saveYml(){
		$bh = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "BlockHide.yml", Config::YAML);
		$bh->setAll($this->bh);
		$bh->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => true]))->get("Korean");
		return $this->ik;
	}

	public function randomBlock($isPlace){
		if(!$level = $this->getServer()->getLevelByName($this->bh["World"])){
			$level = $this->getServer()->getDefaultLevel();
		}
		$for = [[105, 120, 120, 135], [120, 135, 105, 120], [135, 150, 120, 135], [120, 135, 135, 150]][$this->set["Map"]];
		for($x = $for[0] + 1; $x < $for[1]; $x++){
			for($z = $for[2] + 1; $z < $for[3]; $z++){
				$down = new Position($x, 3, $z, $level);
				$up = $down->add(0, 1, 0);
				if($isPlace && $level->getBlock($up)->getID() == 0 && rand(1, 5) == 1){
					$level->setBlock($up, $level->getBlock($down));
				}elseif(!$isPlace){
					if($level->getBlock($up)->getID() == $level->getBlock($down)->getID()){
						$level->setBlock($up, Block::get(0, 0));
					}
					if($level->getBlock($up->add(0, 1, 0))->getID() == $level->getBlock($up)->getID()){
						$level->setBlock($up->add(0, 1, 0), Block::get(0, 0));
					}
				}
			}
		}
	}
}
class Player4NameTag extends Human{
	public $player, $plugin;

	public function __construct($player, $plugin){
		parent::__construct($player->getLevel()->getChunk($player->x >> 4, $player->z >> 4), new Compound("", ["Pos" => new Enum("Pos", [new Double("", $player->x), new Double("", $player->y + 20), new Double("", $player->z)]), "Motion" => new Enum("Motion", [new Double("", 0), new Double("", 0), new Double("", 0)]), "Rotation" => new Enum("Rotation", [new Float("", 0), new Float("", 0)])]));
		$this->player = $player;
		$this->plugin = $plugin;
		$this->inventory = new PlayerInventory($this);
		$this->despawnFromAll();
		$this->spawnTo($player);
	}

	protected function initEntity(){}

	public function saveNBT(){}

	public function onUpdate($currentTick){
		$p = $this->player;
		if(!$p instanceof Player || !$p->spawned){
			if(!$this->closed) parent::close();
			$this->player = null;
			return false;
		}elseif((($pitch = $p->getPitch()) >= 2 || $pitch <= -12)){
			$this->despawnFrom($p);
		}else{
			$name = "\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n";
			switch($this->plugin->set["Start"]){
				case 0:
					$name .= $this->plugin->isKorean() ? "게임이 시작되지 않앗습니다." : "Game is not Start";
				break;
				case 1:
					$name .= ($this->plugin->isKorean() ? "게임이 시작되엇습니다. \n술래 : " : "Game is Started. \n Tagger : ") . $this->plugin->set["Tagger"]->getName();
					$name .= ($this->plugin->isKorean() ? "\n술래 스폰까지 남은시간 : " : "\n Tagger spawn time : ") . ($this->plugin->set["Time"] - time(true));
				break;
				case 2:
					$name .= ($this->plugin->isKorean() ? "게임이 시작되엇습니다. \n술래 : " : "Game is Started. \n Tagger : ") . $this->plugin->set["Tagger"]->getName();
					$name .= ($this->plugin->isKorean() ? "\n 남은시간 : " : "\n Time : ") . ($this->plugin->set["Time"] - time(true));
				break;
			}
			$this->x = $p->x - sin($p->getyaw() / 180 * M_PI) * cos($p->getPitch() / 180 * M_PI * 3);
			$this->y = $p->y + 20;
			$this->z = $p->z + cos($p->getyaw() / 180 * M_PI) * cos($p->getPitch() / 180 * M_PI * 3);
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
			$p->dataPacket($pk);
		}
		return true;
	}

	public function spawnTo(Player $player){
		if($this->player === $player){
			parent::spawnTo($player);
		}
	}

	public function getData(){
		return [];
	}
}