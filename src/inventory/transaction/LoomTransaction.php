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

namespace pocketmine\inventory\transaction;

use pocketmine\block\inventory\LoomInventory;
use pocketmine\block\utils\BannerPatternLayer;
use pocketmine\block\utils\BannerPatternType;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Banner;
use pocketmine\item\BannerPattern;
use pocketmine\item\Dye;
use pocketmine\item\Item;
use pocketmine\player\Player;
use function count;
use function in_array;

class LoomTransaction extends InventoryTransaction{

	public const MAX_PATTERNS = 6;

	/** Patterns requiring a reusable template. */
	public const ITEM_PATTERNS = [
		BannerPatternType::CREEPER, BannerPatternType::SKULL, BannerPatternType::FLOWER, BannerPatternType::MOJANG,
		BannerPatternType::GLOBE, BannerPatternType::PIGLIN, BannerPatternType::FLOW, BannerPatternType::GUSTER,
		BannerPatternType::BRICKS, BannerPatternType::CURLY_BORDER,
	];

	public function __construct(
		Player $source,
		private readonly BannerPatternType $patternType,
		private readonly LoomInventory $inventory
	){
		parent::__construct($source);
	}

	public static function craft(Item $banner, Item $dye, BannerPatternType $type, ?Item $pattern = null) : ?Banner{
		if($banner->isNull() || $dye->isNull() || !$banner instanceof Banner || !$dye instanceof Dye){
			return null;
		}
		if(in_array($type, self::ITEM_PATTERNS, true)){
			if(!$pattern instanceof BannerPattern || $pattern->isNull() || $pattern->getPatternType() !== $type){
				return null;
			}
		}elseif($pattern !== null && !$pattern->isNull()){
			return null;
		}
		$patterns = $banner->getPatterns();
		if(count($patterns) >= self::MAX_PATTERNS){
			return null;
		}
		$patterns[] = new BannerPatternLayer($type, $dye->getColor());
		$result = clone $banner;
		$result->setCount(1);
		return $result->setPatterns($patterns);
	}

	public function validate() : void{
		if($this->source->getCurrentWindow() !== $this->inventory){
			throw new TransactionValidationException("The loom is no longer open");
		}
		$this->squashDuplicateSlotChanges();
		if(count($this->actions) < 1){
			throw new TransactionValidationException("Transaction must have at least one action to be executable");
		}

		/** @var Item[] $inputs */
		$inputs = [];
		/** @var Item[] $outputs */
		$outputs = [];
		$this->matchItems($outputs, $inputs);

		$banner = null;
		$dye = null;
		foreach($inputs as $input){
			if($input instanceof Banner && $banner === null){
				$banner = $input;
			}elseif($input instanceof Dye && $dye === null){
				$dye = $input;
			}else{
				throw new TransactionValidationException("Unexpected loom input " . $input->getName());
			}
		}
		if($banner === null || $dye === null){
			throw new TransactionValidationException("A loom needs one banner and one dye");
		}
		if($banner->getCount() !== 1 || $dye->getCount() !== 1){
			throw new TransactionValidationException("A loom uses exactly one banner and one dye");
		}

		if(($outputCount = count($outputs)) !== 1){
			throw new TransactionValidationException("Expected 1 output item, but received $outputCount");
		}
		$pattern = $this->inventory->getItem(LoomInventory::SLOT_PATTERN);
		foreach($this->actions as $action){
			if($action instanceof SlotChangeAction && $action->getInventory() === $this->inventory && $action->getSlot() === LoomInventory::SLOT_PATTERN){
				$pattern = $action->getTargetItem();
			}
		}
		$expected = self::craft($banner, $dye, $this->patternType, $pattern);
		if($expected === null || !$outputs[0]->equalsExact($expected)){
			throw new TransactionValidationException("Invalid loom output");
		}
	}
}
