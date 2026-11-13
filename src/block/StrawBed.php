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

use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\HorizontalFacingTrait;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

/** Two-part decorative straw bed; does not sleep or set a spawn point. */
class StrawBed extends Transparent implements HorizontalFacing{
	use HorizontalFacingTrait;
	private bool $head = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalFacing($this->facing);
		$w->bool($this->head);
	}

	public function isHeadPart() : bool{ return $this->head; }
	/** @return $this */
	public function setHead(bool $head) : self{ $this->head = $head; return $this; }

	public function getOtherHalf() : ?self{
		$other = $this->getSide($this->head ? Facing::opposite($this->facing) : $this->facing);
		return $other instanceof self && $other->head !== $this->head && $other->facing === $this->facing ? $other : null;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$this->facing = $player !== null ? $player->getHorizontalFacing() : Facing::NORTH;
		$this->head = false;
		$next = $blockReplace->getSide($this->facing);
		if(!$next->canBeReplaced() || $blockReplace->getSide(Facing::DOWN)->getSupportType(Facing::UP) === SupportType::NONE || $next->getSide(Facing::DOWN)->getSupportType(Facing::UP) === SupportType::NONE){
			return false;
		}
		$tx->addBlock($blockReplace->getPosition(), $this)->addBlock($next->getPosition(), (clone $this)->setHead(true));
		return true;
	}

	public function getAffectedBlocks() : array{
		$other = $this->getOtherHalf();
		return $other !== null ? [$this, $other] : [$this];
	}

	public function getDropsForCompatibleTool(Item $item) : array{ return $this->head ? [$this->asItem()] : []; }
	protected function recalculateCollisionBoxes() : array{ return [AxisAlignedBB::one()->trim(Facing::UP, 13 / 16)]; }
	public function getSupportType(int $facing) : SupportType{ return SupportType::NONE; }
	public function getMaxStackSize() : int{ return 16; }
	public function getFlameEncouragement() : int{ return 30; }
	public function getFlammability() : int{ return 60; }
}
