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

use pocketmine\block\utils\StaticSupportTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\math\Facing;

class PaleHangingMoss extends Flowable{
	use StaticSupportTrait { onNearbyBlockChange as private checkSupport; }
	private bool $tip = true;
	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{ $w->bool($this->tip); }
	public function isTip() : bool{ return $this->tip; }
	/** @return $this */
	public function setTip(bool $tip) : self{ $this->tip = $tip; return $this; }
	private function canBeSupportedAt(Block $block) : bool{ $above = $block->getSide(Facing::UP); return $above instanceof self || $above instanceof Leaves || $above->getSupportType(Facing::DOWN)->hasCenterSupport(); }
	public function onNearbyBlockChange() : void{
		if(!$this->canBeSupportedAt($this)){ $this->checkSupport(); return; }
		$tip = !$this->getSide(Facing::DOWN) instanceof self;
		if($tip !== $this->tip){ $this->tip = $tip; $this->position->getWorld()->setBlock($this->position, $this); }
	}
	public function getFlameEncouragement() : int{ return 15; }
	public function getFlammability() : int{ return 100; }
	public function isAffectedBySilkTouch() : bool{ return true; }
}
