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

/** Native Education item with saved metadata, without Education gameplay or GUIs. */
class EducationItem extends Item{
	private int $variant = 0;

	public function __construct(ItemIdentifier $identifier, string $name, private int $stackSize = 64, private int $maxVariant = 0){
		parent::__construct($identifier, $name);
	}

	protected function describeState(RuntimeDataDescriber $w) : void{
		if($this->maxVariant > 0){
			$w->boundedIntAuto(0, $this->maxVariant, $this->variant);
		}
	}

	public function getVariant() : int{
		return $this->variant;
	}

	/** @return $this */
	public function setVariant(int $variant) : self{
		if($variant < 0 || $variant > $this->maxVariant){
			throw new \InvalidArgumentException("Invalid Education item variant: $variant");
		}
		$this->variant = $variant;
		return $this;
	}

	public function getMaxStackSize() : int{
		return $this->stackSize;
	}
}
