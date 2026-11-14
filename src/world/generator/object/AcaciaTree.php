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
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function abs;

final class AcaciaTree extends Tree{
	private const MIN_HEIGHT = 5;

	private ?Vector3 $mainBranchTip = null;
	private ?Vector3 $secondBranchTip = null;

	public function __construct(){
		parent::__construct(
			VanillaBlocks::ACACIA_LOG(),
			VanillaBlocks::ACACIA_LEAVES(),
			0
		);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$this->treeHeight = self::MIN_HEIGHT + $random->nextBoundedInt(3) + $random->nextBoundedInt(3);
		$this->mainBranchTip = $this->secondBranchTip = null;
		$this->beginPlacement($world);
		$tx = parent::getBlockTransaction($world, $x, $y, $z, $random);
		return $tx === null ? null : $this->finishTransaction($world, $tx);
	}

	public function canPlaceObject(ChunkManager $world, int $x, int $y, int $z, Random $random) : bool{
		if(!$this->isAvailable($world, $x, $y - 1, $z) || !$this->canGrowOn($world->getBlockAt($x, $y - 1, $z))) return false;
		for($dy = 0; $dy <= $this->treeHeight + 1; ++$dy){
			$radius = $dy === 0 ? 0 : 1;
			for($dx = -$radius; $dx <= $radius; ++$dx){
				for($dz = -$radius; $dz <= $radius; ++$dz){
					if(!$this->isAvailable($world, $x + $dx, $y + $dy, $z + $dz) || !$this->canOverride($world->getBlockAt($x + $dx, $y + $dy, $z + $dz))) return false;
				}
			}
		}
		return true;
	}

	protected function canOverride(Block $block) : bool{
		return $this->canReplaceForTree($block);
	}

	protected function generateTrunkHeight(Random $random) : int{
		return $this->treeHeight;
	}

	protected function placeTrunk(int $x, int $y, int $z, Random $random, int $trunkHeight, BlockTransaction $transaction) : void{
		// The base dirt block
		$transaction->addBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT());

		$firstBranchHeight = $trunkHeight - 1 - $random->nextRange(0, 3);

		for($yy = 0; $yy <= $firstBranchHeight; ++$yy){
			$this->placeLog($transaction, $x, $y + $yy, $z);
		}

		$mainBranchFacing = Facing::HORIZONTAL[$random->nextBoundedInt(4)];

		//this branch may grow a second trunk if the diagonal length is less than the max length
		$this->mainBranchTip = $this->placeBranch(
			$transaction,
			new Vector3($x, $y + $firstBranchHeight, $z),
			$mainBranchFacing,
			$random->nextRange(1, 3),
			$trunkHeight - $firstBranchHeight
		);

		$secondBranchFacing = Facing::HORIZONTAL[$random->nextBoundedInt(4)];
		if($secondBranchFacing !== $mainBranchFacing){
			$secondBranchLength = $random->nextRange(1, 3);
			$this->secondBranchTip = $this->placeBranch(
				$transaction,
				new Vector3($x, $y + ($firstBranchHeight - $random->nextRange(0, 1)), $z),
				$secondBranchFacing,
				$secondBranchLength,
				$secondBranchLength //the secondary branch may not form a second trunk
			);
		}
	}

	protected function placeBranch(BlockTransaction $transaction, Vector3 $start, int $branchFacing, int $maxDiagonal, int $length) : Vector3{
		$diagonalPlaced = 0;

		$nextBlockPos = $start;
		for($yy = 0; $yy < $length; $yy++){
			$nextBlockPos = $nextBlockPos->up();
			if($diagonalPlaced < $maxDiagonal){
				$nextBlockPos = $nextBlockPos->getSide($branchFacing);
				$diagonalPlaced++;
			}
			$this->placeLog($transaction, $nextBlockPos->getFloorX(), $nextBlockPos->getFloorY(), $nextBlockPos->getFloorZ(), $yy < $maxDiagonal ? Facing::axis($branchFacing) : Axis::Y);
		}

		return $nextBlockPos;
	}

	protected function placeCanopyLayer(BlockTransaction $transaction, Vector3 $center, int $radius, int $maxTaxicabDistance) : void{
		$centerX = $center->getFloorX();
		$centerY = $center->getFloorY();
		$centerZ = $center->getFloorZ();

		for($x = $centerX - $radius; $x <= $centerX + $radius; ++$x){
			for($z = $centerZ - $radius; $z <= $centerZ + $radius; ++$z){
				if(
					abs($x - $centerX) + abs($z - $centerZ) <= $maxTaxicabDistance
				){
					$this->placeLeaf($transaction, $x, $centerY, $z);
				}
			}
		}
	}

	protected function placeCanopy(int $x, int $y, int $z, Random $random, BlockTransaction $transaction) : void{
		$mainBranchTip = $this->mainBranchTip;
		if($mainBranchTip !== null){
			$this->placeCanopyLayer($transaction, $mainBranchTip, radius: 3, maxTaxicabDistance: 5);
			$this->placeCanopyLayer($transaction, $mainBranchTip->up(), radius: 2, maxTaxicabDistance: 2);
		}
		$secondBranchTip = $this->secondBranchTip;
		if($secondBranchTip !== null){
			$this->placeCanopyLayer($transaction, $secondBranchTip, radius: 2, maxTaxicabDistance: 3);
			$this->placeCanopyLayer($transaction, $secondBranchTip->up(), radius: 1, maxTaxicabDistance: 2);
		}
	}
}
