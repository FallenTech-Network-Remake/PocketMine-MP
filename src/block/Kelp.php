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

use pocketmine\block\utils\Ageable;
use pocketmine\block\utils\AgeableTrait;
use pocketmine\block\utils\BlockEventHelper;
use pocketmine\block\utils\StaticSupportTrait;
use pocketmine\block\utils\SupportType;
use pocketmine\item\Fertilizer;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class Kelp extends Flowable implements Ageable{
	use AgeableTrait;
	use StaticSupportTrait { canBePlacedAt as private supportedWhenPlacedAt; }
	public const MAX_AGE = 25;

	private function canBeSupportedAt(Block $block) : bool{
		$below = $block->getSide(Facing::DOWN);
		return $below instanceof self || ($below->getSupportType(Facing::UP) === SupportType::FULL && !$below instanceof Ice && $below->getTypeId() !== BlockTypeIds::MAGMA && $below->getTypeId() !== BlockTypeIds::SOUL_SAND);
	}

	public function canBeFlowedInto() : bool{ return false; }
	public function getSecondaryStateId() : int{ return VanillaBlocks::WATER()->getStateId(); }
	public function canBePlacedAt(Block $blockReplace, Vector3 $clickVector, int $face, bool $isClickedBlock) : bool{
		return $blockReplace instanceof Water && $this->supportedWhenPlacedAt($blockReplace, $clickVector, $face, $isClickedBlock);
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		$this->position->getWorld()->setBlock($this->position, VanillaBlocks::WATER());
		return true;
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if(!$item instanceof Fertilizer){ return false; }
		$top = $this;
		$above = $top->getSide(Facing::UP);
		while($above instanceof self){
			$top = $above;
			$above = $top->getSide(Facing::UP);
		}
		if($above instanceof Water && BlockEventHelper::grow($above, VanillaBlocks::KELP()->setAge(0), $player)){
			$item->pop();
			return true;
		}
		return false;
	}
}
