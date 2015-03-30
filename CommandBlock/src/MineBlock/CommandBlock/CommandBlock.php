<?php
namespace MineBlock\CommandBlock;

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
use pocketmine\level\Position;
use pocketmine\math\Vector3;

class CommandBlock extends PluginBase implements Listener{

	public function onEnable(){
		$this->touch = [];
		$this->place = [];
		$this->player = [];
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this,"onTick"]), 20);
		$this->loadYml();
		$this->getServer()->getLogger()->info("[CommandBlock] Find economy plugin...");
		$pm = $this->getServer()->getPluginManager();
 		if(!($this->money = $pm->getPlugin("PocketMoney")) && !($this->money = $pm->getPlugin("EconomyAPI")) && !($this->money = $pm->getPlugin("MassiveEconomy")) && !($this->money = $pm->getPlugin("Money"))){
			$this->getServer()->getLogger()->info("[CommandBlock] Failed find economy plugin...");
		}else{
			$this->getServer()->getLogger()->info("[CommandBlock] Finded economy plugin : ".$this->money->getName());
		}
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		$n = $sender->getName();
		if(!isset($sub[0])) return false;
		$cb = $this->cb;
		$t = $this->touch;
		$rm = "Usage: /CommandBlock ";
		$mm = "[CommandBlock] ";
		$ik = $this->isKorean();
		switch(strtolower($sub[0])){
			case "add":
			case "a":
			case "추가":
				$sc = true;
				if(isset($t[$n])){
					$r = $mm . ($ik ? "커맨드 블럭 추가 해제": " CommandBlock Add Touch Disable");
					unset($t[$n]);
				}else{
					if(!isset($sub[1])){
						$r = $rm . ($ik ? "추가 <명령어>": "Add(A) <Command>>");
					}else{
						array_shift($sub);
						$command = implode(" ", $sub);
						$r = $mm . ($ik ? "대상 블럭을 터치해주세요. 명령어": "Touch the target block.  Command") . " : $command";
						$t[$n] = ["Type" => "Add","Command" => $command];
					}
				}
			break;
			case "del":
			case "d":
			case "삭제":
			case "제거":
				$sc = true;
				if(isset($t[$n])){
					$r = $mm . ($ik ? "커맨드블럭 제거 해제": " CommandBlock Del Touch Disable");
					unset($t[$n]);
				}else{
					$r = $mm . ($ik ? "대상 블럭을 터치해주세요. ": "Touch the block glass ");
					$t[$n] = ["Type" => "Del"];
				}
			break;
			case "reset":
			case "r":
			case "리셋":
			case "초기화":
				$cb = [];
				$r = $mm . ($ik ? " 리셋됨.": " Reset");
			break;
			default:
				return false;
			break;
		}
		if(isset($r)) $sender->sendMessage($r);
		if($this->cb !== $cb){
			$this->cb = $cb;
			$this->saveYml();
		}
		$this->touch = $t;
		return true;
	}

	public function onPlayerInteract(PlayerInteractEvent $event){
		$b = $event->getBlock();
		$p = $event->getPlayer();
		$n = $p->getName();
		$t = $this->touch;
		$cb = $this->cb;
		$m = "[CommandBlock] ";
		$ik = $this->isKorean();
		if(isset($t[$n])){
			$pos = $this->getPos($b);
			$tc = $t[$n];
			switch($tc["Type"]){
				case "Add":
					$m .= ($ik ? "커맨드블럭이 생성되었습니다.": "CommandBlock Create") . " [$pos]";
					if(!isset($cb[$pos])) $cb[$pos] = [];
					$cb[$pos][] = $tc["Command"];
					unset($t[$n]);
				break;
				case "Del":
					if(!isset($cb[$pos])){
						$m .= $ik ? "이곳에는 커맨드 블럭이 없습니다.": "CommandBlock is not exist here";
					}else{
						$m .= ($ik ? "커맨드블럭이 제거되었습니다.": "CommandBlock is Delete ") . "[$pos]";
						unset($cb[$pos]);
						unset($t[$n]);
					}
				break;
			}
			if(isset($m)) $p->sendMessage($m);
			if($this->cb !== $cb){
				$this->cb = $cb;
				$this->saveYml();
			}
			$this->touch = $t;
			$event->setCancelled();
			if($event->getItem()->isPlaceable()){
				$this->place[$p->getName()] = true;
			}
		}else{
			$this->onBlockEvent($event, true);
		}
	}

	public function onBlockBreak(BlockBreakEvent $event){
		$this->onBlockEvent($event);
	}

	public function onBlockPlace(BlockPlaceEvent $event){
		$this->onBlockEvent($event);
	}

	public function onBlockEvent($event, $isTouch = false){
		$p = $event->getPlayer();
		if(isset($this->place[$p->getName()])){
			$event->setCancelled();
			unset($this->place[$p->getName()]);
		}
		$pos = $this->getPos($event->getBlock());
		if(isset($this->cb[$pos])){
			if($isTouch && $event->getItem()->isPlaceable()){
				$this->place[$p->getName()] = true;
			}
			if(!$p->hasPermission("mineblock.commandblock.block")) $event->setCancelled();
			if($p->hasPermission("mineblock.commandblock.touch")) $this->runCommand($event->getPlayer(), $pos);
		}
	}

	public function onTick(){
		foreach($this->getServer()->getOnlinePlayers() as $p){
			if(!$p->hasPermission("mineblock.commandblock.push")) return;
			$pos = $this->getPos($p->add(0, -1, 0), $p->getLevel()->getName());
			if(isset($this->cb[$pos])){
				foreach($this->cb[$pos] as $cmd){
					$cmd = strtolower($cmd);
					if(strpos($cmd, "%b ") !== false || strpos($cmd, " %b") !== false || strpos($cmd, "%block ") !== false || strpos($cmd, " %block") !== false){
						$this->runCommand($p, $pos, true);
						break;
					}
				}
			}
		}
	}

	public function runCommand($p, $pos, $isBlock = false){
		$cb = $this->cb;
		if(!isset($cb[$pos])) return false;
		$pl = $this->player;
		if(!isset($pl[$n = $p->getName()])) $pl[$n] = [];
		if(!isset($pl[$n][$pos])) $pl[$n][$pos] = 0;
		if(microtime(true) - $pl[$n][$pos] < 0) return;
		$l = explode(":", $pos);
		$cool = 1;
		foreach($cb[$pos] as $str){
			$arr = explode(" ", $str);
			$time = 0;
			$chat = false;
			$console = false;
			$op = false;
			$deop = false;
			$safe = false;
			$block = false;
			$heal = false;
			$damage = false;
			$say = false;
			foreach($arr as $k => $v){
				if(strpos($v, "%") === 0){
					$kk = $k;
					$sub = strtolower(substr($v, 1));
					$e = explode(":", $sub);
					if(isset($e[1])){
						$ee = explode(",", $e[1]);
						switch(strtolower($e[0])){
							case "dice":
							case "d":
								if(isset($ee[1])) $arr[$k] = rand($ee[0], $ee[1]);
								$set = true;
							break;
							case "cool":
							case "c":
								if(is_numeric($e[1])) $cool = $e[1];
							break;
							case "time":
							case "t":
								if(is_numeric($e[1])) $time = $e[1];
							break;
							case "heal":
							case "h":
								if(is_numeric($e[1])) $heal = $e[1];
							break;
							case "damage":
							case "dmg":
								if(is_numeric($e[1])) $damage = $e[1];
							break;
							case "teleport":
							case "tp":
								if(is_numeric($x = $ee[0]) && isset($ee[1]) && is_numeric($y = $ee[1]) && isset($ee[2]) && is_numeric($z = $ee[2])){
									$tpos = [$x,$y,$z];
									if(isset($ee[3]) && $world = $this->getLevelByName($ee[3])){
										$tpos[] = $world;
									}else{
										$tpos[] = $p->getLevel();
									}
								}elseif($world = $this->getLevelByName($ee[0])){
									if(isset($ee[1]) && is_numeric($x = $ee[1]) && isset($ee[2]) && is_numeric($y = $ee[2]) && isset($ee[3]) && is_numeric($z = $ee[3])){
										$tpos = [$x,$y,$z];
									}else{
										$s = $world->getSafeSpawn();
										$tpos = [$s->z,$s->y,$s->z];
									}
									$tpos[] = $world;
								}
								if(isset($tpos)) $p->teleport(new Position(...$tpos));
								else $set = true;
							break;
							case "jump":
							case "j":
								if(isset($ee[2]) && is_numeric($x = $ee[0]) && is_numeric($y = $ee[0]) && is_numeric($z = $ee[0])){
									if(isset($ee[3]) && $ee[3] == "%"){
										$d = (isset($ee[4]) && is_numeric($ee[4]) && $ee[4] >= 0) ? $ee[4] : (max($x, $y, $z) > 0 ? max($x, $y, $z): -min($x, $y, $z));
										$this->move($p, (new Vector3($x * 0.4, $y * 0.4 + 0.1, $z * 0.4))->multiply(1.11 / $d), $d, isset($ee[5]) && is_numeric($ee[5]) ? $ee[5]: 0.15);
									}else{
										$p->setMotion((new Vector3($x, $y, $z))->multiply(0.4));
									}
								}else{
									$set = true;
								}
							break;
							case "havemoney":
							case "hm":
								if(is_numeric($e[1])){
									if($this->getMoney($p) < $e[1]) return;
								}else{
									$set = true;
								}
							break;
							case "nothavemoney":
							case "nm":
								if(is_numeric($e[1])){
									if($this->getMoney($p) >= $e[1]) return;
								}else{
									$set = true;
								}
							break;
							case "givemoney":
							case "gm":
								if(is_numeric($e[1])){
									$this->giveMoney($p, $e[1]);
								}else{
									$set = true;
								}
							break;
							case "takemoney":
							case "tm":
								if(is_numeric($e[1])){
									$this->giveMoney($p, -$e[1]);
								}else{
									$set = true;
								}
							break;
							default:
								$set = true;
							break;
						}
						if(!isset($set)) unset($arr[$k]);
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
							case "blockx":
							case "bx":
								$arr[$k] = $l[0];
							break;
							case "blocky":
							case "by":
								$arr[$k] = $l[1];
							break;
							case "blockz":
							case "bz":
								$arr[$k] = $l[2];
							break;
							case "world":
							case "w":
								$arr[$k] = $p->getLevel()->getFolderName();
							break;
							case "random":
							case "r":
								$ps = $this->getServer()->getOnlinePlayers();
								$arr[$k] = count($ps) < 1 ? "": $ps[array_rand($ps)]->getName();
							break;
							case "server":
							case "sv":
								$arr[$k] = $this->getServer()->getServerName();
							break;
							case "version":
							case "v":
								$arr[$k] = $this->getServer()->getApiVersion();
							break;
							case "money":
							case "m":
								if(($money = $this->getMoney($p)) !== false) $arr[$k] = $money;		
							break;
							case "op":
								unset($arr[$k]);
								$op = true;
							break;
							case "deop":
							case "do":
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
							case "block":
							case "b":
								unset($arr[$k]);
								$block = true;
							break;
							case "say":
								unset($arr[$k]);
								$say = true;
							break;
						}
					}
				}
			}
			$this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this,"dispatchCommand"], [$p,$pos,$isBlock,$chat,$console,$op,$deop,$safe,$block,$arr,$heal,$damage,$say]), $time * 20);
		}
		$pl[$n][$pos] = microtime(true) + $cool;
		$this->player = $pl;
	}

	public function dispatchCommand($p, $pos, $isBlock, $chat, $console, $op, $deop, $safe, $block, $arr, $heal, $damage, $say){
		if(($isBlock && !$block) || (!$isBlock && $block) || ($safe && !$p->isOp()) || ($deop && $p->isOp())) return false;
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
			$ev = $console ? new ServerCommandEvent(new ConsoleCommandSender(), $cmd): new PlayerCommandPreprocessEvent($p, "/" . $cmd);
			$this->getServer()->getPluginManager()->callEvent($ev);
			if(!$ev->isCancelled()){
				if($ev instanceof ServerCommandEvent) $this->getServer()->dispatchCommand(new ConsoleCommandSender(), $ev->getCommand());
				else $this->getServer()->dispatchCommand($p, substr($ev->getMessage(), 1));
			}
			if($op) $p->setOp(false);
		}
		return true;
	}

	public function move(Player $p, Vector3 $m, $t, $cool, $tt = false){
		if(!$tt) $tt = 0;
		if($t - $tt < 1){
			return;
		}else{
			$tt++;
			$p->setMotion($m);
			$p->onGround = true;
			if($t - $tt > 0) $this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this,"move"], [$p,$m,$t,$cool,$tt]), $cool * 20);
		}
	}

	public function getMoney($p){
		if(!$this->money) return false;
		switch($this->money->getName()){
			case "PocketMoney":
		 	case "MassiveEconomy":
				return $this->money->getMoney($p);
			break;
			case "EconomyAPI":
				return $this->money->mymoney($p);
			break;
			case "Money":
				return $this->money->getMoney($p->getName());
			break;
			default:
				return false;
			break;
		}
	}

	public function giveMoney($p, $money){
		if(!$this->money) return false;
		switch($this->money->getName()){
			case "PocketMoney":
				$this->money->grantMoney($p, $money);
			break;
			case "EconomyAPI":
				$this->money->setMoney($p, $this->money->mymoney($p) + $money);
			break;
			case "MassiveEconomy":
				$this->money->setMoney($p, $this->money->getMoney($p) + $money);
			break;
			case "Money":
				$n = $p->getName();
				$this->money->setMoney($n, $this->money->getMoney($n) + $money);
			break;
			default:
				return false;
			break;
		}
		return true;
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

	public function getPos($b, $level = false){
		return floor($b->x) . ":" . floor($b->y) . ":" . floor($b->z) . ":" . (!$level ? $b->getLevel()->getFolderName(): $level);
	}

	public function loadYml(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		$this->cb = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "CommandBlock.yml", Config::YAML))->getAll();
	}

	public function saveYml(){
		ksort($this->cb);
		$cb = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "CommandBlock.yml", Config::YAML);
		$cb->setAll($this->cb);
		$cb->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}