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

use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function abs;

/**
 * Naturally generated pines, including the rare short, broad-crowned lollipop form.
 */
final class PineTree extends SpruceTree{
	public function __construct(private bool $lollipop = false){
		parent::__construct();
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$this->treeHeight = $this->lollipop ? 5 + $random->nextBoundedInt(3) : 7 + $random->nextBoundedInt(5);
		return $this->createTransaction($world, $x, $y, $z, $random);
	}

	protected function generateTrunkHeight(Random $random) : int{
		return $this->treeHeight;
	}

	protected function placeCanopy(int $x, int $y, int $z, Random $random, BlockTransaction $transaction) : void{
		$canopyHeight = 3 + $random->nextBoundedInt(2);
		$targetRadius = $this->lollipop ? 2 + $random->nextBoundedInt($canopyHeight) : 1;
		$radius = 0;
		for($dy = 1; $dy > -$canopyHeight; --$dy){
			for($dx = -$radius; $dx <= $radius; ++$dx){
				for($dz = -$radius; $dz <= $radius; ++$dz){
					if($radius > 0 && abs($dx) === $radius && abs($dz) === $radius){
						continue;
					}
					$this->placeLeaf($transaction, $x + $dx, $y + $this->treeHeight + $dy, $z + $dz);
				}
			}
			if($dy === 2 - $canopyHeight && $radius > 0){
				--$radius;
			}elseif($radius < $targetRadius){
				++$radius;
			}
		}
	}
}
