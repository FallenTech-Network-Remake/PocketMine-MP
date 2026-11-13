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

use pocketmine\block\Air;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Fence;
use pocketmine\block\FenceGate;
use pocketmine\block\Fire;
use pocketmine\block\Liquid;
use pocketmine\block\SnowLayer;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\utils\WallConnectionType;
use pocketmine\block\Wall;
use pocketmine\entity\Location;
use pocketmine\entity\object\Cushion as CushionEntity;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\CushionPlaceSound;
use function abs;
use function in_array;
use function max;
use function min;

/** Places a native, non-solid cushion entity. Color is part of the item type. */
class Cushion extends Item{
	public function __construct(ItemIdentifier $identifier, string $name, private DyeColor $color){
		parent::__construct($identifier, $name);
	}

	public static function forColor(DyeColor $color) : self{
		return match($color){
			DyeColor::WHITE => VanillaItems::WHITE_CUSHION(),
			DyeColor::ORANGE => VanillaItems::ORANGE_CUSHION(),
			DyeColor::MAGENTA => VanillaItems::MAGENTA_CUSHION(),
			DyeColor::LIGHT_BLUE => VanillaItems::LIGHT_BLUE_CUSHION(),
			DyeColor::YELLOW => VanillaItems::YELLOW_CUSHION(),
			DyeColor::LIME => VanillaItems::LIME_CUSHION(),
			DyeColor::PINK => VanillaItems::PINK_CUSHION(),
			DyeColor::GRAY => VanillaItems::GRAY_CUSHION(),
			DyeColor::LIGHT_GRAY => VanillaItems::LIGHT_GRAY_CUSHION(),
			DyeColor::CYAN => VanillaItems::CYAN_CUSHION(),
			DyeColor::PURPLE => VanillaItems::PURPLE_CUSHION(),
			DyeColor::BLUE => VanillaItems::BLUE_CUSHION(),
			DyeColor::BROWN => VanillaItems::BROWN_CUSHION(),
			DyeColor::GREEN => VanillaItems::GREEN_CUSHION(),
			DyeColor::RED => VanillaItems::RED_CUSHION(),
			DyeColor::BLACK => VanillaItems::BLACK_CUSHION(),
		};
	}

	public function getColor() : DyeColor{ return $this->color; }
	public function getMaxStackSize() : int{ return 16; }
	public function getFuelTime() : int{ return 50; }

	/** Returns the actual surface position, including partial-height blocks. */
	public static function getPlacementPosition(Block $support, int $face, Vector3 $clickVector) : ?Vector3{
		if($face !== Facing::UP || $support instanceof Air || $support instanceof Liquid || $support instanceof Fire ||
			in_array($support->getTypeId(), [BlockTypeIds::NETHER_PORTAL, BlockTypeIds::END_PORTAL, BlockTypeIds::END_GATEWAY, BlockTypeIds::LIGHT, BlockTypeIds::STRUCTURE_VOID], true)){
			return null;
		}
		$pos = $support->getPosition();
		$hit = $pos->addVector($clickVector);
		$boxes = $support->getCollisionBoxes();
		if($support instanceof Fence || $support instanceof FenceGate || $support instanceof Wall){
			// These blocks have taller movement barriers than their visible interaction surfaces.
			$surface = $pos->y + $clickVector->y;
			$heights = $support instanceof FenceGate && $support->isInWall() ? [0.8125] : [1.0];
			if ($support instanceof Wall) {
				$heights = $support->isPost() ? [1.0] : [];
				foreach ($support->getConnections() as $connection) {
					$heights[] = $connection === WallConnectionType::TALL ? 1.0 : 0.875;
				}
			}
			$matched = false;
			foreach($heights as $height){
				if(abs($clickVector->y - $height) < 0.01) $matched = true;
			}
			if(!$matched) return null;
		}elseif($support instanceof SnowLayer){
			// Snow's outline is one layer taller than its physical collision box.
			$surface = $pos->y + $support->getLayers() / SnowLayer::MAX_LAYERS;
			if(abs($hit->y - $surface) >= 0.01){
				return null;
			}
		}elseif($boxes !== []){
			$surface = null;
			foreach($boxes as $box){
				if($hit->x >= $box->minX - 0.001 && $hit->x <= $box->maxX + 0.001 &&
					$hit->z >= $box->minZ - 0.001 && $hit->z <= $box->maxZ + 0.001 &&
					abs($hit->y - $box->maxY) < 0.01){
					$surface = $box->maxY;
				}
			}
			if($surface === null){
				return null;
			}
		}else{
			// Non-colliding decorations still have client interaction surfaces (e.g. wildflowers).
			$surface = $pos->y + max(0.0, min(1.0, $clickVector->y));
		}
		return new Vector3($pos->x + 0.5, $surface, $pos->z + 0.5);
	}

	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		if($player->isAdventure() || $player->isSpectator()){
			return ItemUseResult::NONE;
		}
		$pos = self::getPlacementPosition($blockClicked, $face, $clickVector);
		if($pos === null || !$player->getWorld()->isInLoadedTerrain($pos) || !CushionEntity::canFit($player->getWorld(), $pos)){
			return ItemUseResult::NONE;
		}
		$entity = new CushionEntity(Location::fromObject($pos, $player->getWorld()), $this, $blockClicked->getPosition());
		$this->pop();
		$entity->spawnToAll();
		$player->getWorld()->addSound($pos, new CushionPlaceSound());
		return ItemUseResult::SUCCESS;
	}
}
