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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use pocketmine\data\bedrock\block\BlockStateNames;
use pocketmine\entity\Entity;
use pocketmine\event\block\BlockCompostEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginManager;
use pocketmine\Server;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\sound\ComposterEmptySound;
use pocketmine\world\sound\ComposterFillSound;
use pocketmine\world\sound\ComposterReadySound;
use pocketmine\world\World;
use function mt_srand;

final class ComposterTest extends TestCase{
	private Block $stored;
	/** @var Item[] */
	private array $drops = [];
	/** @var int[] */
	private array $delays = [];

	protected function tearDown() : void{
		HandlerListManager::global()->unregisterAll();
	}

	private function world(int $level = 0) : World&MockObject{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods([
			"getBlock", "setBlock", "getTile", "getOrLoadChunkAtPosition",
			"scheduleDelayedBlockUpdate", "addSound", "addParticle", "dropItem", "getNearbyEntities"
		])->getMock();
		$this->stored = VanillaBlocks::COMPOSTER()->setFillLevel($level);
		$this->stored->position($world, 0, 64, 0);
		$world->method("getOrLoadChunkAtPosition")->willReturn(new Chunk([], false));
		$world->method("getBlock")->willReturnCallback(fn() => clone $this->stored);
		$world->method("setBlock")->willReturnCallback(function(Vector3 $pos, Block $block) : void{
			$this->stored = clone $block;
			$this->stored->writeStateToWorld();
		});
		$world->method("scheduleDelayedBlockUpdate")->willReturnCallback(function(Vector3 $pos, int $delay) : void{
			$this->delays[] = $delay;
		});
		$world->method("dropItem")->willReturnCallback(function(Vector3 $pos, Item $item) : null{
			$this->drops[] = clone $item;
			return null;
		});
		return $world;
	}

	private function interact(Item $item) : bool{
		return $this->stored->onInteract($item, Facing::UP, Vector3::zero());
	}

	private function composter() : Composter{
		self::assertInstanceOf(Composter::class, $this->stored);
		return $this->stored;
	}

	public function testNativeStatesAndEmptyItemRoundTrip() : void{
		for($level = 0; $level <= 8; ++$level){
			$block = VanillaBlocks::COMPOSTER()->setFillLevel($level);
			$data = GlobalBlockStateHandlers::getSerializer()->serializeBlock($block);
			self::assertSame($level, $data->getState(BlockStateNames::COMPOSTER_FILL_LEVEL)?->getValue());
			self::assertSame($block->getStateId(), GlobalBlockStateHandlers::getDeserializer()->deserialize($data));
			self::assertSame(VanillaBlocks::COMPOSTER()->getStateId(), $block->asItem()->getBlock()->getStateId());
			self::assertEquals($block, RuntimeBlockStateRegistry::getInstance()->fromStateId($block->getStateId()));
		}
	}

	public function testCompostChances() : void{
		foreach([
			[VanillaItems::WHEAT_SEEDS(), 0.3], [VanillaItems::WHEAT(), 0.65],
			[VanillaItems::MELON(), 0.5], [VanillaItems::BREAD(), 0.85],
			[VanillaItems::PUMPKIN_PIE(), 1.0], [VanillaBlocks::CAKE()->asItem(), 1.0],
			[VanillaBlocks::GRASS()->asItem(), 0.3], [VanillaBlocks::OAK_LEAVES()->asItem(), 0.3],
			[VanillaBlocks::FLOWERING_AZALEA_LEAVES()->asItem(), 0.5],
			[VanillaBlocks::CHERRY_SAPLING()->asItem(), 0.3], [VanillaBlocks::FERN()->asItem(), 0.65],
			[VanillaBlocks::MUSHROOM_STEM()->asItem(), 0.65], [VanillaBlocks::STRAW_BED()->asItem(), 0.65],
			[VanillaBlocks::TORCHFLOWER()->asItem(), 0.85], [VanillaBlocks::DANDELION()->asItem(), 0.65],
			[VanillaBlocks::GOLDEN_DANDELION()->asItem(), 0.0], [VanillaBlocks::BAMBOO()->asItem(), 0.0],
			[VanillaItems::POISONOUS_POTATO(), 0.0], [VanillaItems::GOLDEN_APPLE(), 0.0],
			[VanillaItems::BONE_MEAL(), 0.0], [VanillaItems::AIR(), 0.0]
		] as [$item, $chance]){
			self::assertSame($chance, $item->getCompostChance(), $item->getName());
		}
		foreach([...VanillaItems::getAll(), ...VanillaBlocks::getAll()] as $entry){
			$item = $entry instanceof Block ? $entry->asItem() : $entry;
			self::assertGreaterThanOrEqual(0, $item->getCompostChance(), $item->getName());
			self::assertLessThanOrEqual(1, $item->getCompostChance(), $item->getName());
		}
	}

	public function testFirstLayerGuaranteedAndFailedAttemptConsumesOne() : void{
		$world = $this->world();
		$world->expects(self::exactly(10))->method("addParticle");
		$seeds = VanillaItems::WHEAT_SEEDS()->setCount(3);
		mt_srand(0);
		self::assertTrue($this->interact($seeds));
		self::assertSame(1, $this->composter()->getFillLevel());
		self::assertSame(2, $seeds->getCount());
		mt_srand(0);
		self::assertTrue($this->interact($seeds));
		self::assertSame(1, $this->composter()->getFillLevel());
		self::assertSame(1, $seeds->getCount());
		self::assertFalse($this->interact(VanillaItems::DIAMOND()));
	}

