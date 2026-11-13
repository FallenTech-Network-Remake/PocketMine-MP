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
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\object\FallingBlock;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\AxisAlignedBB;
use pocketmine\world\sound\BucketFillPowderSnowSound;
use pocketmine\world\sound\Sound;

/** Basic sinking and leather-boot support, without freezing damage. */
class PowderSnow extends Transparent implements EntityCollision{
	protected function recalculateCollisionBoxes() : array{ return []; }
	public function getSupportType(int $facing) : SupportType{ return SupportType::NONE; }
	public function isSolid() : bool{ return false; }
	public function hasEntityCollision() : bool{ return true; }
	public function getDrops(Item $item) : array{ return []; }
	public function getBucketFillSound() : Sound{ return new BucketFillPowderSnowSound(); }

	public function getEntityCollisionBoxes(Entity $entity) : array{
		if($entity instanceof FallingBlock || ($entity instanceof Living && !$entity->isSneaking() && $entity->getArmorInventory()->getBoots()->getTypeId() === ItemTypeIds::LEATHER_BOOTS && $entity->getBoundingBox()->minY >= $this->position->y + 1 - 0.00001)){
			return [AxisAlignedBB::one()->offset($this->position->x, $this->position->y, $this->position->z)];
		}
		return [];
	}

	public function onEntityInside(Entity $entity) : bool{
		$entity->resetFallDistance();
		if($entity instanceof Living && !$entity->isSneaking() && $entity->getArmorInventory()->getBoots()->getTypeId() === ItemTypeIds::LEATHER_BOOTS){
			$entity->onGround = true;
		}
		return true;
	}

	public function onEntityLand(Entity $entity) : ?float{
		$entity->resetFallDistance();
		return null;
	}
}
