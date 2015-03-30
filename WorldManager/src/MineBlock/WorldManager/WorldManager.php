<?php

namespace MineBlock\WorldManager;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\level\generator\Generator;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\entity\Arrow;

class WorldManager extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->loadYml();
		$this->loadWorlds();
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		$ik = $this->isKorean();
		$rm = TextFormat::RED . "Usage: /";
		$wm = $this->wm;
		switch(strtolower($cmd->getName())){
			case "worldmanager":
				if(!isset($sub[0])) return false;
				$mm = "[WorldManager] ";
				$rm .= "WorldManager ";
				switch(strtolower($sub[0])){
					case "generate":
					case "generator":
					case "g":
					case "생성":
					case "add":
					case "a":
					case "추가":
						if(!isset($sub[1])){
							$r = $rm . ($ik ? "생성 <이름> <타입> <시드>" : "Generate <Name> <Type> <Seed>");
						}else{
							$seed = isset($sub[3]) ? $sub[3] : null;
							$gn = $this->getServer()->getLevelType();
							$this->getServer()->setConfigString("level-type", isset($sub[2]) ? $sub[2] : null);
							$this->getServer()->generateLevel(strtolower($sub[1]), $seed);
							$this->getServer()->setConfigString("level-type", $gn);
							$r = $mm . ($ik ? "월드가 생성되었습니다. 월드명: " : "World is generate. World: ") . strtolower($sub[1]);
						}
					break;
					case "load":
					case "l":
					case "로딩":
					case "로드":
					case "불러오기":
						if(!isset($sub[1])){
							$r = $rm . ($ik ? "로드 <이름>" : "Load <Name>");
						}else{
							$ln = strtolower($sub[1]);
							if(!$this->getServer()->loadLevel($ln)){
								$r = $mm . $ln . ($ik ? "는 잘못된 월드명입니다." : "is invalid world name");
							}else{
								$wm["Load"][$ln] = true;
								$r = $mm . ($ik ? "$ln 월드를 로딩햇습니다." : "Load $ln world");
							}
						}
					break;
					case "list":
					case "목록":
						$page = 1;
						if(isset($sub[0]) && is_numeric($sub[0])) $page = max(floor($sub[0]), 1);
						$list = array_chunk($this->getServer()->getLevels(), 5, true);
						if($page >= ($c = count($list))) $page = $c;
						$r = $mm . ($ik ? "월드 목록 (페이지" : "World List (Page") . " $page/$c) \n";
						$num = ($page - 1) * 5;
						if($c > 0){
							foreach($list[$page - 1] as $v){
								$num++;
								$r .= "  [$num] " . $v->getName() . " : " . $v->getFolderName() . "\n";
							}
						}
					break;
					case "spawn":
					case "s":
					case "스폰":
						$wm["MainSpawn"] = !$wm["MainSpawn"];
						$r = $mm . ($wm["MainSpawn"] ? ($ik ? "스폰시 메인월드에서 스폰합니다." : "Main spawn is On") : ($ik ? "스폰시 해당 월드에서 스폰합니다." : "Main spawn is off"));
					break;
					default:
						return false;
					break;
				}
			break;
			case "worldprotect":
				if(!isset($sub[0])) return false;
				$mm = "[WorldProtect] ";
				$rm .= "WorldProtect ";
				$wp = $wm["Protect"];
				switch(strtolower($sub[0])){
					case "add":
					case "a":
					case "추가":
						if(!isset($sub[1]) || !$sub[1]){
							$r = $rm . ($ik ? "추가 <월드명>" : "Add(A) <WorldName>");
						}else{
							$w = strtolower($sub[1]);
							if(!in_array($w, $wp)) $wp[] = $w;
							$r = $mm . ($ik ? " 추가됨 " : "Add") . " : $w";
						}
					break;
					case "del":
					case "d":
					case "삭제":
					case "제거":
						if(!isset($sub[1])){
							$r = $rm . ($ik ? "제거 <월드명>" : "Del(D) <WorldName>");
						}else{
							$w = strtolower($sub[1]);
							if(!in_array($w, $wp)){
								$r = " [$w] " . ($ik ? "목록에 존재하지 않습니다.\n $rm 목록 " : "does not exist.\n $rm List(L)");
							}else{
								foreach($wp as $k => $v){
									if($v == $w){
										unset($wp[$k]);
										$r = $mm . ($ik ? " 제거됨 " : "Del") . " : $w";
										break;
									}
								}
							}
						}
					break;
					case "reset":
					case "r":
					case "리셋":
					case "초기화":
						$wp = [];
						$r = $mm . ($ik ? " 리셋됨." : " Reset");
					break;
					case "list":
					case "l":
					case "목록":
					case "리스트":
						$page = 1;
						if(isset($sub[0]) && is_numeric($sub[0])) $page = max(floor($sub[0]), 1);
						$list = array_chunk($wp, 5, true);
						if($page >= ($c = count($list))) $page = $c;
						$r = $mm . ($ik ? "월드보호 목록 (페이지" : "WorldProtect List (Page") . " $page/$c) \n";
						$num = ($page - 1) * 5;
						if($c > 0){
							foreach($list[$page - 1] as $v){
								$num++;
								$r .= "  [$num] $v\n";
							}
						}
					break;
					default:
						return false;
					break;
				}
				$wm["Protect"] = $wp;
			break;
			case "worldpvp":
				if(!isset($sub[0])) return false;
				$mm = "[WorldPVP] ";
				$rm .= "WorldPVP ";
				$wpvp = $wm["PVP"];
				switch(strtolower($sub[0])){
					case "add":
					case "a":
					case "추가":
						if(!isset($sub[1]) || !$sub[1]){
							$r = $rm . ($ik ? "추가 <월드명>" : "Add(A) <WorldName>");
						}else{
							$w = strtolower($sub[1]);
							if(!in_array($w, $wpvp)) $wpvp[] = $w;
							$r = $mm . ($ik ? " 추가됨 " : "Add") . " : $w";
						}
					break;
					case "del":
					case "d":
					case "삭제":
					case "제거":
						if(!isset($sub[1])){
							$r = $rm . ($ik ? "제거 <월드명>" : "Del(D) <WorldName>");
						}else{
							$w = strtolower($sub[1]);
							if(!in_array($w, $wpvp)){
								$r = " [$w] " . ($ik ? "목록에 존재하지 않습니다.\n $rm 목록 " : "does not exist.\n $rm List(L)");
							}else{
								foreach($wpvp as $k => $v){
									if($v == $w){
										unset($wpvp[$k]);
										$r = $mm . ($ik ? " 제거됨 " : "Del") . " : $w";
										break;
									}
								}
							}
						}
					break;
					case "reset":
					case "r":
					case "리셋":
					case "초기화":
						$wpvp = [];
						$r = $mm . ($ik ? " 리셋됨." : " Reset");
					break;
					case "list":
					case "l":
					case "목록":
					case "리스트":
						$page = 1;
						if(isset($sub[0]) && is_numeric($sub[0])) $page = max(floor($sub[0]), 1);
						$list = array_chunk($wpvp, 5, true);
						if($page >= ($c = count($list))) $page = $c;
						$r = $mm . ($ik ? "PVP 월드 목록 (페이지" : "PVP World List (Page") . " $page/$c) \n";
						$num = ($page - 1) * 5;
						if($c > 0){
							foreach($list[$page - 1] as $v){
								$num++;
								$r .= "  [$num] $v\n";
							}
						}
					break;
					default:
						return false;
					break;
				}
				$wm["PVP"] = $wpvp;
			break;
			case "worldinv":
				if(!isset($sub[0])) return false;
				$mm = "[WorldInv] ";
				$rm .= "WorldInv ";
				$winv = $wm["Inv"];
				switch(strtolower($sub[0])){
					case "add":
					case "a":
					case "추가":
						if(!isset($sub[1]) || !$sub[1]){
							$r = $rm . ($ik ? "추가 <월드명>" : "Add(A) <WorldName>");
						}else{
							$w = strtolower($sub[1]);
							if(!in_array($w, $winv)) $winv[] = $w;
							$r = $mm . ($ik ? " 추가됨 " : "Add") . " : $w";
						}
					break;
					case "del":
					case "d":
					case "삭제":
					case "제거":
						if(!isset($sub[1])){
							$r = $rm . ($ik ? "제거 <월드명>" : "Del(D) <WorldName>");
						}else{
							$w = strtolower($sub[1]);
							if(!in_array($w, $winv)){
								$r = " [$w] " . ($ik ? "목록에 존재하지 않습니다.\n $rm 목록 " : "does not exist.\n $rm List(L)");
							}else{
								foreach($winv as $k => $v){
									if($v == $w){
										unset($winv[$k]);
										$r = $mm . ($ik ? " 제거됨 " : "Del") . " : $w";
										break;
									}
								}
							}
						}
					break;
					case "reset":
					case "r":
					case "리셋":
					case "초기화":
						$winv = [];
						$r = $mm . ($ik ? " 리셋됨." : " Reset");
					break;
					case "list":
					case "l":
					case "목록":
					case "리스트":
						$page = 1;
						if(isset($sub[0]) && is_numeric($sub[0])) $page = max(floor($sub[0]), 1);
						$list = array_chunk($winv, 5, true);
						if($page >= ($c = count($list))) $page = $c;
						$r = $mm . ($ik ? "인벤세이브 월드 목록 (페이지" : "InventorySave World List (Page") . " $page/$c) \n";
						$num = ($page - 1) * 5;
						if($c > 0){
							foreach($list[$page - 1] as $v){
								$num++;
								$r .= "  [$num] $v\n";
							}
						}
					break;
					default:
						return false;
					break;
				}
				$wm["Inv"] = $winv;
			break;
			case "setspawn":
				if(isset($sub[0]) && $player = $this->getServer()->getPlayer($sub[0]))
					;
				else $player = $sender;
				$ln = strtolower($player->getLevel()->getFolderName());
				$wm["Spawn"][$ln] = $player->x . ":" . $player->y . ":" . $player->z;
				$r = "[SetSpawn] " . ($ik ? "스폰 설정되었습니다.  월드명: $ln , 좌표: " : "Spawn set. World: $ln , Position: ") . $wm["Spawn"][$ln];
			break;
			case "spawn":
				if($wm["MainSpawn"]) $world = $this->getServer()->getDefaultLevel();
				else $world = $sender->getLevel();
				$sender->teleport($world->getSpawn());
				$r = "[Spawn] " . ($ik ? "스폰으로 텔레포트되었습니다. 월드명: " : "Teleport to spawn. World: ") . $world->getFolderName();
			break;
		}
		if(isset($r)) $sender->sendMessage($r);
		if($this->wm !== $wm){
			$this->wm = $wm;
			$this->saveYml();
		}
		$this->loadWorlds();
		return true;
	}

	public function onPlayerDeath(PlayerDeathEvent $event){
		if(in_array(strtolower($event->getEntity()->getLevel()->getFolderName()), $this->wm["Inv"])) $event->setKeepInventory(true);
	}

	public function onEntityDamage(EntityDamageEvent $event){
		$p = $event->getEntity();
		if($event->isCancelled() || !$p instanceof Player || $event->getCause() > 11) return;
		$w = strtolower($p->getLevel()->getFolderName());
		if($event instanceof EntityDamageByEntityEvent){
			if(!in_array($w, $this->wm["PVP"])){
				$dmg = $event->getDamager();
				if($dmg instanceof Player){
					if(!$dmg->hasPermission("worldmanager.worldpvp.pvp")){
						$event->setCancelled();
						$dmg->sendMessage("[PVP Manager] PVP 권한이 없습니다.");
					}
				}
			}
		}
	}

	public function onBlockBreak(BlockBreakEvent $event){
		if(!$event->isCancelled()) $this->OnBlockEvent($event);
	}

	public function onBlockPlace(BlockPlaceEvent $event){
		if(!$event->isCancelled()) $this->onBlockEvent($event);
	}

	public function onPlayerInteract(PlayerInteractEvent $event){
		if(!$event->isCancelled()) $this->onBlockEvent($event);
	}

	public function onBlockEvent($event){
		if(in_array(strtolower($event->getBlock()->getLevel()->getFolderName()), $this->wm["Protect"]) && !$event->getPlayer()->hasPermission("worldmanager.worldprotect.block")){
			$event->getPlayer()->sendMessage("[WorldProtect] " . ($this->isKorean() ? "이 월드는 보호상태입니다." : "This world is protected"));
			$event->setCancelled();
		}
	}

	public function getLevelByName($name){
		$levels = $this->getServer()->getLevels();
		foreach($levels as $l){
			if(strtolower($l->getFolderName()) == strtolower($name)) return $l;
		}
		foreach($levels as $l){
			if(strtolower($l->getName()) == strtolower($name)) return $l;
		}
		if($this->getServer()->loadLevel($name) != false) return $this->getServer()->getLevelByName($name);
		return false;
	}

	public function loadWorlds(){
		$wm = $this->wm;
		foreach($wm["Load"] as $l => $b){
			if(!($level = $this->getLevelByName($l))){
				unset($wm["Load"][$l]);
			}else{
				if($b){
					$this->getServer()->loadLevel($l);
				}else{
					$this->getServer()->unloadLevel($level);
				}
			}
		}
		foreach($this->getServer()->getLevels() as $l){
			$ln = strtolower($l->getFolderName());
			if(!isset($wm["Load"][$ln])) $wm["Load"][$ln] = true;
			if(!isset($wm["Spawn"][$ln])){
				$s = $l->getSafeSpawn();
				$wm["Spawn"][$ln] = floor($s->x) . ":" . floor($s->y) . ":" . floor($s->z);
			}
		}
		if($this->wm !== $wm){
			$this->wm = $wm;
			$this->saveYml();
		}
		foreach($this->wm["Spawn"] as $k => $v){
			if(!$l = $this->getLevelByName($k)) continue;
			$s = explode(":", $v);
			$l->setSpawn(new Position($s[0], $s[1], $s[2], $l));
		}
	}

	public function loadYml(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		$this->wm = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "WorldManager.yml", Config::YAML, ["MainSpawn" => true, "Load" => [], "Spawn" => [], "Protect" => [], "PVP" => [], "Inv" => []]))->getAll();
	}

	public function saveYml(){
		ksort($this->wm["Load"]);
		ksort($this->wm["Spawn"]);
		sort($this->wm["Protect"]);
		$wm = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "WorldManager.yml", Config::YAML);
		$wm->setAll($this->wm);
		$wm->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}