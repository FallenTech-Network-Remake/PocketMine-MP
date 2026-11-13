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

/** Decorative structure connector without a GUI or generation data. */
class Jigsaw extends DirectionalUtilityBlock{
	private int $rotation = 0;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->facing($this->facing);
		$w->boundedIntAuto(0, 3, $this->rotation);
	}

	public function getRotation() : int{ return $this->rotation; }
	/** @return $this */
	public function setRotation(int $rotation) : self{
		if($rotation < 0 || $rotation > 3){ throw new \InvalidArgumentException("Rotation must be in range 0 ... 3"); }
		$this->rotation = $rotation;
		return $this;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$this->setFacing($face);
		$this->rotation = $player !== null ? match($player->getHorizontalFacing()){ Facing::WEST => 1, Facing::NORTH => 2, Facing::EAST => 3, default => 0 } : 0;
		$tx->addBlock($blockReplace->getPosition(), $this);
		return true;
	}

	public function getDrops(Item $item) : array{ return []; }
}
