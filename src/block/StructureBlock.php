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

/** Keeps native mode textures, without structure loading, saving, export or a GUI. */
class StructureBlock extends Opaque{
	private int $mode = 1;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{ $w->boundedIntAuto(0, 5, $this->mode); }
	public function getMode() : int{ return $this->mode; }
	/** @return $this */
	public function setMode(int $mode) : self{
		if($mode < 0 || $mode > 5){ throw new \InvalidArgumentException("Structure mode must be in range 0 ... 5"); }
		$this->mode = $mode;
		return $this;
	}

	public function getDrops(Item $item) : array{ return []; }
}
