<?php
namespace MineBlock\ShowMoney;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\scheduler\CallbackTask;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\nbt\tag\Byte;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Float;
use pocketmine\nbt\tag\Int;
use pocketmine\Player;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\network\protocol\MovePlayerPacket;
use pocketmine\network\protocol\SetHealthPacket;
use pocketmine\network\protocol\EntityEventPacket;
use pocketmine\network\protocol\Info as ProtocolInfo;
use pocketmine\inventory\PlayerInventory;

class ShowMoney extends PluginBase{

	public function onEnable(){
		$this->getServer()->getLogger()->info("[ShowMoney] Find economy plugin...");
		$pm = $this->getServer()->getPluginManager();
		if(!($this->money = $pm->getPlugin("PocketMoney")) && !($this->money = $pm->getPlugin("EconomyAPI")) && !($this->money = $pm->getPlugin("MassiveEconomy")) && !($this->money = $pm->getPlugin("Money"))){
			$this->getServer()->getLogger()->info("[ShowMoney] Failed find economy plugin...");
			$this->getLogger()->info($this->isKorean() ? "이 플러그인은 머니 플러그인이 반드시 있어야합니다.": "This plugin need the Money plugin");
			$this->getServer()->shutdown();
		}else{
			$this->getServer()->getLogger()->info("[ShowMoney] Finded economy plugin : " . $this->money->getName());
		}
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this,"onTick"]), 20);
		$this->player = [];
		$this->loadYml();
	}

	public function onTick(){
		if(strpos($this->sm["Format"], "%rank")){
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
				if(!isset($this->player[$n = $p->getName()]) || $this->player[$n]->closed == true){
					$this->player[$n] = new Player4NameTag($p);
				}
				$this->player[$n]->setNameTag(str_replace(["%rank","%money"], [$rank,$v], $this->sm["Format"]));
			}
		}else{
			foreach($this->getServer()->getOnlinePlayers() as $p){
				if(!isset($this->player[$n = $p->getName()]) || $this->player[$n]->closed == true){
					$this->player[$n] = new Player4NameTag($p);
				}
				$this->player[$n]->setNameTag(str_replace("%money", $this->getMoney($p), $this->sm["Format"]));
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
		$this->sm = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "ShowMoney.yml", Config::YAML, ["Format" => "\n     [%rank]  %money $"]))->getAll();
	}

	public function saveYml(){
		ksort($this->sm);
		$sm = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "ShowMoney.yml", Config::YAML);
		$sm->setAll($this->sm);
		$sm->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false ]))->get("Korean");
		return $this->ik;
	}
}
class Player4NameTag extends Human{

	public $player;

	public $nameTag = "";

	public function __construct($player){
		parent::__construct($player->getLevel()->getChunk($player->x >> 4, $player->z >> 4), new Compound("", ["Pos" => new Enum("Pos", [new Double("", 0), new Double("", 0), new Double("", 0)]), "Motion" => new Enum("Motion", [new Double("", 0), new Double("", 0), new Double("", 0)]), "Rotation" => new Enum("Rotation", [new Float("", 0), new Float("", 0)])]));
		$this->player = $player;
		$this->inventory = new PlayerInventory($this);
	}

	protected function initEntity(){}

	public function saveNBT(){}

	public function canCollideWith(Entity $entity){
		return false;
	}

	public function onUpdate($currentTick){
		$p = $this->player;
		if($this->closed == true || $this->dead == true || $p instanceof Player && !$p->spawned){
			if(!$this->closed) parent::close();
			$this->player = false;
			return false;
		}else{
			if((($pitch = $p->getPitch()) >= 2 || $pitch <= -12) && !$p->isSleeping()){
				$this->despawnFrom($p);
				return true;
			}else{
				if($p->isSleeping()){
					$property = (new \ReflectionClass("\\pocketmine\\Player"))->getProperty("sleeping");
					$property->setAccessible(true);
					$b = $this->getLevel()->getBlock($property->getValue($p));
					$xTabel = [1 => 2, 3 => -2, 9 => 2, 11 => -2];
					$x = isset($xTabel[$dmg = $b->getDamage()]) ? $xTabel[$dmg] : 0.5;
					$zTabel = [0 => -2, 2 => 2, 8 => -2, 10 => 2];
					$z = isset($zTabel[$dmg]) ? $zTabel[$dmg] : 0.5;
					$this->x = $b->x + $x;
					$this->y = $p->y + 19;
					$this->z = $b->z + $z;
				}else{
					$this->x = $p->x - sin($p->getyaw() / 180 * M_PI) * cos($p->getPitch() / 180 * M_PI * 3);
					$this->y = $p->y + 20;
					$this->z = $p->z + cos($p->getyaw() / 180 * M_PI) * cos($p->getPitch() / 180 * M_PI * 3);
				}
				$this->spawnTo($p);
				$pk = new MovePlayerPacket();
				$pk->eid = $this->id;
				$pk->x = $this->x;
				$pk->y = $this->y;
				$pk->z = $this->z;
				$pk->yaw = 0;
				$pk->bodyYaw = 0;
				$pk->pitch = 0;
				$p->getServer()->broadcastPacket($this->hasSpawned, $pk);
			}
		}
		return true;
	}

	public function setNameTag($name = ""){
		$this->nameTag = "\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n".$name;
		$this->despawnFrom($this->player);
		$this->spawnTo($this->player);
	}

	public function getInventory(){
		return $this->player->getInventory();
	}

	public function getName(){
		return $this->nameTag;
	}

	public function getData(){
		return [];
	}

	public function getDrops(){
		return [];
	}

	public function spawnTo(Player $player){
		if($this->player === $player) parent::spawnTo($player);
	}
}