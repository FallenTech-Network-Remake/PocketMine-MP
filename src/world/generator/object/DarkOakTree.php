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
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function max;
use function min;

class DarkOakTree extends Tree{
	protected int $saplingTypeId = BlockTypeIds::DARK_OAK_SAPLING;

	public function __construct(){
		parent::__construct(VanillaBlocks::DARK_OAK_LOG(), VanillaBlocks::DARK_OAK_LEAVES());
	}

	protected function pickHeight(Random $random) : int{
		return 6 + $random->nextBoundedInt(3) + $random->nextBoundedInt(2);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$this->beginPlacement($world);
		if(!$this->isAvailable($world, $x, $y, $z)) return null;
		if($world->getBlockAt($x, $y, $z)->getTypeId() === $this->saplingTypeId){
			$origin = self::findSaplingSquareForType($world, $x, $y, $z, $this->saplingTypeId);
			if($origin === null) return null;
			$x = $origin->getFloorX(); $z = $origin->getFloorZ();
		}
		$this->treeHeight = $this->pickHeight($random);
		if(!$this->canPlaceObject($world, $x, $y, $z, $random)) return null;
		$tx = new BlockTransaction($world);
		for($dx = 0; $dx < 2; ++$dx){
			for($dz = 0; $dz < 2; ++$dz) $tx->addBlockAt($x + $dx, $y - 1, $z + $dz, VanillaBlocks::DIRT());
		}
		$face = Facing::HORIZONTAL[$random->nextBoundedInt(4)];
		$bendStart = $this->treeHeight - 2 - $random->nextBoundedInt(2);
		$bendLength = $random->nextBoundedInt(3);
		$cx = $x; $cz = $z;
		for($dy = 0; $dy < $this->treeHeight; ++$dy){
			if($dy >= $bendStart && $bendLength-- > 0){
				$cx += Facing::OFFSET[$face][0]; $cz += Facing::OFFSET[$face][2];
			}
			for($dx = 0; $dx < 2; ++$dx){
				for($dz = 0; $dz < 2; ++$dz) $this->placeLog($tx, $cx + $dx, $y + $dy, $cz + $dz);
			}
		}
		$this->placeLeafLayer($tx, $cx, $y + $this->treeHeight - 2, $cz, 2, true);
		$this->placeLeafLayer($tx, $cx, $y + $this->treeHeight - 1, $cz, 3, true);
		$this->placeLeafLayer($tx, $cx, $y + $this->treeHeight, $cz, 2, true);
		if($random->nextBoundedInt(2) === 0) $this->placeLeafLayer($tx, $cx, $y + $this->treeHeight + 1, $cz, 1, true);
		for($dx = -1; $dx <= 2; ++$dx){
			for($dz = -1; $dz <= 2; ++$dz){
				if(($dx >= 0 && $dx <= 1 && $dz >= 0 && $dz <= 1) || $random->nextBoundedInt(3) !== 0) continue;
				$bottom = $y + $this->treeHeight - 3;
				$this->placeLog($tx, $cx + $dx, $bottom, $cz + max(0, min(1, $dz)), Axis::X);
				$branchHeight = 2 + $random->nextBoundedInt(2);
				for($dy = 0; $dy < $branchHeight; ++$dy) $this->placeLog($tx, $cx + $dx, $bottom + $dy, $cz + $dz);
				$this->placeLeafLayer($tx, $cx + $dx, $y + $this->treeHeight - 1, $cz + $dz, 2);
				$this->placeLeafLayer($tx, $cx + $dx, $y + $this->treeHeight, $cz + $dz, 1);
			}
		}
		$this->decorate($tx, $random);
		return $this->finishTransaction($world, $tx);
	}

	protected function decorate(BlockTransaction $transaction, Random $random) : void{}

	public function canPlaceObject(ChunkManager $world, int $x, int $y, int $z, Random $random) : bool{
		for($dx = 0; $dx < 2; ++$dx){
			for($dz = 0; $dz < 2; ++$dz){
				if(!$this->isAvailable($world, $x + $dx, $y - 1, $z + $dz) || !$this->canGrowOn($world->getBlockAt($x + $dx, $y - 1, $z + $dz))) return false;
			}
		}
		for($dy = 0; $dy <= $this->treeHeight + 1; ++$dy){
			$low = $dy === 0 ? 0 : ($dy >= $this->treeHeight - 2 ? -2 : -1);
			$high = $dy === 0 ? 1 : -$low;
			for($dx = $low; $dx <= $high; ++$dx){
				for($dz = $low; $dz <= $high; ++$dz){
					if(!$this->isAvailable($world, $x + $dx, $y + $dy, $z + $dz) || !$this->canOverride($world->getBlockAt($x + $dx, $y + $dy, $z + $dz))) return false;
				}
			}
		}
		return true;
	}

	protected function canOverride(Block $block) : bool{
		return $this->canReplaceForTree($block);
	}
}
