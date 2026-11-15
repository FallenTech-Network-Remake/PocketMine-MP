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

namespace pocketmine\world;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\tile\Chest;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\cache\ChunkCache;
use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\player\ChunkSelector;
use pocketmine\player\Player;
use pocketmine\player\UsedChunkStatus;
use pocketmine\Server;
use pocketmine\timings\Timings;
use pocketmine\utils\ReversePriorityQueue;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\ChunkData;
use pocketmine\world\format\io\LoadedChunkData;
use pocketmine\world\format\io\WritableWorldProvider;
use pocketmine\world\format\SubChunk;
use function array_map;
use function count;

final class RenderedChunkTest extends TestCase{
	/** @var array<int, ChunkData> */
	private array $saved = [];

	protected function setUp() : void{
		Timings::init();
		\GlobalLogger::set(self::createStub(\Logger::class));
	}

	protected function tearDown() : void{
		(new \ReflectionProperty(ChunkCache::class, "instances"))->setValue(null, []);
	}

	private function world(int $tickRadius = 2) : World&MockObject{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods([
			"getFolderName", "isSpawnChunk"
		])->getMock();
		$world->method("getFolderName")->willReturn("chunk-memory-test");
		$world->method("isSpawnChunk")->willReturn(false);
		$world->timings = new WorldTimings($world);
		$provider = self::createStub(WritableWorldProvider::class);
		$provider->method("saveChunk")->willReturnCallback(function(int $x, int $z, ChunkData $data) : void{
			$this->saved[World::chunkHash($x, $z)] = new ChunkData(
				array_map(static fn(SubChunk $subChunk) => clone $subChunk, $data->getSubChunks()),
				$data->isPopulated(), $data->getEntityNBT(), $data->getTileNBT()
			);
		});
		$provider->method("loadChunk")->willReturnCallback(function(int $x, int $z) : ?LoadedChunkData{
			$data = $this->saved[World::chunkHash($x, $z)] ?? null;
			return $data !== null ? new LoadedChunkData($data, false, LoadedChunkData::FIXER_FLAG_NONE) : null;
		});
		foreach([
			"provider" => $provider, "logger" => self::createStub(\Logger::class), "displayName" => "chunk-memory-test",
			"chunkTickRadius" => $tickRadius, "minY" => World::Y_MIN, "maxY" => World::Y_MAX,
			"blockStateRegistry" => RuntimeBlockStateRegistry::getInstance()
		] as $name => $value){
			(new \ReflectionProperty(World::class, $name))->setValue($world, $value);
		}
		$queue = new ReversePriorityQueue();
		$queue->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);
		foreach([
			"time" => 0, "server" => $this->createMock(Server::class),
			"scheduledBlockUpdateQueue" => $queue, "neighbourBlockUpdateQueue" => new \SplQueue()
		] as $name => $value){
			(new \ReflectionProperty(World::class, $name))->setValue($world, $value);
		}
		return $world;
	}

	private function player(World $world, int $viewRadius = 12, ?NetworkSession $session = null) : Player&MockObject{
		$player = $this->getMockBuilder(Player::class)->disableOriginalConstructor()->onlyMethods([
			"isConnected", "getNetworkSession", "spawnEntitiesOnChunk"
		])->getMock();
		$player->method("isConnected")->willReturn(true);
		$player->method("getNetworkSession")->willReturn($session ?? self::createStub(NetworkSession::class));
		$server = $this->createMock(Server::class);
		$server->method("getAllowedViewDistance")->willReturnCallback(static fn(int $radius) => $radius);
		foreach([
			"closed" => true, "location" => new Location(0, 64, 0, $world, 0, 0), "server" => $server
		] as $name => $value){
			(new \ReflectionProperty(Entity::class, $name))->setValue($player, $value);
		}
		foreach([
			"logger" => self::createStub(\Logger::class), "viewDistance" => $viewRadius,
			"chunkSelector" => new ChunkSelector(), "chunkLoader" => new class implements ChunkLoader{},
			"chunkTicker" => new ChunkTicker()
		] as $name => $value){
			(new \ReflectionProperty(Player::class, $name))->setValue($player, $value);
		}
		return $player;
	}

	/** @param array<int, UsedChunkStatus> $chunks */
	private function sentChunks(Player $player, array $chunks) : void{
		(new \ReflectionProperty(Player::class, "usedChunks"))->setValue($player, $chunks);
		$loader = (new \ReflectionProperty(Player::class, "chunkLoader"))->getValue($player);
		self::assertInstanceOf(ChunkLoader::class, $loader);
		foreach($chunks as $hash => $status){
			World::getXZ($hash, $x, $z);
			$player->getWorld()->registerChunkLoader($loader, $x, $z, false);
			$player->getWorld()->registerChunkListener($player, $x, $z);
		}
	}

	private function cache(World $world, int $x, int $z, string $packet) : ChunkCache{
		$cache = ChunkCache::getInstance($world, self::createStub(Compressor::class));
		(new \ReflectionProperty(ChunkCache::class, "caches"))->setValue($cache, [World::chunkHash($x, $z) => $packet]);
		$world->registerChunkListener($cache, $x, $z);
		return $cache;
	}

	public function testRenderedTerrainCanUnloadAndReloadWithoutResending() : void{
		$world = $this->world();
		$player = $this->player($world);
		$used = [];
		$chunks = [];
		foreach((new ChunkSelector())->selectChunks(12, 0, 0) as $hash){
			$used[$hash] = UsedChunkStatus::SENT;
			$chunks[$hash] = new Chunk([], true);
		}
		$farHash = World::chunkHash(8, 0);
		$chunks[$farHash]->setBlockStateId(1, 64, 1, VanillaBlocks::DIAMOND()->getStateId());
		(new \ReflectionProperty(World::class, "chunks"))->setValue($world, $chunks);
		$this->sentChunks($player, $used);
		$cache = $this->cache($world, 8, 0, "cached far terrain");
		(new \ReflectionMethod(Player::class, "orderChunks"))->invoke($player);
		self::assertFalse($world->isChunkInUse(8, 0));
		self::assertTrue($world->isChunkInUse(0, 0));
		$world->unloadChunks(true);
		self::assertNull($world->getChunk(8, 0));
		self::assertSame(UsedChunkStatus::SENT, $player->getUsedChunkStatus(8, 0));
		self::assertSame("cached far terrain", $cache->request(8, 0));
		self::assertLessThan(count($chunks) / 3, count($world->getLoadedChunks()));
		self::assertSame(VanillaBlocks::DIAMOND()->getStateId(), $this->saved[$farHash]->getSubChunks()[4]->getBlockStateId(1, 0, 1));
		$player->expects(self::atLeastOnce())->method("spawnEntitiesOnChunk");
		(new \ReflectionProperty(Entity::class, "location"))->setValue($player, new Location(128, 64, 0, $world, 0, 0));
		(new \ReflectionMethod(Player::class, "orderChunks"))->invoke($player);
		$restored = $world->getChunk(8, 0);
		self::assertNotNull($restored);
		self::assertSame(VanillaBlocks::DIAMOND()->getStateId(), $restored->getBlockStateId(1, 64, 1));
		self::assertSame(UsedChunkStatus::SENT, $player->getUsedChunkStatus(8, 0));
		self::assertTrue($world->isChunkInUse(8, 0));
		$world->unloadChunks(true);
		self::assertNull($world->getChunk(0, 0));
		self::assertSame(UsedChunkStatus::SENT, $player->getUsedChunkStatus(0, 0));
	}

	public function testTickersProtectTheirNeighboursAndReleaseThemAfterwards() : void{
		$world = $this->world();
		$first = new ChunkTicker();
		$second = new ChunkTicker();
		$world->registerTickingChunk($first, -8, 4);
		$world->registerTickingChunk($second, -8, 4);
		for($x = -9; $x <= -7; ++$x){
			for($z = 3; $z <= 5; ++$z){
				self::assertTrue($world->isChunkInUse($x, $z));
				self::assertFalse($world->unloadChunkRequest($x, $z));
			}
		}
		self::assertFalse($world->isChunkInUse(-6, 4));
		$world->unregisterTickingChunk($first, -8, 4);
		self::assertTrue($world->isChunkInUse(-8, 4));
		$world->unregisterTickingChunk($second, -8, 4);
		self::assertFalse($world->isChunkInUse(-8, 4));
		$queue = (new \ReflectionProperty(World::class, "unloadQueue"))->getValue($world);
		self::assertIsArray($queue);
		self::assertCount(9, $queue);
	}

	public function testAllCompressionCachesSurviveUntilLastViewerLeaves() : void{
		$world = $this->world();
		$first = $this->player($world);
		$second = $this->player($world);
		$hash = World::chunkHash(8, 0);
		$this->sentChunks($first, [$hash => UsedChunkStatus::SENT]);
		$this->sentChunks($second, [$hash => UsedChunkStatus::SENT]);
		$a = $this->cache($world, 8, 0, "format one");
		$b = $this->cache($world, 8, 0, "format two");
		$a->onChunkUnloaded(8, 0, new Chunk([], true));
		$b->onChunkUnloaded(8, 0, new Chunk([], true));
		$world->unregisterChunkListener($first, 8, 0);
		ChunkCache::releaseUnusedChunk($world, 8, 0);
		self::assertSame("format one", $a->request(8, 0));
		self::assertSame("format two", $b->request(8, 0));
		$world->unregisterChunkListener($second, 8, 0);
		ChunkCache::releaseUnusedChunk($world, 8, 0);
		self::assertSame(0, $a->calculateCacheSize());
		self::assertSame(0, $b->calculateCacheSize());
		self::assertSame([], $world->getChunkListeners(8, 0));
	}

	public function testViewedChunksWithAutosaveDisabledAreNotEvicted() : void{
		$world = $this->world();
		$world->setAutoSave(false);
		$viewer = $this->player($world);
		$world->registerChunkListener($viewer, 8, 0);
		self::assertFalse($world->unloadChunk(8, 0));
	}

	public function testPluginLoadersStillPinTerrain() : void{
		$world = $this->world();
		$loader = new class implements ChunkLoader{};
		$world->registerChunkLoader($loader, 8, 0, false);
		self::assertFalse($world->unloadChunk(8, 0));
		$world->unregisterChunkLoader($loader, 8, 0);
		self::assertFalse($world->isChunkInUse(8, 0));
	}

	public function testPendingBlockUpdatesFinishBeforeViewedTerrainIsEvicted() : void{
		$world = $this->world(0);
		$viewer = $this->player($world);
		$world->registerChunkListener($viewer, 8, 0);
		(new \ReflectionProperty(World::class, "chunks"))->setValue($world, [World::chunkHash(8, 0) => new Chunk([], true)]);
		$pos = new Vector3(128, 64, 0);
		$world->scheduleDelayedBlockUpdate($pos, 20);
		$world->scheduleDelayedBlockUpdate($pos, 20);
		$world->scheduleDelayedBlockUpdate($pos, 10);
		self::assertFalse($world->unloadChunk(8, 0));
		$world->doTick(10);
		self::assertFalse($world->unloadChunk(8, 0));
		$world->doTick(20);
		self::assertTrue($world->unloadChunk(8, 0));
	}

	public function testChestContentsSurviveEvictionAndReload() : void{
		$world = $this->world();
		$viewer = $this->player($world);
		$hash = World::chunkHash(8, 0);
		(new \ReflectionProperty(Player::class, "usedChunks"))->setValue($viewer, [$hash => UsedChunkStatus::SENT]);
		$world->registerChunkListener($viewer, 8, 0);
		$chunk = new Chunk([], true);
		$chunk->setBlockStateId(1, 64, 1, VanillaBlocks::CHEST()->getStateId());
		(new \ReflectionProperty(World::class, "chunks"))->setValue($world, [$hash => $chunk]);
		$tile = new Chest($world, new Vector3(129, 64, 1));
		$tile->getInventory()->setItem(0, VanillaItems::DIAMOND()->setCount(5));
		$world->addTile($tile);
		$world->doTick(1);
		self::assertTrue($world->unloadChunk(8, 0));
		self::assertTrue($tile->isClosed());
		self::assertCount(1, $this->saved[$hash]->getTileNBT());
		self::assertNotNull($world->loadChunk(8, 0));
		$restored = $world->getTileAt(129, 64, 1);
		self::assertInstanceOf(Chest::class, $restored);
		self::assertTrue($restored->getInventory()->getItem(0)->equalsExact(VanillaItems::DIAMOND()->setCount(5)));
	}

	public function testWorldChangeReleasesTheOldWorldCache() : void{
		$oldWorld = $this->world();
		$newWorld = $this->world();
		$session = (new \ReflectionClass(NetworkSession::class))->newInstanceWithoutConstructor();
		$player = $this->player($newWorld, 12, $session);
		$hash = World::chunkHash(8, 0);
		(new \ReflectionProperty(Player::class, "usedChunks"))->setValue($player, [$hash => UsedChunkStatus::SENT]);
		$oldWorld->registerChunkListener($player, 8, 0);
		$oldCache = $this->cache($oldWorld, 8, 0, "old world terrain");
		$newCache = $this->cache($newWorld, 8, 0, "new world terrain");
		(new \ReflectionMethod(Player::class, "unloadChunk"))->invoke($player, 8, 0, $oldWorld);
		self::assertSame(0, $oldCache->calculateCacheSize());
		self::assertSame("new world terrain", $newCache->request(8, 0));
		self::assertSame([], $oldWorld->getChunkPlayers(8, 0));
	}

	public function testUnsavedPluginEntitiesKeepViewedTerrainLoaded() : void{
		$world = $this->world();
		$viewer = $this->player($world);
		$world->registerChunkListener($viewer, 8, 0);
		$entity = $this->getMockBuilder(Entity::class)->disableOriginalConstructor()->onlyMethods([
			"canSaveWithChunk", "getInitialSizeInfo", "getInitialDragMultiplier", "getInitialGravity", "getNetworkTypeId"
		])->getMock();
		(new \ReflectionProperty(Entity::class, "closed"))->setValue($entity, true);
		$entity->method("canSaveWithChunk")->willReturn(false);
		(new \ReflectionProperty(World::class, "entitiesByChunk"))->setValue($world, [World::chunkHash(8, 0) => [1 => $entity]]);
		self::assertFalse($world->unloadChunk(8, 0));
	}

	public function testPendingGenerationAndSendingKeepTheirLoaders() : void{
		foreach([UsedChunkStatus::REQUESTED_GENERATION, UsedChunkStatus::REQUESTED_SENDING] as $status){
			$world = $this->world();
			$player = $this->player($world);
			$hash = World::chunkHash(8, 0);
			$this->sentChunks($player, [$hash => $status]);
			(new \ReflectionMethod(Player::class, "orderChunks"))->invoke($player);
			self::assertTrue($world->isChunkInUse(8, 0));
			(new \ReflectionProperty(Player::class, "usedChunks"))->setValue($player, [$hash => UsedChunkStatus::SENT]);
			(new \ReflectionMethod(Player::class, "orderChunks"))->invoke($player);
			self::assertFalse($world->isChunkInUse(8, 0));
		}
	}
}
