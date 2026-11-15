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

namespace pocketmine\inventory;

use PHPUnit\Framework\TestCase;
use pocketmine\block\inventory\LoomInventory;
use pocketmine\block\utils\BannerPatternType;
use pocketmine\block\utils\DyeColor;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\inventory\transaction\LoomTransaction;
use pocketmine\inventory\transaction\TransactionValidationException;
use pocketmine\item\Banner;
use pocketmine\item\BannerPattern;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\handler\ItemStackRequestExecutor;
use pocketmine\player\Player;
use pocketmine\world\Position;
use function in_array;

class LoomTestPlayer extends Player{
	public function __destruct(){}
}

class ExecutableLoomTransaction extends LoomTransaction{
	protected function callExecuteEvent() : bool{ return true; }
}

final class LoomTransactionTest extends TestCase{
	/** @return list<BannerPattern> */
	private function templates() : array{
		return [
			VanillaItems::BORDURE_INDENTED_BANNER_PATTERN(), VanillaItems::CREEPER_BANNER_PATTERN(),
			VanillaItems::FIELD_MASONED_BANNER_PATTERN(), VanillaItems::FLOW_BANNER_PATTERN(),
			VanillaItems::FLOWER_BANNER_PATTERN(), VanillaItems::GLOBE_BANNER_PATTERN(),
			VanillaItems::GUSTER_BANNER_PATTERN(), VanillaItems::MOJANG_BANNER_PATTERN(),
			VanillaItems::PIGLIN_BANNER_PATTERN(), VanillaItems::SKULL_BANNER_PATTERN()
		];
	}

	public function testAllPatternsRequireOnlyTheirMatchingTemplates() : void{
		$banner = VanillaItems::BANNER();
		$dye = VanillaItems::DYE()->setColor(DyeColor::RED);
		foreach(BannerPatternType::cases() as $type){
			$requiresTemplate = in_array($type, LoomTransaction::ITEM_PATTERNS, true);
			self::assertSame(!$requiresTemplate, LoomTransaction::craft($banner, $dye, $type) !== null, $type->name);
			foreach($this->templates() as $template){
				self::assertSame($type === $template->getPatternType(), LoomTransaction::craft($banner, $dye, $type, $template) !== null);
			}
		}
		self::assertNull(LoomTransaction::craft($banner, $dye, BannerPatternType::CROSS, VanillaItems::DIAMOND()));
		self::assertNull(LoomTransaction::craft($banner, $dye, BannerPatternType::CREEPER, VanillaItems::CREEPER_BANNER_PATTERN()->setCount(0)));
	}

	private function player(?LoomInventory $window) : Player{
		$player = $this->getMockBuilder(LoomTestPlayer::class)->disableOriginalConstructor()->onlyMethods(["getCurrentWindow"])->getMock();
		$player->method("getCurrentWindow")->willReturn($window);
		return $player;
	}

	private function window() : LoomInventory{
		$window = new LoomInventory(new Position(0, 0, 0, null));
		$window->setItem(LoomInventory::SLOT_BANNER, VanillaItems::BANNER()->setCount(3)->setCustomName("Named banner"));
		$window->setItem(LoomInventory::SLOT_DYE, VanillaItems::DYE()->setColor(DyeColor::RED)->setCount(4));
		return $window;
	}

	private function transaction(LoomInventory $window, Player $player, BannerPatternType $type, Banner $result, int $dyeCount = 1) : ExecutableLoomTransaction{
		$transaction = new ExecutableLoomTransaction($player, $type, $window);
		foreach([LoomInventory::SLOT_BANNER => 1, LoomInventory::SLOT_DYE => $dyeCount] as $slot => $count){
			$before = $window->getItem($slot);
			$transaction->addAction(new SlotChangeAction($window, $slot, $before, (clone $before)->setCount($before->getCount() - $count)));
		}
		$output = new SimpleInventory(1);
		$transaction->addAction(new SlotChangeAction($output, 0, VanillaItems::AIR(), $result));
		return $transaction;
	}

