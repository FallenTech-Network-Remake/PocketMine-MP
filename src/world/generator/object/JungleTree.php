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
use pocketmine\block\Wood;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function abs;
use function cos;
use function iterator_to_array;
use function round;
use function sin;
use const M_PI;

class JungleTree extends Tree{
	public function __construct(private bool $mega = false, private bool $bush = false, private bool $decorated = true){
		parent::__construct(VanillaBlocks::JUNGLE_LOG(), $bush ? VanillaBlocks::OAK_LEAVES() : VanillaBlocks::JUNGLE_LEAVES());
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$this->beginPlacement($world);
		if(!$this->isAvailable($world, $x, $y, $z)) return null;
		if($this->mega && $world->getBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::JUNGLE_SAPLING){
			$origin = self::findSaplingSquareForType($world, $x, $y, $z, BlockTypeIds::JUNGLE_SAPLING);
			if($origin === null) return null;
			$x = $origin->getFloorX();
			$z = $origin->getFloorZ();
		}
		$this->treeHeight = $this->bush ? 1 : ($this->mega ? 10 + $random->nextBoundedInt(3) + $random->nextBoundedInt(19) : 4 + $random->nextBoundedInt(7));
		$width = $this->mega ? 2 : 1;
		for($dx = 0; $dx < $width; ++$dx){
			for($dz = 0; $dz < $width; ++$dz){
				if(!$this->isAvailable($world, $x + $dx, $y - 1, $z + $dz) || !$this->canGrowOn($world->getBlockAt($x + $dx, $y - 1, $z + $dz))) return null;
			}
		}
		for($dy = 0; $dy <= $this->treeHeight + 1; ++$dy){
			$radius = $this->mega ? ($dy === 0 ? 1 : 2) : ($dy === 0 ? 0 : (!$this->bush && $dy >= $this->treeHeight - 2 ? 2 : 1));
			for($dx = -$radius; $dx <= $radius; ++$dx){
				for($dz = -$radius; $dz <= $radius; ++$dz){
					if(!$this->isAvailable($world, $x + $dx, $y + $dy, $z + $dz) || !$this->canOverride($world->getBlockAt($x + $dx, $y + $dy, $z + $dz))) return null;
				}
			}
		}
		$tx = new BlockTransaction($world);
		for($dx = 0; $dx < $width; ++$dx){
			for($dz = 0; $dz < $width; ++$dz){
				$tx->addBlockAt($x + $dx, $y - 1, $z + $dz, VanillaBlocks::DIRT());
				for($dy = 0; $dy < $this->treeHeight; ++$dy) $this->placeLog($tx, $x + $dx, $y + $dy, $z + $dz);
			}
		}
		if($this->mega){
			for($dy = $this->treeHeight - 3; $dy > (int) ($this->treeHeight / 2); $dy -= 2 + $random->nextBoundedInt(3)){
				$angle = $random->nextFloat() * 2 * M_PI;
				$bx = $x; $bz = $z;
				for($step = 1; $step <= 4; ++$step){
					$nx = $x + (int) round(cos($angle) * $step);
					$nz = $z + (int) round(sin($angle) * $step);
					$this->placeLog($tx, $nx, $y + $dy + (int) ($step / 2), $nz, abs($nx - $bx) >= abs($nz - $bz) ? Axis::X : Axis::Z);
					$bx = $nx; $bz = $nz;
				}
				$this->placeLeafLayer($tx, $bx, $y + $dy + 2, $bz, 2);
				$this->placeLeafLayer($tx, $bx, $y + $dy + 3, $bz, 1);
			}
			// Support the outer crown within the engine's native leaf-decay distance.
			foreach(Facing::HORIZONTAL as $face){
				$startX = $x + ($face === Facing::EAST ? 1 : 0);
				$startZ = $z + ($face === Facing::SOUTH ? 1 : 0);
				for($step = 1; $step <= 2; ++$step){
					$this->placeLog($tx, $startX + Facing::OFFSET[$face][0] * $step, $y + $this->treeHeight - 2, $startZ + Facing::OFFSET[$face][2] * $step, Facing::axis($face));
				}
			}
			for($dy = -2; $dy <= 0; ++$dy) $this->placeLeafLayer($tx, $x, $y + $this->treeHeight + $dy, $z, 2 - $dy, true);
		}else{
			for($dy = $this->bush ? -1 : -3; $dy <= ($this->bush ? 1 : 0); ++$dy){
				$this->placeLeafLayer($tx, $x, $y + $this->treeHeight + $dy, $z, $this->bush ? 1 - $dy : 1 - (int) ($dy / 2));
			}
		}
		if(($this->decorated || $this->mega) && !$this->bush) $this->decorate($tx, $random);
		return $this->finishTransaction($world, $tx);
	}

	private function decorate(BlockTransaction $tx, Random $random) : void{
		$blocks = iterator_to_array($tx->getBlocks());
		foreach($blocks as [$x, $y, $z, $block]){
			if(!$block instanceof Leaves && !$block instanceof Wood) continue;
			foreach(Facing::HORIZONTAL as $face){
				if($random->nextBoundedInt(5) !== 0) continue;
				$vx = $x + Facing::OFFSET[$face][0];
				$vz = $z + Facing::OFFSET[$face][2];
				$length = $block instanceof Leaves ? 1 + $random->nextBoundedInt(4) : 1;
				for($dy = 0; $dy < $length; ++$dy){
					if(!$this->isAvailable($this->placementWorld, $vx, $y - $dy, $vz) || !$tx->fetchBlockAt($vx, $y - $dy, $vz) instanceof Air) break;
					$tx->addBlockAt($vx, $y - $dy, $vz, VanillaBlocks::VINES()->setFace(Facing::opposite($face), true));
				}
			}
		}
		if(!$this->mega){
			foreach($blocks as [$x, $y, $z, $block]){
				if(!$block instanceof Wood || $random->nextBoundedInt(5) !== 0) continue;
				$face = Facing::HORIZONTAL[$random->nextBoundedInt(4)];
				$cx = $x + Facing::OFFSET[$face][0]; $cz = $z + Facing::OFFSET[$face][2];
				if($this->isAvailable($this->placementWorld, $cx, $y, $cz) && $tx->fetchBlockAt($cx, $y, $cz) instanceof Air){
					$tx->addBlockAt($cx, $y, $cz, VanillaBlocks::COCOA_POD()->setFacing($face)->setAge($random->nextBoundedInt(3)));
				}
			}
		}
	}

	protected function canOverride(Block $block) : bool{
		return $this->canReplaceForTree($block);
	}
}
