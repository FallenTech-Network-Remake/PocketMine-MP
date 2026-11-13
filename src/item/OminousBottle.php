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

/** Item only; drinking and Bad Omen effects are not implemented. */
class OminousBottle extends Item{
	private int $amplifier = 0;

	protected function describeState(RuntimeDataDescriber $w) : void{
		$w->boundedIntAuto(0, 4, $this->amplifier);
	}

	public function getAmplifier() : int{
		return $this->amplifier;
	}

	/** @return $this */
	public function setAmplifier(int $amplifier) : self{
		if($amplifier < 0 || $amplifier > 4){
			throw new \InvalidArgumentException("Ominous bottle amplifier must be between 0 and 4");
		}
		$this->amplifier = $amplifier;
		return $this;
	}
}
