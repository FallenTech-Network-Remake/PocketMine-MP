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

use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

class Crafter extends Opaque{
	private int $orientation = 10;
	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{ $w->boundedIntAuto(0, 11, $this->orientation); }
	public function getOrientation() : int{ return $this->orientation; }
	/** @return $this */
	public function setOrientation(int $orientation) : self{ if($orientation < 0 || $orientation > 11){ throw new \InvalidArgumentException("Invalid crafter orientation"); } $this->orientation = $orientation; return $this; }
	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$facing = $player !== null ? Facing::opposite($player->getHorizontalFacing()) : Facing::NORTH;
		$this->orientation = match($facing){ Facing::WEST => 8, Facing::EAST => 9, Facing::NORTH => 10, default => 11 };
		if($player !== null && ($player->getLocation()->pitch > 45 || $player->getLocation()->pitch < -45)){
			$this->orientation = ($player->getLocation()->pitch > 45 ? 4 : 0) + match($player->getHorizontalFacing()){ Facing::EAST => 0, Facing::NORTH => 1, Facing::SOUTH => 2, default => 3 };
		}
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}
}
