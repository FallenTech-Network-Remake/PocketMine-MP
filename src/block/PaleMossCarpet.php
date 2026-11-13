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

class PaleMossCarpet extends MossCarpet{
	public function isSolid() : bool{ return !$this->upper; }
	public function getDropsForCompatibleTool(\pocketmine\item\Item $item) : array{ return $this->upper ? [] : parent::getDropsForCompatibleTool($item); }
	private bool $upper = false;
	/** @var int[] */
	private array $sides = [0, 0, 0, 0];
	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->bool($this->upper);
		for($i = 0; $i < 4; $i++){ $w->boundedIntAuto(0, 2, $this->sides[$i]); }
	}
	public function isUpper() : bool{ return $this->upper; }
	/** @return $this */
	public function setUpper(bool $upper) : self{ $this->upper = $upper; return $this; }
	public function getSideHeight(int $side) : int{ return $this->sides[$side]; }
	/** @return $this */
	public function setSideHeight(int $side, int $height) : self{
		if($side < 0 || $side > 3 || $height < 0 || $height > 2){ throw new \InvalidArgumentException("Invalid moss side height"); }
		$this->sides[$side] = $height; return $this;
	}
	protected function recalculateCollisionBoxes() : array{ return $this->upper ? [] : parent::recalculateCollisionBoxes(); }
	protected function canBeSupportedAt(Block $block) : bool{ return $this->upper ? $block->getSide(\pocketmine\math\Facing::DOWN)->hasSameTypeId($this) : parent::canBeSupportedAt($block); }
	public function getFlameEncouragement() : int{ return 15; }
	public function getFlammability() : int{ return 100; }
}
