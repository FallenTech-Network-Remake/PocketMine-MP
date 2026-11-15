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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\world\format\Chunk;
use function array_filter;
use function array_values;

final class BlockUpdatePacketsTest extends TestCase{
	private function world(?Chunk $chunk, int $minY = World::Y_MIN, int $maxY = World::Y_MAX) : World&MockObject{
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods([
			"getBlockAt", "getTileAt", "getChunk"
		])->getMock();
		(new \ReflectionProperty(World::class, "minY"))->setValue($world, $minY);
		(new \ReflectionProperty(World::class, "maxY"))->setValue($world, $maxY);
		$world->method("getBlockAt")->willReturn(VanillaBlocks::AIR());
		$world->method("getTileAt")->willReturn(null);
		$world->method("getChunk")->willReturn($chunk);
		return $world;
	}

	/** @return \Generator<string, array{int, int, int}> */
	public static function boundaryProvider() : \Generator{
		yield "overworld ceiling" => [World::Y_MIN, World::Y_MAX, World::Y_MAX - 1];
		yield "overworld floor" => [World::Y_MIN, World::Y_MAX, World::Y_MIN];
		yield "lower dimension ceiling" => [0, 128, 127];
		yield "lower dimension floor" => [0, 128, 0];
	}

	#[DataProvider("boundaryProvider")]
	public function testNearbySyncAtWorldBoundary(int $minY, int $maxY, int $y) : void{
		$chunk = new Chunk([], false);
		$chunk->getSubChunk($y >> 4)->setExtraBlockStateId(0, $y & 15, 0, VanillaBlocks::WATER()->getStateId());
		$world = $this->world($chunk, $minY, $maxY);
		$center = new Vector3(0, $y, 0);
		$positions = [...$center->sidesArray(), $center];
		$packets = $world->createBlockUpdatePackets($positions);
		$normal = [];
		$liquid = [];
		foreach($packets as $packet){
			self::assertInstanceOf(UpdateBlockPacket::class, $packet);
			if($packet->dataLayerId === UpdateBlockPacket::DATA_LAYER_NORMAL){
				$normal[] = $packet;
			}else{
				self::assertSame(UpdateBlockPacket::DATA_LAYER_LIQUID, $packet->dataLayerId);
				self::assertGreaterThanOrEqual($minY, $packet->blockPosition->getY());
				self::assertLessThan($maxY, $packet->blockPosition->getY());
				$liquid[] = $packet;
			}
		}
		self::assertCount(7, $normal);
		self::assertNotEmpty($liquid);
		$centerPacket = array_values(array_filter($liquid, static fn(UpdateBlockPacket $packet) => $packet->blockPosition->getX() === 0 && $packet->blockPosition->getY() === $y && $packet->blockPosition->getZ() === 0))[0];
		self::assertSame(TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId(VanillaBlocks::WATER()->getStateId()), $centerPacket->blockRuntimeId);
	}

	public function testOutsideWorldDoesNotReadChunk() : void{
		$world = $this->world(new Chunk([], false));
		$world->expects(self::never())->method("getChunk");
		$packets = $world->createBlockUpdatePackets([
			new Vector3(0, World::Y_MAX, 0), new Vector3(0, World::Y_MIN - 1, 0),
			new Vector3(0, World::Y_MAX + 16, 0), new Vector3(0, World::Y_MIN - 16, 0)
		]);
		self::assertCount(4, $packets);
		foreach($packets as $packet){
			self::assertInstanceOf(UpdateBlockPacket::class, $packet);
			self::assertSame(UpdateBlockPacket::DATA_LAYER_NORMAL, $packet->dataLayerId);
		}
	}

	public function testUnloadedChunkStillAllowsNormalSync() : void{
		$packets = $this->world(null)->createBlockUpdatePackets([new Vector3(0, 64, 0)]);
		self::assertCount(1, $packets);
		self::assertInstanceOf(UpdateBlockPacket::class, $packets[0]);
		self::assertSame(UpdateBlockPacket::DATA_LAYER_NORMAL, $packets[0]->dataLayerId);
	}
}
