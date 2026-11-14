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
use pocketmine\block\Farmland;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function abs;
use function intdiv;
use function min;

/**
 * Two-by-two spruce trunks with either a long spruce skirt or a short pine crown.
 */
final class MegaConiferTree extends SpruceTree{
	public function __construct(private bool $pine = false){
		parent::__construct();
	}

	public static function findSaplingSquare(ChunkManager $world, int $x, int $y, int $z) : ?Vector3{
		$tree = new self();
		for($dx = 0; $dx >= -1; --$dx){
			for($dz = 0; $dz >= -1; --$dz){
				$complete = true;
				for($sx = 0; $sx < 2; ++$sx){
					for($sz = 0; $sz < 2; ++$sz){
						if(!$tree->isAvailable($world, $x + $dx + $sx, $y, $z + $dz + $sz)
							|| $world->getBlockAt($x + $dx + $sx, $y, $z + $dz + $sz)->getTypeId() !== BlockTypeIds::SPRUCE_SAPLING){
							$complete = false;
						}
					}
				}
				if($complete){
					return new Vector3($x + $dx, $y, $z + $dz);
				}
			}
		}
		return null;
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		if(!$this->isAvailable($world, $x, $y, $z)){
			return null;
		}
		if($world->getBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::SPRUCE_SAPLING){
			$origin = self::findSaplingSquare($world, $x, $y, $z);
			if($origin === null){
				return null;
			}
			$x = $origin->getFloorX();
			$z = $origin->getFloorZ();
		}
		$this->treeHeight = 13 + $random->nextBoundedInt(3) + $random->nextBoundedInt(15);
		$this->placementWorld = $world;
		$this->placementFailed = false;
		if(!$this->canPlaceObject($world, $x, $y, $z, $random)){
			return null;
		}
		$transaction = new BlockTransaction($world);
		$this->placeCanopy($x, $y, $z, $random, $transaction);
		for($dx = 0; $dx < 2; ++$dx){
			for($dz = 0; $dz < 2; ++$dz){
				$transaction->addBlockAt($x + $dx, $y - 1, $z + $dz, VanillaBlocks::PODZOL());
				for($dy = 0; $dy < $this->treeHeight - 1; ++$dy){
					$transaction->addBlockAt($x + $dx, $y + $dy, $z + $dz, $this->trunkBlock);
				}
			}
		}
		$transaction->addBlockAt($x, $y + $this->treeHeight - 1, $z, $this->trunkBlock);
		$this->placePodzol($x, $y - 1, $z, $random, $transaction);
		return $this->finishTransaction($world, $transaction);
	}

	public function canPlaceObject(ChunkManager $world, int $x, int $y, int $z, Random $random) : bool{
		for($dx = 0; $dx < 2; ++$dx){
			for($dz = 0; $dz < 2; ++$dz){
				if(!$this->isAvailable($world, $x + $dx, $y - 1, $z + $dz)
					|| !$this->canGrowOn($world->getBlockAt($x + $dx, $y - 1, $z + $dz))){
					return false;
				}
			}
		}
		for($dy = 0; $dy <= $this->treeHeight + 1; ++$dy){
			$radius = $dy === 0 ? 1 : 2;
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

	protected function placeCanopy(int $x, int $y, int $z, Random $random, BlockTransaction $transaction) : void{
		// A short sampled trunk must not put the bottom of its skirt below the ground.
		$height = min($this->treeHeight - 1, ($this->pine ? 3 : 13) + $random->nextBoundedInt(5));
		$previousRadius = 0;
		for($dy = -$height; $dy <= 0; ++$dy){
			$leafY = $y + $this->treeHeight + $dy;
			$baseRadius = (int) (-$dy / $height * 3.5);
			$radius = $baseRadius + ($dy !== 0 && $baseRadius === $previousRadius && ($leafY & 1) === 0 ? 1 : 0);
			for($dx = -$radius; $dx <= 1 + $radius; ++$dx){
				for($dz = -$radius; $dz <= 1 + $radius; ++$dz){
					// Union of four circles, centred on the four trunk columns.
					$nearestX = min(abs($dx), abs($dx - 1));
					$nearestZ = min(abs($dz), abs($dz - 1));
					if($nearestX * $nearestX + $nearestZ * $nearestZ <= $radius * $radius){
						$this->placeLeaf($transaction, $x + $dx, $leafY, $z + $dz);
					}
				}
			}
			$previousRadius = $baseRadius;
		}
	}

	private function placePodzol(int $x, int $y, int $z, Random $random, BlockTransaction $transaction) : void{
		foreach([[-1, -1], [2, -1], [-1, 2], [2, 2]] as [$dx, $dz]){
			$this->placePodzolPatch($x + $dx, $y, $z + $dz, $transaction);
		}
		for($i = 0; $i < 5; ++$i){
			$value = $random->nextBoundedInt(64);
			$dx = $value % 8;
			$dz = intdiv($value, 8);
			if($dx === 0 || $dx === 7 || $dz === 0 || $dz === 7){
				$this->placePodzolPatch($x + $dx - 3, $y, $z + $dz - 3, $transaction);
			}
		}
	}

	private function placePodzolPatch(int $x, int $y, int $z, BlockTransaction $transaction) : void{
		for($dx = -2; $dx <= 2; ++$dx){
			for($dz = -2; $dz <= 2; ++$dz){
				if(abs($dx) === 2 && abs($dz) === 2){
					continue;
				}
				for($dy = 2; $dy >= -3; --$dy){
					if(!$this->isAvailable($this->placementWorld, $x + $dx, $y + $dy, $z + $dz)){
						break;
					}
					$block = $transaction->fetchBlockAt($x + $dx, $y + $dy, $z + $dz);
					if($this->canBecomePodzol($block)){
						$transaction->addBlockAt($x + $dx, $y + $dy, $z + $dz, VanillaBlocks::PODZOL());
						break;
					}
					// Do not convert soil underneath floors, water, or other surface obstructions.
					if(!$this->canOverride($block)){
						break;
					}
				}
			}
		}
	}

	private function canBecomePodzol(Block $block) : bool{
		return $this->canGrowOn($block) && !$block instanceof Farmland;
	}
}
