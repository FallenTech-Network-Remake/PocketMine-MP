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

use pocketmine\item\Item;

class ShortDryGrass extends DeadBush{
	public function onInteract(Item $item, int $face, \pocketmine\math\Vector3 $clickVector, ?\pocketmine\player\Player $player = null, array &$returnedItems = []) : bool{
		if($item instanceof \pocketmine\item\Fertilizer && utils\BlockEventHelper::grow($this, VanillaBlocks::TALL_DRY_GRASS(), $player)){ $item->pop(); return true; }
		return false;
	}

	public function canBeReplaced() : bool{
		return true;
	}

	public function getDropsForIncompatibleTool(Item $item) : array{
		return $item->hasEnchantment(\pocketmine\item\enchantment\VanillaEnchantments::SILK_TOUCH()) ? $this->getSilkTouchDrops($item) : [];
	}

	public function getFuelTime() : int{
		return 100;
	}
}
