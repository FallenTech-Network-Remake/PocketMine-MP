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

use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\math\AxisAlignedBB;

/** Native egg appearance and drops; no hatching or sniffer entity. */
class SnifferEgg extends Transparent{
	private int $cracks = 0;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{ $w->boundedIntAuto(0, 2, $this->cracks); }
	public function getCracks() : int{ return $this->cracks; }
	/** @return $this */
	public function setCracks(int $cracks) : self{
		if($cracks < 0 || $cracks > 2){ throw new \InvalidArgumentException("Cracks must be in range 0 ... 2"); }
		$this->cracks = $cracks;
		return $this;
	}

	protected function recalculateCollisionBoxes() : array{ return [new AxisAlignedBB(1 / 16, 0, 2 / 16, 15 / 16, 1, 14 / 16)]; }
	public function getSupportType(int $facing) : SupportType{ return SupportType::NONE; }
}
