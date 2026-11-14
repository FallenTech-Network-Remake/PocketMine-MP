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
use pocketmine\block\Leaves;
use pocketmine\block\MossBlock;
use pocketmine\block\Sapling;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use function abs;

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
}
