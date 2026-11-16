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

namespace pocketmine\player;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use pocketmine\entity\Attribute;
use pocketmine\entity\AttributeFactory;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\Consumable;
use pocketmine\entity\effect\EffectManager;
use pocketmine\entity\Entity;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\RegisteredListener;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\item\Apple;
use pocketmine\item\ConsumableItem;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\ItemUseResult;
use pocketmine\item\Releasable;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\handler\InGamePacketHandler;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\timings\TimingsHandler;
use function min;

class ItemConsumptionPlayer extends Player{
	public function __destruct(){}
}

final class ItemConsumptionTest extends TestCase{
	private int $tick = 100;
	private int $consumptions = 0;
	private int $events = 0;
	private bool $cooldown = false;
	private bool $canConsume = true;
	private bool $canEat = true;
	/** @var list<RegisteredListener<PlayerItemConsumeEvent>> */
	private array $listeners = [];

	protected function tearDown() : void{
		$list = HandlerListManager::global()->getListFor(PlayerItemConsumeEvent::class);
		foreach($this->listeners as $listener) $list->unregister($listener);
		parent::tearDown();
	}

	private function consumptionCount() : int{
		return $this->consumptions;
	}

	private function eventCount() : int{
		return $this->events;
	}

	private function player(Item $held) : Player{
		$server = self::createStub(Server::class);
		$server->method("getTick")->willReturnCallback(fn() => $this->tick);
		$player = $this->getMockBuilder(ItemConsumptionPlayer::class)->disableOriginalConstructor()->onlyMethods([
			"__destruct", "consumeObject", "hasFiniteResources", "hasItemCooldown", "resetItemCooldown",
			"isSpectator", "canEat", "getDirectionVector", "getEffects"
		])->getMock();
		$player->method("consumeObject")->willReturnCallback(function(Consumable $item) : bool{
			++$this->consumptions;
			return $this->canConsume;
		});
		$player->method("hasFiniteResources")->willReturn(true);
		$player->method("hasItemCooldown")->willReturnCallback(fn() => $this->cooldown);
		$player->method("isSpectator")->willReturn(false);
		$player->method("canEat")->willReturnCallback(fn() => $this->canEat);
		$effects = self::createStub(EffectManager::class);
		$effects->method("has")->willReturn(true);
		$player->method("getEffects")->willReturn($effects);
		$player->method("getDirectionVector")->willReturn(new Vector3(0, 0, 1));
		(new \ReflectionProperty(Player::class, "server"))->setValue($player, $server);
		(new \ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		$inventory = new PlayerInventory($player);
		(new \ReflectionProperty(Player::class, "inventory"))->setValue($player, $inventory);
		(new \ReflectionProperty(Player::class, "offHandInventory"))->setValue($player, new PlayerOffHandInventory($player));
		$inventory->setItemInHand($held);
		// Production installs these cancellation hooks during entity initialization.
		$inventory->getListeners()->add(new CallbackInventoryListener(
			static function(Inventory $inventory, int $slot) use ($player) : void{
				if($slot === $player->getInventory()->getHeldItemIndex()) $player->setUsingItem(false);
			},
			static function() use ($player) : void{ $player->setUsingItem(false); }
		));
		$attributes = new AttributeMap();
		$hunger = AttributeFactory::getInstance()->mustGet(Attribute::HUNGER);
		$hunger->markSynchronized();
		$attributes->add($hunger);
		(new \ReflectionProperty(Entity::class, "attributeMap"))->setValue($player, $attributes);
		return $player;
	}

	/** @param \Closure(PlayerItemConsumeEvent): void $callback */
	private function onConsume(\Closure $callback) : void{
		$plugin = self::createStub(Plugin::class);
		$plugin->method("isEnabled")->willReturn(true);
		$listener = new RegisteredListener($callback, EventPriority::NORMAL, $plugin, false, new TimingsHandler("item-consumption-test"));
		$this->listeners[] = $listener;
		HandlerListManager::global()->getListFor(PlayerItemConsumeEvent::class)->register($listener);
	}

	private function clickAir(Player $player) : void{
		$handler = (new \ReflectionClass(InGamePacketHandler::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(InGamePacketHandler::class, "player"))->setValue($handler, $player);
		$data = new UseItemTransactionData();
		(new \ReflectionProperty(UseItemTransactionData::class, "actionType"))->setValue($data, UseItemTransactionData::ACTION_CLICK_AIR);
		(new \ReflectionProperty(UseItemTransactionData::class, "hotbarSlot"))->setValue($data, $player->getInventory()->getHeldItemIndex());
		self::assertTrue((new \ReflectionMethod(InGamePacketHandler::class, "handleUseItemTransaction"))->invoke($handler, $data));
	}

	/** @return iterable<string, array{\Closure(): Item, int}> */
	public static function consumables() : iterable{
		yield "food" => [static fn() => VanillaItems::APPLE(), 32];
		yield "fast food" => [static fn() => VanillaItems::DRIED_KELP(), 16];
		yield "honey" => [static fn() => VanillaItems::HONEY_BOTTLE(), 40];
		yield "milk" => [static fn() => VanillaItems::MILK_BUCKET(), 32];
		yield "potion" => [static fn() => VanillaItems::POTION(), 32];
		yield "medicine" => [static fn() => VanillaItems::MEDICINE(), 32];
	}

	/** @param \Closure(): Item $create */
	#[DataProvider("consumables")]
	public function testConsumptionWaitsForItemDuration(\Closure $create, int $duration) : void{
		$item = $create();
		self::assertInstanceOf(ConsumableItem::class, $item);
		self::assertSame($duration, $item->getConsumptionDurationTicks());
		$count = min(2, $item->getMaxStackSize());
		$player = $this->player($item->setCount($count));
		$this->onConsume(function() : void{ ++$this->events; });
		self::assertFalse($player->consumeHeldItem(), "No active use must not bypass duration validation");
		self::assertTrue($player->useHeldItem());
		$this->tick += $duration - 1;
		self::assertFalse($player->consumeHeldItem());
		self::assertTrue($player->isUsingItem());
		self::assertSame(0, $this->eventCount());
		self::assertSame(0, $this->consumptionCount());
		self::assertSame($count, $player->getInventory()->getItemInHand()->getCount());
		++$this->tick;
		self::assertTrue($player->consumeHeldItem());
		self::assertFalse($player->isUsingItem());
		self::assertSame(1, $this->eventCount());
		self::assertSame(1, $this->consumptionCount());
		$remaining = 0;
		foreach($player->getInventory()->getContents() as $stack){
			if($stack->getTypeId() === $item->getTypeId()) $remaining += $stack->getCount();
		}
		self::assertSame($count - 1, $remaining);
		self::assertFalse($player->consumeHeldItem(), "Duplicate completion must not consume the next item");
		self::assertSame(1, $this->consumptionCount());
	}

	public function testRepeatedNetworkClicksDoNotRestartOrFinishEatingEarly() : void{
		$player = $this->player(VanillaItems::APPLE()->setCount(3));
		$this->clickAir($player);
		foreach([106, 112, 118, 124, 130] as $tick){
			$this->tick = $tick;
			$this->clickAir($player);
			self::assertTrue($player->isUsingItem());
			self::assertSame($tick - 100, $player->getItemUseDuration());
			self::assertSame(0, $this->consumptionCount());
			self::assertFalse($player->getAttributeMap()->get(Attribute::HUNGER)?->isDesynchronized());
		}
		$this->tick = 132;
		$this->clickAir($player);
		self::assertSame(1, $this->consumptionCount());
		self::assertSame(2, $player->getInventory()->getItemInHand()->getCount());
		$this->clickAir($player);
		self::assertSame(0, $player->getItemUseDuration());
		$this->clickAir($player);
		self::assertSame(1, $this->consumptionCount());
	}

	public function testStartNotificationsPreserveTheTimerAndStopCancelsIt() : void{
		$player = $this->player(VanillaItems::APPLE());
		$player->useHeldItem();
		$handler = (new \ReflectionClass(InGamePacketHandler::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(InGamePacketHandler::class, "player"))->setValue($handler, $player);
		$method = new \ReflectionMethod(InGamePacketHandler::class, "handlePlayerActionFromData");
		foreach([112, 124] as $tick){
			$this->tick = $tick;
			self::assertTrue($method->invoke($handler, PlayerAction::START_ITEM_USE_ON, new BlockPosition(0, 0, 0), 0));
			self::assertTrue($player->isUsingItem());
			self::assertSame($this->tick - 100, $player->getItemUseDuration());
		}
		$this->tick = 132;
		self::assertTrue($player->consumeHeldItem());
		$player->getInventory()->setItemInHand(VanillaItems::APPLE());
		$player->useHeldItem();
		$this->tick += 12;
		self::assertTrue($method->invoke($handler, PlayerAction::STOP_ITEM_USE_ON, new BlockPosition(0, 0, 0), 0));
		self::assertFalse($player->isUsingItem());
		$player->useHeldItem();
		$this->tick += 31;
		self::assertFalse($player->consumeHeldItem());
	}

	public function testRearmingPreservesBowAndFoodUseTime() : void{
		foreach([VanillaItems::BOW(), VanillaItems::APPLE()] as $item){
			$player = $this->player($item);
			$player->getInventory()->setItem(1, VanillaItems::ARROW());
			self::assertTrue($player->useHeldItem());
			$this->tick += 12;
			self::assertTrue($player->useHeldItem());
			self::assertSame(12, $player->getItemUseDuration());
			$this->tick += 8;
			self::assertSame(20, $player->getItemUseDuration());
		}
	}

	public function testCancelledConsumptionAppliesNothingAndStopsUse() : void{
		$player = $this->player(VanillaItems::APPLE()->setCount(3));
		$this->onConsume(static function(PlayerItemConsumeEvent $event) : void{ $event->cancel(); });
		$this->clickAir($player);
		$this->tick += 32;
		$this->clickAir($player);
		self::assertSame(0, $this->consumptionCount());
		self::assertSame(3, $player->getInventory()->getItemInHand()->getCount());
		self::assertFalse($player->isUsingItem());
	}

	public function testChangedHeldItemDuringEventIsNotConsumed() : void{
		$player = $this->player(VanillaItems::APPLE());
		$this->onConsume(static function(PlayerItemConsumeEvent $event) use ($player) : void{
			$player->getInventory()->setItemInHand(VanillaItems::DIAMOND());
		});
		$player->useHeldItem();
		$this->tick += 32;
		self::assertFalse($player->consumeHeldItem());
		self::assertSame(0, $this->consumptionCount());
		self::assertSame(VanillaItems::DIAMOND()->getTypeId(), $player->getInventory()->getItemInHand()->getTypeId());
		self::assertFalse($player->isUsingItem());
	}

	public function testItemSwitchAndReleaseCannotCarryUseTime() : void{
		$player = $this->player(VanillaItems::APPLE()->setCount(3));
		$player->getInventory()->setItem(1, VanillaItems::DRIED_KELP());
		$player->useHeldItem();
		$this->tick += 30;
		self::assertTrue($player->selectHotbarSlot(1));
		self::assertSame(-1, $player->getItemUseDuration());
		$player->useHeldItem();
		self::assertFalse($player->consumeHeldItem());
		$this->tick += 10;
		$player->releaseHeldItem();
		self::assertFalse($player->isUsingItem());
		$player->useHeldItem();
		$this->tick += 15;
		self::assertFalse($player->consumeHeldItem());
		++$this->tick;
		self::assertTrue($player->consumeHeldItem());
	}

	public function testEarlyFoodAttemptDoesNotLeaveOtherItemsStuckUsing() : void{
		$player = $this->player(VanillaItems::APPLE());
		$player->useHeldItem();
		$this->tick += 6;
		self::assertFalse($player->consumeHeldItem());
		$player->getInventory()->setItemInHand(VanillaItems::GOAT_HORN());
		// Do not play the horn in the headless world; begin its timed action directly.
		$player->setUsingItem(true);
		$this->clickAir($player);
		self::assertFalse($player->isUsingItem());
		self::assertSame(0, $this->consumptionCount());
	}

	public function testPluginCanSetAnItemSpecificDuration() : void{
		$item = new class(new ItemIdentifier(ItemTypeIds::APPLE), "Quick Apple") extends Apple{
			public function getConsumptionDurationTicks() : int{ return 8; }
		};
		$player = $this->player($item);
		$player->useHeldItem();
		$this->tick += 7;
		self::assertFalse($player->consumeHeldItem());
		++$this->tick;
		self::assertTrue($player->consumeHeldItem());
	}

	public function testPluginResidueIsReturnedOnce() : void{
		$player = $this->player(VanillaItems::APPLE());
		$this->onConsume(static function(PlayerItemConsumeEvent $event) : void{ $event->setResidue([VanillaItems::DIAMOND()]); });
		$player->useHeldItem();
		$this->tick += 32;
		self::assertTrue($player->consumeHeldItem());
		self::assertTrue($player->getInventory()->contains(VanillaItems::DIAMOND()));
		self::assertFalse($player->consumeHeldItem());
		self::assertSame(1, $this->consumptionCount());
	}

	public function testRejectionAndExceptionsStopTheAction() : void{
		$player = $this->player(VanillaItems::APPLE());
		$player->useHeldItem();
		$this->tick += 32;
		$this->canConsume = false;
		self::assertFalse($player->consumeHeldItem());
		self::assertFalse($player->isUsingItem());
		self::assertSame(1, $player->getInventory()->getItemInHand()->getCount());
		$this->canConsume = true;
		$player->useHeldItem();
		$this->tick += 32;
		$this->cooldown = true;
		self::assertFalse($player->consumeHeldItem());
		self::assertFalse($player->isUsingItem());
		self::assertSame(1, $this->consumptionCount());
		$this->cooldown = false;
		$this->onConsume(static function() : void{ throw new \RuntimeException("listener failure"); });
		$player->useHeldItem();
		$this->tick += 32;
		try{
			$player->consumeHeldItem();
			self::fail("Expected the listener exception");
		}catch(\RuntimeException $e){
			self::assertSame("listener failure", $e->getMessage());
		}
		self::assertFalse($player->isUsingItem());
		self::assertSame(1, $player->getInventory()->getItemInHand()->getCount());
	}

	public function testReleasableMinUseDurationEnforced() : void{
		$released = false;
		$releasable = new class(new ItemIdentifier(ItemTypeIds::BOW), "Test Releasable", $released) extends Item implements Releasable{
			public function __construct(ItemIdentifier $ident, string $name, private bool &$releasedRef){
				parent::__construct($ident, $name);
			}

			public function canStartUsingItem(Player $player) : bool{ return true; }

			public function getMinUseDuration() : int{ return 10; }

			public function onReleaseUsing(Player $player, array &$returnedItems) : ItemUseResult{
				$this->releasedRef = true;
				return ItemUseResult::SUCCESS;
			}
		};

		$player = $this->player($releasable);
		self::assertTrue($player->useHeldItem());
		$this->tick += 9;
		self::assertFalse($player->releaseHeldItem());
		self::assertFalse($released, "Must not release before min use duration");
		self::assertFalse($player->isUsingItem());

		// Start using again and wait until duration is met
		self::assertTrue($player->useHeldItem());
		$this->tick += 10;
		self::assertTrue($player->releaseHeldItem());
		self::assertTrue($released, "Must release once min use duration is met");
		self::assertFalse($player->isUsingItem());
	}

	public function testBowAndTridentMinUseDurationValues() : void{
		self::assertSame(5, VanillaItems::BOW()->getMinUseDuration());
		self::assertSame(14, VanillaItems::TRIDENT()->getMinUseDuration());
		self::assertSame(0, VanillaItems::SPYGLASS()->getMinUseDuration());
		self::assertSame(0, VanillaItems::GOAT_HORN()->getMinUseDuration());

		$player = $this->player(VanillaItems::BOW());
		$player->getInventory()->setItem(1, VanillaItems::ARROW());
		self::assertTrue($player->useHeldItem());
		$this->tick += 4;
		// Bow release before 5 ticks must be rejected before reaching onReleaseUsing
		self::assertFalse($player->releaseHeldItem());
		self::assertFalse($player->isUsingItem());

		$tridentPlayer = $this->player(VanillaItems::TRIDENT());
		self::assertTrue($tridentPlayer->useHeldItem());
		$this->tick += 13;
		// Trident release before 14 ticks must be rejected before reaching onReleaseUsing
		self::assertFalse($tridentPlayer->releaseHeldItem());
		self::assertFalse($tridentPlayer->isUsingItem());
	}

	public function testGoldenAppleCanBeConsumedAtFullHungerWithoutDesync() : void{
		$this->canEat = false; // Player is completely full, cannot eat regular food
		$regularFood = VanillaItems::APPLE();
		$playerRegular = $this->player($regularFood);
		$playerRegular->useHeldItem();
		self::assertFalse($playerRegular->isUsingItem(), "Regular food cannot start using item when full");

		$gapple = VanillaItems::GOLDEN_APPLE();
		$player = $this->player($gapple);
		$player->useHeldItem();
		self::assertTrue($player->isUsingItem(), "Golden Apple can start using item even when full");

		// Simulate client sending periodic CLICK_AIR packets during eating
		foreach([106, 112, 118, 124, 130] as $tick){
			$this->tick = $tick;
			$this->clickAir($player);
			self::assertTrue($player->isUsingItem());
			self::assertSame($tick - 100, $player->getItemUseDuration());
			self::assertSame(0, $this->consumptionCount());
			self::assertFalse($player->getAttributeMap()->get(Attribute::HUNGER)?->isDesynchronized(), "Hunger must not desync during eating");
		}

		$this->tick = 132;
		$this->clickAir($player);
		self::assertSame(1, $this->consumptionCount());
		self::assertFalse($player->isUsingItem());
	}
}
