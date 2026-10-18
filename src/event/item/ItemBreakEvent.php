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

namespace pocketmine\event\item;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\Event;
use pocketmine\item\Durable;

/**
 * FallenTech: called when wear is about to destroy an item, i.e. its damage has just reached its max durability.
 * Every durability path ends in Durable::applyDamage() - block breaks, hits, tilling and stripping, armour, bows,
 * plugins - so this is the one place a plugin can keep an item from breaking.
 *
 * Cancel to keep the item. It keeps whatever damage the handler gives it (setDamage()), or is left one point
 * short of breaking if it is still at max durability. There is no holder: items do not know who carries them.
 */
class ItemBreakEvent extends Event implements Cancellable{
	use CancellableTrait;

	public function __construct(
		private Durable $item
	){}

	/**
	 * The item about to break. Changes made to it stick when the event is cancelled.
	 */
	public function getItem() : Durable{
		return $this->item;
	}
}
