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

namespace pocketmine\item;

use pocketmine\block\BlockTypeIds as B;
use pocketmine\block\Flower;
use pocketmine\block\Leaves;
use pocketmine\block\Sapling;

/**
 * Vanilla Bedrock composting chances.
 */
final class CompostChance{
	public static function get(Item $item) : float{
		$chance = match($item->getTypeId()){
			ItemTypeIds::BEETROOT_SEEDS, ItemTypeIds::DRIED_KELP, ItemTypeIds::GLOW_BERRIES,
			ItemTypeIds::MELON_SEEDS, ItemTypeIds::PITCHER_POD, ItemTypeIds::PUMPKIN_SEEDS,
			ItemTypeIds::SWEET_BERRIES, ItemTypeIds::TORCHFLOWER_SEEDS, ItemTypeIds::WHEAT_SEEDS => 0.3,
			ItemTypeIds::MELON => 0.5,
			ItemTypeIds::APPLE, ItemTypeIds::BEETROOT, ItemTypeIds::CARROT, ItemTypeIds::COCOA_BEANS,
			ItemTypeIds::POTATO, ItemTypeIds::WHEAT => 0.65,
			ItemTypeIds::BAKED_POTATO, ItemTypeIds::BREAD, ItemTypeIds::COOKIE => 0.85,
			ItemTypeIds::PUMPKIN_PIE => 1.0,
			default => null
		};
		if($chance !== null){
			return $chance;
		}
		if(!$item instanceof ItemBlock){
			return 0.0;
		}
		$block = $item->getBlock();
		if($block instanceof Leaves){
			return $block->getTypeId() === B::FLOWERING_AZALEA_LEAVES ? 0.5 : 0.3;
		}
		if($block instanceof Sapling){
			return 0.3;
		}
		return match($block->getTypeId()){
			B::BUSH, B::CACTUS_FLOWER, B::FIREFLY_BUSH, B::GRASS, B::HANGING_ROOTS,
			B::KELP, B::LEAF_LITTER, B::MANGROVE_PROPAGULE, B::MANGROVE_ROOTS,
			B::MOSS_CARPET, B::PALE_HANGING_MOSS, B::PALE_MOSS_CARPET, B::PINK_PETALS,
			B::RED_SHRUB, B::SEAGRASS, B::SHORT_DRY_GRASS, B::SMALL_DRIPLEAF,
			B::TALL_DRY_GRASS, B::TALL_GRASS, B::WILDFLOWERS => 0.3,
			B::CACTUS, B::DOUBLE_TALLGRASS, B::DRIED_KELP, B::GLOW_LICHEN,
			B::NETHER_SPROUTS, B::SUGARCANE, B::TWISTING_VINES, B::VINES, B::WEEPING_VINES => 0.5,
			B::AZALEA, B::BIG_DRIPLEAF_HEAD, B::BIG_DRIPLEAF_STEM, B::BROWN_MUSHROOM,
			B::CARVED_PUMPKIN, B::CRIMSON_FUNGUS, B::CRIMSON_ROOTS, B::FERN, B::LARGE_FERN,
			B::LILY_PAD, B::MELON, B::MOSS_BLOCK, B::MUSHROOM_STEM, B::NETHER_WART, B::PALE_MOSS_BLOCK,
			B::PUMPKIN, B::RED_MUSHROOM, B::SEA_PICKLE, B::SHELF_MUSHROOM,
			B::SHROOMLIGHT, B::SPORE_BLOSSOM, B::STRAW_BED, B::WARPED_FUNGUS, B::WARPED_ROOTS,
			B::LILAC, B::PEONY, B::ROSE_BUSH, B::SUNFLOWER => 0.65,
			B::BROWN_MUSHROOM_BLOCK, B::FLOWERING_AZALEA, B::HAY_BALE, B::NETHER_WART_BLOCK,
			B::PITCHER_PLANT, B::RED_MUSHROOM_BLOCK, B::TORCHFLOWER, B::WARPED_WART_BLOCK => 0.85,
			B::CAKE => 1.0,
			B::GOLDEN_DANDELION => 0.0,
			default => $block instanceof Flower ? 0.65 : 0.0
		};
	}
}
