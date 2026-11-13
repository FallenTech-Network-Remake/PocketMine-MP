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
use pocketmine\event\block\BlockGrowEvent;
use pocketmine\item\Fertilizer;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

class Seagrass extends Flowable{
	use StaticSupportTrait { canBePlacedAt as supportedWhenPlacedAt; }
	public const SINGLE = 0;
	public const DOUBLE_BOTTOM = 1;
	public const DOUBLE_TOP = 2;
	private int $part = self::SINGLE;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->boundedIntAuto(self::SINGLE, self::DOUBLE_TOP, $this->part);
	}

	public function getPart() : int{ return $this->part; }

	/** @return $this */
	public function setPart(int $part) : self{
		if($part < self::SINGLE || $part > self::DOUBLE_TOP){
			throw new \InvalidArgumentException("Invalid seagrass part");
		}
		$this->part = $part;
		return $this;
	}

	public function canBeFlowedInto() : bool{
		return false;
	}

	public function getSecondaryStateId() : int{
		return VanillaBlocks::WATER()->getStateId();
	}

	public function canBePlacedAt(Block $blockReplace, Vector3 $clickVector, int $face, bool $isClickedBlock) : bool{
		return $blockReplace instanceof Water && $blockReplace->isSource() && $this->supportedWhenPlacedAt($blockReplace, $clickVector, $face, $isClickedBlock);
	}

	private function canBeSupportedAt(Block $block) : bool{
		$below = $block->getSide(Facing::DOWN);
		if($this->part === self::DOUBLE_TOP){
			return $below instanceof self && $below->part === self::DOUBLE_BOTTOM;
		}
		return $below->getSupportType(Facing::UP) === utils\SupportType::FULL && !$below instanceof Ice;
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if($this->part !== self::SINGLE || !$item instanceof Fertilizer){
			return false;
		}
		$above = $this->getSide(Facing::UP);
		if(!$above instanceof Water || !$above->isSource()){
			return false;
		}
		$bottomEvent = new BlockGrowEvent($this, (clone $this)->setPart(self::DOUBLE_BOTTOM), $player);
		$topEvent = new BlockGrowEvent($above, (clone $this)->setPart(self::DOUBLE_TOP), $player);
		$bottomEvent->call();
		$topEvent->call();
		if($bottomEvent->isCancelled() || $topEvent->isCancelled()){
			return false;
		}
		$tx = new BlockTransaction($this->position->getWorld());
		$tx->addBlock($this->position, $bottomEvent->getNewState())->addBlock($above->getPosition(), $topEvent->getNewState());
		if(!$tx->apply()){
			return false;
		}
		$item->pop();
		return true;
	}

	public function getAffectedBlocks() : array{
		if($this->part !== self::SINGLE){
			$other = $this->getSide($this->part === self::DOUBLE_TOP ? Facing::DOWN : Facing::UP);
			if($other instanceof self && $other->part === ($this->part === self::DOUBLE_TOP ? self::DOUBLE_BOTTOM : self::DOUBLE_TOP)){
				return [$this, $other];
			}
		}
		return [$this];
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		$this->position->getWorld()->setBlock($this->position, VanillaBlocks::WATER());
		return true;
	}
}
