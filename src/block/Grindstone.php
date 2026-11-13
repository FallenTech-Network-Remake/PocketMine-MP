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

namespace pocketmine\block;

use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\HorizontalFacingTrait;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

class Grindstone extends Transparent implements HorizontalFacing{
	use HorizontalFacingTrait;
	private int $attachment = 0;
	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{ $w->horizontalFacing($this->facing); $w->boundedIntAuto(0, 3, $this->attachment); }
	public function getAttachment() : int{ return $this->attachment; }
	/** @return $this */
	public function setAttachment(int $attachment) : self{ if($attachment < 0 || $attachment > 3){ throw new \InvalidArgumentException("Invalid grindstone attachment"); } $this->attachment = $attachment; return $this; }
	protected function recalculateCollisionBoxes() : array{ return [$this->attachment < 2 ? AxisAlignedBB::one()->contract(0.125, 0, 0.125) : (Facing::axis($this->facing) === \pocketmine\math\Axis::X ? AxisAlignedBB::one()->contract(0, 0.125, 0.125) : AxisAlignedBB::one()->contract(0.125, 0.125, 0))]; }
	public function getSupportType(int $facing) : SupportType{ return SupportType::NONE; }
	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$this->attachment = $face === Facing::UP ? 0 : ($face === Facing::DOWN ? 1 : 2);
		$this->setFacing($this->attachment === 2 ? $face : ($player !== null ? Facing::opposite($player->getHorizontalFacing()) : Facing::NORTH));
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}
}
