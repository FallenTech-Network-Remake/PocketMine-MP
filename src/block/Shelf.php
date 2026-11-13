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

use pocketmine\block\utils\FacesOppositePlacingPlayerTrait;
use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\SupportType;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;

/**
 * Decorative shelf without item storage or redstone behavior.
 */
class Shelf extends Transparent implements HorizontalFacing{
	use FacesOppositePlacingPlayerTrait;

	public function __construct(BlockIdentifier $idInfo, string $name, BlockTypeInfo $typeInfo, private bool $flammable = true){
		parent::__construct($idInfo, $name, $typeInfo);
	}

	protected function recalculateCollisionBoxes() : array{
		return [
			// Three-pixel backboard and five-pixel-deep top and bottom shelves.
			AxisAlignedBB::one()->trim($this->facing, 13 / 16),
			AxisAlignedBB::one()->trim($this->facing, 11 / 16)->trim(Facing::UP, 12 / 16),
			AxisAlignedBB::one()->trim($this->facing, 11 / 16)->trim(Facing::DOWN, 12 / 16)
		];
	}

	public function getSupportType(int $facing) : SupportType{
		return $facing === Facing::opposite($this->facing) ? SupportType::FULL : SupportType::NONE;
	}

	public function getFuelTime() : int{
		return $this->flammable ? 300 : 0;
	}

	public function getFlameEncouragement() : int{
		return $this->flammable ? 30 : 0;
	}

	public function getFlammability() : int{
		return $this->flammable ? 20 : 0;
	}
}
