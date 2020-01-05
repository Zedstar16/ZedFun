<?php

namespace Zedstar16\ZedFun\entity;

use pocketmine\entity\Entity;
use pocketmine\entity\projectile\Arrow;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;

class ZedArrow extends Arrow
{

    public $gravity = 0;
    public $shootingentity;
    protected $damage;

    public function __construct(Level $level, CompoundTag $nbt, Entity $shootingEntity = null, $damage = 0)
    {
        $this->damage = $damage;
        $this->shootingentity = $shootingEntity;
        parent::__construct($level, $nbt, $shootingEntity, false);
    }

    public function onCollideWithPlayer(Player $player): void
    {
        if($this->shootingentity instanceof Player) {
            if ($this->shootingentity !== $player) {
                $d = $this->getDirectionVector();
                $player->setMotion(new Vector3(-$d->getX(), 1, -$d->getZ()));
            }
        }
    }

    public function getName(): String{
        return "ZedArrow";
    }

    /**
     * @param int $tickDiff
     * @return bool
     */

    public function entityBaseTick(int $tickDiff = 1): bool
    {
        if ($this->closed) {
            return false;
        }
        $hasUpdate = parent::entityBaseTick($tickDiff);
        return $hasUpdate;
    }


}