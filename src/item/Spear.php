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

/** Item only; jab attacks, charging and lunge are not implemented. */
class Spear extends Durable{
	public function __construct(ItemIdentifier $identifier, string $name, private ToolTier $tier){
		parent::__construct($identifier, $name);
	}
	public function getTier() : ToolTier{ return $this->tier; }
	public function getMaxStackSize() : int{ return 1; }
	public function getMaxDurability() : int{ return $this->tier->getMaxDurability(); }
	public function isFireProof() : bool{ return $this->tier === ToolTier::NETHERITE; }
	public function getFuelTime() : int{ return $this->tier === ToolTier::WOOD ? 200 : 0; }
}
