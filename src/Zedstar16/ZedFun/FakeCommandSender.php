<?php


namespace Zedstar16\ZedFun;

use pocketmine\command\CommandSender;
use pocketmine\command\RemoteConsoleCommandSender;
use pocketmine\lang\TextContainer;
use pocketmine\OfflinePlayer;
use pocketmine\permission\PermissibleBase;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionAttachment;
use pocketmine\permission\PermissionAttachmentInfo;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use Zedstar16\_Api\OwnageAPI;

class FakeCommandSender implements CommandSender
{

    /** @var String  */
    private $username;
    /** @var RemoteConsoleCommandSender $rsender */
    private $rsender;
   /** @var array */
    private $permissions;
    /** @var int|null */
    protected $lineHeight = null;

    public function __construct(String $username, RemoteConsoleCommandSender $rsender){
        $this->username = $username;
        $this->rsender = $rsender;
        $this->permissions = OwnageAPI::$pureperms->getPermissions($this->getServer()->getOfflinePlayer($username), null);
    }

    /**
     * @param Permission|string $name
     */
    public function isPermissionSet($name) : bool{
        return in_array($name, $this->permissions);
    }

    /**
     * @param Permission|string $name
     */
    public function hasPermission($name) : bool{
        return in_array($name, $this->permissions);
    }

    public function addAttachment(Plugin $plugin, string $name = null, bool $value = null) : PermissionAttachment{
        return Server::getInstance()->getOfflinePlayer("")->addAttachment($plugin, $name, $value);
    }

    /**
     * @return void
     */
    public function removeAttachment(PermissionAttachment $attachment){

    }

    public function recalculatePermissions(){

    }

    /**
     * @return PermissionAttachmentInfo[]
     */
    public function getEffectivePermissions() : array{
        return $this->permissions;
    }

    /**
     * @return Server
     */
    public function getServer(){
        return Server::getInstance();
    }

    /**
     * @param TextContainer|string $message
     *
     * @return void
     */
    public function sendMessage($message){
        if($message instanceof TextContainer){
            $message = $this->getServer()->getLanguage()->translate($message);
        }else{
            $message = $this->getServer()->getLanguage()->translateString($message);
        }

        $this->rsender->sendMessage(trim($message, "\r\n"));
    }

    public function getName() : string{
        return $this->username;
    }

    public function isOp() : bool{
        return $this->getServer()->isOp($this->username);
    }

    /**
     * @return void
     */
    public function setOp(bool $value){

    }

    public function getScreenLineHeight() : int{
        return $this->lineHeight ?? PHP_INT_MAX;
    }

    public function setScreenLineHeight(int $height = null){
        if($height !== null and $height < 1){
            throw new \InvalidArgumentException("Line height must be at least 1");
        }
        $this->lineHeight = $height;
    }

}