<?php


namespace Zedstar16\ZedFun;


use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\Player;

class EventListener implements Listener
{

    private $zf;

    public function __construct()
    {
        $this->zf = ZedFun::getInstance();
    }


    public function onUse(DataPacketReceiveEvent $e)
    {
        $pk = $e->getPacket();
        if ($pk instanceof InventoryTransactionPacket) {
            if ($pk->transactionType == InventoryTransactionPacket::TYPE_RELEASE_ITEM) {
                ZedFun::getInstance()->stopFiring($e->getPlayer());
            }
        }
    }


    public function onDamage(EntityDamageByEntityEvent $e)
    {
        $damager = $e->getDamager();
        if ($damager instanceof Player) {
            $z = $this->zf->getZedBowData($damager->getInventory()->getItemInHand());
            if ($z !== null) {
                if ($z[ZedFun::define["automatic"]] == 1) {
                    $e->setBaseDamage(0);
                }else $e->setBaseDamage(5);
            }
        }
    }

    public function f(EntityDamageEvent $e){
        echo $e->getBaseDamage(). " I ".$e->getFinalDamage(), PHP_EOL;
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
            if ($p instanceof Player && $p->hasPermission("zedfun")) {
                $zf->shootZedArrow($p, $data[$zf::define["dartsize"]], $data[$zf::define["force"]]);
                $e->setCancelled(true);
            }
        }
    }
}