	public function testFullCycleAndBreakDrops() : void{
		$this->world();
		$food = VanillaItems::PUMPKIN_PIE()->setCount(9);
		for($level = 1; $level <= 7; ++$level){
			self::assertTrue($this->interact($food));
			self::assertSame($level, $this->composter()->getFillLevel());
		}
		self::assertSame([20], $this->delays);
		self::assertCount(1, $this->stored->getDrops(VanillaItems::AIR()));
		self::assertTrue($this->interact($food));
		self::assertSame(2, $food->getCount());
		self::assertSame([], $this->drops);
		$this->stored->onScheduledUpdate();
		self::assertSame(8, $this->composter()->getFillLevel());
		$drops = $this->stored->getDrops(VanillaItems::AIR());
		self::assertCount(2, $drops);
		self::assertTrue($drops[0]->equals(VanillaBlocks::COMPOSTER()->asItem()));
		self::assertTrue($drops[1]->equals(VanillaItems::BONE_MEAL()));
		$stale = clone $this->stored;
		self::assertTrue($this->interact(VanillaItems::AIR()));
		self::assertSame(0, $this->composter()->getFillLevel());
		self::assertCount(1, $this->drops);
		self::assertTrue($this->drops[0]->equals(VanillaItems::BONE_MEAL()));
		self::assertTrue($stale->onInteract(VanillaItems::AIR(), Facing::UP, Vector3::zero()));
		self::assertCount(1, $this->drops);
		self::assertFalse($this->interact(VanillaItems::AIR()));
		self::assertCount(1, $this->drops);
	}

	public function testLoadedFullComposterResumesAndOtherLevelsDoNotMature() : void{
		$this->world(7);
		$this->stored->readStateFromWorld();
		self::assertSame([20], $this->delays);
		for($level = 0; $level <= 8; ++$level){
			if($level === 7){
				continue;
			}
			$this->stored = VanillaBlocks::COMPOSTER()->setFillLevel($level);
			$this->stored->onScheduledUpdate();
			self::assertSame($level, $this->composter()->getFillLevel());
		}
	}

	public function testCancelledOrReplacedComposterDoesNotConsumeItem() : void{
		$world = $this->world();
		$world->expects(self::never())->method("addSound");
		$plugin = self::createStub(Plugin::class);
		$plugin->method("isEnabled")->willReturn(true);
		$manager = new PluginManager($this->createMock(Server::class), null);
		$manager->registerEvent(BlockCompostEvent::class, function(BlockCompostEvent $event) : void{
			self::assertTrue($event->isSuccessful());
			self::assertNull($event->getPlayer());
			$event->getItem()->setCount(0);
			$event->cancel();
		}, EventPriority::NORMAL, $plugin);
		$item = VanillaItems::WHEAT_SEEDS()->setCount(2);
		self::assertTrue($this->interact($item));
		self::assertSame(0, $this->composter()->getFillLevel());
		self::assertSame(2, $item->getCount());
		HandlerListManager::global()->unregisterAll();
		$manager->registerEvent(BlockCompostEvent::class, function(BlockCompostEvent $event) : void{
			$this->stored = VanillaBlocks::STONE();
		}, EventPriority::NORMAL, $plugin);
		self::assertTrue($this->interact($item));
		self::assertSame(VanillaBlocks::STONE()->getTypeId(), $this->stored->getTypeId());
		self::assertSame(2, $item->getCount());
	}

	public function testRisingFloorPushesEntitiesAndCollisionCacheUpdates() : void{
		$world = $this->world();
		self::assertSame(64.125, $this->stored->getCollisionBoxes()[0]->maxY);
		$entity = $this->getMockBuilder(Entity::class)->disableOriginalConstructor()->onlyMethods([
			"pushUp", "getBoundingBox", "__destruct", "getInitialSizeInfo", "getNetworkTypeId",
			"getInitialDragMultiplier", "getInitialGravity"
		])->getMock();
		(new \ReflectionProperty(Entity::class, "closed"))->setValue($entity, true);
		$entity->method("getBoundingBox")->willReturn(new AxisAlignedBB(0.2, 64.125, 0.2, 0.8, 65.925, 0.8));
		$entity->expects(self::once())->method("pushUp")->with(0.0625);
		$world->method("getNearbyEntities")->willReturn([$entity]);
		$this->interact(VanillaItems::WHEAT_SEEDS());
		self::assertSame(64.1875, $this->stored->getCollisionBoxes()[0]->maxY);
		$this->composter()->setFillLevel(7);
		self::assertSame(64.9375, $this->stored->getCollisionBoxes()[0]->maxY);
	}

	public function testDedicatedSounds() : void{
		foreach([
			[new ComposterFillSound(false), LevelSoundEvent::BLOCK_COMPOSTER_FILL],
			[new ComposterFillSound(true), LevelSoundEvent::BLOCK_COMPOSTER_FILL_SUCCESS],
			[new ComposterReadySound(), LevelSoundEvent::BLOCK_COMPOSTER_READY],
			[new ComposterEmptySound(), LevelSoundEvent::BLOCK_COMPOSTER_EMPTY]
		] as [$sound, $id]){
			$packet = $sound->encode(Vector3::zero())[0];
			self::assertInstanceOf(LevelSoundEventPacket::class, $packet);
			self::assertSame($id, $packet->sound);
		}
	}

	public function testRisingFloorDoesNotMoveSpectators() : void{
		$player = $this->getMockBuilder(Player::class)->disableOriginalConstructor()->onlyMethods([
			"isSpectator", "move", "sendPosition"
		])->getMock();
		(new \ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new \ReflectionProperty(Player::class, "logger"))->setValue($player, self::createStub(\Logger::class));
		$player->method("isSpectator")->willReturn(true);
		$player->expects(self::never())->method("move");
		$player->expects(self::never())->method("sendPosition");
		$player->pushUp(0.125);
	}
}
