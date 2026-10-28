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

use pocketmine\block\utils\HorizontalConnectable;
use pocketmine\block\utils\StaticSupportTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Axis;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use function mt_rand;

final class ChorusPlant extends Flowable implements HorizontalConnectable{
	use StaticSupportTrait;

	/** @var int[] facing => facing */
	protected array $connections = [];

	/**
	 * Set when the connections saved in the world disagreed with the ones recomputed on read, so that the next
	 * neighbour update writes the corrected state back instead of deciding nothing changed.
	 */
	private bool $connectionsStaleInWorld = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->facingFlags($this->connections);
	}

	/**
	 * @param int $facing any of Facing::ALL
	 */
	public function isConnectedAt(int $facing) : bool{
		return isset($this->connections[$facing]);
	}

	/**
	 * @param int $facing any of Facing::ALL
	 */
	public function setConnectedAt(int $facing, bool $connected) : void{
		Facing::validate($facing);
		if($connected){
			$this->connections[$facing] = $facing;
		}else{
			unset($this->connections[$facing]);
		}
	}

	protected function recalculateCollisionBoxes() : array{
		$bb = AxisAlignedBB::one();
		foreach(Facing::ALL as $facing){
			if(!isset($this->connections[$facing])){
				$bb->trim($facing, 2 / 16);
			}
		}

		return [$bb];
	}

	private function recalculateConnections() : bool{
		$changed = false;
		foreach(Facing::ALL as $facing){
			$block = $this->getSide($facing);
			$connected = match($block->getTypeId()){
				BlockTypeIds::CHORUS_FLOWER, $this->getTypeId() => true,
				BlockTypeIds::END_STONE => $facing === Facing::DOWN,
				default => false
			};
			if($connected !== $this->isConnectedAt($facing)){
				$this->setConnectedAt($facing, $connected);
				$changed = true;
			}
		}
		return $changed;
	}

	/**
	 * Connections only became part of the SAVED state in 1.26.60. Every chunk written before that bump stores the
	 * upgrade schema's defaults, and nothing runs a block update on chunk load, so without this the saved value
	 * would be taken at face value until a neighbour changed. Same fix as HorizontalConnectableTrait (1.26.50
	 * fences/panes/bars); FTRecipeFix's StaleShapeHealer then writes the correction back for the client.
	 */
	public function readStateFromWorld() : Block{
		parent::readStateFromWorld();

		if($this->recalculateConnections()){
			$this->collisionBoxes = null;
			$this->connectionsStaleInWorld = true;
		}

		return $this;
	}

	public function onNearbyBlockChange() : void{
		if(!$this->canBeSupportedAt($this)){
			$this->position->getWorld()->useBreakOn($this->position);
		}elseif($this->recalculateConnections() || $this->connectionsStaleInWorld){
			//$connectionsStaleInWorld is checked second on purpose: recalculateConnections() must run either way.
			$this->connectionsStaleInWorld = false;
			$this->position->getWorld()->setBlock($this->position, $this);
		}
	}

	private function canBeSupportedBy(Block $block) : bool{
		return $block->hasSameTypeId($this) || $block->getTypeId() === BlockTypeIds::END_STONE;
	}

	private function canBeSupportedAt(Block $block) : bool{
		$position = $block->position;
		$world = $position->getWorld();

		$down = $world->getBlock($position->down());
		$verticalAir = $down->getTypeId() === BlockTypeIds::AIR || $world->getBlock($position->up())->getTypeId() === BlockTypeIds::AIR;

		foreach($position->sidesAroundAxis(Axis::Y) as $sidePosition){
			$block = $world->getBlock($sidePosition);

			if($block->getTypeId() === BlockTypeIds::CHORUS_PLANT){
				if(!$verticalAir){
					return false;
				}

				if($this->canBeSupportedBy($block->getSide(Facing::DOWN))){
					return true;
				}
			}
		}

		return $this->canBeSupportedBy($down);
	}

	public function getDropsForCompatibleTool(Item $item) : array{
		if(mt_rand(0, 1) === 1){
			return [VanillaItems::CHORUS_FRUIT()];
		}

		return [];
	}
}
