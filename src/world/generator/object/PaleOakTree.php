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

namespace pocketmine\world\generator\object;

use pocketmine\block\Air;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Leaves;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use function count;
use function iterator_to_array;

final class PaleOakTree extends DarkOakTree{
	public function __construct(private bool $decorated = true){
		parent::__construct();
		$this->trunkBlock = VanillaBlocks::PALE_OAK_LOG();
		$this->leafBlock = VanillaBlocks::PALE_OAK_LEAVES();
		$this->saplingTypeId = BlockTypeIds::PALE_OAK_SAPLING;
	}

	protected function pickHeight(Random $random) : int{
		return 7 + $random->nextBoundedInt(3) + $random->nextBoundedInt(2);
	}

	protected function decorate(BlockTransaction $transaction, Random $random) : void{
		if(!$this->decorated) return;
		$blocks = iterator_to_array($transaction->getBlocks());
		$heartCandidates = [];
		foreach($blocks as [$x, $y, $z, $block]){
			if($block instanceof Leaves && $random->nextBoundedInt(6) === 0){
				$length = 1 + $random->nextBoundedInt(4);
				$positions = [];
				for($dy = 1; $dy <= $length; ++$dy){
					if(!$this->isAvailable($this->placementWorld, $x, $y - $dy, $z) || !$transaction->fetchBlockAt($x, $y - $dy, $z) instanceof Air) break;
					$positions[] = $y - $dy;
				}
				foreach($positions as $index => $mossY) $transaction->addBlockAt($x, $mossY, $z, VanillaBlocks::PALE_HANGING_MOSS()->setTip($index === count($positions) - 1));
			}
			if($block->getTypeId() === BlockTypeIds::PALE_OAK_LOG){
				$enclosed = true;
				foreach(Facing::OFFSET as [$dx, $dy, $dz]){
					if(!$this->isAvailable($this->placementWorld, $x + $dx, $y + $dy, $z + $dz) || $transaction->fetchBlockAt($x + $dx, $y + $dy, $z + $dz)->getTypeId() !== BlockTypeIds::PALE_OAK_LOG){ $enclosed = false; break; }
				}
				if($enclosed) $heartCandidates[] = [$x, $y, $z];
			}
			if($block->getTypeId() !== BlockTypeIds::DIRT) continue;
			for($dx = -2; $dx <= 2; ++$dx){
				for($dz = -2; $dz <= 2; ++$dz){
					if($random->nextBoundedInt(4) === 0) continue;
					for($dy = 2; $dy >= -2; --$dy){
						if(!$this->isAvailable($this->placementWorld, $x + $dx, $y + $dy, $z + $dz)) break;
						$surface = $transaction->fetchBlockAt($x + $dx, $y + $dy, $z + $dz);
						if($this->canGrowOn($surface)){
							$transaction->addBlockAt($x + $dx, $y + $dy, $z + $dz, VanillaBlocks::PALE_MOSS_BLOCK());
							break;
						}
						if(!$this->canOverride($surface)) break;
					}
				}
			}
		}
		if($heartCandidates !== [] && $random->nextBoundedInt(5) === 0){
			[$x, $y, $z] = $heartCandidates[$random->nextBoundedInt(count($heartCandidates))];
			$transaction->addBlockAt($x, $y, $z, VanillaBlocks::CREAKING_HEART());
		}
	}
}
