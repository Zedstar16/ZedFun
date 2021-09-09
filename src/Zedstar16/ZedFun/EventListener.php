<?php


namespace Zedstar16\ZedFun;


use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\Player;
use Zedstar16\Supression\Engine;
use Zedstar16\ZDuels\Main;

class EventListener implements Listener
{

    private $zf;
    public $toreturn = [];

    public function __construct()
    {
        $this->zf = ZedFun::getInstance();
    }


    public function onUse(DataPacketReceiveEvent $e)
    {
        $pk = $e->getPacket();
        if ($pk instanceof InventoryTransactionPacket) {
            if ($pk->trData->getTypeId() == InventoryTransactionPacket::TYPE_RELEASE_ITEM) {
                ZedFun::getInstance()->stopFiring($e->getPlayer());
            }
        }
    }

    public function onDeath(PlayerDeathEvent $event)
    {
        $drops = $event->getDrops();
        $new = [];
        foreach ($drops as $item) {
            if ($item->getNamedTag()->hasTag("nv")) {
                $this->toreturn[$event->getPlayer()->getName()][] = $item;
                unset($drops, $item);
            } else {
                $new[] = $item;
            }
        }
        $event->setDrops($new);
    }

    public function onRespawn(PlayerRespawnEvent $event)
    {
        $p = $event->getPlayer();
        $name = $p->getName();
        if (isset($this->toreturn[$name])) {
            foreach ($this->toreturn[$name] as $item) {
                $p->getInventory()->addItem($item);
            }
            unset($this->toreturn[$name]);
        }
    }

    public function onDamage(EntityDamageByEntityEvent $e)
    {
        try {
            $damager = $e->getDamager();
            if ($damager instanceof Player) {
                $z = $this->zf->getZedBowData($damager->getInventory()->getItemInHand());
                if ($z !== null) {
                    if ($z[ZedFun::define["automatic"]] == 1) {
                        $e->setBaseDamage(0);
                    } else $e->setBaseDamage(5);
                }
                if (!$e instanceof EntityDamageByChildEntityEvent) {
                    $name = $damager->getLowerCaseName();
                    if ($this->zf->isReachModified($name)) {
                        $reach = ZedFun::$data["reach"][$name];
                        $target = $e->getEntity();
                        if ($damager->distance($target) >= $reach) {
                            $e->setCancelled(true);
                        }
                    }
                }
            }
        } catch (\Throwable $err) {
        }
    }

    public function onInteract(PlayerInteractEvent $e)
    {
        if ($e->getAction() == 3) {
            $p = $e->getPlayer();
            $item = $p->getInventory()->getItemInHand();
            $zf = $this->zf;
            $data = $zf->getZedBowData($item);
            if ($data !== null) {
                if ($data[ZedFun::define["automatic"]] == 1) {
                    if ($p->hasPermission("zedfun")) {
                        $dartsize = $data[$zf::define["dartsize"]];
                        $force = $data[$zf::define["force"]];
                        $shootfrequency = $data[$zf::define["frequency"]];
                        $zf->startFiring($p, $dartsize, $force, $shootfrequency);
                    }
                }
            }
            $n = $p->getName();
            if(isset(ZedFun::$targets[$n]["toggled"]) && $p->getInventory()->getItemInHand()->getId() === Item::BOW && $p->isOp()){
                foreach (array_filter($p->getLevel()->getPlayers(), function ($player) use($p){
                    return $player->distance($p) < 50 && $player !== $p;
                }) as $target){
                    $aabb = $target->getBoundingBox()->expandedCopy(2, 2 ,2);
                    $aimingAt = $aabb->calculateIntercept($p->asVector3()->add(0, $p->getEyeHeight()), Engine::getEndVector($p, 50)->add($p->getDirectionVector()->multiply(50))) !== null;
                    if($aimingAt){
                        ZedFun::$targets[$n]["targetting"] = [$target, time()];
                        $p->sendActionBarMessage("§aAiming At §f".$target->getName());
                        break;
                    }
                }
            }
        }
    }
/*
    public function onPickup(InventoryPickupItemEvent $event)
    {
        $item = $event->getItem();
        $p = $event->getInventory()->getViewers()[0] ?? null;
        if ($p !== null) {
            if (!$this->check($item, $p)) {
                $this->scan($event->getInventory(), $p);
            }
        }
    }

    public function onTransaction(InventoryTransactionEvent $event)
    {
        $p = $event->getTransaction()->getSource();
        $actions = $event->getTransaction()->getActions();
        $scan = false;
        foreach ($actions as $action) {
            if (!$this->check($action->getSourceItem(), $p) || !$this->check($action->getTargetItem(), $p)) {
                $scan = true;
            }
        }
        $inventories = $event->getTransaction()->getInventories();
        $inventories[] = $p->getArmorInventory();
        $inventories[] = $p->getCursorInventory();
        if (!in_array($p->getInventory(), $inventories, true)) {
            $inventories[] = $p->getInventory();
        }
        if ($scan) {
            foreach ($inventories as $inventory) {
                $this->scan($inventory, $p);
            }
        }
    }*/

