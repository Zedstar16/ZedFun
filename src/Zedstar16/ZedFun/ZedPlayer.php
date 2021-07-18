<?php


namespace Zedstar16\ZedFun;


use pocketmine\network\SourceInterface;
use pocketmine\Player;

class ZedPlayer extends Player
{

    public function __construct(SourceInterface $interface, string $ip, int $port)
    {
        parent::__construct($interface, $ip, $port);
    }

    public function getName(): string{
        $username = $this->username;
        if(isset(ZedFun::$data[$username])) {
            $username = ZedFun::$data[$username];
            return $username;
        }
        return $username;
    }

    public function getDisplayName(): string{
        $displayName = $this->displayName;
        if(isset(ZedFun::$data[$this->username])) {
            $displayName = ZedFun::$data[$this->username];
            return $displayName;
        }
        return $displayName;
    }

    public function getLowerCaseName(): string{
        $iusername = $this->iusername;
        if(isset(ZedFun::$data[$this->username])) {
           $iusername =  strtolower(ZedFun::$data[$this->username]);
        }
        return $iusername;
    }

}
