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
use pocketmine\math\Axis;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

/** Shared native spike shapes; no growth, liquid dripping or falling entities. */
class PointedDripstone extends Transparent{
	public const TIP = 0;
	public const FRUSTUM = 1;
	public const MIDDLE = 2;
	public const BASE = 3;
	public const MERGE = 4;

	private bool $hanging = false;
	private int $thickness = self::TIP;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->bool($this->hanging);
		$w->boundedIntAuto(0, 4, $this->thickness);
	}

	public function isHanging() : bool{ return $this->hanging; }
	/** @return $this */
	public function setHanging(bool $hanging) : self{ $this->hanging = $hanging; return $this; }
	public function getThickness() : int{ return $this->thickness; }
	/** @return $this */
	public function setThickness(int $thickness) : self{
		if($thickness < 0 || $thickness > 4){ throw new \InvalidArgumentException("Thickness must be in range 0 ... 4"); }
		$this->thickness = $thickness;
		return $this;
	}

	/** @phpstan-assert-if-true self $block */
	private function isMatchingSpike(Block $block) : bool{
		return $block instanceof self && $block->hasSameTypeId($this);
	}

	private function hasSupport(Block $target, bool $hanging) : bool{
		$direction = $hanging ? Facing::UP : Facing::DOWN;
		$support = $target->getSide($direction);
		return $support->getSupportType(Facing::opposite($direction)) === SupportType::FULL || ($this->isMatchingSpike($support) && $support->hanging === $hanging);
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$this->hanging = $face === Facing::DOWN || (Facing::axis($face) !== Axis::Y && $player !== null && $player->getLocation()->pitch < 0);
		if(!$this->hasSupport($blockReplace, $this->hanging)){
			$this->hanging = !$this->hanging;
			if(!$this->hasSupport($blockReplace, $this->hanging)){ return false; }
		}
		$this->thickness = $this->calculateThickness($blockReplace);
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	private function calculateThickness(Block $target) : int{
		$tipDirection = $this->hanging ? Facing::DOWN : Facing::UP;
		$next = $target->getSide($tipDirection);
		if($this->isMatchingSpike($next)){
			if($next->hanging !== $this->hanging){ return self::MERGE; }
			if($next->thickness === self::TIP || $next->thickness === self::MERGE){ return self::FRUSTUM; }
			$back = $target->getSide(Facing::opposite($tipDirection));
			return $this->isMatchingSpike($back) && $back->hanging === $this->hanging ? self::MIDDLE : self::BASE;
		}
		return self::TIP;
	}

	public function onNearbyBlockChange() : void{
		if(!$this->hasSupport($this, $this->hanging)){
			$this->position->getWorld()->useBreakOn($this->position);
		}elseif(($thickness = $this->calculateThickness($this)) !== $this->thickness){
			$this->thickness = $thickness;
			$this->position->getWorld()->setBlock($this->position, $this);
		}
	}

	protected function recalculateCollisionBoxes() : array{
		$inset = match($this->thickness){ self::TIP => 5, self::FRUSTUM => 4, self::BASE => 2, default => 3 } / 16;
		return [new AxisAlignedBB($inset, $this->hanging && $this->thickness === self::TIP ? 5 / 16 : 0, $inset, 1 - $inset, !$this->hanging && $this->thickness === self::TIP ? 11 / 16 : 1, 1 - $inset)];
	}

	public function isSolid() : bool{ return false; }
	public function getSupportType(int $facing) : SupportType{ return SupportType::NONE; }
}
