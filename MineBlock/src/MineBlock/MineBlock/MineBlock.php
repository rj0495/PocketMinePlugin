<?php
namespace MineBlock\MineBlock;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\CallbackTask;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\item\Item;
use pocketmine\block\Block;

class MineBlock extends PluginBase implements Listener{

	public function onEnable(){
		$this->item = [];
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->loadYml();
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		if(!isset($sub[0])) return false;
		$mb = $this->mb;
		$set = $mb["Set"];
		$drop = $mb["Drop"];
		$rm = TextFormat::RED . "Usage: /MineBlock ";
		$ik = $this->isKorean();
		$mm = "[MineBlock] ";
		switch(strtolower($sub[0])){
			case "mine":
			case "m":
			case "on":
			case "off":
			case "마인":
			case "마인블럭":
			case "광물블럭":
			case "온":
			case "오프":
				if($set["Mine"] == "On"){
					$set["Mine"] = "Off";
					$r = $mm . ($ik ? "마인블럭을  끕니다.": "MineBlock is Off");
				}else{
					$set["Mine"] = "On";
					$r = $mm . ($ik ? "마인블럭을 켭니다.": "MineBlock is On");
				}
			break;
			case "regen":
			case "r":
			case "리젠":
			case "소생":
				if($set["Regen"] == "On"){
					$set["Regen"] = "Off";
					$r = $mm . ($ik ? "블럭리젠을  끕니다.": "Regen is Off");
				}else{
					$set["Regen"] = "On";
					$r = $mm . ($ik ? "블럭리젠을 켭니다.": "Regen is On");
				}
			break;
			case "block":
			case "b":
			case "블럭":
			case "광물":
				if(!isset($sub[1])){
					$r = $rm . ($ik ? "블럭 <블럭ID>": "Block(B) <BlockID>");
				}else{
					$i = Item::fromString($sub[1]);
					$i = $i->getID() . ":" . $i->getDamage();
					$set["Block"] = $i;
					$r = $mm . ($ik ? "블럭을 [$i] 로 설정했습니다.": "Block is set [$i]");
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
					$set["Time"] = $sub[1];
					$r = $mm . ($ik ? "블럭리젠 딜레이를 [$sub[1]] 로 설정했습니다.": "Block Regen Delay is set [$sub[1]]");
				}
			break;
			case "count":
			case "c":
			case "갯수":
			case "횟수":
				if(!isset($sub[1])){
					$r = $mm . ($ik ? $rm . "횟수 <횟수>": $rm . "Count(C) <Num>");
				}else{
					if($sub[1] < 1 || !is_numeric($sub[1])) $sub[1] = 1;
					if(isset($sub[2]) && $sub[2] > $sub[1] && is_numeric($sub[2]) !== false) $sub[1] = $sub[1] . "~" . $sub[2];
					$set["Count"] = $sub[1];
					$r = $mm . ($ik ? "드랍 횟수를 [$sub[1]] 로 설정했습니다.": "Drop count is set [$sub[1]]");
				}
			break;
			case "drop":
			case "drops":
			case "dr":
			case "드롭":
			case "드롭템":
			case "드랍":
			case "드랍템":
				if(!isset($sub[1])){
					$r = $rm . ($this->isKorean() ? "드롭 <추가|삭제|리셋|목록>":  "Drops(Dr) <Add|Del|Reset|List>");
				}else{
					switch(strtolower($sub[1])){
						case "add":
						case "a":
						case "추가":
							if(!isset($sub[2]) || !isset($sub[3])){
								$r = ($this->isKorean() ? $rm . "드롭템 추가 <아이템ID> <확률> <갯수1> <갯수2>": $rm . "Fishs(F) Add(A) <ItemID> <Petsent> <Count1> <Count2>");
							}else{
								$i = Item::fromString($sub[2]);
								if($sub[3] < 1 || !is_numeric($sub[3])) $sub[3] = 1;
								if(!isset($sub[4]) < 0 || !is_numeric($sub[4])) $sub[4] = 0;
								if(isset($sub[5]) && $sub[5] > $sub[4] && is_numeric($sub[5])) $sub[4] = $sub[4] . "~" . $sub[5];
								$drop[] = $sub[3]." % ".$i->getID() . ":" . $i->getDamage()." % $sub[4]";
								$r = $mm . ($ik ? "드롭템 추가됨 [" . $i->getID() . ":" . $i->getDamage() . " 갯수:$sub[4] 확률:$sub[3]]": "Drops add [" . $i->getID() . ":" . $i->getDamage() . " Count:$sub[4] Persent:$sub[3]]");
							}
						break;
						case "del":
						case "d":
						case "삭제":
						case "제거":
							if(!isset($sub[2])){
								$r = $rm . ($ik ? "드롭템 삭제 <번호>": "Fishs(F) Del(D) <FishNum>");
							}else{
								if($sub[2] < 0 || !is_numeric($sub[2])) $sub[2] = 0;
								if(!isset($drop[$sub[2] - 1])){
									$r = $mm . ($ik ? "[$sub[2]] 는 존재하지않습니다. \n  " . $rm . "드롭템 목록 ": "[$sub[2]] does not exist.\n  " . $rm . "Drops(Dr) List(L)");
								}else{
									$d = $fish[$sub[2] - 1];
									unset($fish[$sub[2] - 1]);
									$r = $mm . ($ik ? "드롭템 제거됨 [" . $d[1] . ":" . $i->getDamage() . " 갯수:" . $d[2] . " 확률:" . $d[0] . "]": "Fish del [" . $d[1] . ":" . $i->getDamage() . " Count:" . $d[2] . " Persent:" . $d[0] . "]");
								}
							}
						break;
						case "reset":
						case "r":
						case "리셋":
						case "초기화":
							$drop = [];
							$r = $mm . ($ik ? "드롭템 목록을 초기화합니다.": "Drop list is Reset");
						break;
						case "list":
						case "l":
						case "목록":
						case "리스트":
							$page = 1;
							if(isset($sub[2]) && is_numeric($sub[2])) $page = round($sub[2]);
							$list = ceil(count($drop) / 5);
							if($page >= $list) $page = $list;
							$r = $mm . (ik ? "목록 (페이지 $page/$list) \n": "List (Page $page/$list) \n");
							$num = 0;
							foreach($drop as $k){
								$num++;
								if($num + 5 > $page * 5 && $num <= $page * 5) $r .= ($ik ? "  [$num] 아이디:" . $k["ID"] . " 갯수:" . $k["Count"] . " 확률:" . $k["Percent"] . " \n": "  [$num] ID:" . $k["ID"] . " Count:" . $k["Count"] . " Percent:" . $k["Percent"] . " \n");
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
			break;
		}
		if(isset($r)) $sender->sendMessage($r);
		if($mb["Set"] !== $set || $mb["Drop"] !== $drop){
			$this->mb = ["Set" => $set, "Drop" => $drop];
			$this->saveYml();
		}
		return true;
	}

	public function onBlockBreak(BlockBreakEvent $event){
		if(count($this->drops) <= 0 || $event->isCancelled() || $this->mb["Set"]["Mine"] == "Off") return;
		$b = $event->getBlock();
		$bb = Item::fromString($this->mb["Set"]["Block"]);
		if($bb->getID() !== $b->getID() || $bb->getDamage() !== $b->getDamage()) return;
		$b->onBreak($event->getItem());
		$cnt = explode("~", $this->mb["Set"]["Count"]);
		$p = $event->getPlayer();
		if(!$p->isCreative() && ($i = $event->getItem()) instanceof Item && $i->isTool()){
			$i = Item::get($i->getID(), $i->getDamage() + 2, 1);
			$p->getInventory()->setItemInHand($i->getDamage() < $i->getMaxDurability() ? $i : Item::get(0, 0, 0));
		}
		for($for = 0; $for < rand($cnt[0], isset($cnt[1]) ? $cnt[1] : $cnt[0]); $for++){
			shuffle($this->drops);
			$d = $this->drops[0];
			$dc = explode("~", $d[2]);
			$b->getLevel()->dropItem($b->add(0.5, 0.25, 0.5), $this->getItem($d[1], rand($dc[0], isset($dc[1]) ? $dc[1] : $dc[0])));
		}
		if($this->mb["Set"]["Regen"] == "On"){
			$t = explode("~", $this->mb["Set"]["Time"]);
			$this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$b->getLevel(),"setBlock"], [$b, $b, false]), rand($t[0], isset($t[1]) ? $t[1] : $t[0]) * 20);
		}
		$event->setCancelled();
	}

