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

use pocketmine\block\utils\AnyFacing;
use pocketmine\block\utils\AnyFacingTrait;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use function abs;

/** Decorative dispenser, dropper or observer without inventory or redstone. */
class DirectionalUtilityBlock extends Opaque implements AnyFacing{
	use AnyFacingTrait;
	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$facing = $face;
		if($player !== null){
			$location = $player->getLocation();
			if($this->getTypeId() === BlockTypeIds::OBSERVER){
				$facing = $location->pitch > 45 ? Facing::DOWN : ($location->pitch < -45 ? Facing::UP : $player->getHorizontalFacing());
			}else{
				$facing = Facing::opposite($player->getHorizontalFacing());
				$target = $blockReplace->getPosition();
				if(abs($location->x - $target->x) < 2 && abs($location->z - $target->z) < 2){
					$eyeY = $location->y + $player->getEyeHeight();
					if($eyeY - $target->y > 2){ $facing = Facing::UP; }
					elseif($target->y > $eyeY){ $facing = Facing::DOWN; }
				}
			}
		}
		$this->setFacing($facing);
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}
}
