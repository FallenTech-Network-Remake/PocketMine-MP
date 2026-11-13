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

final class MushroomStem extends Opaque{
	// Runtime value 0 retains the original ordinary stem state (native metadata 10).
	private int $textureVariant = 0;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		if($this->getTypeId() !== BlockTypeIds::ALL_SIDED_MUSHROOM_STEM){
			$w->boundedIntAuto(0, 14, $this->textureVariant);
		}
	}

	public function getMushroomBits() : int{
		if($this->getTypeId() === BlockTypeIds::ALL_SIDED_MUSHROOM_STEM){ return 15; }
		return $this->textureVariant === 0 ? 10 : ($this->textureVariant <= 10 ? $this->textureVariant - 1 : $this->textureVariant);
	}

	/** @return $this */
	public function setMushroomBits(int $bits) : self{
		if($this->getTypeId() === BlockTypeIds::ALL_SIDED_MUSHROOM_STEM){
			if($bits !== 15){ throw new \InvalidArgumentException("All-sided mushroom stems require metadata 15"); }
		}elseif($bits < 0 || $bits > 14){
			throw new \InvalidArgumentException("Ordinary mushroom stem metadata must be in range 0 ... 14");
		}else{
			$this->textureVariant = $bits === 10 ? 0 : ($bits < 10 ? $bits + 1 : $bits);
		}
		return $this;
	}

	public function getDropsForCompatibleTool(Item $item) : array{ return []; }

	public function isAffectedBySilkTouch() : bool{ return true; }
}
