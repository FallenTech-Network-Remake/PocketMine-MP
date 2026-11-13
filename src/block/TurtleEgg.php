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

use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

/** Stackable eggs; hatching and trampling are not implemented. */
class TurtleEgg extends Transparent{
	private int $count = 1;
	private int $cracks = 0;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->boundedIntAuto(1, 4, $this->count);
		$w->boundedIntAuto(0, 2, $this->cracks);
	}

	public function getCount() : int{ return $this->count; }
	/** @return $this */
	public function setCount(int $count) : self{
		if($count < 1 || $count > 4){ throw new \InvalidArgumentException("Egg count must be in range 1 ... 4"); }
		$this->count = $count;
		return $this;
	}

	public function getCracks() : int{ return $this->cracks; }
	/** @return $this */
	public function setCracks(int $cracks) : self{
		if($cracks < 0 || $cracks > 2){ throw new \InvalidArgumentException("Cracks must be in range 0 ... 2"); }
		$this->cracks = $cracks;
		return $this;
	}

	public function canBePlacedAt(Block $blockReplace, Vector3 $clickVector, int $face, bool $isClickedBlock) : bool{
		return $blockReplace instanceof self ? $blockReplace->count < 4 : parent::canBePlacedAt($blockReplace, $clickVector, $face, $isClickedBlock);
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($blockReplace instanceof self){
			if($blockReplace->count >= 4){ return false; }
			$this->count = $blockReplace->count + 1;
			$this->cracks = $blockReplace->cracks;
		}
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		if($this->count > 1 && !($player !== null && $player->isCreative())){
			$this->position->getWorld()->setBlock($this->position, (clone $this)->setCount($this->count - 1)->setCracks(0));
			return true;
		}
		return parent::onBreak($item, $player, $returnedItems);
	}

	protected function recalculateCollisionBoxes() : array{
		return [$this->count === 1 ? new AxisAlignedBB(3 / 16, 0, 3 / 16, 12 / 16, 7 / 16, 12 / 16) : new AxisAlignedBB(1 / 16, 0, 1 / 16, 15 / 16, 7 / 16, 15 / 16)];
	}

	public function isSolid() : bool{ return false; }
	public function getSupportType(int $facing) : SupportType{ return SupportType::NONE; }
	public function isAffectedBySilkTouch() : bool{ return true; }
	public function getDropsForCompatibleTool(Item $item) : array{ return []; }
	public function getSilkTouchDrops(Item $item) : array{ return [$this->asItem()]; }
}