	public function testTemplatesRemainReusableAfterExecution() : void{
		foreach($this->templates() as $template){
			$window = $this->window();
			$window->setItem(LoomInventory::SLOT_PATTERN, $template);
			$player = $this->player($window);
			for($i = 0; $i < 2; ++$i){
				$result = LoomTransaction::craft($window->getItem(0), $window->getItem(1), $template->getPatternType(), $template);
				self::assertNotNull($result);
				self::assertSame("Named banner", $result->getCustomName());
				$this->transaction($window, $player, $template->getPatternType(), $result)->execute();
				self::assertTrue($window->getItem(2)->equalsExact($template));
			}
			self::assertSame(1, $window->getItem(0)->getCount());
			self::assertSame(2, $window->getItem(1)->getCount());
		}
	}

	public function testInvalidTransactionsLeaveInventoriesUntouched() : void{
		foreach(["missing", "wrong", "consumed", "forged", "duplicate", "extra dye", "closed"] as $case){
			$window = $this->window();
			$template = VanillaItems::CREEPER_BANNER_PATTERN();
			$window->setItem(2, $template);
			$result = LoomTransaction::craft($window->getItem(0), $window->getItem(1), $template->getPatternType(), $template);
			self::assertNotNull($result);
			if($case === "missing") $window->clear(2);
			if($case === "wrong") $window->setItem(2, VanillaItems::FLOWER_BANNER_PATTERN());
			if($case === "forged") $result->setCustomName("Forged");
			if($case === "duplicate") $result->setCount(2);
			$transaction = $this->transaction($window, $this->player($case === "closed" ? null : $window), $template->getPatternType(), $result, $case === "extra dye" ? 2 : 1);
			if($case === "consumed") $transaction->addAction(new SlotChangeAction($window, 2, $template, VanillaItems::AIR()));
			$before = $window->getContents();
			try{
				$transaction->validate();
				self::fail("Accepted invalid transaction: " . $case);
			}catch(TransactionValidationException){
				self::assertEquals($before, $window->getContents());
			}
		}
	}

	public function testStagedTemplateInsertionIsValidated() : void{
		$window = $this->window();
		$template = VanillaItems::CREEPER_BANNER_PATTERN();
		$storage = new SimpleInventory(1);
		$storage->setItem(0, $template);
		$result = LoomTransaction::craft($window->getItem(0), $window->getItem(1), $template->getPatternType(), $template);
		self::assertNotNull($result);
		$transaction = $this->transaction($window, $this->player($window), $template->getPatternType(), $result);
		$transaction->addAction(new SlotChangeAction($storage, 0, $template, VanillaItems::AIR()));
		$transaction->addAction(new SlotChangeAction($window, 2, VanillaItems::AIR(), $template));
		$transaction->execute();
		self::assertTrue($storage->getItem(0)->isNull());
		self::assertTrue($window->getItem(2)->equalsExact($template));
	}

	public function testPreviewReadsPendingLoomInputs() : void{
		$window = $this->window();
		$window->clearAll();
		$executor = (new \ReflectionClass(ItemStackRequestExecutor::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(ItemStackRequestExecutor::class, "player"))->setValue($executor, $this->player($window));
		$builder = new transaction\TransactionBuilder();
		(new \ReflectionProperty(ItemStackRequestExecutor::class, "builder"))->setValue($executor, $builder);
		$staged = $builder->getInventory($window);
		$staged->setItem(0, VanillaItems::BANNER());
		$staged->setItem(1, VanillaItems::DYE());
		$staged->setItem(2, VanillaItems::CREEPER_BANNER_PATTERN());
		(new \ReflectionMethod(ItemStackRequestExecutor::class, "beginLoom"))->invoke($executor, "cre");
		$result = (new \ReflectionProperty(ItemStackRequestExecutor::class, "nextCreatedItem"))->getValue($executor);
		self::assertInstanceOf(Banner::class, $result);
		self::assertSame(BannerPatternType::CREEPER, $result->getPatterns()[0]->getType());
		self::assertSame([], $window->getContents());
	}

	public function testSixLayerLimitAndMetadataPreservation() : void{
		$original = VanillaItems::BANNER()->setCustomName("Named banner");
		$banner = clone $original;
		for($i = 0; $i < 6; ++$i){
			$banner = LoomTransaction::craft($banner, VanillaItems::DYE(), BannerPatternType::CROSS);
			self::assertNotNull($banner);
		}
		self::assertCount(6, $banner->getPatterns());
		self::assertSame("Named banner", $banner->getCustomName());
		self::assertCount(0, $original->getPatterns());
		self::assertNull(LoomTransaction::craft($banner, VanillaItems::DYE(), BannerPatternType::CROSS));
	}
}