	public function getItem($id = 0, $cnt = 0){
		$id = explode(":", $id);
		return Item::get($id[0], isset($id[1]) ? $id[1] : 0, $cnt);
	}

	public function loadYml(){
		@mkdir($this->path = ($this->getServer()->getDataPath() . "/plugins/! MineBlock/"));
		$this->mb = (new Config($this->file = $this->path . "MineBlock.yml", Config::YAML, [
			"Set" => [
				"Mine" => "On",
				"Block" => "48:0",
				"Regen" => "On",
				"Time" => "3~5",
				"Count" => "1~2"
			],
			"Drop" => is_file($this->file) ? [] : [
				"700 % 4:0 % 1",
				"70 % 263 % 1~3",
				"50 % 15:0 % 1",
				"20 % 331:0 % 1~7",
				"15 % 14:0 % 1",
				"5 % 351:4 % 1~7",
				"3 % 388:0 % 1",
				"1 % 264:0 % 1"
			]
		]))->getAll();
		$this->drops = [];
		foreach($this->mb["Drop"] as $drop){
			$info = explode(" % ", $drop);
			for($for = 0; $for < $info[0]; $for++) $this->drops[] = $info;
		}
	}

	public function saveYml(){
		$mb = new Config($this->file, Config::YAML);
		$mb->setAll($this->mb);
		$mb->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}