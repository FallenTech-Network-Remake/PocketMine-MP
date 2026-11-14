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
use pocketmine\block\Block;
use pocketmine\block\utils\LeavesType;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;

final class PoplarTree extends Tree{
	public function __construct(LeavesType $colour = LeavesType::YELLOW_POPLAR){
		$leaves = match($colour){
			LeavesType::YELLOW_POPLAR => VanillaBlocks::YELLOW_POPLAR_LEAVES(),
			LeavesType::ORANGE_POPLAR => VanillaBlocks::ORANGE_POPLAR_LEAVES(),
			LeavesType::RED_POPLAR => VanillaBlocks::RED_POPLAR_LEAVES(),
			default => throw new \InvalidArgumentException("Expected a poplar leaf colour")
		};
		parent::__construct(VanillaBlocks::POPLAR_LOG(), $leaves);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$this->beginPlacement($world);
		$this->treeHeight = 7 + $random->nextBoundedInt(5);
		if(!$this->isAvailable($world, $x, $y - 1, $z) || !$this->canGrowOn($world->getBlockAt($x, $y - 1, $z))) return null;
		for($dy = 0; $dy <= $this->treeHeight + 2; ++$dy){
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
		$this->placeCrown($tx, $x, $y + $this->treeHeight - 1, $z);
		$count = 1 + $random->nextBoundedInt(4);
		$start = $random->nextBoundedInt(4);
		for($branch = 0; $branch < $count; ++$branch){
			$face = Facing::HORIZONTAL[($start + $branch) % 4];
			$length = 2 + $random->nextBoundedInt(3);
			$cx = $x; $cz = $z; $cy = $y + $this->treeHeight - 4;
			for($step = 0; $step < $length; ++$step){
				++$cy; $this->placeLog($tx, $cx, $cy, $cz);
				$cx += Facing::OFFSET[$face][0]; $cz += Facing::OFFSET[$face][2];
				$this->placeLog($tx, $cx, $cy, $cz, Facing::axis($face));
			}
			$this->placeCrown($tx, $cx, $cy, $cz);
		}
		for($dy = 1; $dy < $this->treeHeight - 3; ++$dy){
			if($random->nextBoundedInt(4) !== 0) continue;
			$face = Facing::HORIZONTAL[$random->nextBoundedInt(4)];
			$mx = $x + Facing::OFFSET[$face][0]; $mz = $z + Facing::OFFSET[$face][2];
			if($this->isAvailable($world, $mx, $y + $dy, $mz) && $tx->fetchBlockAt($mx, $y + $dy, $mz) instanceof Air){
				$tx->addBlockAt($mx, $y + $dy, $mz, VanillaBlocks::SHELF_MUSHROOM()->setFacing($face)->setLarge($random->nextBoundedInt(2) === 0));
			}
		}
		return $this->finishTransaction($world, $tx);
	}

	private function placeCrown(BlockTransaction $tx, int $x, int $y, int $z) : void{
		foreach(Facing::HORIZONTAL as $face){
			$this->placeLog($tx, $x + Facing::OFFSET[$face][0], $y, $z + Facing::OFFSET[$face][2], Facing::axis($face));
		}
		foreach([-2 => 2, -1 => 3, 0 => 3, 1 => 2, 2 => 1] as $dy => $radius){
			$this->placeLeafLayer($tx, $x, $y + $dy, $z, $radius);
		}
	}

	protected function canOverride(Block $block) : bool{
		return $this->canReplaceForTree($block);
	}
}
