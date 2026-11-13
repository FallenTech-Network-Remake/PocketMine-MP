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

use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class SculkVein extends GlowLichen{
	public function getLightLevel() : int{ return 0; }
	public function getFlameEncouragement() : int{ return 0; }
	public function getFlammability() : int{ return 0; }
	protected function getInitialPlaceFaces(Block $blockReplace) : array{ return $blockReplace instanceof self ? $blockReplace->getFaces() : []; }
	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{ return false; }
	public function getDrops(Item $item) : array{ return $item->hasEnchantment(VanillaEnchantments::SILK_TOUCH()) ? [$this->asItem()] : []; }
}
