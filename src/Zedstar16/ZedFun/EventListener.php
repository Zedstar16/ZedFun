<?php


namespace Zedstar16\ZedFun;


use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\Player;

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

    public function onDeath(PlayerDeathEvent $event){
        $drops = $event->getDrops();
        $new = [];
        foreach($drops as $item){
            if($item->getNamedTag()->hasTag("nv")){
                $this->toreturn[$event->getPlayer()->getName()][] = $item;
                unset($drops, $item);
            }else{
                $new[] = $item;
            }
        }
        $event->setDrops($new);
    }

    public function onRespawn(PlayerRespawnEvent $event){
        $p = $event->getPlayer();
        $name = $p->getName();
        if(isset($this->toreturn[$name])){
            foreach($this->toreturn[$name] as $item){
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
        }
    }

    public function onHold(PlayerItemHeldEvent $event)
    {
        $p = $event->getPlayer();
        $item = $event->getItem();
        $data = $this->zf->getZedBowData($item);
        if($item->getNamedTag()->hasTag("nv") && !$event->getPlayer()->isOp()){
            $p->getPlayer()->getInventory()->remove($item);
        }
        if ($data !== null && (!$p->hasPermission("zedfun") || !$p->hasPermission("zedgun"))) {
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
            // i know i could have used the child permission node in the plugin.yml to avoid needing multiple permission checks
            if ($p instanceof Player && ($p->hasPermission("zedfun") || $p->hasPermission("zedgun"))) {
                $zf->shootZedArrow($p, $data[$zf::define["dartsize"]], $data[$zf::define["force"]]);
                $e->setCancelled(true);
            }
        }
    }
}
