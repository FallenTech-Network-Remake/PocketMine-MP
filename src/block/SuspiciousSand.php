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
use pocketmine\entity\object\FallingBlock;
use pocketmine\item\Item;

/** Shared by suspicious sand and gravel; no archaeology loot or brushing. */
class SuspiciousSand extends Sand{
	private int $brushedProgress = 0;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->boundedIntAuto(0, 3, $this->brushedProgress);
	}

	public function getBrushedProgress() : int{ return $this->brushedProgress; }
	/** @return $this */
	public function setBrushedProgress(int $progress) : self{
		if($progress < 0 || $progress > 3){ throw new \InvalidArgumentException("Brushing progress must be in range 0 ... 3"); }
		$this->brushedProgress = $progress;
		return $this;
	}

	public function getDrops(Item $item) : array{ return []; }
	public function onHitGround(FallingBlock $blockEntity) : bool{ return false; }
}
