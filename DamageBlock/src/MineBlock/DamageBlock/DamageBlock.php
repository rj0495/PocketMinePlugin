<?php

namespace MineBlock\DamageBlock;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;
use pocketmine\scheduler\CallbackTask;
use pocketmine\math\Math;
use pocketmine\item\Item;

class DamageBlock extends PluginBase{

	public function onEnable(){
		$this->player = [];
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, "onTick"]), 20);
		$this->loadYml();
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		if(!isset($sub[0])) return false;
		$db = $this->db;
		$rm = TextFormat::RED . "Usage: /DamageBlock ";
		$mm = "[DamageBlock] ";
		$ik = $this->isKorean();
		switch(strtolower($sub[0])){
			case "add":
			case "a":
			case "추가":
				if(!isset($sub[1]) || !isset($sub[2])){
					$r = $rm . ($ik ? "추가 <블럭ID> <데미지1> <데미지2>" : "Add(A) <BlockID> <Damage1> <Damage2>");
				}else{
					$i = Item::fromString($sub[1]);
					if($i->getID() == 0 && $sub[1] !== 0){
						$r = $sub[1] . " " . ($ik ? "는 잘못된 블럭ID입니다.." : "is invalid BlockID");
					}else{
						$id = $i->getID() . ":" . $i->getDamage();
						if(!is_numeric($sub[2])) $sub[2] = 0;
						$sub[2] = round($sub[2]);
						if(isset($sub[3]) && $sub[3] > $sub[2] && is_numeric($sub[3])) $sub[2] = $sub[2] . "~" . round($sub[3]);
						$db[$id] = $sub[2];
						$r = $mm . ($ik ? "추가됨" : "Add") . " [$id] => $sub[2]";
					}
				}
			break;
			case "del":
			case "d":
			case "제거":
			case "삭제":
				if(!isset($sub[1])){
					$r = $rm . ($ik ? "제거 <블럭ID>" : "Del(D) <BlockID>");
				}else{
					$i = Item::fromString($sub[1]);
					if($i->getID() == 0 && $sub[1] !== 0){
						$r = $sub[1] . " " . ($ik ? "는 잘못된 블럭ID입니다.." : "is invalid BlockID");
					}else{
						$id = $i->getID() . ":" . $i->getDamage();
						if(!isset($db[$id])){
							$r = "$mm [$id] " . ($ik ? " 목록에 존재하지 않습니다..\n   $rm 목록 " : " does not exist.\n   $rm List(L)");
						}else{
							foreach($db as $k => $v){
								if($k == $id) unset($db[$k]);
							}
							$r = $mm . ($ik ? "제거됨" : "Del") . "[$id]";
						}
					}
				}
			break;
			case "reset":
			case "r":
			case "리셋":
			case "초기화":
				$db = [];
				$r = $mm . ($ik ? " 리셋됨." : " Reset");
			break;
			case "list":
			case "l":
			case "목록":
			case "리스트":
				$page = 1;
				if(isset($sub[1]) && is_numeric($sub[1])) $page = max(floor($sub[1]), 1);
				$list = array_chunk($db, 5, true);
				if($page >= ($c = count($list))) $page = $c;
				$r = $mm . ($ik ? "데미지블럭 목록 (페이지" : "DamageBlock List (Page") . " $page/$c) \n";
				$num = ($page - 1) * 5;
				if($c > 0){
					foreach($list[$page - 1] as $k => $v){
						$num++;
						$r .= "  [$num] $k : [$v]\n";
					}
				}
			break;
			default:
				return false;
			break;
		}
		if(isset($r)) $sender->sendMessage($r);
		if($this->db !== $db){
			$this->db = $db;
			$this->saveYml();
		}
		return true;
	}

	public function onTick(){
		$ps = $this->player;
		foreach($this->getServer()->getOnlinePlayers() as $p){
			$n = $p->getName();
			if(!isset($ps[$n])) $ps[$n] = 0;
			if(!$p->isSurvival() || microtime(true) - $ps[$n] < 0 || $p->hasPermission("damageblock.inv")) continue;
			$bb = $p->getBoundingBox();
			$minX = Math::floorFloat($bb->minX - 0.001);
			$minY = Math::floorFloat($bb->minY - 0.001);
			$minZ = Math::floorFloat($bb->minZ - 0.001);
			$maxX = Math::floorFloat($bb->maxX + 0.001);
			$maxY = Math::floorFloat($bb->maxY + 0.001);
			$maxZ = Math::floorFloat($bb->maxZ + 0.001);
			$block = [];
			$damage = 0;
			for($z = $minZ; $z <= $maxZ; ++$z){
				for($x = $minX; $x <= $maxX; ++$x){
					for($y = $minY; $y <= $maxY; ++$y){
						$getDamage = $this->getDamage($p->getLevel()->getBlock(new Vector3($x, $y, $z)));
						if(!in_array($getDamage[1], $block)){
							$damage += $getDamage[0];
							$block[] = $getDamage[1];
						}
					}
				}
			}
			if($damage !== 0) $p->attack($damage);
			$ps[$n] = microtime(true) + 1;
		}
		$this->player = $ps;
	}

	public function getDamage($b){
		$id = $b->getID() . ":" . $b->getDamage();
		if(!isset($this->db[$id])) return false;
		$d = explode("~", $this->db[$id]);
		if(isset($d[1])) $damage = rand($d[0], $d[1]);
		else $damage = $d[0];
		return [$damage, $id];
	}

	public function loadYml(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		$this->db = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "DamageBlock.yml", Config::YAML, []))->getAll();
	}

	public function saveYml(){
		ksort($this->db);
		$db = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "DamageBlock.yml", Config::YAML, []);
		$db->setAll($this->db);
		$db->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}