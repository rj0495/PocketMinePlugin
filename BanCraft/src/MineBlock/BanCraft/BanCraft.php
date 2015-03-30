<?php

namespace MineBlock\BanCraft;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\inventory\CraftItemEvent;
use pocketmine\item\Item;
use pocketmine\Player;

class BanCraft extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->loadYml();
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		if(!isset($sub[0])) return false;
		$bc = $this->bc;
		$rm = TextFormat::RED . "Usage: /BanCraft ";
		$mm = "[BanCraft] ";
		$ik = $this->isKorean();
		switch(strtolower($sub[0])){
			case "add":
			case "a":
			case "추가":
				if(!isset($sub[1])){
					$r = $rm . ($ik ? "추가 <아이템ID>" : "Add(A) <ItemID>");
				}else{
					$i = Item::fromString($sub[1]);
					if($i->getID() == 0 && $sub[1] !== 0){
						$r = $sub[1] . " " . ($ik ? "는 잘못된 아이템ID입니다.." : "is invalid ItemID");
					}else{
						$id = $i->getID() . ":" . $i->getDamage();
						$bc[] = $id;
						$r = $mm . ($ik ? "추가됨 : " : "Add") . " $id";
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
						if(!in_array($id, $bc)){
							$r = "$mm $id" . ($ik ? "는 목록에 존재하지 않습니다..\n   $rm 목록 " : "is does not exist in list.\n   $rm List(L)");
						}else{
							foreach($bc as $k => $v){
								if($v == $id) unset($bc[$k]);
							}
							$r = $mm . ($ik ? "제거됨 : " : "Delete ") . " $id";
						}
					}
				}
			break;
			case "reset":
			case "r":
			case "리셋":
			case "초기화":
				$bc = [];
				$r = $mm . ($ik ? " 리셋됨." : " Reset");
			break;
			case "list":
			case "l":
			case "목록":
			case "리스트":
				$page = 1;
				if(isset($sub[1]) && is_numeric($sub[1])) $page = max(floor($sub[1]), 1);
				$list = array_chunk($bc, 5, true);
				if($page >= ($c = count($list))) $page = $c;
				$r = $mm . ($ik ? "조합금지 목록 (페이지" : "BanCraft List (Page") . " $page/$c) \n";
				$num = ($page - 1) * 5;
				if($c > 0){
					foreach($list[$page - 1] as $k => $v){
						$num++;
						$r .= "  [$num] $v\n";
					}
				}
			break;
			default:
				return false;
			break;
		}
		if(isset($r)) $sender->sendMessage($r);
		if($this->bc !== $bc){
			$this->bc = $bc;
			$this->saveYml();
		}
		return true;
	}

	public function onCraftItem(CraftItemEvent $event){
		$t = $event->getTransaction();
		if(!(($p = $t->getSource()) instanceof Player)) return;
		$r = $t->getResult();
		if(/*!$p->hasPermission("bancraft.craft") &&*/ in_array($id = $r->getID() . ":" . $r->getDamage(), $this->bc)){
			$p->attack(0);
			$p->sendMessage("[BanCraft] $id" . ($this->isKorean() ? "는 조합금지 아이템입니다. 조합할수없습니다." : " is Ban. You can't craft."));
			$p->getInventory()->sendContents($p);
			$event->setCancelled();
		}
	}

	public function loadYml(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		$this->bc = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "BanCraft.yml", Config::YAML))->getAll();
	}

	public function saveYml(){
		sort($this->bc);
		$bc = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "BanCraft.yml", Config::YAML);
		$bc->setAll($this->bc);
		$bc->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}