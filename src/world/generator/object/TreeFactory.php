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

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;

final class TreeFactory{

	/**
	 * @param TreeType|null $type default oak
	 */
	public static function get(Random $random, ?TreeType $type = null) : ?Tree{
		return match($type){
			null, TreeType::OAK => $random->nextBoundedInt(10) === 0 ? new FancyOakTree() : new OakTree(),
			TreeType::FANCY_OAK => new FancyOakTree(),
			TreeType::BALLOON_OAK => new FancyOakTree(5),
			TreeType::SWAMP_OAK => new SwampOakTree(),
			TreeType::SPRUCE => $random->nextBoundedInt(3) === 0 ? new PineTree($random->nextBoundedInt(10) === 0) : new SpruceTree(),
			TreeType::PINE => new PineTree(),
			TreeType::LOLLIPOP_PINE => new PineTree(true),
			TreeType::MEGA_SPRUCE => new MegaConiferTree(),
			TreeType::MEGA_PINE => new MegaConiferTree(true),
			TreeType::JUNGLE => new JungleTree(),
			TreeType::MEGA_JUNGLE => new JungleTree(mega: true),
			TreeType::JUNGLE_BUSH => new JungleTree(bush: true),
			TreeType::ACACIA => new AcaciaTree(),
			TreeType::DARK_OAK => new DarkOakTree(),
			TreeType::CHERRY => new CherryTree(),
			TreeType::BIRCH => new BirchTree($random->nextBoundedInt(39) === 0),
			TreeType::TALL_BIRCH => new BirchTree(true),
			TreeType::AZALEA => new AzaleaTree(),
			TreeType::MANGROVE => new MangroveTree($random->nextBoundedInt(2) === 0),
			TreeType::TALL_MANGROVE => new MangroveTree(true),
			TreeType::CRIMSON => new NetherTree(VanillaBlocks::CRIMSON_STEM(), VanillaBlocks::NETHER_WART_BLOCK(), VanillaBlocks::SHROOMLIGHT(), ($random->nextBoundedInt(9) + 4) * ($random->nextBoundedInt(12) === 0 ? 2 : 1), hasVines: true, huge: $random->nextFloat() < 0.06),
			TreeType::WARPED => new NetherTree(VanillaBlocks::WARPED_STEM(), VanillaBlocks::WARPED_WART_BLOCK(), VanillaBlocks::SHROOMLIGHT(), ($random->nextBoundedInt(9) + 4) * ($random->nextBoundedInt(12) === 0 ? 2 : 1), hasVines: false, huge: $random->nextFloat() < 0.06),
			default => null,
		};
	}

	/** Selects sapling forms independently of the world-generation mix. */
	public static function getForSapling(Random $random, TreeType $type, ChunkManager $world, int $x, int $y, int $z) : ?Tree{
		if($type === TreeType::OAK && OakTree::hasFancyBrace($world, $x, $y, $z)){
			return new FancyOakTree();
		}
		if($type === TreeType::SPRUCE){
			return MegaConiferTree::findSaplingSquare($world, $x, $y, $z) !== null
				? new MegaConiferTree($random->nextBoundedInt(2) === 0)
				: new SpruceTree();
		}
		if($type === TreeType::JUNGLE){
			return new JungleTree(mega: Tree::findSaplingSquareForType($world, $x, $y, $z, BlockTypeIds::JUNGLE_SAPLING) !== null, decorated: false);
		}
		if($type === TreeType::BIRCH){
			return new BirchTree();
		}
		return self::get($random, $type);
	}
}
