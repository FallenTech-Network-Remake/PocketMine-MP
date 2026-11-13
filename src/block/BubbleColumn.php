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
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use function max;
use function min;

class BubbleColumn extends Water{
	private bool $dragDown = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{ $w->bool($this->dragDown); }
	public function isDragDown() : bool{ return $this->dragDown; }
	/** @return $this */
	public function setDragDown(bool $dragDown) : self{ $this->dragDown = $dragDown; return $this; }

	/** Null means this block cannot support a bubble column. */
	public static function getDragFromSupport(Block $support) : ?bool{
		return match($support->getTypeId()){
			BlockTypeIds::SOUL_SAND => false,
			BlockTypeIds::MAGMA => true,
			default => $support instanceof self ? $support->dragDown : null
		};
	}

	public function onScheduledUpdate() : void{
		$below = $this->getSide(Facing::DOWN);
		$drag = self::getDragFromSupport($below);
		if($drag === null){
			BlockEventHelper::form($this, VanillaBlocks::WATER(), $below);
			return;
		}
		if($drag !== $this->dragDown){
			BlockEventHelper::form($this, (clone $this)->setDragDown($drag), $below);
			return;
		}
		$above = $this->getSide(Facing::UP);
		if($above instanceof Water && !$above instanceof self && $above->isSource()){
			BlockEventHelper::form($above, VanillaBlocks::BUBBLE_COLUMN()->setDragDown($drag), $this);
		}
		parent::onScheduledUpdate();
	}

	/** Bubbles spread ordinary water sideways and downward, never unsupported bubbles. */
	protected function createFlowingBlock() : Liquid{ return VanillaBlocks::WATER(); }
	public function getStillForm() : Block{ return VanillaBlocks::WATER()->getStillForm(); }
	public function getFlowingForm() : Block{ return VanillaBlocks::WATER(); }
	public function getFluidHeightPercent() : float{ return 0.0; }
	public function addVelocityToEntity(Entity $entity) : ?Vector3{ return null; }

	public function onEntityInside(Entity $entity) : bool{
		parent::onEntityInside($entity);
		if($entity instanceof Living){
			$entity->setAirSupplyTicks($entity->getMaxAirSupplyTicks());
			$entity->setBreathing();
		}
		// Players apply native bubble movement on their client, like water currents.
		$pos = $entity->getPosition();
		if(!$entity instanceof Player && $pos->getFloorX() === $this->position->getFloorX() && $pos->getFloorY() === $this->position->getFloorY() && $pos->getFloorZ() === $this->position->getFloorZ()){
			$motion = $entity->getMotion();
			$surface = $this->getSide(Facing::UP)->getTypeId() === BlockTypeIds::AIR;
			$y = $this->dragDown ? max($motion->y - 0.03, $surface ? -0.9 : -0.3) : min($motion->y + ($surface ? 0.1 : 0.06), $surface ? 1.8 : 0.7);
			$entity->setMotion($motion->withComponents(null, $y, null));
		}
		return true;
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		$this->position->getWorld()->setBlock($this->position, VanillaBlocks::WATER());
		return true;
	}
}
