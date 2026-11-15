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
use pocketmine\entity\Entity;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\format\io\WorldProviderManager;
use function array_map;

final class WorldManagerAutoSaveTest extends TestCase{
	private WorldManager $manager;
	/** @var array<int, list<int>> */
	private array $worldSaves = [];
	/** @var array<int, list<int>> */
	private array $playerSaves = [];
	/** @var array<int, array<int, Player>> */
	private array $players = [];
	/** @var \Closure(int) : void|null */
	private ?\Closure $onWorldSave = null;
	/** @var \Closure(int) : void|null */
	private ?\Closure $onPlayerSave = null;

	protected function setUp() : void{
		$server = self::createStub(Server::class);
		$server->method("getLogger")->willReturn(new class extends \pocketmine\thread\log\AttachableThreadSafeLogger{
			public function emergency(mixed $message) : void{}
			public function alert(mixed $message) : void{}
			public function critical(mixed $message) : void{}
			public function error(mixed $message) : void{}
			public function warning(mixed $message) : void{}
			public function notice(mixed $message) : void{}
			public function info(mixed $message) : void{}
			public function debug(mixed $message) : void{}
			public function log(mixed $level, mixed $message) : void{}
			public function logException(\Throwable $e, mixed $trace = null) : void{}
		});
		$this->manager = new WorldManager($server, "", new WorldProviderManager());
	}

	private function clock() : int{
		$tick = (new \ReflectionProperty(WorldManager::class, "autoSaveTicker"))->getValue($this->manager);
		self::assertIsInt($tick);
		return $tick;
	}

	/** @return list<int> */
	private function getWorldSaves(int $id) : array{ return $this->worldSaves[$id] ?? []; }

	/** @return list<int> */
	private function getPlayerSaves(int $id) : array{ return $this->playerSaves[$id] ?? []; }

	private function world(int $id) : World&MockObject{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods([
			"doTick", "getPlayers", "save", "getLogger", "getDisplayName", "onUnload"
		])->getMock();
		(new \ReflectionProperty(World::class, "worldId"))->setValue($world, $id);
		$world->method("getPlayers")->willReturnCallback(fn() => $this->players[$id] ?? []);
		$world->method("getLogger")->willReturn(self::createStub(\Logger::class));
		$world->method("getDisplayName")->willReturn("test");
		$world->method("save")->willReturnCallback(function(bool $force) use ($id, $world) : bool{
			self::assertFalse($force);
			if(!$world->getAutoSave()){
				return false;
			}
			$this->worldSaves[$id][] = $this->clock();
			($this->onWorldSave ?? static function(int $id) : void{})($id);
			return true;
		});
		$worlds = $this->manager->getWorlds();
		$worlds[$id] = $world;
		(new \ReflectionProperty(WorldManager::class, "worlds"))->setValue($this->manager, $worlds);
		return $world;
	}

	private function player(int $id, int $worldId) : Player{
		$player = $this->getMockBuilder(Player::class)->disableOriginalConstructor()->onlyMethods(["save", "__destruct"])->getMock();
		(new \ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new \ReflectionProperty(Player::class, "logger"))->setValue($player, new \PrefixedLogger(self::createStub(\Logger::class), "test"));
		$player->spawned = true;
		$player->method("save")->willReturnCallback(function() use ($id) : void{
			$this->playerSaves[$id][] = $this->clock();
			($this->onPlayerSave ?? static function(int $id) : void{})($id);
		});
		$this->players[$worldId][$id] = $player;
		return $player;
	}

	/** @param list<int> $ticks */
	private static function assertCadence(array $ticks, int $interval, int $count) : void{
		self::assertCount($count, $ticks);
		self::assertGreaterThanOrEqual(1, $ticks[0]);
		self::assertLessThanOrEqual($interval, $ticks[0]);
		for($i = 1; $i < $count; ++$i){
			self::assertSame($interval, $ticks[$i] - $ticks[$i - 1]);
		}
	}

	public function testIndependentWorldAndPlayerCadence() : void{
		$this->manager->setAutoSaveInterval(32);
		for($id = 1; $id <= 64; ++$id){
			$this->world($id);
			$this->player($id, $id);
		}
		for($tick = 1; $tick <= 128; ++$tick){
			$this->manager->tick($tick);
		}
		for($id = 1; $id <= 64; ++$id){
			self::assertCadence($this->worldSaves[$id], 32, 4);
			self::assertCadence($this->playerSaves[$id], 32, 4);
		}
	}

	public function testTransfersCannotPostponeOrDuplicatePlayerSaves() : void{
		$this->manager->setAutoSaveInterval(7);
		$this->world(1);
		$this->world(2);
		$player = $this->player(1, 1);
		$this->onPlayerSave = function(int $id) use ($player) : void{
			$this->players = [2 => [$id => $player]];
		};
		for($tick = 1; $tick <= 70; ++$tick){
			$this->players = [($tick % 2) + 1 => [1 => $player]];
			$this->manager->tick($tick);
		}
		self::assertCadence($this->playerSaves[1], 7, 10);
	}

