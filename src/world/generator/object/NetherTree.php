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
use pocketmine\block\Fire;
use pocketmine\block\Liquid;
use pocketmine\block\NetherFungus;
use pocketmine\block\NetherVines;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function abs;
use function min;

class NetherTree extends Tree{

	public function __construct(
		Block $stemBlock,
		Block $hatBlock,
		private readonly Block $decorBlock,
		int $treeHeight,
		private readonly bool $hasVines,
		private readonly bool $huge
	){
		parent::__construct($stemBlock, $hatBlock, $treeHeight);
	}

	public function canPlaceObject(ChunkManager $world, int $x, int $y, int $z, Random $random) : bool{
		if(!$this->isAvailable($world, $x, $y - 1, $z) || !$this->isAvailable($world, $x, $y + $this->treeHeight, $z)) return false;
		$nylium = $this->trunkBlock->getTypeId() === BlockTypeIds::CRIMSON_STEM ? BlockTypeIds::CRIMSON_NYLIUM : BlockTypeIds::WARPED_NYLIUM;
		$plant = $world->getBlockAt($x, $y, $z);
		$fungus = $nylium === BlockTypeIds::CRIMSON_NYLIUM ? BlockTypeIds::CRIMSON_FUNGUS : BlockTypeIds::WARPED_FUNGUS;
		return $world->getBlockAt($x, $y - 1, $z)->getTypeId() === $nylium
			&& (!$plant instanceof NetherFungus || $plant->getTypeId() === $fungus) && $this->canOverride($plant);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$this->beginPlacement($world);
		$tx = parent::getBlockTransaction($world, $x, $y, $z, $random);
		if($tx === null) return null;
		// Include unchanged nylium so event-time substrate changes invalidate the whole growth.
		$tx->addBlockAt($x, $y - 1, $z, $world->getBlockAt($x, $y - 1, $z));
		return $this->finishTransaction($world, $tx);
	}

	private function canPlaceAt(BlockTransaction $transaction, int $x, int $y, int $z) : bool{
		if(!$this->isAvailable($this->placementWorld, $x, $y, $z)){
			$this->placementFailed = true;
			return false;
		}
		return $this->canOverride($transaction->fetchBlockAt($x, $y, $z));
	}

	protected function placeTrunk(int $x, int $y, int $z, Random $random, int $trunkHeight, BlockTransaction $transaction) : void{
		$i = $this->huge ? 1 : 0;

		for($j = -$i; $j <= $i; ++$j){
			for($k = -$i; $k <= $i; ++$k){
				$isCorner = $this->huge && abs($j) === $i && abs($k) === $i;

				for($l = 0; $l < $trunkHeight; ++$l){
					$blockX = $x + $j;
					$blockY = $y + $l;
					$blockZ = $z + $k;

					if((!$isCorner || $random->nextFloat() < 0.1) && $this->canPlaceAt($transaction, $blockX, $blockY, $blockZ)){
						$transaction->addBlockAt($blockX, $blockY, $blockZ, $this->trunkBlock);
					}
				}
			}
		}
	}

	protected function placeCanopy(int $x, int $y, int $z, Random $random, BlockTransaction $transaction) : void{
		$isCrimson = $this->hasVines;
		$i = min($random->nextBoundedInt(1 + (int) ($this->treeHeight / 3)) + 5, $this->treeHeight);
		$j = $this->treeHeight - $i;

		for($k = $j; $k <= $this->treeHeight; ++$k){
			$l = $k < $this->treeHeight - $random->nextBoundedInt(3) ? 2 : 1;

			if($i > 8 && $k < $j + 4){
				$l = 3;
			}

			if($this->huge){
				++$l;
			}

			for($i1 = -$l; $i1 <= $l; ++$i1){
				for($j1 = -$l; $j1 <= $l; ++$j1){
					$isEdgeX = $i1 === -$l || $i1 === $l;
					$isEdgeZ = $j1 === -$l || $j1 === $l;
					$isInner = !$isEdgeX && !$isEdgeZ && $k !== $this->treeHeight;
					$isCorner = $isEdgeX && $isEdgeZ;
					$isLowerSection = $k < $j + 3;

					$blockX = $x + $i1;
					$blockY = $y + $k;
					$blockZ = $z + $j1;

					if($this->canPlaceAt($transaction, $blockX, $blockY, $blockZ)){
						if($isLowerSection){
							if(!$isInner){
								$this->placeHatDropBlock($blockX, $blockY, $blockZ, $random, $transaction, $isCrimson);
							}
						}elseif($isInner){
							$this->placeHatBlock($blockX, $blockY, $blockZ, $random, $transaction, 0.1, 0.2, $isCrimson ? 0.1 : 0.0);
						}elseif($isCorner){
							$this->placeHatBlock($blockX, $blockY, $blockZ, $random, $transaction, 0.01, 0.7, $isCrimson ? 0.083 : 0.0);
						}else{
							$this->placeHatBlock($blockX, $blockY, $blockZ, $random, $transaction, 0.0005, 0.98, $isCrimson ? 0.07 : 0.0);
						}
					}
				}
			}
		}
	}

	protected function placeHatBlock(int $x, int $y, int $z, Random $random, BlockTransaction $transaction, float $decorChance, float $hatChance, float $vineChance) : void{
		if($random->nextFloat() < $decorChance){
			$transaction->addBlockAt($x, $y, $z, $this->decorBlock);
		}elseif($random->nextFloat() < $hatChance){
			$transaction->addBlockAt($x, $y, $z, $this->leafBlock);
			if($random->nextFloat() < $vineChance){
				$this->tryPlaceWeepingVines($x, $y, $z, $random, $transaction);
			}
		}
	}

	protected function placeHatDropBlock(int $x, int $y, int $z, Random $random, BlockTransaction $transaction, bool $isCrimson) : void{
		$blockBelow = $transaction->fetchBlockAt($x, $y - 1, $z);
		if($blockBelow->getTypeId() === $this->leafBlock->getTypeId()){
			$transaction->addBlockAt($x, $y, $z, $this->leafBlock);
		}elseif($random->nextFloat() < 0.15){
			$transaction->addBlockAt($x, $y, $z, $this->leafBlock);
			if($isCrimson && $random->nextBoundedInt(11) === 0){
				$this->tryPlaceWeepingVines($x, $y, $z, $random, $transaction);
			}
		}
	}

	protected function tryPlaceWeepingVines(int $x, int $y, int $z, Random $random, BlockTransaction $transaction) : void{
		$currentY = $y - 1;

		if($this->canPlaceAt($transaction, $x, $currentY, $z)){
			$i = $random->nextBoundedInt(5) + 1;
			if($random->nextBoundedInt(7) === 0){
				$i *= 2;
			}

			$maxAge = NetherVines::MAX_AGE;
			$startAge = 23;

			for($v = 0; $v < $i; ++$v){
				$vy = $currentY - $v;
				if($vy < $this->placementWorld->getMinY()){
					break;
				}

				if($this->canPlaceAt($transaction, $x, $vy, $z)){
					$vineAge = min($maxAge, $startAge + $v);
					$transaction->addBlockAt($x, $vy, $z, VanillaBlocks::WEEPING_VINES()->setAge($vineAge));
				}else{
					break;
				}
			}
		}
	}

	protected function canOverride(Block $block) : bool{
		return $block instanceof Air || (!$block instanceof Liquid && !$block instanceof Fire && ($block->canBeReplaced() || $block->hasTypeTag(BlockTypeTags::HUGE_FUNGUS_REPLACEABLE)));
	}

	protected function generateTrunkHeight(Random $random) : int{
		return $this->treeHeight;
	}
}
