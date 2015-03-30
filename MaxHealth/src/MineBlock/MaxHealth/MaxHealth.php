<?php

namespace MineBlock\MaxHealth;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\scheduler\CallbackTask;
use pocketmine\Player;
use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\nbt\tag\Byte;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Float;
use pocketmine\nbt\tag\Int;
use pocketmine\item\Item;
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

class MaxHealth extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, "onTick"]), 20);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->player = [];
		$this->tick = 0;
		$this->loadYml();
	}

	public function onDisable(){
		$this->saveYml();
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		if(!isset($sub[0])) return false;
		$rm = "Usage: /MaxHealth ";
		$mm = "[MaxHealth] ";
		$ik = $this->isKorean();
		$mh = $this->mh;
		switch(strtolower($sub[0])){
			case "set":
			case "s":
			case "설정":
				if(!isset($sub[2])){
					$r = $rm . ($ik ? "설정 <플레이어명> <체력>" : "Set(S) <PlayerName> <Health>");
				}else{
					if(!$player = $this->getServer()->getPlayer($sub[1])){
						$r = $mm . $sub[1] . ($ik ? " 는 잘못된 플레이어명입니다." : "is invalid player");
					}elseif(!is_numeric($sub[2]) || $sub[2] < 1){
						$r = $mm . $sub[2] . ($ik ? "는 잘못된 숫자입니다." : " is invalid number");
					}else{
						$sub[2] = floor($sub[2]);
						$mh["Set"][strtolower($n = $player->getName())] = $sub[2];
						$r = $mm . ($ik ? "$n 님의 최대체력을 $sub[2]로 설정했습니다." : "Set $n\'s Max health to $sub[2]");
					}
				}
			break;
			case "default":
			case "d":
			case "all":
			case "a":
			case "기본":
			case "전체":
				if(!isset($sub[1])){
					$r = $rm . ($ik ? "기본 <체력>" : "Default(D) <Health>");
				}elseif(!is_numeric($sub[1]) || $sub[1] < 1){
					$r = $mm . $sub[1] . ($ik ? "는 잘못된 숫자입니다." : " is invalid number");
				}else{
					$sub[2] = floor($sub[1]);
					$mh["Default"] = $sub[1];
					$r = $mm . ($ik ? "기본 최대체력을 $sub[1]로 설정했습니다." : "Set Default Max health to $sub[1]");
				}
			break;
			default:
				return false;
			break;
		}
		if(isset($r)) $sender->sendMessage($r);
		if($this->mh !== $mh){
			$this->mh = $mh;
			$this->saveYml();
		}
		return true;
	}

	/**
	 * @priority HIGHEST
	 */
	public function onPlayerInteract(PlayerInteractEvent $event){
		if($event->isCancelled()) return;
		$p = $event->getPlayer();
		$b = $event->getBlock();
		if($b->getID() !== 92 || $p->getHealth() >= $p->getMaxHealth()) return;
		$this->getServer()->getPluginManager()->callEvent($ev = new EntityRegainHealthEvent($p, 3, EntityRegainHealthEvent::CAUSE_EATING));
		if(!$ev->isCancelled()){
			if(($dmg = $b->getDamage() + 1) >= 0x06){
				$b->getLevel()->setBlock($b, Block::get(0, 0));
			}else{
				$b->getLevel()->setBlock($b, Block::get(92, $dmg));
			}
			$p->heal($ev->getAmount(), $ev);
		}
	}

	public function onDataPacketReceive(DataPacketReceiveEvent $event){
		$pk = $event->getPacket();
		$p = $event->getPlayer();
		if($pk->pid() == ProtocolInfo::ENTITY_EVENT_PACKET && $pk->event == 9){
			$i = $p->getInventory()->getItemInHand();
			$items = [Item::APPLE => 4, Item::MUSHROOM_STEW => 10, Item::BEETROOT_SOUP => 10, Item::BREAD => 5, Item::RAW_PORKCHOP => 3, Item::COOKED_PORKCHOP => 8, Item::RAW_BEEF => 3, Item::STEAK => 8, Item::COOKED_CHICKEN => 6, Item::RAW_CHICKEN => 2, Item::MELON_SLICE => 2, Item::GOLDEN_APPLE => 10, Item::PUMPKIN_PIE => 8, Item::CARROT => 4, Item::POTATO => 1, Item::BAKED_POTATO => 6];
			if($p->getHealth() >= 20 && $p->getHealth() < $p->getMaxHealth() && isset($items[$i->getID()])){
				$this->getServer()->getPluginManager()->callEvent($ev = new PlayerItemConsumeEvent($p, $i));
				if($ev->isCancelled()){
					$p->getInventory()->sendContents($p);
					break;
				}
				$pk = new EntityEventPacket();
				$pk->eid = 0;
				$pk->event = 9;
				$p->dataPacket($pk);
				$pk->eid = $p->getId();
				$this->getServer()->broadcastPacket($p->getViewers(), $pk);
				$amount = $items[$i->getID()];
				$this->getServer()->getPluginManager()->callEvent($ev = new EntityRegainHealthEvent($p, $amount, EntityRegainHealthEvent::CAUSE_EATING));
				if(!$ev->isCancelled()){
					$p->heal($ev->getAmount(), $ev);
				}
				--$i->count;
				$p->getInventory()->setItemInHand($i, $p);
				if($i->getID() === Item::MUSHROOM_STEW or $i->getID() === Item::BEETROOT_SOUP){
					$p->getInventory()->addItem(Item::get(Item::BOWL, 0, 1), $p);
				}
			}
		}elseif($pk->pid() == ProtocolInfo::RESPAWN_PACKET && $p->spawned && $p->dead){
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
			$event->setCancelled();
		}
	}

	public function onDataPacketSend(DataPacketSendEvent $event){
		$pk = $event->getPacket();
		$p = $event->getPlayer();
		if($pk instanceof SetHealthPacket){
			$health = floor(($p->getHealth() / $p->getMaxHealth()) * 20);
			$pk->health = $p->dead ? 0 : ($health <= 0 && $p->getHealth() > 0 ? 1 : $health);
		}
	}

	/**
	 * @priority HIGHEST
	 */
	public function onPlayerPreLogin(PlayerPreLoginEvent $event){
		if($event->isCancelled()) return;
		$p = $event->getPlayer();
		$p->setMaxHealth(isset($this->mh["Set"][$n = strtolower($p->getName())]) ? $this->mh["Set"][$n] : $this->mh["Default"]);
		$p->setHealth(isset($this->mh["Player"][$n]) ? $this->mh["Player"][$n] : $p->getMaxHealth());
	}

	public function onTick(){
		$this->tick++;
		foreach($this->getServer()->getOnlinePlayers() as $p){
			if($p->dead) continue;
			$p->setMaxHealth(isset($this->mh["Set"][$n = strtolower($p->getName())]) ? $this->mh["Set"][$n] : $this->mh["Default"]);
			$p->setHealth($p->getHealth());
			$this->mh["Player"][$n] = $p->getHealth();
			if(!isset($this->player[$n]) || $this->player[$n]->closed == true){
				$this->player[$n] = new Player4NameTag($p);
			}
		}
		if($this->tick >= 60){
			$this->tick = 0;
			$this->saveYml();
		}
	}

	public function loadYml(){
		@mkdir($this->getServer()->getDataPath() . "/plugins/! MineBlock/");
		$this->mh = (new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "MaxHealth.yml", Config::YAML, ["Default" => 20, "Set" => [], "Player" => []]))->getAll();
	}

	public function saveYml(){
		$mh = new Config($this->getServer()->getDataPath() . "/plugins/! MineBlock/" . "MaxHealth.yml", Config::YAML);
		$mh->setAll($this->mh);
		$mh->save();
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
				$name = "\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n";
				if($p->dead){
					$name .= "You Dead...";
				}else{
					$name .= "Health: " . $p->getHealth() . "/" . $p->getMaxHealth() . " " . (($p->getHealth() / $p->getMaxHealth()) * 100) . "%";
				}
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
				$p->getServer()->broadcastPacket($this->hasSpawned, $pk);
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