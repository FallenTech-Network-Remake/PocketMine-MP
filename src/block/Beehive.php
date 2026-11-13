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
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;

class Beehive extends Opaque implements HorizontalFacing{
	use FacesOppositePlacingPlayerTrait;
	private int $honeyLevel = 0;
	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{ $w->horizontalFacing($this->facing); $w->boundedIntAuto(0, 5, $this->honeyLevel); }
	public function getHoneyLevel() : int{ return $this->honeyLevel; }
	/** @return $this */
	public function setHoneyLevel(int $level) : self{ if($level < 0 || $level > 5){ throw new \InvalidArgumentException("Honey level must be between 0 and 5"); } $this->honeyLevel = $level; return $this; }
	public function getDropsForCompatibleTool(Item $item) : array{ return $this->getTypeId() === BlockTypeIds::BEE_NEST ? [] : parent::getDropsForCompatibleTool($item); }
	public function isAffectedBySilkTouch() : bool{ return $this->getTypeId() === BlockTypeIds::BEE_NEST; }
	public function getFlameEncouragement() : int{ return 5; }
	public function getFlammability() : int{ return 20; }
	public function getFuelTime() : int{ return 300; }
}
