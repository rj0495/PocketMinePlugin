<?php

namespace MineBlock\Cheque;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\Player;
use pocketmine\item\Item;

class Cheque extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getLogger()->info("[Cheque] Find economy plugin...");
		$pm = $this->getServer()->getPluginManager();
		if(!($this->money = $pm->getPlugin("PocketMoney")) && !($this->money = $pm->getPlugin("EconomyAPI")) && !($this->money = $pm->getPlugin("MassiveEconomy")) && !($this->money = $pm->getPlugin("Money"))){
			$this->getServer()->getLogger()->info("[Cheque] Failed find economy plugin...");
			$this->getLogger()->info($this->isKorean() ? TextFormat::RED . "이 플러그인은 머니 플러그인이 반드시 있어야합니다." : TextFormat::RED . "This plugin need the Money plugin");
			$this->getServer()->shutdown();
		}else{
			$this->getServer()->getLogger()->info("[Cheque] Finded economy plugin : " . $this->money->getName());
		}
		$this->touch = [];
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		if(!isset($sub[0])) return false;
		$mm = "[Cheque] ";
		$ik = $this->isKorean();
		if(!$sender instanceof Player){
			$r = $mm . ($ik ? "게임내에서 실행해주세요." : "Please run this command in game");
		}elseif(!is_numeric($sub[0]) || $sub[0] < 1){
			$r = $mm . $sub[0] . ($ik ? "는 잘못된 숫자입니다." : "is invalid number");
		}else{
			$sub[0] = floor($sub[0]);
			if($this->getMoney($sender) < $sub[0]){
				$r = $mm . ($ik ? "당신은 돈이 " . $sub[0] . "$ 보다 적습니다. 당신의 돈 : " : "You has less money than " . $sub[0] . "$ . Your money : ") . $this->getMoney($sender);
			}else{
				$this->giveMoney($sender, -$sub[0]);
				$sender->getInventory()->addItem(Item::get(339, $sub[0], 1));
				$r = $mm . ($ik ? "당신은 " . $sub[0] . "$ 수표를 받앗습니다. 당신의 돈 : " : "You have been " . $sub[0] . "$ check. Your money : ") . $this->getMoney($sender);
			}
		}
		if(isset($r)) $sender->sendMessage($r);
		return true;
	}

	public function onPlayerInteract(PlayerInteractEvent $event){
		$p = $event->getPlayer();
		$i = $event->getItem();
		if($i->getID() !== 339 || ($money = $i->getDamage()) < 1) return;
		$m = "[Cheque] ";
		$ik = $this->isKorean();
		if(!isset($this->touch[$n = $p->getName()])) $this->touch[$n] = 0;
		$c = microtime(true) - $this->touch[$n];
		if($c > 0){
			$m .= ($ik ? "수표를 사용하시려면 다시한번눌러주세요. \n 수표 정보 : " . $money . "$" : "If you want to use this check, One more touch block \n Cheque Info : " . $money . "$");
		}else{
			$i->setCount($i->getCount() - 1);
			$p->getInventory()->setItem($p->getInventory()->getHeldItemSlot(), $i);
			$this->giveMoney($p, $money);
			$m .= ($ik ? "수표를 사용하셨습니다.\n 수표 정보 : " . $money . "$" : "You use the check. \n Cheque Info : " . $money . "$");
		}
		$this->touch[$n] = microtime(true) + 1;
		if(isset($m)) $p->sendMessage($m);
		$event->setCancelled();
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

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}