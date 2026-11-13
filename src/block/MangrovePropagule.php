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

use pocketmine\block\utils\BlockEventHelper;
use pocketmine\block\utils\StaticSupportTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Fertilizer;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class MangrovePropagule extends Flowable{
	use StaticSupportTrait;
	public const MAX_AGE = 4;
	private int $age = 0;
	private bool $hanging = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->boundedIntAuto(0, self::MAX_AGE, $this->age);
		$w->bool($this->hanging);
	}

	public function getAge() : int{ return $this->age; }
	public function isHanging() : bool{ return $this->hanging; }

	/** @return $this */
	public function setAge(int $age) : self{
		if($age < 0 || $age > self::MAX_AGE){
			throw new \InvalidArgumentException("Propagule age must be between 0 and 4");
		}
		$this->age = $age;
		return $this;
	}

	/** @return $this */
	public function setHanging(bool $hanging) : self{
		$this->hanging = $hanging;
		return $this;
	}

	private function canBeSupportedAt(Block $block) : bool{
		if($this->hanging){
			return $block->getSide(Facing::UP)->getTypeId() === BlockTypeIds::MANGROVE_LEAVES;
		}
		$below = $block->getSide(Facing::DOWN);
		return $below->hasTypeTag(BlockTypeTags::DIRT) || $below->hasTypeTag(BlockTypeTags::MUD) || $below->getTypeId() === BlockTypeIds::CLAY;
	}

	public function getDropsForCompatibleTool(Item $item) : array{
		return $this->hanging && $this->age < self::MAX_AGE ? [] : [$this->asItem()];
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if($this->hanging && $this->age < self::MAX_AGE && $item instanceof Fertilizer && BlockEventHelper::grow($this, (clone $this)->setAge($this->age + 1), $player)){
			$item->pop();
			return true;
		}
		return false;
	}

	public function getFuelTime() : int{
		return 100;
	}
}
