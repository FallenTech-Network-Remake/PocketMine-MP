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

namespace pocketmine\world\light;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use pocketmine\block\Block;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\format\Chunk;
use pocketmine\world\SimpleChunkManager;
use pocketmine\world\utils\SubChunkExplorer;
use pocketmine\world\utils\SubChunkExplorerStatus;
use pocketmine\world\World;

final class SkyLightUpdateWithoutVoidOptimization extends SkyLightUpdate{
	public function execute() : int{
		return LightUpdate::execute();
	}
}

final class SkyLightUpdateTest extends TestCase{

	private static function updater(SimpleChunkManager $world, bool $optimized = true) : SkyLightUpdate{
		$registry = RuntimeBlockStateRegistry::getInstance();
		return $optimized ?
			new SkyLightUpdate(new SubChunkExplorer($world), $registry->lightFilter, $registry->blocksDirectSkyLight) :
			new SkyLightUpdateWithoutVoidOptimization(new SubChunkExplorer($world), $registry->lightFilter, $registry->blocksDirectSkyLight);
	}

	/** @return \Generator<int, Chunk> */
	private static function chunks(SimpleChunkManager $world) : \Generator{
		for($x = -3; $x <= 3; ++$x){
			for($z = -3; $z <= 3; ++$z){
				$chunk = $world->getChunk($x, $z);
				if($chunk !== null){
					yield World::chunkHash($x, $z) => $chunk;
				}
			}
		}
	}

	private static function populateLight(SimpleChunkManager $world) : void{
		$update = self::updater($world);
		foreach(self::chunks($world) as $hash => $chunk){
			World::getXZ($hash, $x, $z);
			$update->recalculateChunk($x, $z);
			$chunk->setLightPopulated();
		}
		$update->execute();
	}

	private static function world(int $x, int $y, int $z, Block $roof) : SimpleChunkManager{
		$world = new SimpleChunkManager(World::Y_MIN, World::Y_MAX);
		for($cx = -3; $cx <= 3; ++$cx){
			for($cz = -3; $cz <= 3; ++$cz){
				$world->setChunk($cx, $cz, new Chunk([], true));
			}
		}
		for($rx = $x - 18; $rx <= $x + 21; ++$rx){
			for($rz = $z - 16; $rz <= $z + 23; ++$rz){
				$world->setBlockAt($rx, $y, $rz, $roof);
			}
		}
		self::populateLight($world);
		return $world;
	}

	private static function copyWorld(SimpleChunkManager $world) : SimpleChunkManager{
		$copy = new SimpleChunkManager(World::Y_MIN, World::Y_MAX);
		foreach(self::chunks($world) as $hash => $chunk){
			World::getXZ($hash, $x, $z);
			$copy->setChunk($x, $z, clone $chunk);
		}
		return $copy;
	}

	private static function assertSameLighting(SimpleChunkManager $expected, SimpleChunkManager $actual) : void{
		foreach(self::chunks($expected) as $hash => $chunk){
			World::getXZ($hash, $x, $z);
			$other = $actual->getChunk($x, $z);
			self::assertNotNull($other);
			self::assertSame($chunk->getHeightMapArray(), $other->getHeightMapArray());
			foreach($chunk->getSubChunks() as $y => $subChunk){
				self::assertSame($subChunk->getBlockSkyLightArray()->getData(), $other->getSubChunk($y)->getBlockSkyLightArray()->getData(), "Skylight differs in chunk $x:$y:$z");
				self::assertSame($subChunk->getBlockLightArray()->getData(), $other->getSubChunk($y)->getBlockLightArray()->getData(), "Block light differs in chunk $x:$y:$z");
			}
		}
	}

	/** @return \Generator<string, array{int, int, int, string}> */
	public static function islandProvider() : \Generator{
		yield "high island" => [8, 192, 8, "stone"];
		yield "low island" => [8, 16, 8, "stone"];
		yield "near floor" => [8, -48, 8, "stone"];
		yield "height limit" => [8, World::Y_MAX - 1, 8, "stone"];
		yield "negative coordinates" => [-1, 128, -1, "stone"];
		yield "four chunk corner" => [15, 192, 15, "stone"];
		yield "filtering leaves" => [8, 192, 8, "leaves"];
		yield "filtering water" => [8, 192, 8, "water"];
	}

