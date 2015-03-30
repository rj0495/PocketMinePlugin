<?php
namespace MineBlock\MoneyNick;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\scheduler\CallbackTask;

class MoneyNick extends PluginBase{

	public function onEnable(){
		$this->getServer()->getLogger()->info("[MoneyNick] Find economy plugin...");
		$pm = $this->getServer()->getPluginManager();
		if(!($this->money = $pm->getPlugin("PocketMoney")) && !($this->money = $pm->getPlugin("EconomyAPI")) && !($this->money = $pm->getPlugin("MassiveEconomy")) && !($this->money = $pm->getPlugin("Money"))){
			$this->getServer()->getLogger()->info("[MoneyNick] Failed find economy plugin...");
			$this->getLogger()->info($this->isKorean() ? "이 플러그인은 머니 플러그인이 반드시 있어야합니다.": "This plugin need the Money plugin");
			$this->getServer()->shutdown();
		}else{
			$this->getServer()->getLogger()->info("[MoneyNick] Finded economy plugin : " . $this->money->getName());
		}
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this,"onTick"]), 20);
		$this->loadYml();
	}

	public function onTick(){
		if(strpos($this->mn["Format"], "%rank")){
			if(!is_array($moneys = $this->getAllMoneys())) return;
			arsort($moneys);
			$num = 1;
			foreach($moneys as $k => $v){
				if($this->getServer()->isOp($k)) $rank = "OP";
				elseif(!$this->getMoney($k)) $rank = "-";
				else{
					if(!isset($same)) $same = [$v,$num];
					if($v == $same[0]){
						$rank = $same[1];
					}else{
						$rank = $num;
						$same = [$v,$num];
					}
					$num++;
				}
				if(!($p = $this->getServer()->getPlayerExact($k))) continue;
				if($p->getNameTag() !== ($name = str_replace(["%rank","%money", "%name"], [$rank, $v, $p->getName()], $this->mn["Format"]))) $p->setNameTag($name);
			}
		}else{
			foreach($this->getServer()->getOnlinePlayers() as $p){
				if($p->getNameTag() !== ($name = str_replace(["%rank","%money", "%name"], [$rank, $this->getMoney($p), $p->getName()], $this->mn["Format"]))) $p->setNameTag($name);
			}
		}
	}

	public function getMoney($p){
		switch($this->money->getName()){
			case "PocketMoney":
			case "MessiveEconomy":
			case "Money":
				return $this->money->getMoney($p);
			break;
			case "EconomyAPI":
				return $this->money->mymoney($p);
			break;
			default:
				return false;
			break;
		}
	}

	public function getAllMoneys(){
		switch($this->money->getName()){
			case "PocketMoney":
				$property = (new \ReflectionClass("\\PocketMoney\\PocketMoney"))->getProperty("users");
				$property->setAccessible(true);
				$allMoney = [];
				foreach($property->getValue($this->money)->getAll() as $k => $v)
					$allMoney[strtolower($k)] = $v["money"];
			break;
			case "EconomyAPI":
				$allMoney = $this->money->getAllMoney()["money"];
			break;
			case "MassiveEconomy":
				$property = (new \ReflectionClass("\\MassiveEconomy\\MassiveEconomyAPI"))->getProperty("data");
				$property->setAccessible(true);
				$allMoney = [];
				$dir = @opendir($path = $property->getValue($this->money) . "users/");
				$cnt = 0;
				while($open = readdir($dir)){
					if(strpos($open, ".yml") !== false){
						$allMoney[strtolower(explode(".", $open)[0])] = (new Config($path . $open, Config::YAML, ["money" => 0 ]))->get("money");
					}
				}
			break;
			case "Money":
				$allMoney = $this->money->getAllMoneys();
			break;
			default:
				return false;
			break;
		}
		return $allMoney;
	}

	public function loadYml(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		$this->mn = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "MoneyNick.yml", Config::YAML, ["Format" => " %name \n [%rank] %money"]))->getAll();
	}

	public function saveYml(){
		ksort($this->mn);
		$mn = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "MoneyNick.yml", Config::YAML);
		$mn->setAll($this->mn);
		$mn->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false ]))->get("Korean");
		return $this->ik;
	}
}