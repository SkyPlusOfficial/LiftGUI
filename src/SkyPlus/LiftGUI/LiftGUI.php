<?php

declare(strict_types=1);

namespace SkyPlus\LiftGUI;

use Closure;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\ItemFactory;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use pocketmine\world\sound\ChestCloseSound;
use pocketmine\world\sound\ChestOpenSound;

class LiftGUI extends PluginBase {

    private const EXIT_INDEX = 49;

    private array $warps;
    private array $indexes;

    protected function onEnable() : void {
        $this->saveDefaultConfig();

        if ($this->getConfig()->get("version") !== "1.0") {
            $this->getLogger()->notice("Config outdated! Renaming to config_old.yml...");
            rename($this->getDataFolder() . "config.yml", $this->getDataFolder() . "config_old.yml");
            $this->saveDefaultConfig();
            $this->getConfig()->reload();
        }

        $this->warps = $this->getConfig()->get("warps");
        $this->indexes = array_column($this->warps, "index");

        // Invalid index check
        $filter = array_filter($this->indexes, function (int $value) : bool {
           return $value < 0 || $value > 53 || $value === self::EXIT_INDEX;
        });
        if (!empty($filter)) {
            $this->getLogger()->error("Invalid index detected, disabling plugin...");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        if (!InvMenuHandler::isRegistered()) {
            InvMenuHandler::register($this);
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool {
        if ($command->getName() === "liftgui") {
            if (!$sender instanceof Player) {
                $sender->sendMessage(TextFormat::RED . "This command can only be run as player!");
                return true;
            }
            if (!$sender->hasPermission("liftgui.open")) {
                $sender->sendMessage(TextFormat::RED . "You don't have permission to run this command!");
                return true;
            }
            $this->getConfig()->reload();
            $this->warps = $this->getConfig()->get("warps");
            $this->indexes = array_column($this->warps, "index");
            $this->open($sender);
        }
        return true;
    }

    private function open(Player $player) {
        $inv = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $inv->setListener(InvMenu::readonly(Closure::fromCallable([$this, "handle"])));
        $inv->setName(TextFormat::colorize($this->getConfig()->get("name")));

        $contents = $inv->getInventory();

        for ($i = 0; $i <= 53; $i++) {
            if (in_array($i, $this->indexes, true) || $i === self::EXIT_INDEX) {
                continue;
            }
            $contents->setItem($i, ItemFactory::getInstance()->get(95, 15, 1)->setCustomName("§r"));
        }

        foreach ($this->warps as $warp) {
            $index = $warp["index"];
            $id = $warp["item"]["id"];
            $meta = $warp["item"]["meta"];
            $name = TextFormat::colorize($warp["name"]);
            $contents->setItem($index, ItemFactory::getInstance()->get($id, $meta, 1)->setCustomName($name));
        }

        $contents->setItem(self::EXIT_INDEX, ItemFactory::getInstance()->get(-161, 0, 1)->setCustomName("§cClose"));
        $inv->send($player);
    }

    private function handle(DeterministicInvMenuTransaction $transaction) {
        $player = $transaction->getPlayer();
        if (in_array($slot = $transaction->getAction()->getSlot(), $this->indexes)) {
            $arrayKey = array_search($slot, $this->indexes, true);
            $location = $this->warps[$arrayKey]["location"];
            $title = TextFormat::colorize($this->warps[$arrayKey]["title"]);

            $player->removeCurrentWindow();
            // Activate this when /warp command is available
            //$this->getServer()->dispatchCommand($player, "warp " . $location);
            $player->sendTitle($title);
            $player->broadcastSound(new ChestOpenSound());
        } elseif ($slot === self::EXIT_INDEX) {
            $player->removeCurrentWindow();
            $player->broadcastSound(new ChestCloseSound());
        }
    }

}