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

namespace pocketmine\item;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\BucketEmptyPowderSnowSound;

class PowderSnowBucket extends Item{
	public function getMaxStackSize() : int{ return 1; }

	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		if(!$blockReplace->canBeReplaced()){ return ItemUseResult::NONE; }
		$ev = new PlayerBucketEmptyEvent($player, $blockReplace, $face, $this, VanillaItems::BUCKET());
		$ev->call();
		if($ev->isCancelled()){ return ItemUseResult::FAIL; }
		$player->getWorld()->setBlock($blockReplace->getPosition(), VanillaBlocks::POWDER_SNOW());
		$player->getWorld()->addSound($blockReplace->getPosition()->add(0.5, 0.5, 0.5), new BucketEmptyPowderSnowSound());
		$this->pop();
		$returnedItems[] = $ev->getItem();
		return ItemUseResult::SUCCESS;
	}
}
