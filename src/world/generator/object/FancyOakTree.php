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

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Axis;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function abs;
use function cos;
use function floor;
use function max;
use function min;
use function sin;
use function sqrt;
use const M_PI;

/**
 * Branching fancy oak; height 5 is the small "balloon" configuration.
 */
class FancyOakTree extends OakTree{
	public function __construct(private ?int $fixedHeight = null){
		if($fixedHeight !== null && ($fixedHeight < 5 || $fixedHeight > 16)){
			throw new \InvalidArgumentException("Fancy oak height must be between 5 and 16");
		}
		parent::__construct();
	}

	public function canPlaceObject(ChunkManager $world, int $x, int $y, int $z, Random $random) : bool{
		return $this->isAvailable($world, $x, $y - 1, $z) && $this->canGrowOn($world->getBlockAt($x, $y - 1, $z))
			&& $this->checkLine($world, $x, $y, $z, $x, $y + 4, $z) === -1;
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$this->placementFailed = false;
		$this->placementWorld = $world;
		if(!$this->canPlaceObject($world, $x, $y, $z, $random)){
			return null;
		}
		$height = $this->fixedHeight ?? (5 + $random->nextBoundedInt(12));
		$obstruction = $this->checkLine($world, $x, $y, $z, $x, $y + $height - 1, $z);
		if($obstruction !== -1){
			if($obstruction < 5){
				return null;
			}
			$height = $obstruction;
		}
		$this->treeHeight = $height;
		$trunkTop = min($height - 1, (int) ($height * 0.618));
		$clustersPerLayer = max(1, (int) (1.382 + ($height / 13) ** 2));
		/** @var array<array{int, int, int, int}> $foliage */
		$foliage = [[$x, $y + $height - 4, $z, $y + $trunkTop]];
		for($relativeY = $height - 4; $relativeY >= 0; --$relativeY){
			if($relativeY < $height * 0.3){
				continue;
			}
			$half = $height / 2;
			$radius = sqrt(max(0.0, $half ** 2 - ($half - $relativeY) ** 2)) * 0.5;
			for($i = 0; $i < $clustersPerLayer; ++$i){
				$distance = ($random->nextFloat() + 0.328) * $radius;
				$angle = $random->nextFloat() * 2 * M_PI;
				$leafX = $x + (int) floor(sin($angle) * $distance + 0.5);
				$leafZ = $z + (int) floor(cos($angle) * $distance + 0.5);
				$leafY = $y + $relativeY - 1;
				$branchY = min($y + $trunkTop, (int) floor($leafY - sqrt(($leafX - $x) ** 2 + ($leafZ - $z) ** 2) * 0.381));
				if($branchY - $y < $height * 0.2
					|| $this->checkLine($world, $leafX, $leafY, $leafZ, $leafX, $leafY + 4, $leafZ) !== -1
					|| $this->checkLine($world, $x, $branchY, $z, $leafX, $leafY, $leafZ) !== -1){
					continue;
				}
				$foliage[] = [$leafX, $leafY, $leafZ, $branchY];
			}
		}
		$transaction = new BlockTransaction($world);
		foreach($foliage as [$leafX, $leafY, $leafZ]){
			for($layer = 0; $layer < 4; ++$layer){
				$radius = $layer === 0 || $layer === 3 ? 2 : 3;
				for($dx = -$radius; $dx <= $radius; ++$dx){
					for($dz = -$radius; $dz <= $radius; ++$dz){
						if((abs($dx) + 0.5) ** 2 + (abs($dz) + 0.5) ** 2 <= $radius ** 2){
							$this->placeLeaf($transaction, $leafX + $dx, $leafY + $layer, $leafZ + $dz);
						}
					}
				}
			}
		}
		$transaction->addBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT());
		$this->placeLimb($transaction, $x, $y, $z, $x, $y + $trunkTop, $z);
		foreach($foliage as [$leafX, $leafY, $leafZ, $branchY]){
			$this->placeLimb($transaction, $x, $branchY, $z, $leafX, $leafY, $leafZ);
		}
		return $this->finishTransaction($world, $transaction);
	}

	/** Returns the first blocked step, or -1 for a clear line. */
	private function checkLine(ChunkManager $world, int $sx, int $sy, int $sz, int $ex, int $ey, int $ez) : int{
		$steps = max(abs($ex - $sx), abs($ey - $sy), abs($ez - $sz));
		for($i = 0; $i <= $steps; ++$i){
			$fraction = $steps === 0 ? 0.0 : $i / $steps;
			$x = $sx + (int) floor(0.5 + ($ex - $sx) * $fraction);
			$y = $sy + (int) floor(0.5 + ($ey - $sy) * $fraction);
			$z = $sz + (int) floor(0.5 + ($ez - $sz) * $fraction);
			if(!$this->isAvailable($world, $x, $y, $z) || !$this->canOverride($world->getBlockAt($x, $y, $z))){
				return $i;
			}
		}
		return -1;
	}

	private function placeLimb(BlockTransaction $transaction, int $sx, int $sy, int $sz, int $ex, int $ey, int $ez) : void{
		$steps = max(abs($ex - $sx), abs($ey - $sy), abs($ez - $sz));
		for($i = 0; $i <= $steps; ++$i){
			$fraction = $steps === 0 ? 0.0 : $i / $steps;
			$dx = (int) floor(0.5 + ($ex - $sx) * $fraction);
			$dy = (int) floor(0.5 + ($ey - $sy) * $fraction);
			$dz = (int) floor(0.5 + ($ez - $sz) * $fraction);
			$axis = $dx === 0 && $dz === 0 ? Axis::Y : (abs($dx) >= abs($dz) ? Axis::X : Axis::Z);
			$transaction->addBlockAt($sx + $dx, $sy + $dy, $sz + $dz, VanillaBlocks::OAK_LOG()->setAxis($axis));
		}
	}
}