	#[DataProvider("islandProvider")]
	public function testOpeningIslandMatchesFullLightPopulation(int $x, int $y, int $z, string $material) : void{
		$roof = match($material){
			"leaves" => VanillaBlocks::OAK_LEAVES(),
			"water" => VanillaBlocks::WATER(),
			default => VanillaBlocks::STONE(),
		};
		$world = self::world($x, $y, $z, $roof);
		$world->setBlockAt($x, $y, $z, VanillaBlocks::AIR());
		$reference = self::copyWorld($world);
		$update = self::updater($world);
		$update->recalculateNode($x, $y, $z);
		$nodes = $update->execute();
		self::populateLight($reference);
		self::assertSameLighting($reference, $world);
		if($material === "stone" && $y >= 128){
			self::assertLessThan(10_000, $nodes, "An opening above a deep void must not propagate through every empty layer");
		}
	}

	public function testSeveralOpeningsShareOneUpdate() : void{
		$world = self::world(8, 192, 8, VanillaBlocks::STONE());
		$update = self::updater($world);
		foreach([[7, 8], [8, 8], [15, 15], [16, 16]] as [$x, $z]){
			$world->setBlockAt($x, 192, $z, VanillaBlocks::AIR());
			$update->recalculateNode($x, 192, $z);
		}
		$reference = self::copyWorld($world);
		$update->execute();
		self::populateLight($reference);
		self::assertSameLighting($reference, $world);
	}

	public function testLowerTerrainPreventsVoidCompression() : void{
		$world = self::world(8, 192, 8, VanillaBlocks::STONE());
		$world->setBlockAt(20, -48, 20, VanillaBlocks::STONE());
		self::populateLight($world);
		$world->setBlockAt(8, 192, 8, VanillaBlocks::AIR());
		$reference = self::copyWorld($world);
		$update = self::updater($world);
		$update->recalculateNode(8, 192, 8);
		$nodes = $update->execute();
		self::assertGreaterThan(10_000, $nodes);
		self::populateLight($reference);
		self::assertSameLighting($reference, $world);
	}

	public function testUnpopulatedNeighbourPreventsVoidCompression() : void{
		$world = self::world(8, 192, 8, VanillaBlocks::STONE());
		$chunk = $world->getChunk(2, 2);
		self::assertNotNull($chunk);
		$chunk->setLightPopulated(false);
		$world->setBlockAt(8, 192, 8, VanillaBlocks::AIR());
		$reference = self::copyWorld($world);
		$update = self::updater($world);
		$update->recalculateNode(8, 192, 8);
		self::assertGreaterThan(10_000, $update->execute());
		self::populateLight($reference);
		self::assertSameLighting($reference, $world);
	}

	public function testUnloadedChunksAreNotReloaded() : void{
		$world = self::world(8, 192, 8, VanillaBlocks::STONE());
		$world->setBlockAt(8, 192, 8, VanillaBlocks::AIR());
		$update = self::updater($world);
		$update->recalculateNode(8, 192, 8);
		$chunk = $world->getChunk(0, 0);
		self::assertNotNull($chunk);
		$reference = \WeakReference::create($chunk);
		unset($chunk);
		$world->cleanChunks();
		$update->execute();
		self::assertNull($world->getChunk(0, 0));
		self::assertNull($reference->get());
	}

	public function testReplacingAChunkBeforeExecutionMatchesOrdinaryPropagation() : void{
		$fixture = self::world(8, 192, 8, VanillaBlocks::STONE());
		foreach([false, true] as $sameHeight){
			$worlds = [];
			foreach([false, true] as $optimized){
				$world = self::copyWorld($fixture);
				$world->setBlockAt(8, 192, 8, VanillaBlocks::AIR());
				$update = self::updater($world, $optimized);
				$update->recalculateNode(8, 192, 8);
				$replacement = ($sameHeight ? $world : $fixture)->getChunk(0, 0);
				self::assertNotNull($replacement);
				$world->setChunk(0, 0, clone $replacement);
				$update->execute();
				$worlds[] = $world;
			}
			self::assertSameLighting($worlds[0], $worlds[1]);
		}
	}

	public function testRepeatedExecutionPreservesThePendingNodes() : void{
		$world = self::world(8, 192, 8, VanillaBlocks::STONE());
		$world->setBlockAt(8, 192, 8, VanillaBlocks::AIR());
		$update = self::updater($world);
		$update->recalculateNode(8, 192, 8);
		$update->execute();
		$reference = self::copyWorld($world);
		$chunk = $world->getChunk(0, 0);
		self::assertNotNull($chunk);
		$chunk->getSubChunk(-2)->getBlockSkyLightArray()->set(8, 0, 8, 0);
		$update->execute();
		self::assertSameLighting($reference, $world);
	}

