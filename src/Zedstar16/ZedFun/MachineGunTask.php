<?php


namespace Zedstar16\ZedFun;


use pocketmine\Player;
use pocketmine\scheduler\Task;

class MachineGunTask extends Task
{

    private $p, $dartsize, $force;

    public function __construct(Player $p, $dartsize, $force)
    {
        $this->p = $p;
        $this->dartsize = $dartsize;
        $this->force = $force;
    }

    public function onRun(int $currentTick)
    {
        $p = $this->p;
        $zf = ZedFun::getInstance();
        if($zf->isFiring($p)){
            $zf->shootZedArrow($p, $this->dartsize, $this->force, true);
        }else $zf->getScheduler()->cancelTask($this->getTaskId());
    }


}