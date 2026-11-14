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
use pocketmine\block\BlockTypeTags;
use pocketmine\block\Farmland;
use pocketmine\block\Fire;
use pocketmine\block\Leaves;
use pocketmine\block\Liquid;
use pocketmine\block\MossBlock;
use pocketmine\block\Sapling;
use pocketmine\block\utils\PillarRotation;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use function abs;
use function min;

abstract class Tree{
	protected bool $placementFailed = false;
	protected ChunkManager $placementWorld;

	public function __construct(
		protected Block $trunkBlock,
		protected Block $leafBlock,
		protected int $treeHeight = 7
	){}

	public function canPlaceObject(ChunkManager $world, int $x, int $y, int $z, Random $random) : bool{
		$radiusToCheck = 0;
		for($yy = 0; $yy < $this->treeHeight + 3; ++$yy){
			if($yy === 1 || $yy === $this->treeHeight){
				++$radiusToCheck;
			}
			for($xx = -$radiusToCheck; $xx < ($radiusToCheck + 1); ++$xx){
				for($zz = -$radiusToCheck; $zz < ($radiusToCheck + 1); ++$zz){
					if(!$this->canOverride($world->getBlockAt($x + $xx, $y + $yy, $z + $zz))){
						return false;
					}
				}
			}
		}

		return true;
	}

	/**
	 * Returns the BlockTransaction containing all the blocks the tree would change upon growing at the given coordinates
	 * or null if the tree can't be grown
	 */
	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		if(!$this->canPlaceObject($world, $x, $y, $z, $random)){
			return null;
		}

		$transaction = new BlockTransaction($world);
		$this->placeTrunk($x, $y, $z, $random, $this->generateTrunkHeight($random), $transaction);
		$this->placeCanopy($x, $y, $z, $random, $transaction);

