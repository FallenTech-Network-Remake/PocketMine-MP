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

namespace pocketmine\block\utils;

use pocketmine\block\Block;
use pocketmine\data\runtime\RuntimeDataDescriber;

trait HorizontalConnectableTrait{
	/** @var int[] facing => facing */
	protected array $connections = [];

	/**
	 * Set when the connections saved in the world disagreed with the ones recomputed on read, so that the next
	 * neighbour update writes the corrected state back instead of deciding nothing changed.
	 */
	private bool $connectionsStaleInWorld = false;

	/**
	 * @see Block::describeBlockOnlyState()
	 */
	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalFacingFlags($this->connections);
	}

	public function isConnectedAt(int $facing) : bool{
		return isset($this->connections[$facing]);
	}

	public function setConnectedAt(int $facing, bool $connected) : void{
		if($connected){
			$this->connections[$facing] = $facing;
		}else{
			unset($this->connections[$facing]);
		}
	}

	/**
	 * @see Block::readStateFromWorld()
	 *
	 * Connections only became part of the SAVED state in 1.26.50 (kqg 2026-09-16). Every chunk written before that
	 * bump stores them all-false, and nothing runs a block update on chunk load, so the saved value would be taken at
	 * face value: existing fence rows, glass pane walls and iron bars would load as unconnected centre posts that
	 * players and mobs walk straight through. Recompute on read - which is what this engine did before the bump - so
	 * collision is correct the moment the chunk loads, and remember the disagreement so the next neighbour update
	 * persists the correction for the client. A chunk written by this engine recomputes to what it already stored, so
	 * this is a no-op there.
	 */
	public function readStateFromWorld() : Block{
		parent::readStateFromWorld();

		if($this->recalculateConnections()){
			$this->collisionBoxes = null;
			$this->connectionsStaleInWorld = true;
		}

		return $this;
	}

	/**
	 * @see Block::onNearbyBlockChange()
	 */
	public function onNearbyBlockChange() : void{
		//$connectionsStaleInWorld is checked second on purpose: recalculateConnections() must run either way, since it
		//is what brings the in-memory state up to date before it is written back.
		if($this->recalculateConnections() || $this->connectionsStaleInWorld){
			$this->connectionsStaleInWorld = false;
			$this->position->getWorld()->setBlock($this->position, $this);
		}
	}

	/**
	 * Implement this to (re)compute connections from the block's neighbours. Must return whether anything changed.
	 */
	abstract protected function recalculateConnections() : bool;
}
