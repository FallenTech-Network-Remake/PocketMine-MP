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
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;

final class CherryTree extends Tree{
	public function __construct(){
		parent::__construct(VanillaBlocks::CHERRY_LOG(), VanillaBlocks::CHERRY_LEAVES());
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$this->beginPlacement($world);
		$this->treeHeight = 4 + $random->nextBoundedInt(3);
		if(!$this->isAvailable($world, $x, $y - 1, $z) || !$this->canGrowOn($world->getBlockAt($x, $y - 1, $z))) return null;
		for($dy = 0; $dy <= $this->treeHeight; ++$dy){
			$radius = $dy === 0 ? 0 : 1;
			for($dx = -$radius; $dx <= $radius; ++$dx){
				for($dz = -$radius; $dz <= $radius; ++$dz){
					if(!$this->isAvailable($world, $x + $dx, $y + $dy, $z + $dz) || !$this->canOverride($world->getBlockAt($x + $dx, $y + $dy, $z + $dz))) return null;
				}
			}
		}
		$tx = new BlockTransaction($world);
		$tx->addBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT());
		for($dy = 0; $dy < $this->treeHeight; ++$dy) $this->placeLog($tx, $x, $y + $dy, $z);
		$face = Facing::HORIZONTAL[$random->nextBoundedInt(4)];
		$branches = 1 + $random->nextBoundedInt(2);
		$this->placeCrown($tx, $x, $y + $this->treeHeight - 1, $z);
		for($branch = 0; $branch < $branches; ++$branch){
			$direction = $branch === 0 ? $face : Facing::opposite($face);
			$length = 2 + $random->nextBoundedInt(3);
			$cx = $x; $cz = $z; $cy = $y + $this->treeHeight - 2;
			for($step = 1; $step <= $length; ++$step){
				if(($step & 1) === 0){ ++$cy; $this->placeLog($tx, $cx, $cy, $cz); }
				$cx += Facing::OFFSET[$direction][0]; $cz += Facing::OFFSET[$direction][2];
				$this->placeLog($tx, $cx, $cy, $cz, Facing::axis($direction));
			}
			$this->placeCrown($tx, $cx, $cy, $cz);
		}
		return $this->finishTransaction($world, $tx);
	}

	private function placeCrown(BlockTransaction $tx, int $x, int $y, int $z) : void{
		// Short crown limbs keep the broad foliage connected to supporting wood.
		foreach(Facing::HORIZONTAL as $face){
			$this->placeLog($tx, $x + Facing::OFFSET[$face][0], $y, $z + Facing::OFFSET[$face][2], Facing::axis($face));
		}
		for($dy = -1; $dy <= 2; ++$dy){
			$this->placeLeafLayer($tx, $x, $y + $dy, $z, $dy <= 0 ? 3 : ($dy === 1 ? 2 : 1));
		}
	}

	protected function canOverride(Block $block) : bool{
		return $this->canReplaceForTree($block);
	}
}