	public function testDiscontinuousOldLightMatchesOrdinaryPropagation() : void{
		$fixture = self::world(8, 192, 8, VanillaBlocks::STONE());
		$worlds = [];
		foreach([false, true] as $optimized){
			$world = self::copyWorld($fixture);
			$chunk = $world->getChunk(0, 0);
			self::assertNotNull($chunk);
			for($sy = Chunk::MIN_SUBCHUNK_INDEX; $sy < 11; ++$sy){
				$light = $chunk->getSubChunk($sy)->getBlockSkyLightArray();
				for($y = 0; $y < 16; ++$y){
					foreach([[9, 8], [7, 8], [8, 9], [8, 7]] as [$x, $z]){
						$light->set($x, $y, $z, 14);
					}
				}
			}
			$world->setBlockAt(8, 192, 8, VanillaBlocks::AIR());
			$update = self::updater($world, $optimized);
			$update->recalculateNode(8, 192, 8);
			$update->execute();
			$worlds[] = $world;
		}
		self::assertSameLighting($worlds[0], $worlds[1]);
	}

	public function testBoundedExplorerDoesNotChangeTheOriginal() : void{
		$world = new SimpleChunkManager(World::Y_MIN, World::Y_MAX);
		$world->setChunk(0, 0, new Chunk([], true));
		$explorer = new SubChunkExplorer($world);
		self::assertSame(SubChunkExplorerStatus::MOVED, $explorer->moveTo(0, -1, 0));
		$bounded = $explorer->withMinSubChunkIndex(0);
		self::assertSame(SubChunkExplorerStatus::INVALID, $bounded->moveTo(0, -1, 0));
		self::assertSame(SubChunkExplorerStatus::OK, $explorer->moveTo(0, -1, 0));
		self::assertSame(SubChunkExplorerStatus::MOVED, $bounded->moveTo(0, 0, 0));
		self::assertSame(SubChunkExplorerStatus::INVALID, $bounded->moveTo(0, World::Y_MAX, 0));
	}

	public function testCallerSuppliedLowerBoundIsRespected() : void{
		$fixture = self::world(8, 192, 8, VanillaBlocks::STONE());
		$registry = RuntimeBlockStateRegistry::getInstance();
		$worlds = [];
		foreach([false, true] as $optimized){
			$world = self::copyWorld($fixture);
			$world->setBlockAt(8, 192, 8, VanillaBlocks::AIR());
			$explorer = (new SubChunkExplorer($world))->withMinSubChunkIndex(0);
			$update = $optimized ?
				new SkyLightUpdate($explorer, $registry->lightFilter, $registry->blocksDirectSkyLight) :
				new SkyLightUpdateWithoutVoidOptimization($explorer, $registry->lightFilter, $registry->blocksDirectSkyLight);
			$update->recalculateNode(8, 192, 8);
			$update->execute();
			$worlds[] = $world;
		}
		self::assertSameLighting($worlds[0], $worlds[1]);
	}

	public function testExplicitLightOutsideTheExposedRangeIsPreserved() : void{
		$fixture = self::world(8, 192, 8, VanillaBlocks::STONE());
		$fixture->setBlockAt(8, 128, 8, VanillaBlocks::STONE());
		self::populateLight($fixture);
		$worlds = [];
		foreach([false, true] as $optimized){
			$world = self::copyWorld($fixture);
			$world->setBlockAt(8, 192, 8, VanillaBlocks::AIR());
			$update = self::updater($world, $optimized);
			$update->recalculateNode(8, 192, 8);
			$update->setAndUpdateLight(8, World::Y_MIN, 8, 15);
			$update->execute();
			$worlds[] = $world;
		}
		self::assertSameLighting($worlds[0], $worlds[1]);
	}

	public function testNonstandardEmptyBlockAttenuationMatchesOrdinaryPropagation() : void{
		$fixture = self::world(8, 192, 8, VanillaBlocks::STONE());
		$registry = RuntimeBlockStateRegistry::getInstance();
		$filters = $registry->lightFilter;
		$filters[VanillaBlocks::AIR()->getStateId()] = 2;
		$worlds = [];
		foreach([false, true] as $optimized){
			$world = self::copyWorld($fixture);
			$world->setBlockAt(8, 192, 8, VanillaBlocks::AIR());
			$explorer = new SubChunkExplorer($world);
			$update = $optimized ?
				new SkyLightUpdate($explorer, $filters, $registry->blocksDirectSkyLight) :
				new SkyLightUpdateWithoutVoidOptimization($explorer, $filters, $registry->blocksDirectSkyLight);
			$update->recalculateNode(8, 192, 8);
			$update->execute();
			$worlds[] = $world;
		}
		self::assertSameLighting($worlds[0], $worlds[1]);
	}
}