	public function testPauseAndIntervalChangesPreserveDeadlines() : void{
		$this->manager->setAutoSaveInterval(10);
		$this->world(1);
		$player = $this->player(1, 1);
		$this->manager->tick(1);
		$this->worldSaves = $this->playerSaves = [];
		(new \ReflectionProperty(WorldManager::class, "worldAutoSaveTicks"))->setValue($this->manager, [1 => 10]);
		$playerDeadlines = new \WeakMap();
		$playerDeadlines[$player] = 5;
		(new \ReflectionProperty(WorldManager::class, "playerAutoSaveTicks"))->setValue($this->manager, $playerDeadlines);
		$this->manager->setAutoSave(false);
		for($tick = 2; $tick <= 100; ++$tick){
			$this->manager->tick($tick);
		}
		self::assertSame(1, $this->clock());
		self::assertSame([], $this->worldSaves);
		self::assertSame([], $this->playerSaves);
		$this->manager->setAutoSaveInterval(3);
		$this->manager->setAutoSave(true);
		$this->manager->tick(101);
		$this->manager->tick(102);
		self::assertSame([3], $this->getWorldSaves(1));
		self::assertSame([2], $this->getPlayerSaves(1));
		$this->manager->setAutoSaveInterval(10);
		for($tick = 103; $tick <= 112; ++$tick){
			$this->manager->setAutoSaveInterval(10);
			$this->manager->tick($tick);
		}
		self::assertSame([3, 13], $this->getWorldSaves(1));
		self::assertSame([2, 12], $this->getPlayerSaves(1));
	}

	public function testUnspawnedPlayersAndDisabledWorlds() : void{
		$this->manager->setAutoSaveInterval(1);
		$world = $this->world(1);
		$world->setAutoSave(false);
		$player = $this->player(1, 1);
		$player->spawned = false;
		$this->manager->tick(1);
		self::assertSame([], $this->worldSaves);
		self::assertSame([], $this->playerSaves);
		$player->spawned = true;
		$this->manager->tick(2);
		self::assertSame([2], $this->getPlayerSaves(1));
		$world->setAutoSave(true);
		$this->manager->tick(3);
		self::assertSame([3], $this->getWorldSaves(1));
		self::assertSame([2, 3], $this->getPlayerSaves(1));
	}

	public function testWorldUnloadedDuringAutoSaveIsSkipped() : void{
		$this->manager->setAutoSaveInterval(1);
		$this->world(1);
		$removed = $this->world(2);
		$this->onWorldSave = function(int $id) use ($removed) : void{
			if($id === 1){
				self::assertTrue($this->manager->unloadWorld($removed));
			}
		};
		$this->manager->tick(1);
		self::assertArrayNotHasKey(2, $this->manager->getWorlds());
		self::assertArrayNotHasKey(2, $this->worldSaves);
		self::assertSame([1 => 2], (new \ReflectionProperty(WorldManager::class, "worldAutoSaveTicks"))->getValue($this->manager));
	}

	public function testInvalidInterval() : void{
		$this->expectException(\InvalidArgumentException::class);
		$this->manager->setAutoSaveInterval(0);
	}

	public function testLateWorldsAndPlayersStartWithinOneInterval() : void{
		$this->manager->setAutoSaveInterval(8);
		$this->world(1);
		for($tick = 1; $tick <= 16; ++$tick){
			$this->manager->tick($tick);
		}
		$this->world(2);
		$player = $this->player(2, 2);
		$player->spawned = false;
		for($tick = 17; $tick <= 24; ++$tick){
			$this->manager->tick($tick);
		}
		self::assertSame([], $this->getPlayerSaves(2));
		$player->spawned = true;
		for($tick = 25; $tick <= 48; ++$tick){
			$this->manager->tick($tick);
		}
		self::assertCadence(array_map(static fn(int $tick) : int => $tick - 16, $this->getWorldSaves(2)), 8, 4);
		self::assertCadence(array_map(static fn(int $tick) : int => $tick - 24, $this->getPlayerSaves(2)), 8, 3);
	}

	public function testDisablingAutoSaveDuringSaveStopsRemainingWorlds() : void{
		$this->manager->setAutoSaveInterval(1);
		$this->world(1);
		$this->world(2);
		$this->onWorldSave = function(int $id) : void{ $this->manager->setAutoSave(false); };
		$this->manager->tick(1);
		self::assertSame([1], $this->getWorldSaves(1));
		self::assertSame([], $this->getWorldSaves(2));
		$this->onWorldSave = null;
		$this->manager->setAutoSave(true);
		$this->manager->tick(2);
		self::assertSame([1, 2], $this->getWorldSaves(1));
		self::assertSame([2], $this->getWorldSaves(2));
	}
}
