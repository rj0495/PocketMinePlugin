<?php

namespace MineBlock\CommandItem;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\scheduler\CallbackTask;
use pocketmine\Player;
use pocketmine\item\Item;
use pocketmine\inventory\PlayerInventory;

class CommandItem extends PluginBase implements Listener{

	public function onEnable(){
		$this->place = [];
		$this->player = [];
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, "onTick"]), 20);
		$this->loadYml();
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		$n = $sender->getName();
		if(!isset($sub[0])) return false;
		$ci = $this->ci;
		$rm = "Usage: /CommandItem ";
		$mm = "[CommandItem] ";
		$ik = $this->isKorean();
		switch(strtolower(array_shift($sub))){
			case "add":
			case "a":
			case "추가":
				if(!isset($sub[1])){
					$r = $rm . ($ik ? "추가 <아이템ID> <명령어>" : "Add(A) <ItemID> <Command>");
				}else{
					if(!$id = $this->getId(Item::fromString(array_shift($sub)))){
						$r = $sub[0] . " " . ($ik ? "는 잘못된 아이템ID입니다.." : "is invalid ItemID");
					}else{
						$command = implode(" ", $sub);
						if(!isset($ci[$id])) $ci[$id] = [];
						$ci[$id][] = $command;
						$r = $mm . ($ik ? " 추가됨" : " add") . "[$id] => $command";
					}
				}
			break;
			case "del":
			case "d":
			case "삭제":
			case "제거":
				if(!isset($sub[0])){
					$r = $rm . "Del(D) <Alias>";
				}else{
					if(!$id = $this->getId(Item::fromString(array_shift($sub)))){
						$r = $sub[0] . " " . ($ik ? "는 잘못된 아이템ID입니다.." : "is invalid ItemID");
					}else{
						if(!isset($ci[$id])){
							$r = "$mm [$id] " . ($ik ? " 목록에 존재하지 않습니다..\n   $rm 목록 " : " does not exist.\n   $rm List(L)");
						}else{
							unset($ci[$id]);
							$r = $mm . ($ik ? " 제거됨" : " del") . "[$id]";
						}
					}
				}
			break;
			case "reset":
			case "r":
			case "리셋":
			case "초기화":
				$ci = [];
				$r = $mm . ($ik ? " 리셋됨." : " Reset");
			break;
			case "list":
			case "l":
			case "목록":
			case "리스트":
				$page = 1;
				if(isset($sub[1]) && is_numeric($sub[1])) $page = max(floor($sub[1]), 1);
				$list = array_chunk($ci, 5, true);
				if($page >= ($c = count($list))) $page = $c;
				$r = $mm . ($ik ? "커맨드아이템 목록 (페이지" : "CommandItem List (Page") . " $page/$c) \n";
				$num = ($page - 1) * 5;
				if($c > 0){
					foreach($list[$page - 1] as $k => $v){
						$num++;
						$r .= "  [$num] $k\n";
					}
				}
			break;
			default:
				return false;
			break;
		}
		if(isset($r)) $sender->sendMessage($r);
		if($this->ci !== $ci){
			$this->ci = $ci;
			$this->saveYml();
		}
		return true;
	}

	public function onPlayerInteract(PlayerInteractEvent $event){
		$this->onBlockEvent($event, true);
	}

	public function onBlockBreak(BlockBreakEvent $event){
		$this->onBlockEvent($event);
	}

	public function onBlockPlace(BlockPlaceEvent $event){
		$this->onBlockEvent($event);
	}

	public function onBlockEvent($event, $isHand = false){
		$p = $event->getPlayer();
		if(isset($this->place[$p->getName()])){
			$event->setCancelled();
			unset($this->place[$p->getName()]);
		}
		if($isHand){
			$id = $this->getId($i = $event->getItem());
			if(isset($this->ci[$id])){
				if($i->isPlaceable()){
					$this->place[$p->getName()] = true;
					$event->setCancelled();
				}
			}
			$this->runCommand($p, $id);
		}
	}

	public function onTick(){
		foreach($this->getServer()->getOnlinePlayers() as $p){
			$id = $this->getHand($p);
			if(isset($this->ci[$id])){
				foreach($this->ci[$id] as $cmd){
					$cmd = strtolower($cmd);
					if(strpos($cmd, "%h") !== false){
						$this->runCommand($p, $id, true);
						break;
					}
				}
			}
		}
	}

	public function runCommand($p, $id, $isHand = false){
		$ci = $this->ci;
		if(!isset($ci[$id])) return false;
		$ps = $this->player;
		$n = $p->getName();
		if(!isset($ps[$n])) $ps[$n] = [];
		if(!isset($ps[$n][$id])) $ps[$n][$id] = 0;
		if(microtime(true) - $ps[$n][$id] < 0) return;
		$l = explode(":", $id);
		$cool = 1;
		foreach($ci[$id] as $str){
			$arr = explode(" ", $str);
			$time = 0;
			$chat = false;
			$console = false;
			$op = false;
			$deop = false;
			$safe = false;
			$hand = false;
			$heal = false;
			$damage = false;
			$say = false;
			foreach($arr as $k => $v){
				if(strpos($v, "%") === 0){
					$kk = $k;
					$sub = strtolower(substr($v, 1));
					$e = explode(":", $sub);
					if(isset($e[1])){
						switch($e[0]){
							case "dice":
							case "d":
								$ee = explode(",", $e[1]);
								if(isset($ee[1])) $arr[$k] = rand($ee[0], $ee[1]);
							break;
							case "cool":
							case "c":
								if(is_numeric($e[1])){
									$cool = $e[1];
									unset($arr[$k]);
								}
							break;
							case "time":
							case "t":
								if(is_numeric($e[1])){
									$time = $e[1];
									unset($arr[$k]);
								}
							break;
							case "heal":
							case "h":
								if(is_numeric($e[1])){
									$heal = $e[1];
									unset($arr[$k]);
								}
							break;
							case "damage":
							case "dmg":
								if(is_numeric($e[1])){
									$damage = $e[1];
									unset($arr[$k]);
								}
							break;
						}
					}else{
						switch($sub){
							case "player":
							case "p":
								$arr[$k] = $p->getName();
							break;
							case "x":
								$arr[$k] = $p->x;
							break;
							case "y":
								$arr[$k] = $p->y;
							break;
							case "z":
								$arr[$k] = $p->z;
							break;
							case "world":
							case "w":
								$arr[$k] = $p->getLevel()->getFolderName();
							break;
							case "random":
							case "r":
								$ps = $this->getServer()->getOnlinePlayers();
								$arr[$k] = count($ps) < 1 ? "" : $ps[array_rand($ps)]->getName();
							break;
							case "server":
							case "s":
								$arr[$k] = $this->getServer()->getServerName();
							break;
							case "version":
							case "v":
								$arr[$k] = $this->getServer()->getApiVersion();
							break;
							case "op":
								unset($arr[$k]);
								$op = true;
							break;
							case "deop":
								unset($arr[$k]);
								$deop = true;
							break;
							case "safe":
							case "s":
								unset($arr[$k]);
								$safe = true;
							break;
							case "chat":
							case "c":
								unset($arr[$k]);
								$chat = true;
							break;
							case "console":
							case "cs":
								unset($arr[$k]);
								$console = true;
							break;
							case "hand":
							case "h":
								unset($arr[$k]);
								$hand = true;
							break;
							case "say":
								unset($arr[$k]);
								$say = true;
							break;
						}
					}
				}
			}
			$this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "dispatchCommand"], [$p, $id, $isHand, $chat, $console, $op, $deop, $safe, $hand, $arr, $heal, $damage, $say]), $time * 20);
		}
		$ps[$n][$id] = microtime(true) + $cool;
		$this->player = $ps;
	}

	public function dispatchCommand($p, $id, $isHand, $chat, $console, $op, $deop, $safe, $hand, $arr, $heal, $damage){
		if(($isHand && !$hand) || (!$isHand && $hand) || ($safe && !$p->isOp()) || ($deop && $p->isOp())) return false;
		$cmd = implode(" ", $arr);
		if($heal) $p->heal($heal);
		if($damage) $p->attack($damage);
		if($chat){
			$p->sendMessage($cmd);
		}elseif($say){
			$this->getServer()->broadcastMessage($cmd);
		}else{
			$op = $op && !$p->isOp() && !$console;
			if($op) $p->setOp(true);
			$ev = $console ? new ServerCommandEvent(new ConsoleCommandSender(), $cmd) : new PlayerCommandPreprocessEvent($p, "/" . $cmd);
			$this->getServer()->getPluginManager()->callEvent($ev);
			if(!$ev->isCancelled()){
				if($ev instanceof ServerCommandEvent) $this->getServer()->dispatchCommand(new ConsoleCommandSender(), $ev->getCommand());
				else $this->getServer()->dispatchCommand($p, substr($ev->getMessage(), 1));
			}
			if($op) $p->setOp(false);
		}
		return true;
	}

	public function getHand($p){
		return $p instanceof Player && ($inv = $p->getInventory()) instanceof PlayerInventory && ($i = $inv->getItemInHand()) instanceof Item ? $this->getId($i) : false;
	}

	public function getId($i){
		return !$i ? false : $i->getID() == 0 ? false : $i->getID() . ":" . $i->getDamage();
	}

	public function loadYml(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		$this->ci = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "CommandItem.yml", Config::YAML))->getAll();
	}

	public function saveYml(){
		ksort($this->ci);
		$ci = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "CommandItem.yml", Config::YAML);
		$ci->setAll($this->ci);
		$ci->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}