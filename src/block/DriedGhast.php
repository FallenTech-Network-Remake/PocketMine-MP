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

use pocketmine\block\utils\FacesOppositePlacingPlayerTrait;
use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\math\AxisAlignedBB;

/** Preserves rehydration appearance without timers or ghast spawning. */
class DriedGhast extends Transparent implements HorizontalFacing{
	use FacesOppositePlacingPlayerTrait;
	private int $rehydrationLevel = 0;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalFacing($this->facing);
		$w->boundedIntAuto(0, 3, $this->rehydrationLevel);
	}

	public function getRehydrationLevel() : int{ return $this->rehydrationLevel; }
	/** @return $this */
	public function setRehydrationLevel(int $level) : self{
		if($level < 0 || $level > 3){ throw new \InvalidArgumentException("Rehydration level must be in range 0 ... 3"); }
		$this->rehydrationLevel = $level;
		return $this;
	}

	protected function recalculateCollisionBoxes() : array{ return [new AxisAlignedBB(3 / 16, 0, 3 / 16, 13 / 16, 10 / 16, 13 / 16)]; }
	public function getSupportType(int $facing) : SupportType{ return SupportType::NONE; }
}
