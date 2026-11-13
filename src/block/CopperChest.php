<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\tile\Chest as TileChest;
use pocketmine\block\utils\CopperMaterial;
use pocketmine\block\utils\CopperTrait;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class CopperChest extends Chest implements CopperMaterial{
	use CopperTrait {
		onInteract as private onCopperInteract;
	}

	protected function onPaired(Chest $other) : void{
		if($other instanceof self){
			$oxidation = $this->getOxidation()->value < $other->getOxidation()->value ? $this->getOxidation() : $other->getOxidation();
			$waxed = $this->isWaxed() && $other->isWaxed();
			$this->setOxidation($oxidation)->setWaxed($waxed);
			$other->setOxidation($oxidation)->setWaxed($waxed);
			$world = $this->position->getWorld();
			$world->setBlock($this->position, $this);
			$world->setBlock($other->position, $other);
		}
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if(($player === null || $player->isSneaking()) && $this->onCopperInteract($item, $face, $clickVector, $player, $returnedItems)){
			$world = $this->position->getWorld();
			$tile = $world->getTile($this->position);
			$pair = $tile instanceof TileChest ? $tile->getPair()?->getBlock() : null;
			if($pair instanceof self){
				$pair->setOxidation($this->getOxidation())->setWaxed($this->isWaxed());
				$world->setBlock($pair->position, $pair);
			}
			return true;
		}
		return parent::onInteract($item, $face, $clickVector, $player, $returnedItems);
	}

	public function getFuelTime() : int{
		return 0;
	}
}
