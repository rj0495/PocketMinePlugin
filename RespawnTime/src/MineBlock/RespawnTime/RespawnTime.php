<?php

namespace MineBlock\RespawnTime;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\scheduler\CallbackTask;
use pocketmine\Player;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\nbt\tag\Byte;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Float;
use pocketmine\nbt\tag\Int;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\protocol\SetHealthPacket;
use pocketmine\network\protocol\MovePlayerPacket;
use pocketmine\network\protocol\Info as ProtocolInfo;
use pocketmine\inventory\PlayerInventory;

class RespawnTime extends PluginBase implements Listener{

	public $player;

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->player = [];
		$this->loadYml();
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		if(!isset($sub[0]) || !is_numeric($sub[0]) || $sub[0] < 0) return false;
		$ik = $this->isKorean();
		$rt = $this->rt;
		$sub[0] = floor($sub[0]);
		$rt["Time"] = $sub[0];
		$sender->sendMessage("[RespawnTime] " . ($ik ? "부활시간을 $sub[0]로 설정했습니다." : "Set respawn time to $sub[0]"));
		if($this->rt !== $rt){
			$this->rt = $rt;
			$this->saveYml();
		}
		return true;
	}

	public function onPlayerJoin(PlayerJoinEvent $event){
		$p = $event->getPlayer();
		if($p->isCreative()) return;
		if($p->dead) $this->player[$p->getName()] = ["Tag" => new Player4NameTag($p, $this), "Time" => time(true) + $this->rt["Time"], "Task" => $this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "runRespawn"], [$p]), $this->rt["Time"] * 20)->getTaskId()];
	}

	public function onPlayerQuit(PlayerQuitEvent $event){
		$p = $event->getPlayer();
		if($p->isCreative()) return;
		if(isset($this->player[$n = $p->getName()])){
			if($this->player[$n]["Tag"] instanceof Player4NameTag) $this->player[$n]["Tag"]->close();
			$this->getServer()->getScheduler()->cancelTask($this->player[$n]["Task"]);
			unset($this->player[$n]);
		}
	}

	public function onPlayerDeath(PlayerDeathEvent $event){
		$p = $event->getEntity();
		if($p->isCreative()) return;
		if(isset($this->player[$n = $p->getName()])){
			if($this->player[$n]["Tag"] instanceof Player4NameTag) $this->player[$n]["Tag"]->close();
			$this->getServer()->getScheduler()->cancelTask($this->player[$n]["Task"]);
		}
		$this->player[$n] = ["Tag" => new Player4NameTag($p, $this), "Time" => time(true) + $this->rt["Time"], "Task" => $this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "runRespawn"], [$p]), $this->rt["Time"] * 20)->getTaskId()];
	}

	public function onDataPacketReceive(DataPacketReceiveEvent $event){
		$pk = $event->getPacket();
		$p = $event->getPlayer();
		if($p->isCreative()) return;
		if($pk->pid() == ProtocolInfo::RESPAWN_PACKET && isset($this->player[$n = $p->getName()])){
			if($this->player[$n]["Tag"]->closed) $this->plauer[$n]["Tag"] = new Player4NameTag($p, $this);
			$pk = new SetHealthPacket();
			$pk->health = 0;
			$p->dataPacket($pk);
			$event->setCancelled();
		}
	}

	public function runRespawn($p){
		$p->craftingType = 0;
		$this->getServer()->getPluginManager()->callEvent($ev = new PlayerRespawnEvent($p, $p->getSpawn()));
		$p->teleport($ev->getRespawnPosition());
		$p->fireTicks = 0;
		$p->airTicks = 300;
		$p->deadTicks = 0;
		$p->noDamageTicks = 0;
		$p->dead = false;
		$p->setHealth($p->getMaxHealth());
		$p->sendMetadata($p->getViewers());
		$p->sendMetadata($p);
		$p->sendSettings();
		$p->getInventory()->sendContents($p);
		$p->getInventory()->sendArmorContents($p);
		$p->blocked = false;
		$p->spawnToAll();
		$p->scheduleUpdate();
		unset($this->player[$p->getName()]);
	}

	public function loadYml(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		$this->rt = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "RespawnTime.yml", Config::YAML, ["Time" => 30]))->getAll();
	}

	public function saveYml(){
		$rt = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "RespawnTime.yml", Config::YAML);
		$rt->setAll($this->rt);
		$rt->save();
	}

	public function isKorean(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		if(!isset($this->ik)) $this->ik = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "! Korean.yml", Config::YAML, ["Korean" => false]))->get("Korean");
		return $this->ik;
	}
}
class Player4NameTag extends Human{

	public $player;

	public $nameTag = "";

	public $plugin;

	public function __construct($player, $plugin){
		parent::__construct($player->getLevel()->getChunk($player->x >> 4, $player->z >> 4), new Compound("", ["Pos" => new Enum("Pos", [new Double("", 0), new Double("", 0), new Double("", 0)]), "Motion" => new Enum("Motion", [new Double("", 0), new Double("", 0), new Double("", 0)]), "Rotation" => new Enum("Rotation", [new Float("", 0), new Float("", 0)])]));
		$this->player = $player;
		$this->plugin = $plugin;
		$this->inventory = new PlayerInventory($this);
	}

	protected function initEntity(){}

	public function saveNBT(){}

	public function canCollideWith(Entity $entity){
		return false;
	}

	public function onUpdate($currentTick){
		$p = $this->player;
		if($this->closed == true || $this->dead == true || $p instanceof Player && !$p->dead){
			if(!$this->closed) parent::close();
			$this->player = false;
			return false;
		}else{
			if(!$p->dead || !isset($this->plugin->player[$p->getName()])){
				$this->despawnFrom($p);
				return true;
			}else{
				$name = "\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n";
				$name .= ($this->plugin->isKorean() ? "부활까지 남은시간 : " : "Respawn time : ") . ($this->plugin->player[$p->getName()]["Time"] - time(true));
				$p->setRotation($p->getYaw(), 0);
				$this->x = $p->x - sin($p->getyaw() / 180 * M_PI) * 3;
				$this->y = $p->y + 20;
				$this->z = $p->z + cos($p->getyaw() / 180 * M_PI) * 3;
				if($this->nameTag !== $name){
					$this->nameTag = $name;
					$this->despawnFrom($p);
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
				$p->dataPacket($pk);
			}
		}
		return true;
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