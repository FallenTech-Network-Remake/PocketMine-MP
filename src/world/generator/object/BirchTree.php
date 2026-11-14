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

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function abs;

class BirchTree extends Tree{
	public function __construct(protected bool $superBirch = false){
		parent::__construct(VanillaBlocks::BIRCH_LOG(), VanillaBlocks::BIRCH_LEAVES());
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$this->treeHeight = 5 + $random->nextBoundedInt(3) + ($this->superBirch ? $random->nextBoundedInt(7) : 0);
		$this->beginPlacement($world);
		$transaction = parent::getBlockTransaction($world, $x, $y, $z, $random);
		return $transaction === null ? null : $this->finishTransaction($world, $transaction);
	}

	public function canPlaceObject(ChunkManager $world, int $x, int $y, int $z, Random $random) : bool{
		if(!$this->isAvailable($world, $x, $y - 1, $z) || !$this->canGrowOn($world->getBlockAt($x, $y - 1, $z))){
			return false;
		}
		for($dy = 0; $dy <= $this->treeHeight + 1; ++$dy){
			$radius = $dy === 0 ? 0 : 1;
			for($dx = -$radius; $dx <= $radius; ++$dx){
				for($dz = -$radius; $dz <= $radius; ++$dz){
					if(!$this->isAvailable($world, $x + $dx, $y + $dy, $z + $dz)
						|| !$this->canOverride($world->getBlockAt($x + $dx, $y + $dy, $z + $dz))){
						return false;
					}
				}
			}
		}
		return true;
	}

	protected function generateTrunkHeight(Random $random) : int{
		return $this->treeHeight;
	}

	protected function placeTrunk(int $x, int $y, int $z, Random $random, int $trunkHeight, BlockTransaction $transaction) : void{
		$transaction->addBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT());
		for($dy = 0; $dy < $trunkHeight; ++$dy){
			$this->placeLog($transaction, $x, $y + $dy, $z);
		}
	}

	protected function placeCanopy(int $x, int $y, int $z, Random $random, BlockTransaction $transaction) : void{
		for($dy = -3; $dy <= 0; ++$dy){
			$radius = 1 - (int) ($dy / 2);
			for($dx = -$radius; $dx <= $radius; ++$dx){
				for($dz = -$radius; $dz <= $radius; ++$dz){
					if(abs($dx) === $radius && abs($dz) === $radius && ($dy === 0 || $random->nextBoundedInt(2) === 0)){
						continue;
					}
					$this->placeLeaf($transaction, $x + $dx, $y + $this->treeHeight + $dy, $z + $dz);
				}
			}
		}
	}

	protected function canOverride(Block $block) : bool{
		return $this->canReplaceForTree($block);
	}
}
