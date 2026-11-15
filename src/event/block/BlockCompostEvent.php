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

namespace pocketmine\event\block;

use pocketmine\block\Composter;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\item\Item;
use pocketmine\player\Player;

/**
 * Called before consuming an item in a composter, including failed composting rolls.
 */
final class BlockCompostEvent extends BlockEvent implements Cancellable{
	use CancellableTrait;

	private readonly Item $item;

	public function __construct(
		Composter $block,
		Item $item,
		private readonly bool $successful,
		private readonly ?Player $player
	){
		parent::__construct($block);
		$this->item = clone $item;
	}

	public function getItem() : Item{ return clone $this->item; }
	public function isSuccessful() : bool{ return $this->successful; }
	public function getPlayer() : ?Player{ return $this->player; }
}