    public function scan(Inventory $inventory, Player $p)
    {
        foreach ($inventory->getContents() as $item) {
            if (!$this->check($item, $p)) {
                $inventory->remove($item);
            }
        }
    }

    public function check($item, Player $p)
    {
        if (Main::getDuelManager()->getDuel($p) !== null) {
            return true;
        }
        /** @var Item $item */
        $nbt = $item->getNamedTag();
        if ($this->zf->getZedBowData($item) !== null && !$p->hasPermission("zedgun")) {
            return false;
        }
        if ($nbt->hasTag("zfperm") && strtolower($nbt->getString("zfperm")) !== $p->getLowerCaseName()) {
            return false;
        }
        if ($nbt->hasTag("zduels") && Main::getDuelManager()->getDuel($p) === null) {
            return false;
        }
        if ($item->getId() === Item::ENCHANTED_GOLDEN_APPLE && strpos($item->getCustomName(), "INSANELY OP GOLDEN APPLE") === false) {
            return false;
        }
        return true;
    }

    public function onHold(PlayerItemHeldEvent $event)
    {
        $p = $event->getPlayer();
        $item = $event->getItem();
        $data = $this->zf->getZedBowData($item);
        if ($item->getNamedTag()->hasTag("nv") && !$event->getPlayer()->hasPermission("keepnv")) {
            $p->getPlayer()->getInventory()->remove($item);
        }
        if ($data !== null && (!$p->hasPermission("zedfun") && !$p->hasPermission("zedgun"))) {
            $p->sendMessage($item->getCustomName() . " §cremoved");
            $p->getInventory()->remove($item);
        }
        if ($this->zf->getZedBowData($item) == null && !$this->zf->isFiring($p)) {
            $this->zf->stopFiring($p);
        }
    }

    public function bowShoot(EntityShootBowEvent $e)
    {
        $zf = $this->zf;
        $bow = $e->getBow();
        $p = $e->getEntity();
        $data = $zf->getZedBowData($bow);
        if ($data !== null) {
            // i know i could have used the child permission node in the plugin.ymld to avoid needing multiple permission checks
            if ($p instanceof Player && ($p->hasPermission("zedfun") || $p->hasPermission("zedgun"))) {
                $zf->shootZedArrow($p, $data[$zf::define["dartsize"]], $data[$zf::define["force"]]);
                $e->setCancelled(true);
            }
        }
        if ($p instanceof Player && $p->isOp()) {
            $n = $p->getName();
            $data = ZedFun::$targets[$n] ?? null;
            if($data !== null){
                if($data["toggled"] ?? false){
                    if(isset($data["targetting"]) && (time() - $data["targetting"][1]) < 3){
                        ZedFun::shootMissile($p, $data["targetting"][0]);
                        $e->setCancelled(true);
                    }
                }else{
                    $target = $data["target"] ?? null;
                   if($target !== null && $p->getLevel() === $target->getLevel()){
                       ZedFun::shootMissile($p, $target);
                       $e->setCancelled(true);
                   }
                }
            }
        }
    }

   /* public function onMove(PlayerMoveEvent $event){
        $p = $event->getPlayer();
        $n = $p->getName();
        if(isset(ZedFun::$targets[$n]["toggled"])){
            foreach (array_filter($p->getLevel()->getPlayers(), function ($player) use($p){
                return $player->distance($p) < 50 && $player !== $p;
            }) as $target){
                $aabb = $target->getBoundingBox()->expand(3, 3 ,3);
                $aimingAt = $aabb->calculateIntercept($p->asVector3()->add(0, $p->getEyeHeight()), Engine::getEndVector($p, 50)->add($p->getDirectionVector()->multiply(50))) !== null;
                if($aimingAt){
                    ZedFun::$targets[$n]["targetting"] = [$target, time()];
                    $p->sendActionBarMessage("§aAiming At §f".$target->getName());
                    break;
                }
            }
        }
    }*/
}
