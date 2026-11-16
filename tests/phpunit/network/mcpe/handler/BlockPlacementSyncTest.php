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

namespace pocketmine\network\mcpe\handler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\InventoryManager;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\inventory\HandSlot;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\inventory\PredictedResult;
use pocketmine\network\mcpe\protocol\types\inventory\TriggerType;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use pocketmine\player\Player;
use pocketmine\world\World;
use function array_values;

final class BlockPlacementSyncTest extends TestCase{
	/** @return list<Vector3> */
	private function syncPositions(Item $heldItem, int $clientRuntimeId, PredictedResult $prediction = PredictedResult::SUCCESS, bool $accepted = true, bool $consume = false, ?Item $reportedItem = null) : array{
		$positions = [];
		$world = $this->getMockBuilder(World::class)->disableOriginalConstructor()->onlyMethods(["createBlockUpdatePackets"])->getMock();
		$world->method("createBlockUpdatePackets")->willReturnCallback(static function(array $blocks) use (&$positions) : array{
			$positions = array_values($blocks);
			return [];
		});
		$logger = self::createStub(\Logger::class);
		$inventory = $this->getMockBuilder(PlayerInventory::class)->disableOriginalConstructor()->onlyMethods(["getItemInHand"])->getMock();
		$inventory->method("getItemInHand")->willReturnCallback(static fn() => $heldItem);
		$player = $this->getMockBuilder(Player::class)->disableOriginalConstructor()->onlyMethods([
			"selectHotbarSlot", "getInventory", "getWorld", "getLocation", "interactBlock", "__destruct"
		])->getMock();
		(new \ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new \ReflectionProperty(Player::class, "logger"))->setValue($player, $logger);
		$player->method("getInventory")->willReturn($inventory);
		$player->method("getWorld")->willReturn($world);
		$player->method("getLocation")->willReturn(new Location(0, 64, 0, $world, 0, 0));
		$player->method("interactBlock")->willReturnCallback(static function() use ($heldItem, $accepted, $consume) : bool{
			if($consume){
				$heldItem->setCount(0);
			}
			return $accepted;
		});
		$session = self::createStub(NetworkSession::class);
		$session->method("getLogger")->willReturn($logger);
		$converter = TypeConverter::getInstance();
		$session->method("getTypeConverter")->willReturn($converter);
		$networkItem = $converter->coreItemStackToNet($reportedItem ?? $heldItem);
		$reportedItem = new ItemStack($networkItem->getId(), $networkItem->getMeta(), $networkItem->getCount(), $clientRuntimeId, $networkItem->getRawExtraData());
		$data = UseItemTransactionData::new([], UseItemTransactionData::ACTION_CLICK_BLOCK, TriggerType::PLAYER_INPUT,
			new BlockPosition(0, 64, 0), Facing::EAST, 0, HandSlot::MAINHAND, ItemStackWrapper::legacy($reportedItem),
			new Vector3(0, 64, 0), new Vector3(1, 0.5, 0.5), 0, $prediction, 0);
		$handler = new InGamePacketHandler($player, $session, self::createStub(InventoryManager::class));
		self::assertTrue((new \ReflectionMethod($handler, "handleUseItemTransaction"))->invoke($handler, $data));
		return $positions;
	}

	/** @return \Generator<string, array{Item, int}> */
	public static function items() : \Generator{
		yield "single block with omitted runtime ID" => [VanillaBlocks::EMERALD()->asItem(), 7];
		yield "door" => [VanillaBlocks::OAK_DOOR()->asItem(), 12];
		yield "bed" => [VanillaBlocks::BED()->asItem(), 12];
		yield "water bucket" => [VanillaItems::WATER_BUCKET(), 12];
		yield "empty hand" => [VanillaItems::AIR(), 12];
	}

	#[DataProvider("items")]
	public function testCorrectionScopeUsesServerHeldItem(Item $item, int $count) : void{
		$positions = $this->syncPositions($item, 0);
		self::assertCount($count, $positions);
		self::assertContainsEquals(new Vector3(0, 64, 0), $positions);
		self::assertContainsEquals(new Vector3(1, 64, 0), $positions);
		if($count === 7){
			self::assertNotContainsEquals(new Vector3(2, 64, 0), $positions);
		}else{
			self::assertContainsEquals(new Vector3(2, 64, 0), $positions);
		}
	}

	public function testLastItemKeepsSingleBlockCorrectionScope() : void{
		self::assertCount(7, $this->syncPositions(VanillaBlocks::EMERALD()->asItem(), 0, consume: true));
	}

	public function testRejectedPlacementStillCorrectsTheTarget() : void{
		$positions = $this->syncPositions(VanillaBlocks::EMERALD()->asItem(), 0, accepted: false);
		self::assertContainsEquals(new Vector3(0, 64, 0), $positions);
		self::assertContainsEquals(new Vector3(1, 64, 0), $positions);
		self::assertNotContainsEquals(new Vector3(2, 64, 0), $positions);
	}

	public function testExistingRuntimeIdAndFailedPredictionAreUnchanged() : void{
		$item = VanillaBlocks::EMERALD()->asItem();
		$runtimeId = TypeConverter::getInstance()->coreItemStackToNet($item)->getBlockRuntimeId();
		self::assertCount(7, $this->syncPositions($item, $runtimeId));
		self::assertSame([], $this->syncPositions($item, 0, PredictedResult::FAILURE));
	}

	public function testMismatchedClientItemKeepsFullCorrectionScope() : void{
		self::assertCount(12, $this->syncPositions(VanillaBlocks::EMERALD()->asItem(), 0, reportedItem: VanillaBlocks::OAK_DOOR()->asItem()));
	}
}
