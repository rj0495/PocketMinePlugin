<?php

namespace MineBlock\Money;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\Player;
use pocketmine\item\Item;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;

class Money extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->loadYml();
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		if(!isset($sub[0])) return false;
		$mm = "[Money] ";
		$rm = TextFormat::RED . "Usage: /";
		$mn = $this->mn;
		$ik = $this->isKorean();
		$n = $sender->getName();
		$c = false;
		switch(strtolower($cmd->getName())){
			case "money":
				$rm .= "Money ";
				switch(strtolower($sub[0])){
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
						if(!isset($sub[1])){
							$r = $rm . ($ik ? "보기 <플레이어명>" : "View(V) <PlayerName>");
						}elseif(!($p = $this->getPlayer($sub[1]))){
							$r = $mm . $sub[1] . ($ik ? "은 잘못된 이름입니다." : " is invalid name");
						}else{
							$r = $mm . $p . ($ik ? "의 돈 : " : "'s Money : ") . $this->getMoney($p) . ($ik ? "원  ,  랭킹 : " : "$  ,  Rank : ") . $this->getRank($p);
						}
					break;
					case "pay":
					case "p":
					case "지불":
						if(!$sender instanceof Player){
							$r = $mm . ($ik ? "게임내에서 실행해주세요." : "Please run this command in-game");
						}elseif(!isset($sub[1]) || !isset($sub[2])){
							$r = $rm . ($ik ? "지불 <플레이어명> <돈> " : "Pay <PlayerName> <Money>");
						}elseif(!($p = $this->getPlayer($sub[1])) || strtolower($n) == strtolower($sub[1])){
							$r = $mm . $sub[1] . ($ik ? "은 잘못된 이름입니다." : " is invalid name");
						}elseif(!is_numeric($sub[2]) || $sub[2] < 1){
							$r = $mm . $sub[2] . ($ik ? "은 잘못된 숫자입니다." : " is invalid number");
						}elseif(!$this->hasMoney($n, $sub[2])){
							$r = $mm . ($ik ? "돈이 $sub[2] 보다 부족합니다. (나의 돈 : $getMoney 원)" : "You don't have $sub[2] $ (You have : $getMoney $)");
						}else{
							$sub[2] = $sub[2] < 0 ? 0 : floor($sub[2]);
							$this->takeMoney($n, $sub[2]);
							$this->giveMoney($p, $sub[2]);
							$r = $mm . ($ik ? "당신은 $sub[2] 원을  $p 님에게 지불햇습니다. " : "You pay $sub[2] $ (To : $p)");
							if($player = $this->getServer()->getPlayerExact($p)) $player->sendMessage($mm . $n . ($ik ? "님이 당신에게 $sub[2] 원을 지불햇습니다. " : "$n pay $sub[2]$ to you"));
						}
					break;
					case "rank":
					case "r":
					case "랭킹":
					case "순위":
						if(isset($sub[1]) && is_numeric($sub[1]) && $sub[1] > 1){
							$r = $this->getRanks(round($sub[1]));
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
				$rm .= "MoneyOP ";
				switch(strtolower($sub[0])){
					case "set":
					case "s":
					case "설정":
						if(!isset($sub[1])){
							$r = $rm . ($ik ? "설정 <플레이어명> <돈>" : "Set(S) <PlayerName> <Money>");
						}elseif(!($p = $this->getPlayer($sub[1]))){
							$r = $mm . $sub[1] . ($ik ? "은 잘못된 이름입니다." : " is invalid name");
						}elseif(!is_numeric($sub[2]) || $sub[2] < 0){
							$r = $mm . $sub[2] . ($ik ? "은 잘못된 숫자입니다." : " is invalid number");
						}else{
							$sub[2] = $sub[2] < 0 ? 0 : floor($sub[2]);
							$this->setMoney($p, $sub[2]);
							$r = $mm . $p . ($ik ? "의 돈을 $sub[2] 원으로 설정했습니다.  " : "'s money is set to $sub[2] $");
							if($player = $this->getServer()->getPlayerExact($p)) $player->sendMessage($mm . ($ik ? "당신의 돈이 어드민에 의해 변경되었습니다. 나의 돈 : " : "Your money is change by admin. Your money : ") . $this->getMoney($p) . ($ik ? "원" : "$"));
						}
					break;
					case "give":
					case "g":
					case "지급":
						if(!isset($sub[1])){
							$r = $rm . ($ik ? "지급 <플레이어명> <돈>" : "Give(G) <PlayerName> <Money>");
						}elseif(!($p = $this->getPlayer($sub[1]))){
							$r = $mm . $sub[1] . ($ik ? "은 잘못된 이름입니다." : " is invalid name");
						}elseif(!is_numeric($sub[2]) || $sub[2] < 0){
							$r = $mm . $sub[2] . ($ik ? "은 잘못된 숫자입니다." : " is invalid number");
						}else{
							$sub[2] = $sub[2] < 0 ? 0 : floor($sub[2]);
							$this->giveMoney($p, $sub[2]);
							$r = $mm . ($ik ? "$p 님에게 $sub[2] 원을 지급햇습니다. " : "Give the $sub[2] $ to $p");
						}
					break;
					case "take":
					case "t":
					case "뺏기":
						if(!isset($sub[1])){
							$r = $rm . ($ik ? "뺏기 <플레이어명> <돈>" : "Take(T) <PlayerName> <Money>");
						}elseif(!($p = $this->getPlayer($sub[1]))){
							$r = $mm . $sub[1] . ($ik ? "은 잘못된 이름입니다." : " is invalid name");
						}elseif(!is_numeric($sub[2]) || $sub[2] < 0){
							$r = $mm . $sub[2] . ($ik ? "은 잘못된 숫자입니다." : " is invalid number");
						}else{
							$sub[2] = $sub[2] < 0 ? 0 : floor($sub[2]);
							$this->takeMoney($p, $sub[2]);
							$r = $mm . ($ik ? "$p 님에게서 $sub[2] 원을 빼앗았습니다. " : "Take the $sub[2] $ to $p");
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
						if(!isset($sub[1])){
							$r = $rm . ($ik ? "기본 <돈>" : "Defualt(D) <Money>");
						}elseif(!is_numeric($sub[1]) || $sub[1] < 0){
							$r = $mm . $sub[1] . ($ik ? "은 잘못된 숫자입니다." : " is invalid number");
						}else{
							$sub[1] = floor($sub[1]);
							$mn["Default"] = $sub[1];
							$m = $mm . ($ik ? "기초자금이 $sub[1] 로 설정되었습니다." : "Defualt money is set to $sub[1] $");
							$c = true;
						}
					break;
					case "op":
					case "o":
					case "오피":
						$mn["OP"] = !$mn["OP"];
						$m = $mm . ($ik ? "오피를 랭킹에 포함" . ($mn["OP"] ? "" : "안") . "합니다." : "Show on rank the Op is " . ($mn["OP"] ? "On" : "Off"));
						$c = true;
					break;
					default:
						return false;
					break;
				}
			break;
		}
		if(isset($r)) $sender->sendMessage($r);
		if(isset($m)) $this->getServer()->broadcastMessage($m);
		if($c && $this->mn !== $mn) $this->mn = $mn;
		return true;
	}

	public function onPlayerJoin(PlayerJoinEvent $event){
		$n = strtolower($event->getPlayer()->getName());
		if(!isset($this->mn["Money"][$n])){
			$this->mn["Money"][$n] = $this->mn["Default"];
			$this->saveYml();
		}
	}

	public function getMP($name = ""){
		if($name instanceof Player) $name = $name->getName();
		if(!$name) return false;
		if(isset($this->mn["Money"][$name = strtolower($name)])) return ["Player" => $name, "Money" => $this->mn["Money"][$name]];
		else return false;
	}

	public function getPlayer($name = ""){
		if($name instanceof Player) $name = $name->getName();
		return !$this->getMP($name) ? false : $this->getMP($name)["Player"];
	}

	public function getMoney($name = ""){
		if($name instanceof Player) $name = $name->getName();
		return !$this->getMP($name) ? false : $this->getMP($name)["Money"];
	}

	public function hasMoney($name = "", $money = 0){
		if($name instanceof Player) $name = $name->getName();
		if(!$m = $this->getMoney($name)) return false;
		else return $money <= $m;
	}

	public function setMoney($name = "", $money = 0){
		$mn = $this->mn["Money"];
		if($name instanceof Player) $name = $name->getName();
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
		if($name instanceof Player) $name = $name->getName();
		if(!$name && !$all && !$this->getMoney($name)){
			return false;
		}else{
			$this->setMoney($name, $this->getMoney($name) + $money);
		}
		return true;
	}

	public function takeMoney($name = "", $money = 0){
		if(!is_numeric($money) || $money < 0) $money = 0;
		if($name instanceof Player) $name = $name->getName();
		if(!$name && !$all && !$this->getMoney($name)){
			return false;
		}else{
			$getMoney = $this->getMoney($name);
			if($getMoney < $money) $money = $getMoney;
			$this->setMoney($name, $this->getMoney($name) - $money);
		}
		return true;
	}

	public function getAllMoneys(){
		return $this->mn["Money"];
	}

	public function setAllMoneys($moneys){
		if(is_array($moneys)){
			$this->mn["Money"] = $moneys;
			$this->saveYml();
			return true;
		}
		return false;
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
		if($name instanceof Player) $name = $name->getName();
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

	public function loadYml(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		$this->mn = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "Money.yml", Config::YAML, ["Money" => [], "Default" => "10000", "OP" => true]))->getAll();
	}

	public function saveYml(){
		asort($this->mn);
		$mn = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "Money.yml", Config::YAML);
		$mn->setAll($this->mn);
		$mn->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}