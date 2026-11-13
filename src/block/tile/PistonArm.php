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

namespace pocketmine\block\tile;

use pocketmine\block\BlockTypeIds;
use pocketmine\nbt\tag\CompoundTag;

/** Keeps the native piston renderer retracted; moving-block data is not retained. */
class PistonArm extends Spawnable{
	public function readSaveData(CompoundTag $nbt) : void{
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$this->addAdditionalSpawnData($nbt);
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		$nbt->setByte("State", 0)
			->setByte("NewState", 0)
			->setFloat("Progress", 0.0)
			->setFloat("LastProgress", 0.0)
			->setByte("Sticky", $this->getBlock()->getTypeId() === BlockTypeIds::STICKY_PISTON ? 1 : 0);
	}
}
