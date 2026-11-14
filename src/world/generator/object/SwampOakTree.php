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
use pocketmine\block\Leaves;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\Vine;
use pocketmine\block\Water;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function abs;

/** Broad, flat-topped oak with hanging vines. World generation only, not a sapling outcome. */
class SwampOakTree extends OakTree{
	public function __construct(){
		parent::__construct();
		$this->treeHeight = 5;
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$this->treeHeight = 5 + $random->nextBoundedInt(4);
		$transaction = $this->createTransaction($world, $x, $y, $z, $random);
		if($transaction === null){
			return null;
		}
		// Snapshot leaves before appending vines to the same transaction.
		$leaves = [];
		foreach($transaction->getBlocks() as [$xx, $yy, $zz, $block]){
			if($block instanceof Leaves) $leaves[] = [$xx, $yy, $zz];
		}
		foreach($leaves as [$xx, $yy, $zz]){
			foreach(Facing::HORIZONTAL as $face){
				if($random->nextBoundedInt(4) !== 0) continue;
				$vx = $xx + Facing::OFFSET[$face][0];
				$vz = $zz + Facing::OFFSET[$face][2];
				for($length = 0; $length < 5; ++$length){
					$vy = $yy - $length;
					if(!$this->isAvailable($world, $vx, $vy, $vz)) break;
					$existing = $transaction->fetchBlockAt($vx, $vy, $vz);
					if(!$existing instanceof Air && !$existing instanceof Vine) break;
					$vine = $existing instanceof Vine ? clone $existing : VanillaBlocks::VINES();
					$transaction->addBlockAt($vx, $vy, $vz, $vine->setFace(Facing::opposite($face), true));
				}
			}
		}
		return $this->finishTransaction($world, $transaction);
	}

	public function canPlaceObject(ChunkManager $world, int $x, int $y, int $z, Random $random) : bool{
		if(!$this->isAvailable($world, $x, $y - 1, $z) || !$this->canGrowOn($world->getBlockAt($x, $y - 1, $z))){
			return false;
		}
		for($dy = 0; $dy <= $this->treeHeight; ++$dy){
			if(!$this->isAvailable($world, $x, $y + $dy, $z)) return false;
			$block = $world->getBlockAt($x, $y + $dy, $z);
			if(!($dy === 0 && $block instanceof Water) && !$this->canOverride($block)) return false;
		}
		return true;
	}

	protected function placeCanopy(int $x, int $y, int $z, Random $random, BlockTransaction $transaction) : void{
		for($dy = $this->treeHeight - 3; $dy <= $this->treeHeight; ++$dy){
			$offset = $dy - $this->treeHeight;
			$radius = 2 - (int) ($offset / 2);
			for($dx = -$radius; $dx <= $radius; ++$dx){
				for($dz = -$radius; $dz <= $radius; ++$dz){
					if(abs($dx) === $radius && abs($dz) === $radius && ($offset === 0 || $random->nextBoundedInt(2) === 0)) continue;
					$this->placeLeaf($transaction, $x + $dx, $y + $dy, $z + $dz);
				}
			}
		}
	}
}
