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
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Leaves;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\Water;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function abs;
use function iterator_to_array;
use function max;

final class MangroveTree extends Tree{
	public function __construct(private bool $tall = false){
		parent::__construct(VanillaBlocks::MANGROVE_LOG(), VanillaBlocks::MANGROVE_LEAVES());
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$this->beginPlacement($world);
		if(!$this->isAvailable($world, $x, $y - 1, $z) || !$this->canGrowOn($world->getBlockAt($x, $y - 1, $z))) return null;
		$rise = ($this->tall ? 6 : 3) + $random->nextBoundedInt($this->tall ? 7 : 4);
		$this->treeHeight = $rise + 3 + $random->nextBoundedInt(4);
		$tx = new BlockTransaction($world);
		for($dy = 0; $dy < $rise; ++$dy) $this->placeRoot($tx, $x, $y + $dy, $z);
		if($world->getBlockAt($x, $y - 1, $z)->getTypeId() === BlockTypeIds::MUD){
			$tx->addBlockAt($x, $y - 1, $z, VanillaBlocks::MUDDY_MANGROVE_ROOTS());
		}
		foreach(Facing::HORIZONTAL as $face){
			$rx = $x; $rz = $z; $ry = $y + $rise - 1;
			for($step = 1; $step <= 4; ++$step){
				$rx += Facing::OFFSET[$face][0]; $rz += Facing::OFFSET[$face][2];
				$this->placeRoot($tx, $rx, $ry, $rz);
				$nextY = $y + $rise - 1 - (int) ($rise * $step / 4);
				while($ry > $nextY){
					--$ry;
					if(!$this->isAvailable($world, $rx, $ry, $rz)){ $this->placementFailed = true; break; }
					$existing = $world->getBlockAt($rx, $ry, $rz);
					if(!$this->canOverride($existing) && $existing->getTypeId() !== BlockTypeIds::MUD) break;
					$this->placeRoot($tx, $rx, $ry, $rz);
				}
			}
			$anchored = false;
			for($depth = 0; $depth < 8; ++$depth, --$ry){
				if(!$this->isAvailable($world, $rx, $ry, $rz)) break;
				$block = $world->getBlockAt($rx, $ry, $rz);
				if($block->getTypeId() === BlockTypeIds::MUD){ $this->placeRoot($tx, $rx, $ry, $rz); $anchored = true; break; }
				if($block->isSolid() && !$block instanceof Leaves){ $anchored = true; break; }
				$this->placeRoot($tx, $rx, $ry, $rz);
			}
			if(!$anchored) $this->placementFailed = true;
		}
		for($dy = $rise; $dy < $this->treeHeight; ++$dy) $this->placeLog($tx, $x, $y + $dy, $z);
		foreach([[0, 0, 0], [3, 0, 1], [-3, 0, 1], [0, 3, 2], [0, -3, 2]] as [$dx, $dz, $lift]){
			$cy = $y + $this->treeHeight - 2 + $lift;
			for($step = 1; $step <= max(abs($dx), abs($dz)); ++$step){
				$this->placeLog($tx, $x + ($dx <=> 0) * $step, $cy, $z + ($dz <=> 0) * $step, $dx !== 0 ? Axis::X : Axis::Z);
			}
			// Vertical branch junctions connect raised crowns to the main trunk.
			for($dy = $y + $this->treeHeight - 2; $dy <= $cy; ++$dy) $this->placeLog($tx, $x, $dy, $z);
			for($dy = -1; $dy <= 1; ++$dy) $this->placeLeafLayer($tx, $x + $dx, $cy + $dy, $z + $dz, $dy === 1 ? 1 : 2);
		}
		foreach(iterator_to_array($tx->getBlocks()) as [$xx, $yy, $zz, $block]){
			if($block instanceof Leaves && $this->isAvailable($world, $xx, $yy - 1, $zz) && $tx->fetchBlockAt($xx, $yy - 1, $zz) instanceof Air && $random->nextBoundedInt(12) === 0){
				$tx->addBlockAt($xx, $yy - 1, $zz, VanillaBlocks::MANGROVE_PROPAGULE()->setHanging(true)->setAge($random->nextBoundedInt(5)));
			}
			if($block->getTypeId() === BlockTypeIds::MANGROVE_ROOTS && $this->isAvailable($world, $xx, $yy + 1, $zz) && $tx->fetchBlockAt($xx, $yy + 1, $zz) instanceof Air && $random->nextBoundedInt(8) === 0){
				$tx->addBlockAt($xx, $yy + 1, $zz, VanillaBlocks::MOSS_CARPET());
			}
			if(!$block instanceof Leaves) continue;
			foreach(Facing::HORIZONTAL as $face){
				if($random->nextBoundedInt(10) !== 0) continue;
				$vx = $xx + Facing::OFFSET[$face][0]; $vz = $zz + Facing::OFFSET[$face][2];
				$length = 1 + $random->nextBoundedInt(4);
				for($dy = 0; $dy < $length; ++$dy){
					if(!$this->isAvailable($world, $vx, $yy - $dy, $vz) || !$tx->fetchBlockAt($vx, $yy - $dy, $vz) instanceof Air) break;
					$tx->addBlockAt($vx, $yy - $dy, $vz, VanillaBlocks::VINES()->setFace(Facing::opposite($face), true));
				}
			}
		}
		return $this->finishTransaction($world, $tx);
	}

	private function placeRoot(BlockTransaction $tx, int $x, int $y, int $z) : void{
		if(!$this->isAvailable($this->placementWorld, $x, $y, $z)){ $this->placementFailed = true; return; }
		$old = $this->placementWorld->getBlockAt($x, $y, $z);
		if($old->getTypeId() === BlockTypeIds::MUD || $old->getTypeId() === BlockTypeIds::MUDDY_MANGROVE_ROOTS){
			$tx->addBlockAt($x, $y, $z, VanillaBlocks::MUDDY_MANGROVE_ROOTS());
		}elseif($this->canOverride($old)){
			$tx->addBlockAt($x, $y, $z, VanillaBlocks::MANGROVE_ROOTS());
		}else{
			$this->placementFailed = true;
		}
	}

	protected function canReplaceLeaf(Block $block) : bool{
		return parent::canReplaceLeaf($block) || $block instanceof Water;
	}

	protected function canGrowOn(Block $block) : bool{
		return parent::canGrowOn($block) || $block->getTypeId() === BlockTypeIds::CLAY;
	}

	protected function canOverride(Block $block) : bool{
		return $this->canReplaceForTree($block) || $block instanceof Water || $block->getTypeId() === BlockTypeIds::MANGROVE_PROPAGULE;
	}
}
