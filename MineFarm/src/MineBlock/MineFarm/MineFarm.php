<?php

namespace MineBlock\MineFarm;

use pocketmine\plugin\PluginBase;
use pocketmine\level\generator\Generator;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockUpdateEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ExplosionPrimeEvent;
use pocketmine\event\inventory\FurnaceBurnEvent;
use pocketmine\event\inventory\FurnaceSmeltEvent;
use pocketmine\network\protocol\MessagePacket;
use pocketmine\network\protocol\AddItemEntityPacket;
use pocketmine\network\protocol\RemoveEntityPacket;
use pocketmine\network\protocol\MoveEntityPacket;
use pocketmine\scheduler\CallbackTask;
use pocketmine\level\Position;
use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\math\Math;
use pocketmine\Player;
use pocketmine\level\format\mcregion\Chunk;
use pocketmine\tile\Tile;
use pocketmine\inventory\InventoryHolder;
use pocketmine\block\Chest;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Int;
use pocketmine\nbt\tag\String;

class MineFarm extends PluginBase implements Listener{

	public function onLoad(){
		Generator::addGenerator(MineFarmGenerator::class, $this->name = "minefarm");
		$this->getServer()->getLogger()->info("[MineFarm] MineFarmGenerator is Loaded");
	}

	public function onEnable(){
		$this->tick = 0;
		$this->place = [];
		$this->touch = [];
		$this->tap = [];
		$this->item = [];
		$this->player = [];
		$this->spawn = [];
		$this->spawnTick = 0;
		$this->eid = 99999;
		$this->nt = ["Time" => 0, "Count" => 0];
		$s = $this->getServer();
		$this->path = $s->getDataPath() . "/plugins/! MineBlock/MineFarm/";
		$this->loadYml();
		foreach($s->getOnlinePlayers() as $p){
			$this->sendLogin($p, true);
			$this->spawn[strtolower($p->getName())] = $p->getPosition();
		}
		$gn = $s->getLevelType();
		$n = $this->name;
		$s->setConfigString("level-type", $n);
		if(!$s->isLevelLoaded($n) && !$s->loadLevel($n)) $s->generateLevel($n);
		$s->setConfigString("level-type", $gn);
		$s->getLogger()->info("[MineFarm] MineFarmWorld is Loaded");
		$s->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, "onTick"]), 10);
		$this->level = $s->getLevelByName($n);
		$s->getPluginManager()->registerEvents($this, $this);
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		if(!isset($sub[0])) return false;
		$ik = $this->isKorean();
		$rm = TextFormat::RED . "Usage: /";
		$mm = "[MineFarm] ";
		$smd = strtolower(array_shift($sub));
		$n = strtolower($sender->getName());
		$mn = $this->mn;
		$c = false;
		$sh = $this->sh;
		$t = $this->touch;
		switch(strtolower($cmd->getName())){
			case "myfarm":
				if(!$sender instanceof Player){
					$r = $mm . ($ik ? "게임내에서만 실행해주세요." : "Please run this command in-game");
				}else{
					$rm .= "MyFarm ";
					switch($smd){
						case "move":
						case "my":
						case "me":
						case "m":
						case "이동":
							if(!in_array($n, $this->mf["Farm"])){
								$r = $mm . ($ik ? "팜을 보유하고있지 않습니다." : "You don't have farm");
							}else{
								$sender->teleport($this->getPosition($n));
								$r = $mm . ($ik ? "나의 팜으로 텔레포트되었습니다. : " : "Teleported to your farm. : ") . $this->getNum($sender);
							}
						break;
						case "buy":
						case "b":
						case "구매":
							if(in_array($n, $this->mf["Farm"])){
								$r = $mm . ($ik ? "이미 팜을 보유하고있습니다." : "You already have farm");
							}elseif(!$this->mf["Sell"]){
								$r = $mm . ($ik ? "이 서버는 팜을 판매하지 않습니다.." : "This server not sell the farm");
							}elseif(!$this->hasMoney($n, $pr = $this->mf["Price"])){
								$r = $mm . ($ik ? "당신은 $pr 보다 돈이 적습니다. 나의 돈 : " : "You don't have $pr $. Your money : ") . $this->getMoney($n);
							}else{
								$this->takeMoney($n, $pr);
								$this->giveFarm($n);
								$r = $mm . ($ik ? "팜을 구매하였습니다. 나의 돈 : " : "Buy the farm. Your money : ") . $this->getMoney($n) . "\n/‡ " . $mm . ($ik ? "팜 번호 : " : "Farm Number : ") . $this->getNum($n);
							}
						break;
						case "visit":
						case "v":
						case "방문":
							if(!isset($sub[0]) || !$sub[0] || (is_numeric($sub[0]) && $sub[0] < 1)){
								$r = $mm . ($ik ? "이동 <팜번호 or 플레이어명>" : "Move <FarmNum or PlayerName>");
							}else{
								if(is_numeric($sub[0])){
									$fn = floor($sub[0]);
									$nm = $ik ? "번" : "";
								}else{
									$fn = strtolower($sub[0]);
									if(!in_array($fn, $this->mf["Farm"])){
										$r = $mm . $fn . ($ik ? "는 잘못된 플레이어명입니다." : " is invalid player");
									}else{
										$nm = $ik ? "님의" : "'s ";
									}
								}
								if(!isset($r)){
									if(!$this->isInvite($n, $fn)){
										$r = $mm . ($ik ? "$fn $nm 팜에 초대받지 않았습니다." : "You don't invited to $fn $nm farm");
									}else{
										$sender->teleport($this->getPosition($fn));
										$r = $mm . ($ik ? "$fn $nm 팜으로 텔레포트되었습니다." : "Teleported to $fn $nm Minefarm");
										if($p = $this->getServer()->getplayerExact($this->getOwnName($fn))) $p->sendMessage("/☜ [MineFarm] " . $n . ($ik ? "님이 당신의 팜에 방문햇습니다." : " is invited to your farm."));
									}
								}
							}
						break;
						case "invite":
						case "i":
						case "초대":
							if(!in_array($n, $this->mf["Farm"])){
								$r = $mm . ($ik ? "팜을 보유하고있지 않습니다." : "You don't have farm");
							}elseif(!isset($sub[0])){
								$r = $rm . ($ik ? "초대 <플레이어명>" : "Invite <PlayerName");
							}elseif($this->isInvite($sub[0] = strtolower($sub[0]), $n)){
								$r = $mm . $sub[0] . ($ik ? "님은 이미 초대된 상태입니다." : " is already invited");
							}else{
								$this->mf["Invite"][$n][$sub[0]] = false;
								$this->saveYml();
								$r = $mm . ($ik ? "$sub[0] 님을 팜에 초대합니다." : "Invite $sub[0] on my farm");
								if($p = $this->getServer()->getplayerExact($sub[0])) $p->sendMessage("/☜ [MineFarm] " . $n . ($ik ? "님이 당신을 팜에 초대하였습니다." : " invite you out to farm"));
							}
						break;
						case "share":
						case "s":
						case "초대":
							if(!in_array($n, $this->mf["Farm"])){
								$r = $mm . ($ik ? "마인팜을 보유하고있지 않습니다." : "You don't have MineFarm");
							}elseif(!isset($sub[0])){
								$r = $rm . ($ik ? "공유 <플레이어명>" : "Share <PlayerName");
							}elseif($this->isShare($sub[0] = strtolower($sub[0]), $n)){
								$r = $mm . $sub[0] . ($ik ? "님은 이미 공유된 상태입니다." : " is already shared");
							}else{
								$this->mf["Invite"][$n][$sub[0]] = true;
								$this->saveYml();
								$r = $mm . ($ik ? "$sub[0] 님에게 팜을 공유합니다." : "Shared your farm to $sub[0]");
								if($p = $this->getServer()->getplayerExact($sub[0])) $p->sendMessage("/☜ [MineFarm] " . $n . ($ik ? "님이 당신에게 팜을 공유하였습니다." : " shared the farm with you"));
							}
						break;
						case "kick":
						case "k":
						case "강퇴":
							if(!in_array($n, $this->mf["Farm"])){
								$r = $mm . ($ik ? "마인팜을 보유하고있지 않습니다." : "You don't have MineFarm");
							}elseif(!isset($sub[0])){
								$r = $rm . ($ik ? "강퇴 <플레이어명>" : "Kick <PlayerName");
							}elseif(!$this->isInvite($sub[0] = strtolower($sub[0]), $n)){
								$r = $mm . $sub[0] . ($ik ? "님은 초대되지 않았습니다." : " is not invited");
							}else{
								unset($this->mf["Invite"][$n][$sub[0]]);
								$this->saveYml();
								$r = $mm . ($ik ? "$sub[0] 님을 마인팜에서 강퇴합니다." : "Kick $sub[0] on my minefarm");
								if($p = $this->getServer()->getplayerExact($sub[0])) $p->sendMessage("/☜ [MineFarm] " . ($ik ? "$n 님의 팜에서 강퇴되었습니다." : "You are kicked from $n's Minefarm."));
							}
						break;
						case "list":
						case "l":
						case "목록":
							if(!in_array($n, $this->mf["Farm"])){
								$r = $mm . ($ik ? "마인팜을 보유하고있지 않습니다." : "You don't have MineFarm");
							}else{
								$page = 1;
								if(isset($sub[0]) && is_numeric($sub[0])) $page = round($sub[0]);
								$list = array_chunk($this->mf["Invite"][$n], 5, true);
								if($page >= ($c = count($list))) $page = $c;
								$r = $mm . ($ik ? "초대 (공유) 목록 (페이지" : "Invite(Share) List (Page") . " $page/$c) \n";
								$num = ($page - 1) * 5;
								if($c > 0){
									foreach($list[$page - 1] as $k => $v){
										$num++;
										$r .= "  [$num] " . (strlen($k) <= 3 ? ($ik ? "오류." : "Error.") : ("[" . ($ik ? ($v ? "공유" : "초대") : ($v ? "Share" : "Invite")) . "] $k\n"));
									}
								}
							}
						break;
						case "message":
						case "msg":
						case "메세지":
						case "tip":
						case "t":
						case "팁":
							if(in_array($n, $this->mf["Edge"])){
								unset($this->mf["Edge"][array_search($n, $this->mf["Edge"])]);
								$a = false;
							}else{
								$this->mf["Edge"][] = $n;
								$a = true;
							}
							$this->saveYml();
							$r = $mm . ($ik ? "이제 팁 메세지를 받" . ($a ? "" : "지않") . "습니다." : "Now " . ($a ? "" : "not ") . "show tip message");
						break;
						case "here":
						case "h":
						case "여기":
							if(!$this->isFarm($sender)){
								$r = $mm . ($ik ? "이곳은 팜이 아닙니다." : "Here is not Farm");
							}else{
								$r = $mm . ($ik ? "이곳의 팜 번호 : " : "Here farm number : ") . $this->getNum($sender, true) . ",  " . ($this->getOwnName($sender, true) !== false ? ($ik ? "주인 : " : "Own : ") . $this->getOwnName($sender, true) : "");
							}
						break;
						default:
							return false;
						break;
					}
				}
			break;
			case "minefarm":
				$rm .= "MineFarm ";
				switch($smd){
					case "give":
					case "g":
					case "지급":
						if(!isset($sub[0])){
							$r = $rm . ($ik ? "지급 <플레이어명> (지역번호)" : "Give(G) <PlayerName> (FarmNumber)");
						}elseif(!($p = $this->getServer()->getPlayer($sub[0]))){
							$r = $mm . $sub[0] . ($ik ? "는 잘못된 플레이어명입니다." : " is invalid player");
						}elseif(in_array(strtolower($p->getName()), $this->mf["Farm"])){
							$r = $mm . $sub[0] . ($ik ? "님은 이미 마인팜을 소유중입니다. " : " is already have minefarm");
						}else{
							$num = $this->giveFarm($p) + 1;
							$pn = $p->getName();
							$r = $mm . ($ik ? "$pn 님에게 마인팜을 지급했습니다. : " : "Give the minefarm to $pn : ") . ($num = $this->getNum($p));
							$p->sendMessage($mm . ($ik ? "마인팜을 지급받았습니다. : " : "Now you have your minefarm : ") . $num);
						}
					break;
					case "move":
					case "m":
					case "이동":
						if(!isset($sub[0]) || !$sub[0] || (is_numeric($sub[0]) && $sub[0] < 1)){
							$r = $mm . ($ik ? "이동 <땅번호 or 플레이어명>" : "Move <FarmNum or PlayerName>");
						}else{
							if(is_numeric($sub[0])){
								$n = floor($sub[0]);
								$nm = $ik ? "번" : "";
							}else{
								$n = $sub[0];
								if(!in_array($n, $this->mf["Farm"])){
									$r = $mm . $n . ($ik ? "는 잘못된 플레이어명입니다." : " is invalid player");
								}else{
									$nm = $ik ? "님의" : "'s ";
								}
							}
							if(!isset($r)){
								$sender->teleport($this->getPosition($n));
								$r = $mm . ($ik ? "$n $nm 마인팜으로 텔레포트되었습니다." : "Teleported to $n $nm Minefarm");
							}
						}
					break;
					case "here":
					case "h":
					case "여기":
						if(!$sender instanceof Player){
							$r = $mm . ($ik ? "게임내에서만 실행해주세요." : "Please run this command in-game");
						}elseif(!$this->isFarm($sender)){
							$r = $mm . ($ik ? "이곳은 팜이 아닙니다." : "Here is not Farm");
						}else{
							$r = $mm . ($ik ? "이곳의 팜 번호 : " : "Here farm number : ") . $this->getNum($sender, true) . ",  " . ($this->getOwnName($sender, true) !== false ? ($ik ? "주인 : " : "Own : ") . $this->getOwnName($sender, true) : "");
						}
					break;
					case "distace":
					case "d":
					case "거리":
					case "간격":
						if(!isset($sub[0])){
							$r = $rm . ($ik ? "거리 <숫자>" : "Distance(D) (Number)");
						}elseif(!is_numeric($sub[0]) || $sub[0] < 0){
							$r = $mm . $sub[0] . ($ik ? "는 잘못된 숫자입니다." : " is invalid number");
						}else{
							$this->mf["Distance"] = floor($sub[0]);
							$this->saveYml();
							$r = $mm . ($ik ? " 마인팜간 간격이 $sub[0] 으로 설정되엇습니다." : "minefarm distance is set to $sub[0]");
						}
					break;
					case "size":
					case "sz":
					case "크기":
						if(!isset($sub[0])){
							$r = $rm . ($ik ? "크기 <숫자>" : "Size(SZ) (Number)");
						}elseif(!is_numeric($sub[0])){
							$r = $mm . $sub[0] . ($ik ? "는 잘못된 숫자입니다." : " is invalid number");
						}else{
							$this->mf["Size"] = floor($sub[0]);
							$this->saveYml();
							$r = $mm . ($ik ? " 마인팜의 크기가 $sub[0] 으로 설정되엇습니다." : "minefarm size is set to $sub[0]");
						}
					break;
					case "air":
					case "a":
					case "공기":
						if(!isset($sub[0])){
							$r = $rm . ($ik ? "공기 <숫자>" : "Air(A) (Number)");
						}elseif(!is_numeric($sub[0]) || $sub[0] < 0){
							$r = $mm . $sub[0] . ($ik ? "는 잘못된 숫자입니다." : " is invalid number");
						}else{
							$this->mf["Air"] = floor($sub[0]);
							$this->saveYml();
							$r = $mm . ($ik ? " 마인팜의 공기지역 크기가 $sub[0] 으로 설정되엇습니다." : "minefarm air place size is set to $sub[0]");
						}
					break;
					case "sell":
					case "s":
					case "판매":
						$a = !$this->mf["Sell"];
						$this->mf["Sell"] = $a;
						$this->saveYml();
						$m = $mm . ($ik ? "이제 마인팜을 판매" . ($a ? "합" : "하지않습") . "니다." : "Now " . ($a ? "" : "not ") . "sell the minefarm");
					break;
					case "price":
					case "p":
					case "가격":
						if(!isset($sub[0])){
							$r = $rm . ($ik ? "가격 <숫자>" : "Money(Mn) (Number)");
						}elseif(!$sub[0] || !is_numeric($sub[0])){
							$r = $mm . $sub[0] . ($ik ? "는 잘못된 숫자입니다." : " is invalid number");
						}else{
							$this->mf["Price"] = floor($sub[0]);
							$this->saveYml();
							$m = $mm . ($ik ? "마인팜의 가격이 $sub[0] 으로 설정되엇습니다." : "minefarm distance is set to $sub[0]");
						}
					break;
					case "auto":
					case "at":
					case "자동":
						$a = !$this->mf["Auto"];
						$this->mf["Auto"] = $a;
						$this->saveYml();
						if($a){
							foreach($this->getServer()->getOnlinePlayers() as $p){
								if($this->giveFarm($p)) $p->sendMessage("[MineFarm] [Auto] " . ($ik ? "마인팜을 지급받았습니다. : " : "Now you gave minefarm. : ") . $this->getNum($p));
							}
						}
						$m = $mm . ($ik ? "이제 마인팜을 자동 분배" . ($a ? "합" : "하지않습") . "니다." : "Now " . ($a ? "" : "not ") . "auto give the minefarm");
					break;
					case "item":
					case "i":
					case "아이템":
						$a = !$this->mf["Item"];
						$this->mf["Item"] = $a;
						$this->saveYml();
						$m = $mm . ($ik ? "이제 기초 지급템을 " . ($a ? "줍" : "주지않습") . "니다." : "Now " . ($a ? "" : "not ") . "give the first item");
					break;
					case "list":
					case "l":
					case "목록":
						$page = 1;
						if(isset($sub[0]) && is_numeric($sub[0])) $page = max(floor($sub[0]), 1);
						$list = array_chunk($this->mf["Farm"], 5, true);
						if($page >= ($c = count($list))) $page = $c;
						$r = $mm . ($ik ? "마인팜 목록 (페이지" : "MineFarm List (Page") . " $page/$c) \n";
						$num = ($page - 1) * 5;
						if($c > 0){
							foreach($list[$page - 1] as $v){
								$num++;
								$r .= "  [$num] $v\n";
							}
						}
					break;
					case "reset":
					case "r":
					case "리셋":
						$this->mf["Farm"] = [];
						$this->mf["Invite"] = [];
						$this->saveYml();
						if($this->mf["Auto"]){
							foreach($this->getServer()->getOnlinePlayers() as $p){
								if($this->giveFarm($p)) $p->sendMessage("[MineFarm] [Auto] " . ($ik ? "마인팜을 지급받았습니다. : " : "Now you gave minefarm. : ") . $this->getNum($p));
							}
						}
						$r = $mm . ($ik ? "리셋됨" : "Reset");
					break;
					case "trim":
						$full = count($this->mf["Edge"]);
						$count = 0;
						foreach($this->mf["Edge"] as $k => $v){
							if(is_bool($v)){
								unset($this->mf["Edge"][$k]);
								$count++;
							}
						}
						if($count > 0) $this->saveYml();
						$r = $mm . ($ik ? "필요없는 데이터를 제거했습니다. 갯수 : " : "Delete useless data. Count : ") . $count . " [$full => " . count($this->mf["Edge"]) . "]";
					break;
					default:
						return false;
					break;
				}
			break;
			case "money":
				$mm = "[Money] ";
				$rm = TextFormat::RED . "Usage: /Money ";
				switch($smd){
					case "me":
					case "my":
					case "m":
					case "내돈":
					case "나":
						$r = $mm . ($ik ? "나의 돈 : " : "Your Money : ") . $this->getMoney($n) . ($ik ? "원  ,  랭킹 : " : "$  ,  Rank : ") . $this->getRank($n);
					break;
					case "see":
					case "view":
					case "v":
					case "보기":
						if(!isset($sub[0])){
							$r = $rm . ($ik ? "보기 <플레이어명>" : "View(V) <PlayerName>");
						}elseif(!($p = $this->getPlayer($sub[0]))){
							$r = $mm . $sub[0] . ($ik ? "은 잘못된 이름입니다." : " is invalid name");
						}else{
							$r = $mm . $p . ($ik ? "의 돈 : " : "'s Money : ") . $this->getMoney($p) . ($ik ? "원  ,  랭킹 : " : "$  ,  Rank : ") . $this->getRank($p);
						}
					break;
					case "pay":
					case "p":
					case "지불":
						if(!$sender instanceof Player){
							$r = $mm . ($ik ? "게임내에서 실행해주세요." : "Please run this command in-game");
						}elseif(!isset($sub[1])){
							$r = $rm . ($ik ? "지불 <플레이어명> <돈> " : "Pay <PlayerName> <Money>");
						}elseif(!($p = $this->getPlayer($sub[0])) || strtolower($n) == strtolower($sub[0])){
							$r = $mm . $sub[0] . ($ik ? "은 잘못된 이름입니다." : " is invalid name");
						}elseif(!is_numeric($sub[1]) || $sub[1] < 1){
							$r = $mm . $sub[1] . ($ik ? "은 잘못된 숫자입니다." : " is invalid number");
						}elseif(!$this->hasMoney($n, $sub[1])){
							$r = $mm . ($ik ? "돈이 $sub[1] 보다 부족합니다. (나의 돈 : " : "You don't have $sub[1] $ (You have : ") . $this->getMoney($n) . ($ik ? " 원" : "$");
						}else{
							$sub[1] = $sub[1] < 0 ? 0 : floor($sub[1]);
							$this->takeMoney($n, $sub[1]);
							$this->giveMoney($p, $sub[1]);
							$r = $mm . ($ik ? "당신은 $sub[1] 원을  $p 님에게 지불햇습니다. " : "You pay $sub[1] $ (To : $p)");
							if($player = $this->getServer()->getPlayerExact($p)) $player->sendMessage($mm . $n . ($ik ? "님이 당신에게 $sub[1] 원을 지불햇습니다. " : "$n pay $sub[2]$ to you"));
						}
					break;
					case "rank":
					case "r":
					case "랭킹":
					case "순위":
						if(isset($sub[0]) && is_numeric($sub[0]) && $sub[0] > 1){
							$r = $this->getRanks(round($sub[0]));
						}else{
							$r = $this->getRanks(1);
						}
					break;
					default:
						return false;
					break;
				}
			break;
			case "moneyop":
				$mm = "[Money] ";
				$rm = TextFormat::RED . "Usage: /MoneyOP ";
				switch($smd){
					case "set":
					case "s":
					case "설정":
						if(!isset($sub[1])){
							$r = $rm . ($ik ? "설정 <플레이어명> <돈>" : "Set(S) <PlayerName> <Money>");
						}elseif(!($p = $this->getPlayer($sub[0]))){
							$r = $mm . $sub[0] . ($ik ? "은 잘못된 이름입니다." : " is invalid name");
						}elseif(!is_numeric($sub[1]) || $sub[1] < 0){
							$r = $mm . $sub[1] . ($ik ? "은 잘못된 숫자입니다." : " is invalid number");
						}else{
							$sub[1] = $sub[1] < 0 ? 0 : floor($sub[1]);
							$this->setMoney($p, $sub[1]);
							$r = $mm . $p . ($ik ? "의 돈을 $sub[1] 원으로 설정했습니다.  " : "'s money is set to $sub[1] $");
							if($player = $this->getServer()->getPlayerExact($p)) $player->sendMessage($mm . ($ik ? "당신의 돈이 어드민에 의해 변경되었습니다. 나의 돈 : " : "Your money is change by admin. Your money : ") . $this->getMoney($p) . ($ik ? "원" : "$"));
						}
					break;
					case "give":
					case "g":
					case "지급":
						if(!isset($sub[1])){
							$r = $rm . ($ik ? "지급 <플레이어명> <돈>" : "Give(G) <PlayerName> <Money>");
						}elseif(!($p = $this->getPlayer($sub[0]))){
							$r = $mm . $sub[0] . ($ik ? "은 잘못된 이름입니다." : " is invalid name");
						}elseif(!is_numeric($sub[1]) || $sub[1] < 0){
							$r = $mm . $sub[1] . ($ik ? "은 잘못된 숫자입니다." : " is invalid number");
						}else{
							$sub[1] = $sub[1] < 0 ? 0 : floor($sub[1]);
							$this->giveMoney($p, $sub[1]);
							$r = $mm . ($ik ? "$p 님에게 $sub[1] 원을 지급햇습니다. " : "Give the $sub[1] $ to $p");
						}
					break;
					case "take":
					case "t":
					case "뺏기":
						if(!isset($sub[1])){
							$r = $rm . ($ik ? "뺏기 <플레이어명> <돈>" : "Take(T) <PlayerName> <Money>");
						}elseif(!($p = $this->getPlayer($sub[0]))){
							$r = $mm . $sub[0] . ($ik ? "은 잘못된 이름입니다." : " is invalid name");
						}elseif(!is_numeric($sub[1]) || $sub[1] < 0){
							$r = $mm . $sub[1] . ($ik ? "은 잘못된 숫자입니다." : " is invalid number");
						}else{
							$sub[1] = $sub[1] < 0 ? 0 : floor($sub[1]);
							$this->takeMoney($p, $sub[1]);
							$r = $mm . ($ik ? "$p 님에게서 $sub[1] 원을 빼앗았습니다. " : "Take the $sub[1] $ to $p");
						}
					break;
					break;
					case "clear":
					case "c":
					case "초기화":
						foreach($mn["Money"] as $k => $v)
							$mn["Money"][$k] = $mn["Default"];
						$m = $mm . ($ik ? "모든 플레이어의 돈이 초기화되었습다." : "All Player's money is reset");
						$c = true;
					break;
					case "default":
					case "d":
					case "기본":
						if(!isset($sub[0])){
							$r = $rm . ($ik ? "기본 <돈>" : "Defualt(D) <Money>");
						}elseif(!is_numeric($sub[0]) || $sub[0] < 0){
							$r = $mm . $sub[0] . ($ik ? "은 잘못된 숫자입니다." : " is invalid number");
						}else{
							$sub[0] = floor($sub[0]);
							$mn["Default"] = $sub[0];
							$r = $mm . ($ik ? "기초자금이 $sub[0] 로 설정되었습니다." : "Defualt money is set to $sub[0] $");
							$c = true;
						}
					break;
					case "nick":
					case "n":
					case "닉네임":
						$mn["Nick"] = !$mn["Nick"];
						$r = $mm . ($ik ? "닉네임 모드를 " . ($mn["Nick"] ? "켭" : "끕") . "니다." : "MickName mode is " . ($mn["Nick"] ? "On" : "Off"));
						$c = true;
					break;
					case "op":
					case "o":
					case "오피":
						$mn["OP"] = !$mn["OP"];
						$r = $mm . ($ik ? "오피를 랭킹에 포함" . ($mn["OP"] ? "" : "안") . "합니다." : "Show on rank the Op is " . ($mn["OP"] ? "On" : "Off"));
						$c = true;
					break;
					case "trim":
						$full = count($mn["Money"]);
						$count = 0;
						foreach($mn["Money"] as $k => $v){
							if($mn["Money"][$k] == $mn["Default"] || $this->getServer()->getNameBans()->isBanned($k) || !file_exists($this->getServer()->getDataPath() . "players/" . $k . ".dat")){
								unset($mn["Money"][$k]);
								$count++;
							}
						}
						if($count > 0) $c = true;
						$r = $mm . ($ik ? "필요없는 데이터를 제거했습니다. 갯수 : " : "Delete useless data. Count : ") . $count . " [$full => " . count($mn["Money"]) . "]";
					break;
					default:
						return false;
					break;
				}
			break;
			case "shop":
				$mm = "[Shop] ";
				$rm = TextFormat::RED . "Usage: /Shop ";
				switch($smd){
					case "add":
					case "a":
					case "추가":
						if(isset($t[$n])){
							$r = $mm . ($ik ? "상점 추가 해제" : " Shop Add Touch Disable");
							unset($t[$n]);
						}else{
							if(!isset($sub[3])){
								$r = $rm . ($ik ? "추가 <구매|판매> <아이템ID> <갯수> <가격>" : "Add(A) <Buy|Sell> <ItemID> <Amount> <Price>");
							}else{
								switch(strtolower($sub[0])){
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
								$i = Item::fromString($sub[1]);
								if(!isset($mode)){
									$r = "$sub[0] " . ($ik ? "는 잘못된 모드입니다. (구매/판매)" : "is invalid Mode (Buy/Sell)");
								}elseif($i->getID() == 0){
									$r = "$sub[1] " . ($ik ? "는 잘못된 아이템ID입니다." : "is invalid ItemID");
								}elseif(!is_numeric($sub[2]) || $sub[2] < 1){
									$r = "$sub[2] " . ($ik ? "는 잘못된 갯수입니다." : "is invalid count");
								}elseif(!is_numeric($sub[3]) || $sub[3] < 0){
									$r = "$sub[3] " . ($ik ? "는 잘못된 가격입니다." : "is invalid price");
								}else{
									$id = $i->getID() . ":" . $i->getDamage();
									$r = $mm . ($ik ? "대상 블럭을 터치해주세요." : "Touch the target block");
									$t[$n] = ["Type" => "Add", "Mode" => $mode, "Item" => $id, "Count" => floor($sub[2]), "Price" => floor($sub[3])];
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
			case "login":
				if($this->isLogin($sender)){
					$sender->sendMessage($mm . ($ik ? "이미 로그인되었습니다." : "Already logined"));
				}else{
					$this->login($sender, $smd, false, isset($sub[0]) ? $sub[0] : "");
				}
			break;
			case "register":
				if($this->isRegister($sender)){
					$sender->sendMessage($mm . ($ik ? "이미 가입되었습니다." : "Already registered"));
				}elseif(!isset($sub[0]) || $sub[0] == "" || $smd !== $sub[0]){
					return false;
				}elseif(strlen($smd) < 5){
					$sender->sendMessage($mm . ($ik ? "비밀번호가 너무 짧습니다." : "Password is too short"));
					return false;
				}else{
					$this->register($sender, $smd);
					if(!$sender->isOp()) $this->login($sender, $smd);
				}
			break;
			case "loginop":
				if(isset($sub[0])) $sub[0] = strtolower($sub[0]);
				switch(strtolower($smd)){
					case "del":
					case "d":
					case "제거":
					case "탈퇴":
						if(!isset($sub[0]) || $sub[0] == "" || !isset($this->lg[$sub[0]])){
							$sender->sendMessage($mm . ($ik ? "<플레이어명>을 확인해주세요." : "Please check <PlayerName>"));
							return false;
						}else{
							unset($this->lg[$sub[0]]);
							$sender->sendMessage($mm . ($ik ? "$sub[0] 님의 비밀번호을 제거합니다." : "Delete $sub[0] 's password"));
						}
					break;
					case "change":
					case "c":
						if(!isset($sub[0]) || $sub[0] == "" || !isset($this->lg[$sub[0]])){
							$sender->sendMessage($mm . ($ik ? "<플레이어명>을 확인해주세요." : "Please check <PlayerName>"));
							return false;
						}else{
							$this->lg[$sub[0]]["PW"] = hash("sha256", $sub[1]);
							$sender->sendMessage($mm . $sub[0] . ($ik ? "님의 비밀번호를 바꿨습니다. : " : "'s Password is changed : ") . "$sub[1]");
						}
					break;
					case "reset":
					case "r":
					case "리셋":
					case "초기화":
						$this->lg = [];
						$r = $mm . ($ik ? " 리셋됨." : " Reset");
					break;
					case "trim":
						$full = count($list = glob($a = $this->getServer()->getDataPath() . "players/*.dat"));
						$count = 0;
						foreach($list as $v){
							if(!isset($this->lg[$n = strtolower(str_replace([$this->getServer()->getDataPath() . "players/", ".dat"], ["", ""], $v))])){
								if($p = $this->getServer()->getPlayerExact($n)) $p->close($ik ? "플레이어 데이터 제거" : "Delete player data");
								@unlink($v);
								$count++;
							}
						}
						if($count > 0) $c = true;
						$r = $mm . ($ik ? "필요없는 데이터를 제거했습니다. 갯수 : " : "Delete useless data. Count : ") . $count . " [$full => " . count(glob($this->getServer()->getDataPath() . "/players/*.dat")) . "]";
					break;
				}
				$this->saveYml();
			break;
		}
		if(isset($r)) $sender->sendMessage($r);
		if(isset($m)) $this->getServer()->broadcastMessage($m);
		if($c && $this->mn !== $mn) $this->mn = $mn;
		if($this->sh !== $sh){
			$this->sh = $sh;
			$this->saveYml();
		}
		$this->touch = $t;
		return true;
	}

	public function onPlayerJoin(PlayerJoinEvent $event){
		if($this->mf["Auto"]) $this->giveFarm($event->getPlayer());
		if(!isset($this->mn["Money"][$n = strtolower($event->getPlayer()->getName())])){
			$this->mn["Money"][$n] = $this->mn["Default"];
			$this->saveYml();
		}
		$this->sendLogin($event->getPlayer(), true);
		$event->setJoinMessage("");
	}

	public function onPlayerQuit(PlayerQuitEvent $event){
		if(!$this->isRegister($p = $event->getPlayer()) || isset($this->mn["Money"][$n = strtolower($event->getPlayer()->getName())]) && $this->mn["Money"][$n] == $this->mn["Default"] || $this->getServer()->getNameBans()->isBanned($n)){
			unset($this->mn["Money"][$n = strtolower($p->getName())]);
			if(!$this->isRegister($p)){ // $p instanceof Player && count($p->getInventory()->getContents()) <= 0){
				@unlink($this->getServer()->getDataPath() . "players/" . $n . ".dat");
				$this->getServer()->getLogger()->info("[Login] " . ($this->isKorean() ? "자동으로 $n 님의 플레이어 데이터를 제거힙니다." : "Auto delete $n 's data"));
			}
			$this->saveYml();
			$event->setQuitMessage("");
		}elseif($this->isLogin($p)){
			$this->unLogin($event->getPlayer());
			$event->setQuitMessage("/☆ [" . ($this->isKorean() ? "퇴장" : "Quit") . "]  " . $p->getName());
		}else{
			$event->setQuitMessage("");
		}
	}

	public function onPlayerDeath(PlayerDeathEvent $event){
		$event->setDeathMessage("/♣ [" . ($this->isKorean() ? "사망" : "Died") . "]  " . $event->getEntity()->getName());
	}

	public function onPlayerInteract(PlayerInteractEvent $event){
		$this->onBlockEvent($event, false, true);
	}

	public function onBlockBreak(BlockBreakEvent $event){
		$this->onBlockEvent($event, true);
	}

	public function onBlockPlace(BlockPlaceEvent $event){
		$this->onBlockEvent($event);
	}

	public function onBlockEvent($event, $isBreak = false, $isTouch = false){
		if(!$this->isLogin($p = $event->getPlayer())){
			$event->setCancelled();
			return;
		}
		$b = $event->getBlock();
		$i = $event->getItem();
		$ik = $this->isKorean();
		$n = strtolower($p->getName());
		$pos = $this->getPos($b);
		if($isTouch){
			$bb = $b->getID() === 20 ? $b : $b->getSide($event->getFace());
			$t = $this->touch;
			$sh = $this->sh;
			$m = "/‡ [Shop] ";
			$bpos = $this->getPos($bb);
			if(isset($t[$n])){
				$tc = $t[$n];
				switch($tc["Type"]){
					case "Add":
						$this->addShop($bpos, $tc["Mode"], $tc["Item"], $tc["Count"], $tc["Price"]);
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
						if($p->getGamemode() == 1){
							$m .= ($ik ? "당신은 크리에이티브입니다.\n 상점정보 : [구매] 아이디: $s[1] (갯수 : $s[2]) 가격 : $pr 원" : "You are creative. \n StoreInfo : [Buy] ID: $s[1] (Count: $s[2]) Price: $pr $");
						}elseif($c > 0){
							$m .= ($ik ? "구매하시려면 다시한번눌러주세요. \n 상점정보 : [구매] 아이디: $s[1] (갯수 : $s[2]) 가격 : $pr 원" : "If you want to buy, One more touch block \n StoreInfo : [Buy] ID: $s[1] (Count: $s[2]) Price: $pr $");
						}elseif(!$this->hasMoney($n, $pr)){
							$m .= ($ik ? "돈이 부족합니다. \n 나의돈 : " . $this->getMoney($n) . " 원" : "You has less money than its price \nYour money : " . $this->getMoney($n) . "$");
						}else{
							$inv->addItem($i);
							$this->takeMoney($n, $pr);
							$m .= ($ik ? "아이템을 구매하셨습니다. 아이디 : $s[1] (갯수 : $s[2]) 가격 : $pr 원 \n 나의 돈:" . $this->getMoney($n) . "$" : "You buy Item.  ID: $s[1] (Count: $s[2]) Price: $pr $ \n Your money:" . $this->getMoney($n) . "$");
						}
					break;
					case "Sell":
						if($p->getGamemode() == 1){
							$m .= ($ik ? "당신은 크리에이티브입니다.\n 상점정보 : [판매] 아이디: $s[1] (갯수 : $s[2]) 가격 : $pr 원" : "You are creative. \n StoreInfo : [Sell] ID: $s[1] (Count: $s[2]) Price: $pr $");
						}elseif($c > 0){
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
								$this->giveMoney($n, $pr);
								$m .= ($ik ? "아이템을 판매하셨습니다. 아이디 : $s[1] (갯수 : $s[2]) 가격 : $pr 원 \n 나의 돈 :" . $this->getMoney($n) . "$" : "You sell Item.  ID: $s[1] (Count: $s[2]) Price: $pr $ \n Your money:" . $this->getMoney($n) . "$");
							}
						}
					break;
				}
				$inv->sendContents($p);
				$this->tap[$n] = [microtime(true) + 1, $pos];
			}else
				$shop = true;
			if(!isset($shop)){
				if(isset($m)) $p->sendMessage($m);
				$event->setCancelled();
				if($i->isPlaceable()) $this->place[$n] = true;
			}
		}elseif($isBreak){
			if(isset($this->sh[$this->getPos($b)])){
				if(!$p->hasPermission("shop.block")) $event->setCancelled();
			}
		}else{
			if(isset($this->place[$n])){
				$event->setCancelled();
				unset($this->place[$n]);
			}
		}
		if(strtolower($b->getLevel()->getFolderName()) == strtolower($this->mf["MineWorld"]) && !$p->hasPermission("minefarm.block")){
			if(!($isTouch && !$b->getID() == 58)) $event->setCancelled();
			if($isBreak && ($b->getID() . ":" . $b->getDamage()) == $this->mf["MineBlock"] && count($b->getDrops($i)) !== 0){
				if($p->isSurvival() && ($p->lastBreak + $b->getBreakTime($i) - 1) >= microtime(true)){
					$this->getServer()->getLogger()->warning($r = ("[MineFarm] " . $p->getName() . ($ik ? "님이 비정상적인 광물파괴를 시도했습니다." : " is break block wrongly!")));
					$p->kick($ik ? "버그" : "Cheat");
				}else{
					$p->lastBreak = microtime(true);
					$id = $this->mine[array_rand($this->mine)];
					$p->getInventory()->addItem(Item::get($this->mine[array_rand($this->mine)]));
					if($i instanceof Item){
						if($i->isTool()){
							$i = Item::get($i->getID(), $i->getDamage() + 2, 1);
							$p->getInventory()->setItemInHand($i->getDamage() + 2 < $i->getMaxDurability() ? $i : Item::get(0, 0, 0));
						}
					}
				}
			}
		}
		if($event->isCancelled() || strtolower($p->getLevel()->getName()) !== $this->name || $p->hasPermission("minefarm.block")) return;
		if($this->isFarm($b)){
			if(!$this->isOwn($p, $b) && !$this->isShare($p, $b)){
				$event->setCancelled();
				return;
			}elseif($isBreak){
				if($this->isMain($b) && $b->y < 8){
					if($b->y > 2 && $b->y < 7){
						if($p->isSurvival() && ($p->lastBreak + $b->getBreakTime($i) - 1) >= microtime(true)){
							$this->getServer()->getLogger()->warning($r = ("[MineFarm] " . $p->getName() . ($ik ? "님이 비정상적인 광물파괴를 시도했습니다." : " is break block wrongly!")));
							$event->setCancelled();
							$p->kick($ik ? "핵" : "Cheat");
						}else{
							$x = $b->x % 16;
							$z = $b->z % 16;
							if(($x == 5 || $x == 10) && ($z == 5 || $z == 10)){
								$id = 17;
							}elseif($x > 4 && $x < 11 && $z > 4 && $z < 11 && count($b->getDrops($i)) !== 0){
								$id = $this->drop[array_rand($this->drop)];
							}else{
								$event->setCancelled();
							}
						}
						if(isset($id)) $this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "blockRegen"], [$b, $id]), 10);
					}else{
						$event->setCancelled();
					}
				}
				if($event->isCancelled()) return;
				$event->setCancelled();
				$drops = $b->getDrops($i);
				$b->onBreak($i);
				$tile = $b->getLevel()->getTile($b);
				if($tile instanceof Tile){
					if($tile instanceof InventoryHolder){
						if($tile instanceof Chest) $tile->unpair();
						foreach($tile->getInventory()->getContents() as $chestItem){
							if(!($p instanceof Player)) $b->getLevel()->dropItem($b, $chestItem);
							else $p->getInventory()->addItem($chestItem);
						}
					}
					$tile->close();
				}
				if($i instanceof Item){
					if($i->isTool()){
						$i = Item::get($i->getID(), $i->getDamage() + 2, 1);
						$p->getInventory()->setItemInHand($i->getDamage() < $i->getMaxDurability() ? $i : Item::get(0, 0, 0));
					}
				}
				foreach($drops as $drop){
					if($drop[2] <= 0) continue;
					elseif($p instanceof Player && $p->isSurvival()) $p->getInventory()->addItem(Item::get($drop[0], $drop[1], $drop[2]));
					else continue;
				}
			}elseif($isTouch){
				if($this->isMain($b) && $b->y < 8) $event->setCancelled();
				$bf = $b->getSide($event->getFace());
				if($bf->y > 2 && $bf->y < 7){
					$x = $bf->x % 16;
					$z = $bf->z % 16;
					if(($x == 5 || $x == 10) && ($z == 5 || $z == 10)){
						$id = 17;
					}elseif($x > 4 && $x < 11 && $z > 4 && $z < 11){
						$id = $this->drop[array_rand($this->drop)];
					}else{
						$event->setCancelled();
						return;
					}
					$this->blockRegen($bf, $id);
				}
			}else{
				if($this->isMain($b) && $b->y < 8) $event->setCancelled();
			}
		}else{
			$event->setCancelled();
		}
	}

	public function onBlockUpdate(BlockUpdateEvent $event){
		$b = $event->getBlock();
		if(in_array($b->getID(), [8, 9, 10, 11])) $event->setCancelled();
	}

	public function onExplosionPrime(ExplosionPrimeEvent $event){
		$event->setCancelled();
	}

	public function onEntityDamage(EntityDamageEvent $event){
		if(($e = $event->getEntity()) instanceof Player && !$this->isLogin($e)) $event->setCancelled();
	}

	public function onPlayerRespawn(PlayerRespawnEvent $event){
		$this->spawn[strtolower($event->getPlayer()->getName())] = $event->getRespawnPosition();
	}

	public function onPlayerCommandPreprocess(PlayerCommandPreprocessEvent $event){
		$p = $event->getPlayer();
		if(!$this->isLogin($p) && !in_array(strtolower(explode(" ", substr($event->getMessage(), 1))[0]), ["register", "login"])) $event->setCancelled($this->sendLogin($p));
	}

	public function onPlayerDropItem(PlayerDropItemEvent $event){
		if(!$this->isLogin($event->getPlayer())) $event->setCancelled();
	}

	public function onPlayerItemConsume(PlayerItemConsumeEvent $event){
		if(!$this->isLogin($event->getPlayer())) $event->setCancelled();
	}

	public function onInventoryOpen(InventoryOpenEvent $event){
		if($event->getPlayer()->getInventory() !== $event->getInventory() && !$this->isLogin($event->getPlayer())) $event->setCancelled();
	}

	public function onFuranceBurn(FurnaceBurnEvent $event){
		$event->getFurnace()->namedtag["CookTime"] = 200;
	}

	public function onFuranceSmelt(FurnaceSmeltEvent $event){
		$event->getFurnace()->namedtag["BurnTime"] = 0;
		$event->getFurnace()->namedtag["CookTime"] = 200;
	}

	public function onTick(){
		$ik = $this->isKorean();
		$pk = new MessagePacket();
		$this->tick++;
		foreach($this->getServer()->getOnlinePlayers() as $p){
			if(!isset($this->move[$n = strtolower($p->getName())])) $this->move[$n] = $p->getPosition();
			if(!$this->isLogin($p)){
				if(!isset($this->spawn[$n])) $this->spawn[$n] = $p->getPosition();
				$p->teleport($this->spawn[$n]);
				continue;
			}
			if(!isset($this->move[$n = strtolower($p->getName())])) $this->move[$n] = $p->getPosition();
			$isTp = true;
			if(strtolower($p->getLevel()->getName()) == $this->name && !$p->hasPermission("minefarm.move") && (!$this->isFarm($p) || !$this->isOwn($p, $p) && !$this->isInvite($p, $p))){
				$p->teleport($this->move[$n]);
			}else{
				$this->move[$n] = $p->getPosition();
			}
			if($this->tick == 20){
				if(in_array($n, $this->mf["Edge"])){
					$s = "\n                         ";
					$pk->message = "\n\n\n\n\n$s" . ($ik ? "보유 팜 : " : "Your Farm : ") . (in_array(strtolower($p->getName()), $this->mf["Farm"]) ? $this->getNum($p) : ($ik ? "없음" : "None")) . $s . ($this->isFarm($p) ? ($ik ? "여기 팜 : " : "Here farm : ") . $this->getNum($p, true) . ",  " . ($this->getOwnName($p, true) !== false ? ($ik ? "주인 : " : "Own : ") . $this->getOwnName($p, true) : "") : "") . $s . ($ik ? "나의 돈 : " : "Your Money : ") . $this->getMoney($p->getName()) . "$s X: " . floor($p->x) . " Y: " . floor($p->y) . " Z: " . floor($p->z) . " World: " . $p->getLevel()->getFolderName();
					$p->directDataPacket($pk);
				}
				if(!isset($this->nick)) $this->nick = true;
				if(!$this->nick && !$this->mn["Nick"]) return;
				$this->nick = $this->mn["Nick"];
				$mn = $this->mn["Money"];
				arsort($mn);
				$num = 1;
				foreach($mn as $k => $v){
					if(!$this->mn["OP"] && $this->getServer()->isOp($k)) $rank = "OP";
					elseif(!$this->getMoney($k)) $rank = "-";
					else{
						if(!isset($same)) $same = [$v, $num];
						if($v == $same[0]){
							$rank = $same[1];
						}else{
							$rank = $num;
							$same = [$v, $num];
						}
						$num++;
					}
					if(!($player = $this->getServer()->getPlayerExact($k))) continue;
					$n = $player->getDisplayName();
					$nt = $this->mn["Nick"] ? str_replace(["%name", "%rank", "%money", "%farm"], [$n, $rank, $v, in_array(strtolower($player->getName()), $this->mf["Farm"]) ? $this->getNum($player) : "-"], $this->mn["Nick_Format"]) : $n;
					if($player->getNameTag() !== $nt) $player->setNameTag($nt);
				}
			}
			if($this->spawnTick >= 100){
				$this->spawnTick = 0;
				$this->spawnCase();
			}else{
				$this->spawnTick++;
			}
		}
		if($this->tick > 20) $this->tick = 0;
		if($this->an["On"] && count($this->an["Message"]) !== 0){
			if($this->an["Time"] > $this->nt["Time"] / 2){
				$this->nt["Time"]++;
			}else{
				$this->nt["Time"] = 0;
				if(count($this->getServer()->getOnlinePlayers()) > 0){
					if(!isset($this->an["Message"][$this->nt["Count"]])) $this->nt["Count"] = 0;
					$this->getServer()->broadCastMessage(str_replace("\\n", "\n", $this->an["Message"][$this->nt["Count"]]));
					$this->nt["Count"]++;
				}
			}
		}
	}

	public function blockRegen($b, $id){
		$i = Item::fromString($id);
		$b->getLevel()->setBlock($b, Block::get($i->getID(), $i->getDamage()), false);
	}

	public function giveFarm($name){
		if($pp = $this->getServer()->getPlayerExact($name)) $p = $pp;
		if($name instanceof Player){
			$p = $name;
			$name = $p->getName();
		}
		if(in_array(strtolower($name), $this->mf["Farm"])) return false;
		$this->mf["Farm"][] = strtolower($name);
		$this->mf["Invite"][strtolower($name)] = [];
		$this->saveYml();
		if(isset($p)) $p->setSpawn($this->getPosition($name));
		if($this->mf["Item"]){
			if(isset($p)){
				$p->sendMessage("[MineFarm] " . ($this->isKorean() ? "마인팜 지하에는 광물과 나무를 캘수있는 장소가 있습니다." : "There are infinity ores and infinity trees (at underground of minefarm)"));
				foreach($this->mf["Items"] as $item){
					$i = Item::fromString($item[0]);
					$i->setCount($item[1]);
					if($p instanceof Player){
						$p->getInventory()->addItem($i);
					}
				}
			}else{
				$this->level->setBlock($pos = $this->getPosition($name)->add(1, -3, 0), Block::get(54), true, true);
				$nbt = new Compound(false, [new Enum("Items", []), new String("id", 54), new Int("x", $pos->x), new Int("y", $pos->y), new Int("z", $pos->z)]);
				$nbt->Items->setTagType(NBT::TAG_Compound);
				$chest = Tile::createTile("Chest", $this->level->getChunk($pos->x >> 4, $pos->z >> 4), $nbt);
				foreach($this->mf["Items"] as $item){
					$i = Item::fromString($item[0]);
					$i->setCount($item[1]);
					$chest->getInventory()->addItem($i);
				}
			}
		}
		return true;
	}

	public function isFarm($farm){
		if($farm instanceof Position) return strtolower($farm->getLevel()->getName()) == $this->name ? $this->isFarm($this->level->getChunk($farm->x >> 4, $farm->z >> 4)) : false;
		if($farm instanceof Chunk){
			$x = $farm->getX();
			$z = $farm->getZ();
			$dd = $this->mf["Size"] + $this->mf["Air"];
			$d = $this->mf["Distance"] + 1 + $dd;
			return $x >= 0 && $x % $d <= $dd && $z >= 0 && $z % $d <= $dd;
		}else{
			return false;
		}
	}

	public function isLand($farm){
		if($farm instanceof Position) return strtolower($farm->getLevel()->getName()) == $this->name ? $this->isFarm($this->level->getChunk($farm->x >> 4, $farm->z >> 4)) : false;
		if($farm instanceof Chunk){
			$x = $farm->getX();
			$z = $farm->getZ();
			$dd = $this->mf["Size"];
			$d = $this->mf["Distance"] + 1 + $this->mf["Air"] + $dd;
			return $x >= 0 && $x % $d < $dd && $z >= 0 && $z % $d < $dd;
		}else{
			return false;
		}
	}

	public function isMain($farm){
		if($farm instanceof Position) return strtolower($farm->getLevel()->getName()) == $this->name ? $this->isFarm($this->level->getChunk($farm->x >> 4, $farm->z >> 4)) : false;
		if($farm instanceof Chunk){
			$x = $farm->getX();
			$z = $farm->getZ();
			$d = $this->mf["Distance"] + 1 + $this->mf["Size"] + $this->mf["Air"];
			return $x >= 0 && $x % $d === 0 && $z >= 0 && $z % $d === 0;
		}else{
			return false;
		}
	}

	public function isOwn($name, $farm){
		if($name instanceof Player) $name = $name->getName();
		return in_array(strtolower($name), $this->mf["Farm"]) ? $this->getNum($name) == $this->getNum($farm, true) : false;
	}

	public function isInvite($name, $farm){
		if($this->isOwn($name, $farm)) return true;
		if($name instanceof Player) $name = $name->getName();
		if(($this->isFarm($farm) || $this->isFarm($this->getPosition($farm, true))) && $on = $this->getOwnName($farm, true)){return isset($this->mf["Invite"][$on][strtolower($name)]);}
		return false;
	}

	public function isShare($name, $farm){
		if($this->isOwn($name, $farm)) return true;
		if($name instanceof Player) $name = $name->getName();
		if(($this->isFarm($farm) || $this->isFarm($this->getPosition($farm))) && $on = $this->getOwnName($farm, true)){return isset($this->mf["Invite"][$on][$name = strtolower($name)]) && $this->mf["Invite"][$on][$name] === true;}
		return false;
	}

	public function getNum($farm, $isPos = false){
		$d = $this->mf["Distance"] + 1 + $this->mf["Size"] + $this->mf["Air"];
		if(!$isPos && $farm instanceof Player) $farm = $farm->getName();
		if($farm instanceof Position){
			$dd = $this->mf["Size"] + $this->mf["Air"];
			$d = $this->mf["Distance"] + 1 + $dd;
			return floor(($farm->x >> 4) / $d) + floor(($farm->z >> 4) / $d) * 10 + 1;
		}elseif($farm instanceof Chunk){
			return $this->getNum(new Position($farm->x * 16, 12, $farm->z * 16, $this->level));
		}else{
			return array_search(strtolower($farm), $this->mf["Farm"]) + 1;
		}
		return false;
	}

	public function getOwnName($farm, $isPos = false){
		if(($n = $this->getNum($farm, $isPos)) === false) return false;
		return isset($this->mf["Farm"][$n - 1]) ? $this->mf["Farm"][$n - 1] : false;
	}

	public function getPosition($farm, $isPos = false){
		$d = $this->mf["Distance"] + 1 + $this->mf["Size"] + $this->mf["Air"];
		if(!$isPos && $farm instanceof Player) $farm = $farm->getName();
		if($farm instanceof Position){
			return new Position(($farm->x >> 4) * 16 * $d + 8, 12, ($farm->z >> 4) * 16 * $d + 8, $this->level);
		}elseif($farm instanceof Chunk){
			return new Position($farm->x * 16 * $d + 8, 12, $chunk->z * 16 * $d + 8, $this->level);
		}elseif(is_numeric($farm)){
			$farm = floor($farm - 1);
			$x = $farm % 10;
			$z = floor(($farm - $x) / 10);
			return new Position($x * 16 * $d + 8, 12, $z * 16 * $d + 8, $this->level);
		}else{
			return $this->getPosition($this->getNum($farm));
		}
	}

	public function getMP($name = ""){
		if(!$name) return false;
		if(isset($this->mn["Money"][$name = strtolower($name)])) return ["Player" => $name, "Money" => $this->mn["Money"][$name]];
		else return false;
	}

	public function getPlayer($name = ""){
		return !$this->getMP($name) ? false : $this->getMP($name)["Player"];
	}

	public function getMoney($name = ""){
		return !$this->getMP($name) ? false : $this->getMP($name)["Money"];
	}

	public function hasMoney($name = "", $money = 0){
		if(!$m = $this->getMoney($name)) return false;
		else return $money <= $m;
	}

	public function setMoney($name = "", $money = 0){
		$mn = $this->mn["Money"];
		$name = strtolower($name);
		if(!is_numeric($money) || $money < 0) $money = 0;
		if(!$name && !$all && !$this->getMoney($name)){
			return false;
		}else{
			$mn[strtolower($name)] = floor($money);
		}
		if($this->mn["Money"] !== $mn){
			$this->mn["Money"] = $mn;
			$this->saveYml();
		}
		return true;
	}

	public function giveMoney($name = "", $money = 0){
		if(!is_numeric($money) || $money < 0) $money = 0;
		if(!$name && !$all && !$this->getMoney($name)){
			return false;
		}else{
			$this->setMoney($name, $this->getMoney($name) + $money);
		}
		return true;
	}

	public function takeMoney($name = "", $money = 0){
		if(!is_numeric($money) || $money < 0) $money = 0;
		if(!$name && !$all && !$this->getMoney($name)){
			return false;
		}else{
			$getMoney = $this->getMoney($name);
			if($getMoney < $money) $money = $getMoney;
			$this->setMoney($name, $this->getMoney($name) - $money);
		}
		return true;
	}

	public function getRanks($page = 1){
		$m = $this->mn["Money"];
		arsort($m);
		$ik = $this->isKorean();
		$list = ceil(count($m) / 5);
		if($page >= $list) $page = $list;
		$r = "[Rank] (" . ($ik ? "페이지" : "Page") . " $page/$list) \n";
		$num = 1;
		foreach($m as $k => $v){
			if(!$this->mn["OP"] && $this->getServer()->isOp($k)) continue;
			if(!isset($same)) $same = [$v, $num];
			if($v == $same[0]){
				$rank = $same[1];
			}else{
				$rank = $num;
				$same = [$v, $num];
			}
			if($num + 5 > $page * 5 && $num <= $page * 5) $r .= "  [" . ($v > 0 ? $rank : "-") . "] $k : $v \n";
			$num++;
		}
		return $r;
	}

	public function getRank($name = ""){
		if(!$name) return false;
		$m = $this->mn["Money"];
		arsort($m);
		$num = 1;
		if(!$this->mn["OP"] && $this->getServer()->isOp($name)) return "OP";
		elseif(!$this->getMoney($name)) return "-";
		else{
			foreach($m as $k => $v){
				if(!$this->mn["OP"] && $this->getServer()->isOp($k)) continue;
				if(!isset($same)) $same = [$v, $num];
				if($v == $same[0]){
					$rank = $same[1];
				}else{
					$rank = $num;
					$same = [$v, $num];
				}
				$num++;
				if($k == strtolower($name)) return $rank;
				else continue;
			}
		}
		return false;
	}

	public function addShop($pos, $mode, $id, $cnt, $pr){
		if(isset($this->sh[$pos])) return false;
		$this->sh[$pos] = [$mode, $id, $cnt, $pr];
		$this->saveYml();
		$pos = explode(":", $pos);
		$l = $this->getServer()->getLevelByName($pos[3]);
		$pos[3] = $l;
		if($l != false) $l->setBlock(new Position($pos[0], $pos[1], $pos[2]), Block::get(20));
		return true;
	}

	public function delShop($pos){
		if(!isset($this->sh[$pos])) return false;
		unset($this->sh[$pos]);
		$this->saveYml();
		return true;
	}

	public function getPos($b){
		return $b->x . ":" . $b->y . ":" . $b->z . ":" . $b->getLevel()->getFolderName();
	}

	public function spawnCase(){
		$this->despawnCase();
		foreach($this->sh as $k => $v){
			if($this->eid > 99999999) $this->eid = 9999;
			$pk = new AddItemEntityPacket();
			$pk->eid = $this->eid;
			$pk->item = Item::fromString($v[1]);
			$pk->item->setCount(1);
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
			if($pk instanceof RemoveEntityPacket || strtolower($p->getLevel()->getFolderName()) == strtolower(explode(":", $pos)[3])) $p->directDataPacket($pk);
		}
	}

	public function register($p, $pw){
		$p->sendMessage("[Login] " . ($this->isKorean() ? "가입 완료" : "Register to complete"));
		$this->lg[strtolower($p->getName())] = ["PW" => hash("sha256", $pw), "IP" => $p->getAddress()];
		$this->saveYml();
	}

	public function isRegister($p){
		return $p instanceof Player && isset($this->lg[strtolower($p->getName())]) ? true : false;
	}

	public function login($p, $pw = "", $auto = false, $opw = ""){
		if($this->isLogin($p)) return;
		$n = strtolower($p->getName());
		$ik = $this->isKorean();
		if(!isset($this->lg[$n])){
			$p->sendMessage("[Login]" . ($ik ? "당신은 가입되지 않았습니다.\n/Register <비밀번호> <비밀번호>" : "You are not registered.\n/Register <Password> <Password>"));
			return false;
		}
		if($pw) $pw = hash("sha256", $pw);
		if(!$auto){
			if($pw !== $this->lg[$n]["PW"]){
				$p->sendMessage("[Login] " . ($ik ? "로그인 실패" : "Login to failed"));
				return false;
			}
			if($p->isOp()){
				$op = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Login-OP.yml", Config::YAML, ["Op" => false, "PW" => "op"]))->getAll();
				if($op["Op"] && $op["PW"] !== $opw){
					$p->sendMessage("[Login] " . ($ik ? "로그인 실패" : "Login to failed"));
					$p->sendMessage("/Login " . ($ik ? "<비밀번호> <오피비밀번호>" : "<Password> <OP PassWord>"));
					return true;
				}
			}
		}
		$this->player[$n] = true;
		$this->lg[$n]["IP"] = $p->getAddress();
		$p->sendMessage("[Login] " . ($auto ? ($ik ? "자동" : "Auto") : "") . ($ik ? "로그인 완료" : "Login to complete"));
		$this->getServer()->broadCastMessage("/☆ [" . ($ik ? "입장" : "Join") . "] " . $p->getName());
		$this->saveYml();
		return true;
	}

	public function isLogin($p){
		return $p instanceof Player && isset($this->player[strtolower($p->getName())]) ? true : false;
	}

	public function unLogin($p){
		unset($this->player[strtolower($p->getName())]);
	}

	public function sendLogin($p, $l = false){
		if($p instanceof Player){
			$mm = "[Login] ";
			$ik = $this->isKorean();
			$n = strtolower($p->getName());
			if(!$this->isLogin($p)){
				if(!isset($this->lg[$n])){
					$p->sendMessage($mm . ($ik ? "당신은 가입되지 않았습니다.\n/Register <비밀번호> <비밀번호>" : "You are not registered.\n/Register <Password> <Password>"));
				}elseif($l && $this->lg[$n]["IP"] == $p->getAddress()){
					$this->login($p, "", true);
				}else{
					$p->sendMessage($mm . ($ik ? "당신은 로그인하지 않았습니다.\n/Login <비밀번호>" : "You are not logined.\n/Login <Password>"));
				}
			}
		}
		return true;
	}

	public function loadYml(){
		@mkdir($this->path);
		$this->mf = (new Config($this->path . "Farm.yml", Config::YAML, ["Auto" => false, "Sell" => true, "Price" => 100000, "Distance" => 5, "Size" => 1, "Air" => 3, "MineWorld" => "Mine", "MineBlock" => "48:0", "Item" => true, "Items" => [["269:0", 1], ["270:0", 1], ["271:0", 1], ["290:0", 1]], "Farm" => [], "Invite" => [], "Edge" => []]))->getAll();
		$drops = (new Config($this->path . "Drops.yml", Config::YAML, ["Drop" => ["500" => "1:0", "100" => "16:0", "50" => "15:0", "15" => "73:0", "10" => "14:0", "10" => "21:0", "2" => "56:0", "1" => "129:0"], "MineDrop" => ["500" => "4:0", "200" => "263:0", "50" => "15:0", "3" => "14:0"]]))->getAll();
		$this->drop = [];
		foreach($drops["Drop"] as $per => $id){
			for($for = 0; $for < $per; $for++)
				$this->drop[] = $id;
		}
		$this->mine = [];
		foreach($drops["MineDrop"] as $per => $id){
			for($for = 0; $for < $per; $for++)
				$this->mine[] = $id;
		}
		$this->mn = (new Config($this->path . "Money.yml", Config::YAML, ["Money" => [], "Default" => "10000", "Nick" => true, "Nick_Format" => "[Rank: %rank] %money\$\n[Farm: %farm] %name", "OP" => false]))->getAll();
		$this->sh = (new Config($this->path . "Shop.yml", Config::YAML))->getAll();
		$this->lg = (new Config($this->path . "Login.yml", Config::YAML))->getAll();
		$this->an = (new Config($this->path . "AutoNotice.yml", Config::YAML, ["On" => true, "Time" => 60, "Message" => ["[MinaFarm] Hello. This server is MineFarm Server \n [MineFarm] MineFarm Plugin is made by MineBlock (huu6677@naver.com)"]]))->getAll();
	}

	public function saveYml(){
		$mf = new Config($this->path . "Farm.yml", Config::YAML);
		$mf->setAll($this->mf);
		$mf->save();
		asort($this->mn);
		$mn = new Config($this->path . "Money.yml", Config::YAML);
		$mn->setAll($this->mn);
		$mn->save();
		ksort($this->sh);
		$sh = new Config($this->path . "Shop.yml", Config::YAML);
		$sh->setAll($this->sh);
		$sh->save();
		ksort($this->lg);
		$lg = new Config($this->path . "Login.yml", Config::YAML);
		$lg->setAll($this->lg);
		$lg->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}