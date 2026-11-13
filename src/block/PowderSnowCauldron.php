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

use pocketmine\block\tile\Cauldron as TileCauldron;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityExtinguishEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\CauldronEmptyPowderSnowSound;
use pocketmine\world\sound\CauldronFillPowderSnowSound;
use pocketmine\world\sound\Sound;
use function assert;

final class PowderSnowCauldron extends FillableCauldron{
	public function writeStateToWorld() : void{
		parent::writeStateToWorld();
		$tile = $this->position->getWorld()->getTile($this->position);
		assert($tile instanceof TileCauldron);
		$tile->setCustomWaterColor(null);
		$tile->setPotionItem(null);
	}

	public function getFillSound() : Sound{ return new CauldronFillPowderSnowSound(); }
	public function getEmptySound() : Sound{ return new CauldronEmptyPowderSnowSound(); }

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if($item->getTypeId() === ItemTypeIds::POWDER_SNOW_BUCKET){
			// Snow buckets place against an already-filled snow cauldron instead of topping it up.
			return false;
		}
		match($item->getTypeId()){
			ItemTypeIds::BUCKET => $this->removeFillLevels(self::MAX_FILL_LEVEL, $item, VanillaItems::POWDER_SNOW_BUCKET(), $returnedItems),
			ItemTypeIds::WATER_BUCKET, ItemTypeIds::LAVA_BUCKET => $this->mix($item, VanillaItems::BUCKET(), $returnedItems),
			ItemTypeIds::LINGERING_POTION, ItemTypeIds::POTION, ItemTypeIds::SPLASH_POTION => $this->mix($item, VanillaItems::GLASS_BOTTLE(), $returnedItems),
			default => null
		};
		return true;
	}

	public function hasEntityCollision() : bool{ return true; }

	public function onEntityInside(Entity $entity) : bool{
		if($entity->isOnFire()){
			$entity->extinguish(EntityExtinguishEvent::CAUSE_POWDER_SNOW);
			$remaining = $this->getFillLevel() - 1;
			$this->position->getWorld()->setBlock($this->position, $remaining === 0 ? VanillaBlocks::CAULDRON() : VanillaBlocks::WATER_CAULDRON()->setFillLevel($remaining));
		}
		return true;
	}
}
