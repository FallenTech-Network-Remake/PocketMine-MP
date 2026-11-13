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

use pocketmine\block\utils\EntityCollision;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use function min;

class Scaffolding extends Transparent implements EntityCollision{
	private int $stability = 0;
	private bool $stabilityChecked = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->boundedIntAuto(0, 7, $this->stability);
		$w->bool($this->stabilityChecked);
	}

	public function getStability() : int{ return $this->stability; }
	/** @return $this */
	public function setStability(int $stability) : self{
		if($stability < 0 || $stability > 7){ throw new \InvalidArgumentException("Stability must be in range 0 ... 7"); }
		$this->stability = $stability;
		return $this;
	}
	public function isStabilityChecked() : bool{ return $this->stabilityChecked; }
	/** @return $this */
	public function setStabilityChecked(bool $checked) : self{ $this->stabilityChecked = $checked; return $this; }

	private function calculateStability(Block $target) : int{
		$below = $target->getSide(Facing::DOWN);
		$distance = $below instanceof self ? $below->stability : ($below->getSupportType(Facing::UP) === SupportType::FULL ? 0 : 7);
		foreach(Facing::HORIZONTAL as $face){
			$neighbor = $target->getSide($face);
			if($neighbor instanceof self){ $distance = min($distance, $neighbor->stability + 1); }
		}
		return $distance;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$this->stability = $this->calculateStability($blockReplace);
		$this->stabilityChecked = true;
		return $this->stability < 7 && parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onNearbyBlockChange() : void{
		$distance = $this->calculateStability($this);
		if($distance >= 7){
			$this->position->getWorld()->useBreakOn($this->position);
		}elseif($distance !== $this->stability || !$this->stabilityChecked){
			$this->stability = $distance;
			$this->stabilityChecked = true;
			$this->position->getWorld()->setBlock($this->position, $this);
		}
	}

	protected function recalculateCollisionBoxes() : array{
		return [new AxisAlignedBB(0, 14 / 16, 0, 1, 1, 1)];
	}

	public function getEntityCollisionBoxes(Entity $entity) : array{
		if(($entity instanceof Living && $entity->isSneaking()) || $entity->getBoundingBox()->minY < $this->position->y + 1 - 0.00001 || $entity->getMotion()->y > 0){ return []; }
		return $this->getCollisionBoxes();
	}

	public function hasEntityCollision() : bool{ return true; }
	public function onEntityInside(Entity $entity) : bool{
		if($entity instanceof Living){
			$entity->resetFallDistance();
			$entity->onGround = true;
		}
		return true;
	}
	public function canClimb() : bool{ return true; }
	public function isSolid() : bool{ return false; }
	public function getSupportType(int $facing) : SupportType{ return SupportType::NONE; }
	public function getFlameEncouragement() : int{ return 60; }
	public function getFlammability() : int{ return 20; }
	public function getFuelTime() : int{ return 50; }
}
