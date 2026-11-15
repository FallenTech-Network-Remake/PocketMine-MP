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
use pocketmine\event\block\BlockCompostEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\sound\ComposterEmptySound;
use pocketmine\world\sound\ComposterFillSound;
use pocketmine\world\sound\ComposterReadySound;
use function max;
use function min;
use function mt_getrandmax;
use function mt_rand;

class Composter extends Transparent{
	public const int MAX_COMPOST_LEVEL = 7;
	public const int READY_LEVEL = 8;
	public const int COMPOSTING_DELAY = 20;

	private int $fillLevel = 0;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->boundedIntAuto(0, self::READY_LEVEL, $this->fillLevel);
	}

	public function getFillLevel() : int{ return $this->fillLevel; }

	/** @return $this */
	public function setFillLevel(int $level) : self{
		if($level < 0 || $level > self::READY_LEVEL){
			throw new \InvalidArgumentException("Composter fill level must be in range 0 ... " . self::READY_LEVEL);
		}
		$this->fillLevel = $level;
		$this->collisionBoxes = null;
		return $this;
	}

	private function getFloorHeight() : float{
		return max(2, 1 + 2 * min($this->fillLevel, self::MAX_COMPOST_LEVEL)) / 16;
	}

	protected function recalculateCollisionBoxes() : array{
		return [
			new AxisAlignedBB(0, 0, 0, 1, $this->getFloorHeight(), 1),
			new AxisAlignedBB(0, 0, 0, 0.125, 1, 1),
			new AxisAlignedBB(0.875, 0, 0, 1, 1, 1),
			new AxisAlignedBB(0, 0, 0, 1, 1, 0.125),
			new AxisAlignedBB(0, 0, 0.875, 1, 1, 1)
		];
	}

	public function getSupportType(int $facing) : SupportType{
		return match($facing){
			Facing::DOWN => SupportType::FULL,
			Facing::UP => SupportType::EDGE,
			default => SupportType::NONE
		};
	}

	public function writeStateToWorld() : void{
		parent::writeStateToWorld();
		$this->scheduleComposting();
	}

	public function readStateFromWorld() : Block{
		$this->scheduleComposting();
		return parent::readStateFromWorld();
	}

	private function scheduleComposting() : void{
		if($this->fillLevel === self::MAX_COMPOST_LEVEL){
			$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, self::COMPOSTING_DELAY);
		}
	}

	public function onScheduledUpdate() : void{
		if($this->fillLevel === self::MAX_COMPOST_LEVEL){
			$this->fillLevel = self::READY_LEVEL;
			$world = $this->position->getWorld();
			$world->setBlock($this->position, $this);
			$world->addSound($this->position->add(0.5, 0.5, 0.5), new ComposterReadySound());
		}
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		$world = $this->position->getWorld();
		if($world->getBlock($this->position)->getStateId() !== $this->getStateId()){
			return true;
		}
		if($this->fillLevel === self::READY_LEVEL){
			$this->setFillLevel(0);
			$world->setBlock($this->position, $this);
			$world->dropItem($this->position->add(0.5, 1.01, 0.5), VanillaItems::BONE_MEAL());
			$world->addSound($this->position->add(0.5, 0.5, 0.5), new ComposterEmptySound());
			return true;
		}
		$chance = $item->getCompostChance();
		if($item->isNull() || $chance <= 0){
			return false;
		}
		if($this->fillLevel === self::MAX_COMPOST_LEVEL){
			return true;
		}
		$success = $this->fillLevel === 0 || mt_rand() / mt_getrandmax() < $chance;
		if(BlockCompostEvent::hasHandlers()){
			$event = new BlockCompostEvent($this, $item, $success, $player);
			$event->call();
			if($event->isCancelled()){
				return true;
			}
		}
		if($world->getBlock($this->position)->getStateId() !== $this->getStateId()){
			return true;
		}
		$item->pop();
		if($success){
			$oldFloorHeight = $this->getFloorHeight();
			$this->setFillLevel($this->fillLevel + 1);
			$world->setBlock($this->position, $this);
			$raisedFloor = new AxisAlignedBB(0.125, $oldFloorHeight, 0.125, 0.875, $this->getFloorHeight(), 0.875);
			$raisedFloor->offset($this->position->x, $this->position->y, $this->position->z);
			foreach($world->getNearbyEntities($raisedFloor) as $entity){
				$entity->pushUp(min($this->getFloorHeight() - $oldFloorHeight, $raisedFloor->maxY - $entity->getBoundingBox()->minY));
			}
			for($i = 0; $i < 10; ++$i){
				$world->addParticle($this->position->add(
					0.125 + mt_rand() / mt_getrandmax() * 0.75,
					$this->getFloorHeight() + 0.1,
					0.125 + mt_rand() / mt_getrandmax() * 0.75
				), new HappyVillagerParticle());
			}
		}
		$world->addSound($this->position->add(0.5, 0.5, 0.5), new ComposterFillSound($success));
		return true;
	}

	public function getDropsForCompatibleTool(Item $item) : array{
		$drops = parent::getDropsForCompatibleTool($item);
		if($this->fillLevel === self::READY_LEVEL){
			$drops[] = VanillaItems::BONE_MEAL();
		}
		return $drops;
	}

	public function getFlameEncouragement() : int{ return 5; }
	public function getFlammability() : int{ return 20; }
	public function getFuelTime() : int{ return 300; }
}