		return $transaction;
	}

	protected function generateTrunkHeight(Random $random) : int{
		return $this->treeHeight - 1;
	}

	protected function placeTrunk(int $x, int $y, int $z, Random $random, int $trunkHeight, BlockTransaction $transaction) : void{
		// The base dirt block
		$transaction->addBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT());

		for($yy = 0; $yy < $trunkHeight; ++$yy){
			if($this->canOverride($transaction->fetchBlockAt($x, $y + $yy, $z))){
				$transaction->addBlockAt($x, $y + $yy, $z, $this->trunkBlock);
			}
		}
	}

	protected function placeCanopy(int $x, int $y, int $z, Random $random, BlockTransaction $transaction) : void{
		for($yy = $y - 3 + $this->treeHeight; $yy <= $y + $this->treeHeight; ++$yy){
			$yOff = $yy - ($y + $this->treeHeight);
			$mid = (int) (1 - $yOff / 2);
			for($xx = $x - $mid; $xx <= $x + $mid; ++$xx){
				$xOff = abs($xx - $x);
				for($zz = $z - $mid; $zz <= $z + $mid; ++$zz){
					$zOff = abs($zz - $z);
					if($xOff === $mid && $zOff === $mid && ($yOff === 0 || $random->nextBoundedInt(2) === 0)){
						continue;
					}
					if(!$transaction->fetchBlockAt($xx, $yy, $zz)->isSolid()){
						$transaction->addBlockAt($xx, $yy, $zz, $this->leafBlock);
					}
				}
			}
		}
	}

	protected function canOverride(Block $block) : bool{
		return $block->canBeReplaced() || $block instanceof Sapling || $block instanceof Leaves;
	}

	protected function canGrowOn(Block $block) : bool{
		return $block->hasTypeTag(BlockTypeTags::DIRT) || $block->hasTypeTag(BlockTypeTags::MUD)
			|| $block instanceof MossBlock || $block instanceof Farmland || $block->getTypeId() === BlockTypeIds::MUDDY_MANGROVE_ROOTS;
	}

	protected function isAvailable(ChunkManager $world, int $x, int $y, int $z) : bool{
		return $world->isInWorld($x, $y, $z) && $world->getChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE) !== null;
	}

	protected function placeLeaf(BlockTransaction $transaction, int $x, int $y, int $z) : void{
		if(!$this->isAvailable($this->placementWorld, $x, $y, $z)){
			$this->placementFailed = true;
			return;
		}
		$existing = $transaction->fetchBlockAt($x, $y, $z);
		if($this->canReplaceLeaf($existing)){
			$transaction->addBlockAt($x, $y, $z, $this->leafBlock);
		}
	}

	protected function finishTransaction(ChunkManager $world, BlockTransaction $transaction) : ?BlockTransaction{
		if($this->placementFailed){
			return null;
		}
		$result = new BlockTransaction($world);
		$original = [];
		foreach($transaction->getBlocks() as [$x, $y, $z, $block]){
			if(!$this->isAvailable($world, $x, $y, $z)){
				return null;
			}
			$existing = $world->getBlockAt($x, $y, $z);
			if($block instanceof Leaves && !$this->canReplaceLeaf($existing)){
				// Foliage grows around solid obstacles, never through them or through liquids/containers.
				continue;
			}
			$original[$x][$y][$z] = $existing->getStateId();
			$result->addBlockAt($x, $y, $z, $block);
		}
		$result->addValidator(function(ChunkManager $world, int $x, int $y, int $z) use ($original) : bool{
			return $this->isAvailable($world, $x, $y, $z)
				&& (!isset($original[$x][$y][$z]) || $world->getBlockAt($x, $y, $z)->getStateId() === $original[$x][$y][$z]);
		});
		return $result;
	}

	protected function canReplaceLeaf(Block $block) : bool{
		return $block instanceof Air || $block instanceof Leaves;
	}

	protected function beginPlacement(ChunkManager $world) : void{
		$this->placementWorld = $world;
		$this->placementFailed = false;
	}

	protected function canReplaceForTree(Block $block) : bool{
		return $block instanceof Air || $block instanceof Leaves || $block instanceof Sapling
			|| ($block->canBeReplaced() && !$block instanceof Liquid && !$block instanceof Fire);
	}

	protected function placeLog(BlockTransaction $transaction, int $x, int $y, int $z, ?int $axis = null) : void{
		if(!$this->isAvailable($this->placementWorld, $x, $y, $z)
			|| !$this->canOverride($this->placementWorld->getBlockAt($x, $y, $z))){
			$this->placementFailed = true;
			return;
		}
		$log = clone $this->trunkBlock;
		if($axis !== null && $log instanceof PillarRotation){
			$log->setAxis($axis);
		}
		$transaction->addBlockAt($x, $y, $z, $log);
	}

	protected function placeLeafLayer(BlockTransaction $transaction, int $x, int $y, int $z, int $radius, bool $wide = false) : void{
		for($dx = -$radius; $dx <= $radius + ($wide ? 1 : 0); ++$dx){
			for($dz = -$radius; $dz <= $radius + ($wide ? 1 : 0); ++$dz){
				$nx = $wide ? min(abs($dx), abs($dx - 1)) : abs($dx);
				$nz = $wide ? min(abs($dz), abs($dz - 1)) : abs($dz);
				if($nx * $nx + $nz * $nz <= $radius * $radius + $radius){
					$this->placeLeaf($transaction, $x + $dx, $y, $z + $dz);
				}
			}
		}
	}

	public static function findSaplingSquareForType(ChunkManager $world, int $x, int $y, int $z, int $saplingTypeId) : ?\pocketmine\math\Vector3{
		for($dx = 0; $dx >= -1; --$dx){
			for($dz = 0; $dz >= -1; --$dz){
				$complete = true;
				for($sx = 0; $sx < 2; ++$sx){
					for($sz = 0; $sz < 2; ++$sz){
						$xx = $x + $dx + $sx;
						$zz = $z + $dz + $sz;
						if(!$world->isInWorld($xx, $y, $zz) || $world->getChunk($xx >> Chunk::COORD_BIT_SIZE, $zz >> Chunk::COORD_BIT_SIZE) === null
							|| $world->getBlockAt($xx, $y, $zz)->getTypeId() !== $saplingTypeId){
							$complete = false;
						}
					}
				}
				if($complete){
					return new \pocketmine\math\Vector3($x + $dx, $y, $z + $dz);
				}
			}
		}
		return null;
	}

}
