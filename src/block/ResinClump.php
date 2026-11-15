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

use pocketmine\block\utils\MultiAnyFacing;
use pocketmine\block\utils\MultiAnySupportTrait;
use pocketmine\block\utils\SupportType;

final class ResinClump extends Transparent implements MultiAnyFacing{
	use MultiAnySupportTrait;

	public function isSolid() : bool{
		return false;
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE;
	}

	public function canBeReplaced() : bool{
		return true;
	}

	/**
	 * @return int[]
	 */
	/**
	 * FALLENTECH PATCH (kqg 2026-08-26): "resin cant be placed on leaves".
	 *
	 * Leaves::getSupportType() returns NONE, and the trait demanded FULL, so a resin clump
	 * could never attach - even though in vanilla it generates ON leaves near a creaking
	 * heart. Widened for THIS block only; GlowLichen (the trait's only other user) keeps the
	 * strict rule, and Leaves itself is untouched so torches/rails/doors are unaffected.
	 */
	protected function isValidMultiAnySupport(int $face) : bool{
		// NOT parent::: this method comes from MultiAnySupportTrait, and a class's own method
		// takes precedence over a trait's - parent:: would resolve to Transparent, which has
		// no such method, and fatal at runtime while linting clean. The default rule is
		// restated inline instead.
		return $this->getAdjacentSupportType($face) === SupportType::FULL
			|| $this->getSide($face) instanceof Leaves;
	}

	protected function getInitialPlaceFaces(Block $blockReplace) : array{
		return $blockReplace instanceof ResinClump ? $blockReplace->faces : [];
	}

	protected function recalculateCollisionBoxes() : array{
		return [];
	}
}
