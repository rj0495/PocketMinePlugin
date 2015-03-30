<?php

namespace MineBlock\Shop;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\item\Item;
use pocketmine\block\Block;
use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\scheduler\CallbackTask;
use pocketmine\network\protocol\AddItemEntityPacket;
use pocketmine\network\protocol\RemoveEntityPacket;
use pocketmine\network\protocol\MoveEntityPacket;

class Shop extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getLogger()->info("[Shop] Find economy plugin...");
		$pm = $this->getServer()->getPluginManager();
		if(!($this->money = $pm->getPlugin("PocketMoney")) && !($this->money = $pm->getPlugin("EconomyAPI")) && !($this->money = $pm->getPlugin("MassiveEconomy")) && !($this->money = $pm->getPlugin("Money"))){
			$this->getServer()->getLogger()->info("[Shop] Failed find economy plugin...");
			$this->getLogger()->info($this->isKorean() ? TextFormat::RED . "이 플러그인은 머니 플러그인이 반드시 있어야합니다." : TextFormat::RED . "This plugin need the Money plugin");
			$this->getServer()->shutdown();
		}else{
			$this->getServer()->getLogger()->info("[Shop] Finded economy plugin : " . $this->money->getName());
		}
		$this->touch = [];
		$this->tap = [];
		$this->place = [];
		$this->item = [];
		$this->level = [];
		$this->time = false;
		$this->eid = 99999;
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, "onTick"]), 20);
		$this->loadYml();
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		$n = $sender->getName();
		if(!isset($sub[0])) return false;
		$sh = $this->sh;
		$t = $this->touch;
		$rm = "Usage: /Shop ";
		$mm = "[Shop] ";
		$ik = $this->isKorean();
		switch(strtolower($sub[0])){
			case "add":
			case "a":
			case "추가":
				if(isset($t[$n])){
					$r = $mm . ($ik ? "상점 추가 해제" : " Shop Add Touch Disable");
					unset($t[$n]);
				}else{
					if(!isset($sub[4])){
						$r = $rm . ($ik ? "추가 <구매|판매> <아이템ID> <갯수> <가격>" : "Add(A) <Buy|Sell> <ItemID> <Amount> <Price>");
					}else{
						switch(strtolower($sub[1])){
							case "buy":
							case "b":
							case "shop":
							case "구매":
								$mode = "Buy";
							break;
							case "sell":
							case "s":
							case "판매":
								$mode = "Sell";
							break;
						}
						$i = Item::fromString($sub[2]);
						if(!isset($mode)){
							$r = "$sub[1] " . ($ik ? "는 잘못된 모드입니다. (구매/판매)" : "is invalid Mode (Buy/Sell)");
						}elseif($i->getID() == 0){
							$r = "$sub[2] " . ($ik ? "는 잘못된 아이템ID입니다." : "is invalid ItemID");
						}elseif(!is_numeric($sub[3]) || $sub[3] < 1){
							$r = "$sub[3] " . ($ik ? "는 잘못된 갯수입니다." : "is invalid count");
						}elseif(!is_numeric($sub[4]) || $sub[4] < 1){
							$r = "$sub[4] " . ($ik ? "는 잘못된 가격입니다." : "is invalid price");
						}else{
							$id = $i->getID() . ":" . $i->getDamage();
							$r = $mm . ($ik ? "대상 블럭을 터치해주세요." : "Touch the target block");
							$t[$n] = ["Type" => "Add", "Mode" => $mode, "Item" => $id, "Count" => floor($sub[3]), "Price" => floor($sub[4])];
						}
					}
				}
			break;
			case "del":
			case "d":
			case "삭제":
			case "제거":
				if(isset($t[$n])){
					$r = $mm . ($ik ? "상점 제거 해제" : " Shop Del Touch Disable");
					unset($t[$n]);
				}else{
					$r = $mm . ($ik ? "대상 블럭을 터치해주세요. " : "Touch the block");
					$t[$n] = ["Type" => "Del"];
				}
			break;
			case "reset":
			case "r":
			case "리셋":
			case "초기화":
				$sh = [];
				$r = $mm . ($ik ? " 리셋됨." : " Reset");
				$this->spawnCase();
			break;
			default:
				return false;
			break;
		}
		if(isset($r)) $sender->sendMessage($r);
		if($this->sh !== $sh){
			$this->sh = $sh;
			$this->saveYml();
		}
		$this->touch = $t;
		return true;
	}

	public function onPlayerRespawn(PlayerRespawnEvent $event){
		$this->spawnCase();
	}

	public function onPlayerInteract(PlayerInteractEvent $event){
		$b = $event->getBlock();
		if($b->getID() !== 20) $b = $b->getSide($event->getFace());
		$p = $event->getPlayer();
		$n = $p->getName();
		$t = $this->touch;
		$sh = $this->sh;
		$m = "[Shop] ";
		$ik = $this->isKorean();
		$pos = $this->getPos($b);
		if(isset($t[$n])){
			$tc = $t[$n];
			switch($tc["Type"]){
				case "Add":
					$this->addShop($pos, $tc["Mode"], $tc["Item"], $tc["Count"], $tc["Price"]);
					$m .= ($ik ? "상점이 생성되었습니다." : "Shop Create");
					unset($t[$n]);
				break;
				case "Del":
					if(!isset($sh[$pos])){
						$m .= $ik ? "이곳에는 상점이 없습니다." : "Shop is not exist here";
					}else{
						$this->delShop($pos);
						$m .= ($ik ? "상점이 제거되었습니다." : "Shop is Delete ");
						unset($t[$n]);
					}
				break;
			}
			$this->touch = $t;
		}elseif(isset($sh[$pos])){
			if($p->getGamemode() == 1){
				$m .= ($ik ? " 당신은 크리에이티브입니다." : " You - Creative mode");
			}else{
				$tap = $this->tap;
				$s = $sh[$pos];
				$i = Item::fromString($s[1]);
				$i->setCount($s[2]);
				$pr = $s[3];
				if(!isset($tap[$n]) || $tap[$n][1] !== $pos) $tap[$n] = [0, $pos];
				$c = microtime(true) - $tap[$n][0];
				$inv = $p->getInventory();
				switch($s[0]){
					case "Buy":
						if($c > 0){
							$m .= ($ik ? "구매하시려면 다시한번눌러주세요. \n 상점정보 : [구매] 아이디: $s[1] (갯수 : $s[2]) 가격 : $pr 원" : "If you want to buy, One more touch block \n StoreInfo : [Buy] ID: $s[1] (Count: $s[2]) Price: $pr $");
						}elseif($this->getMoney($p) < $pr){
							$m .= ($ik ? "돈이 부족합니다. \n 나의돈 : " . $this->getMoney($p) . " 원" : "You has less money than its price \nYour money : " . $this->getMoney($p) . "$");
						}else{
							$inv->addItem($i);
							$this->giveMoney($p, -$pr);
							$m .= ($ik ? "아이템을 구매하셨습니다. 아이디 : $s[1] (갯수 : $s[2]) 가격 : $pr 원 \n 나의 돈:" . $this->getMoney($p) . "$" : "You buy Item.  ID: $s[1] (Count: $s[2]) Price: $pr $ \n Your money:" . $this->getMoney($p) . "$");
						}
					break;
					case "Sell":
						if($c > 0){
							$m .= ($ik ? "판매하시려면 다시한번눌러주세요. \n 상점정보 : [판매] 아이디: $s[1] (갯수 : $s[2]) 가격 : $pr 원" : "If you want to sell, One more touch block \n StoreInfo : [Sell] ID: $s[1] (Count: $s[2]) Price: $pr $");
						}else{
							$cnt = 0;
							foreach($inv->getContents() as $ii){
								if($i->equals($ii, true)) $cnt += $ii->getCount();
							}
							if($cnt < $i->getCount()){
								$m .= ($ik ? "아이템이 부족합니다. \n 소유갯수 : " : "You has less Item than its count \n Your have : ") . $cnt;
							}else{
								$inv->removeItem($i, $p);
								$this->giveMoney($p, $pr);
								$m .= ($ik ? "아이템을 판매하셨습니다. 아이디 : $s[1] (갯수 : $s[2]) 가격 : $pr 원 \n 나의 돈 :" . $this->getMoney($p) . "$" : "You sell Item.  ID: $s[1] (Count: $s[2]) Price: $pr $ \n Your money:" . $this->getMoney($p) . "$");
							}
						}
					break;
				}
				$inv->sendContents($p);
				$this->tap[$n] = [microtime(true) + 1, $pos];
			}
		}else{
			return;
		}
		if(isset($m)) $p->sendMessage($m);
		$event->setCancelled();
		if($event->getItem()->isPlaceable()){
			$this->place[$p->getName()] = true;
		}
		$this->onBlockEvent($event, true);
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
		if(isset($this->sh[$this->getPos($event->getBlock())])){
			if($isTouch && $event->getItem()->isPlaceable()){
				$this->place[$p->getName()] = true;
			}
			if(!$p->hasPermission("shop.block")) $event->setCancelled();
			else $this->spawnCase(true);
		}
	}

	public function onTick(){
		foreach($this->getServer()->getOnlinePlayers() as $p){
			$n = $p->getName();
			$l = $p->getLevel()->getFolderName();
			if(!isset($this->level[$n])) $this->level[$n] = $l;
			elseif($this->level[$n] !== $l) $this->spawnCase(true);
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

	public function addShop($pos, $mode, $id, $cnt, $pr){
		if(isset($this->sh[$pos])) return false;
		$this->sh[$pos] = [$mode, $id, $cnt, $pr];
		$this->saveYml();
		$pos = explode(":", $pos);
		$l = $this->getServer()->getLevelByName($pos[3]);
		if($l != false) $l->setBlock(new Vector3($pos[0], $pos[1], $pos[2]), Block::get(20));
		return true;
	}

	public function delShop($pos){
		if(!isset($this->sh[$pos])) return false;
		unset($this->sh[$pos]);
		$this->saveYml();
		$pos = explode(":", $pos);
		$l = $this->getServer()->getLevelByName($pos[3]);
		if($l != false) $l->setBlock(new Vector3($pos[0], $pos[1], $pos[2]), Block::get(0));
		return true;
	}

	public function getPos($b){
		return $b->x . ":" . $b->y . ":" . $b->z . ":" . $b->getLevel()->getFolderName();
	}

	public function spawnCase($time = false){
		if(!$this->time) $this->time = microtime(true);
		if($time && time(true) - $this->time < 5) return;
		$this->time = time(true);
		$this->despawnCase();
		foreach($this->sh as $k => $v){
			if($this->eid > 2100000000) $this->eid = 9999;
			$i = $pk = new AddItemEntityPacket();
			$pk->eid = $this->eid;
			$pk->item = Item::fromString($v[1]);
			$pos = explode(":", $k);
			$pk->x = $pos[0] + 0.5;
			$pk->y = $pos[1];
			$pk->z = $pos[2] + 0.5;
			$pk->yaw = 0;
			$pk->pitch = 0;
			$pk->roll = 0;
			$this->dataPacket($pk, $k);
			$pk = new MoveEntityPacket();
			$pk->entities = [[$this->eid, $pos[0] + 0.5, $pos[1] + 0.25, $pos[2] + 0.5, 0, 0]];
			$this->dataPacket($pk, $k);
			$this->item[] = $this->eid;
			$this->eid++;
			$this->dataPacket($pk, $k);
		}
	}

	public function despawnCase(){
		foreach($this->item as $v){
			$pk = new RemoveEntityPacket();
			$pk->eid = $v;
			$this->dataPacket($pk);
		}
		$this->item = [];
	}

	public function dataPacket($pk, $pos = ""){
		foreach($this->getServer()->getOnlinePlayers() as $p){
			if($pk instanceof RemoveEntityPacket || $p->getLevel()->getFolderName() == explode(":", $pos)[3]) $p->directDataPacket($pk);
		}
	}

	public function loadYml(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		$this->sh = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "Shop.yml", Config::YAML))->getAll();
	}

	public function saveYml(){
		ksort($this->sh);
		$sh = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "Shop.yml", Config::YAML);
		$sh->setAll($this->sh);
		$sh->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}