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

namespace pocketmine\item;

use pocketmine\data\runtime\RuntimeDataDescriber;

/** Empty/filled map item only; rendering, exploration and cartography are not implemented. */
class Map extends Item{
	private int $variant = 0;
	public const MAX_VARIANT = 0x7fff;

	protected function describeState(RuntimeDataDescriber $w) : void{
		$w->boundedIntAuto(0, self::MAX_VARIANT, $this->variant);
	}
	public function getVariant() : int{ return $this->variant; }
	/** @return $this */
	public function setVariant(int $variant) : self{
		if($variant < 0 || $variant > self::MAX_VARIANT){
			throw new \InvalidArgumentException("Invalid map variant: $variant");
		}
		$this->variant = $variant;
		return $this;
	}
}
