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
use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\HorizontalFacingTrait;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Fertilizer;
use pocketmine\item\Item;
use pocketmine\math\Axis;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

class ShelfMushroom extends Flowable implements HorizontalFacing{
	use HorizontalFacingTrait;
	private bool $large = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalFacing($this->facing);
		$w->bool($this->large);
	}

	public function isLarge() : bool{ return $this->large; }

	/** @return $this */
	public function setLarge(bool $large) : self{
		$this->large = $large;
		return $this;
	}

	protected function recalculateCollisionBoxes() : array{
		$inset = $this->large ? 1 / 16 : 3 / 16;
		$depth = $this->large ? 10 / 16 : 7 / 16;
		return [match($this->facing){
			Facing::NORTH => new AxisAlignedBB($inset, 4 / 16, 1 - $depth, 1 - $inset, 11 / 16, 1),
			Facing::SOUTH => new AxisAlignedBB($inset, 4 / 16, 0, 1 - $inset, 11 / 16, $depth),
			Facing::WEST => new AxisAlignedBB(1 - $depth, 4 / 16, $inset, 1, 11 / 16, 1 - $inset),
			Facing::EAST => new AxisAlignedBB(0, 4 / 16, $inset, $depth, 11 / 16, 1 - $inset),
			default => throw new \LogicException("Invalid horizontal facing")
		}];
	}

	public function canBePlacedAt(Block $blockReplace, Vector3 $clickVector, int $face, bool $isClickedBlock) : bool{
		return Facing::axis($face) !== Axis::Y && $blockReplace->getSide(Facing::opposite($face))->getSupportType($face) === SupportType::FULL && parent::canBePlacedAt($blockReplace, $clickVector, $face, $isClickedBlock);
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if(!$this->canBePlacedAt($blockReplace, $clickVector, $face, true)){
			return false;
		}
		$this->setFacing($face);
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onNearbyBlockChange() : void{
		if($this->getSide(Facing::opposite($this->facing))->getSupportType($this->facing) !== SupportType::FULL){
			$this->position->getWorld()->useBreakOn($this->position);
		}
	}

	public function getDropsForCompatibleTool(Item $item) : array{
		return [$this->asItem()->setCount($this->large ? 2 : 1)];
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if(!$this->large && $item instanceof Fertilizer && BlockEventHelper::grow($this, (clone $this)->setLarge(true), $player)){
			$item->pop();
			return true;
		}
		return false;
	}
}
