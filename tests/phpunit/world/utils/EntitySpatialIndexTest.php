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

namespace pocketmine\world\utils;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\world\World;

final class SpatialIndexTestEntity extends Entity implements NeverSavedWithChunkEntity{
	public function __construct(World $world, int $id, AxisAlignedBB $bb){ // @phpstan-ignore constructor.missingParentCall (Headless lifecycle fixture.)
		$this->id = $id;
		$this->location = new Location(($bb->minX + $bb->maxX) / 2, $bb->minY, ($bb->minZ + $bb->maxZ) / 2, $world, 0, 0);
		$this->size = new EntitySizeInfo($bb->maxY - $bb->minY, $bb->maxX - $bb->minX);
		$this->boundingBox = $bb;
	}

	public static function getNetworkTypeId() : string{ return "test:spatial_index"; }
	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(1.8, 0.6); }
	protected function getInitialDragMultiplier() : float{ return 0.0; }
	protected function getInitialGravity() : float{ return 0.0; }
	public function moveTo(Vector3 $position) : void{ $this->setPosition($position); }
	public function __destruct(){}
}

final class EntitySpatialIndexTest extends TestCase{
	private static function world() : World{
		return (new \ReflectionClass(World::class))->newInstanceWithoutConstructor();
	}

	public function testWorldMovementResizeTransferAndRemoval() : void{
		$world = self::world();
		$otherWorld = self::world();
		$entity = new SpatialIndexTestEntity($world, 1, new AxisAlignedBB(0, 0, 0, 1, 1, 1));
		$world->addEntity($entity);
		self::assertSame([$entity], $world->getNearbyEntities(new AxisAlignedBB(0, 0, 0, 1, 1, 1)));
		self::assertSame([], $world->getNearbyEntities($entity->boundingBox, $entity));
		$entity->moveTo(new Vector3(0.5, 200, 0.5));
		self::assertSame([], $world->getNearbyEntities(new AxisAlignedBB(0, 0, 0, 1, 1, 1)));
		self::assertSame([$entity], $world->getNearbyEntities(new AxisAlignedBB(0, 200, 0, 1, 201, 1)));
		$entity->setScale(30);
		$edge = new AxisAlignedBB(8, 220, 0, 9, 221, 1);
		self::assertSame([$entity], $world->getNearbyEntities($edge));
		$entity->setScale(1);
		self::assertSame([], $world->getNearbyEntities($edge));
		$entity->moveTo(new Vector3(-16, -100, -16));
		self::assertSame([], $world->getChunkEntities(0, 0));
		self::assertSame([1 => $entity], $world->getChunkEntities(-1, -1));
		self::assertSame([$entity], $world->getNearbyEntities($entity->boundingBox));
		$entity->moveTo(new Position(30, 500, 30, $otherWorld));
		self::assertSame([], $world->getEntities());
		self::assertSame([], $world->getNearbyEntities($entity->boundingBox));
		self::assertSame([$entity], $otherWorld->getNearbyEntities($entity->boundingBox));
		$otherWorld->removeEntity($entity);
		self::assertSame([], $otherWorld->getNearbyEntities($entity->boundingBox));
	}

	public function testOverlapsBoundariesOversizedAndWideQueries() : void{
		$world = self::world();
		$index = new EntitySpatialIndex();
		$small = new SpatialIndexTestEntity($world, 1, new AxisAlignedBB(-4.5, -4.5, -4.5, 4.5, 4.5, 4.5));
		$large = new SpatialIndexTestEntity($world, 2, new AxisAlignedBB(-1000, -1000, -1000, 1000, 1000, 1000));
		$index->update($small, 0, 0);
		$index->update($large, 0, 0);
		self::assertSame([$small, $large], $index->getNearbyEntities(new AxisAlignedBB(-4, -4, -4, 4, 4, 4)));
		self::assertSame([$large], $index->getNearbyEntities(new AxisAlignedBB(4.5, 4.5, 4.5, 5, 5, 5)));
		self::assertSame([$large], $index->getNearbyEntities(new AxisAlignedBB(900, 900, 900, 901, 901, 901)));
		self::assertSame([$small, $large], $index->getNearbyEntities(new AxisAlignedBB(-1000000, -1000000, -1000000, 1000000, 1000000, 1000000)));
		$index->remove($small);
		$index->remove($large);
		self::assertSame([], $index->getNearbyEntities(new AxisAlignedBB(-1000000, -1000000, -1000000, 1000000, 1000000, 1000000)));
		foreach(["cells", "entitiesByChunk", "oversizedEntities", "bounds", "order"] as $property){
			self::assertSame([], (new \ReflectionProperty($index, $property))->getValue($index));
		}
		self::assertSame([], (new \ReflectionProperty($index, "sortedChunks"))->getValue($index));
	}

	public function testOrderMatchesChunkTraversalAfterReentry() : void{
		$world = self::world();
		$a = new SpatialIndexTestEntity($world, 1, new AxisAlignedBB(0, 0, 0, 1, 1, 1));
		$b = new SpatialIndexTestEntity($world, 2, new AxisAlignedBB(0, 0, 0, 1, 1, 1));
		$c = new SpatialIndexTestEntity($world, 3, new AxisAlignedBB(-1, 0, -1, 0, 1, 0));
		foreach([$a, $b, $c] as $entity){
			$world->addEntity($entity);
		}
		$query = new AxisAlignedBB(-2, -1, -2, 2, 2, 2);
		$wide = new AxisAlignedBB(-1000000, -1000000, -1000000, 1000000, 1000000, 1000000);
		self::assertSame([$c, $a, $b], $world->getNearbyEntities($query));
		self::assertSame([$c, $a, $b], $world->getNearbyEntities($wide));
		$a->moveTo(new Vector3(20, 0, 20));
		$a->moveTo(new Vector3(0.5, 0, 0.5));
		self::assertSame([$c, $b, $a], $world->getNearbyEntities($query));
		self::assertSame([$c, $b, $a], $world->getNearbyEntities($wide));
		$world->removeEntity($b);
		self::assertSame([$c, $a], $world->getNearbyEntities($wide));
		$world->addEntity($b);
		self::assertSame([$c, $a, $b], $world->getNearbyEntities($wide));
	}
}
