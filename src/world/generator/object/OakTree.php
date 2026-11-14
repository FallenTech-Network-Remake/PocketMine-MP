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
use pocketmine\block\BlockTypeTags;
use pocketmine\block\Fire;
use pocketmine\block\Leaves;
use pocketmine\block\Liquid;
use pocketmine\block\Sapling;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\Wood;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function abs;

class OakTree extends Tree{

	public function __construct(){
		parent::__construct(VanillaBlocks::OAK_LOG(), VanillaBlocks::OAK_LEAVES(), 4);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$this->treeHeight = $random->nextBoundedInt(3) + 4;
		return $this->createTransaction($world, $x, $y, $z, $random);
	}

	protected function createTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$this->placementFailed = false;
		$this->placementWorld = $world;
		$transaction = parent::getBlockTransaction($world, $x, $y, $z, $random);
		return $transaction === null ? null : $this->finishTransaction($world, $transaction);
	}

	public function canPlaceObject(ChunkManager $world, int $x, int $y, int $z, Random $random) : bool{
		if(!$this->isAvailable($world, $x, $y - 1, $z) || !$this->canGrowOn($world->getBlockAt($x, $y - 1, $z))){
			return false;
		}
		// Oaks can grow in a narrow shaft; only the trunk column is mandatory.
		for($dy = 0; $dy <= $this->treeHeight; ++$dy){
			if(!$this->isAvailable($world, $x, $y + $dy, $z) || !$this->canOverride($world->getBlockAt($x, $y + $dy, $z))){
				return false;
			}
		}
		return true;
	}

	public static function hasFancyBrace(ChunkManager $world, int $x, int $y, int $z) : bool{
		$oak = new self();
		for($dx = -1; $dx <= 1; ++$dx){
			for($dz = -1; $dz <= 1; ++$dz){
				if(($dx !== 0 || $dz !== 0) && $oak->isAvailable($world, $x + $dx, $y + 1, $z + $dz)
					&& !$oak->canOverride($world->getBlockAt($x + $dx, $y + 1, $z + $dz))){
					return true;
				}
			}
		}
		return false;
	}

	protected function generateTrunkHeight(Random $random) : int{
		return $this->treeHeight;
	}

	protected function placeTrunk(int $x, int $y, int $z, Random $random, int $trunkHeight, BlockTransaction $transaction) : void{
		$transaction->addBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT());
		for($dy = 0; $dy < $trunkHeight; ++$dy){
			$transaction->addBlockAt($x, $y + $dy, $z, $this->trunkBlock);
		}
	}

	protected function placeCanopy(int $x, int $y, int $z, Random $random, BlockTransaction $transaction) : void{
		for($dy = $this->treeHeight - 3; $dy <= $this->treeHeight; ++$dy){
			$offset = $dy - $this->treeHeight;
			$radius = 1 - (int) ($offset / 2);
			for($dx = -$radius; $dx <= $radius; ++$dx){
				for($dz = -$radius; $dz <= $radius; ++$dz){
					if(abs($dx) === $radius && abs($dz) === $radius && ($offset === 0 || $random->nextBoundedInt(2) === 0)){
						continue;
					}
					$this->placeLeaf($transaction, $x + $dx, $y + $dy, $z + $dz);
				}
			}
		}
	}

	protected function canOverride(Block $block) : bool{
		return $block instanceof Air || $block instanceof Leaves || $block instanceof Sapling || $block instanceof Wood
			|| $block->hasTypeTag(BlockTypeTags::DIRT)
			|| ($block->canBeReplaced() && !$block instanceof Liquid && !$block instanceof Fire);
	}
}
