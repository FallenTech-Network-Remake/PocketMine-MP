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

namespace pocketmine\entity\object;

use pocketmine\block\Air;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Fence;
use pocketmine\block\FenceGate;
use pocketmine\block\Fire;
use pocketmine\block\Liquid;
use pocketmine\block\SnowLayer;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\utils\WallConnectionType;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\Wall;
use pocketmine\data\bedrock\DyeColorIdMap;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Cushion as CushionItem;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\particle\BlockBreakParticle;
use pocketmine\world\Position;
use pocketmine\world\sound\CushionBreakSound;
use pocketmine\world\sound\CushionGetUpSound;
use pocketmine\world\sound\CushionSitSound;
use pocketmine\world\World;
use WeakMap;
use function abs;
use function ceil;
use function floor;
use function in_array;
use function spl_object_id;

/** Native cushion entity. There is deliberately no cushion block or tile. */
class Cushion extends Entity{
	public const WIDTH = 0.999;
	public const HEIGHT = 0.249;
	public const SEAT_HEIGHT = 0.0625;
	// Bedrock seat metadata locates the seated player's eyes, not their feet.
	public const SEATED_EYE_HEIGHT_FACTOR = 0.75308642;
	/** @var WeakMap<Player, self>|null */
	private static ?WeakMap $seats = null;
	private ?Player $rider = null;
	private CushionItem $item;
	private Vector3 $support;
	private int $supportCheckTicks = 0;

	public static function getNetworkTypeId() : string{ return EntityIds::CUSHION; }

	public function __construct(Location $location, ?CushionItem $item = null, ?Vector3 $support = null, ?CompoundTag $nbt = null){
		if($item === null && ($savedItem = $nbt?->getCompoundTag("Item")) !== null){
			$loaded = Item::nbtDeserialize($savedItem);
			if($loaded instanceof CushionItem){
				$item = $loaded;
			}
		}
		if($item === null){
			$color = DyeColorIdMap::getInstance()->fromInvertedId($nbt?->getInt("Variant", $nbt->getByte("Color", 15)) ?? 15) ?? DyeColor::WHITE;
			$item = CushionItem::forColor($color);
		}
		$this->item = (clone $item)->setCount(1);
		$this->support = $support?->asVector3() ?? new Vector3(
			$nbt?->getInt("SupportX", (int) floor($location->x)) ?? (int) floor($location->x),
			$nbt?->getInt("SupportY", (int) floor($location->y - 0.001)) ?? (int) floor($location->y - 0.001),
			$nbt?->getInt("SupportZ", (int) floor($location->z)) ?? (int) floor($location->z)
		);
		parent::__construct($location, $nbt);
		$this->motion = Vector3::zero();
		if($item->hasCustomName()){
			$this->setNameTag($item->getCustomName());
		}
		$this->setNoClientPredictions();
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(self::HEIGHT, self::WIDTH); }
	protected function getInitialGravity() : float{ return 0.0; }
	protected function getInitialDragMultiplier() : float{ return 1.0; }
	public function canBeRenamed() : bool{ return false; }
	public function getColor() : DyeColor{ return $this->item->getColor(); }
	public function getRider() : ?Player{ return $this->rider; }
	public static function getSeatFor(Player $player) : ?self{ return self::$seats[$player] ?? null; }

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->setMaxHealth(1);
		$this->setHealth(1);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$color = DyeColorIdMap::getInstance()->toInvertedId($this->getColor());
		return $nbt->setByte("Color", $color)->setInt("Variant", $color)
			->setInt("SupportX", (int) $this->support->x)->setInt("SupportY", (int) $this->support->y)->setInt("SupportZ", (int) $this->support->z)
			->setTag("Item", $this->getPickedItem()->nbtSerialize());
	}

	public function getPickedItem() : CushionItem{
		return (clone $this->item)->setCustomName($this->getNameTag());
	}

	public static function canFit(World $world, Vector3 $position) : bool{
		$box = new AxisAlignedBB($position->x - self::WIDTH / 2, $position->y, $position->z - self::WIDTH / 2, $position->x + self::WIDTH / 2, $position->y + self::HEIGHT, $position->z + self::WIDTH / 2);
		for($y = (int) floor($box->minY); $y < (int) ceil($box->maxY); ++$y){
			if(!$world->isInWorld((int) floor($position->x), $y, (int) floor($position->z))){
				return false;
			}
			// Bedrock does not support cushion entities overlapping custom block cells.
			if($world->getBlockAt((int) floor($position->x), $y, (int) floor($position->z))->getTypeId() >= BlockTypeIds::FIRST_UNUSED_BLOCK_ID){
				return false;
			}
		}
		foreach($world->getNearbyEntities($box) as $entity){
			if($entity instanceof self && !$entity->isFlaggedForDespawn() && $entity->isAlive()){
				return false;
			}
		}
		return true;
	}

	public function hasSupport() : bool{
		if(!$this->getWorld()->isInLoadedTerrain($this->support)){
			return true; // Never destroy saved entities because a neighboring chunk is unavailable.
		}
		$block = $this->getWorld()->getBlock($this->support);
		if($block instanceof Air || $block instanceof Liquid || $block instanceof Fire || in_array($block->getTypeId(), [BlockTypeIds::NETHER_PORTAL, BlockTypeIds::END_PORTAL, BlockTypeIds::END_GATEWAY, BlockTypeIds::LIGHT, BlockTypeIds::STRUCTURE_VOID], true)){
			return false;
		}
		if($block instanceof Fence || $block instanceof FenceGate || $block instanceof Wall){
			$heights = $block instanceof FenceGate && $block->isInWall() ? [0.8125] : [1.0];
			if ($block instanceof Wall) {
				$heights = $block->isPost() ? [1.0] : [];
				foreach ($block->getConnections() as $connection) {
					$heights[] = $connection === WallConnectionType::TALL ? 1.0 : 0.875;
				}
			}
			foreach($heights as $height){
				if(abs($block->getPosition()->y + $height - $this->location->y) < 0.01) return true;
			}
			return false;
		}
		if($block instanceof SnowLayer){
			return abs($block->getPosition()->y + $block->getLayers() / SnowLayer::MAX_LAYERS - $this->location->y) < 0.01;
		}
		$boxes = $block->getCollisionBoxes();
		if($boxes === []){
			return true;
		}
		foreach($boxes as $box){
			if(abs($box->maxY - $this->location->y) < 0.01){
				return true;
			}
		}
		return false;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		parent::entityBaseTick($tickDiff);
		if(!$this->isAlive()){
			return true;
		}
		$this->supportCheckTicks += $tickDiff;
		if($this->supportCheckTicks >= 100){
			$this->supportCheckTicks = 0;
			if(!$this->hasSupport()){
				$this->kill();
			}
		}
		if($this->rider !== null){
			$this->rider->resetFallDistance();
		}
		if($this->rider !== null && (!$this->rider->isConnected() || !$this->rider->isAlive() || $this->rider->isSpectator() || $this->rider->getWorld() !== $this->getWorld())){
			$this->dismount(false);
		}
		return true;
	}

	public function teleport(Vector3 $pos, ?float $yaw = null, ?float $pitch = null) : bool{
		if(!parent::teleport($pos, $yaw, $pitch)){
			return false;
		}
		$this->dismount(false);
		$this->support = new Vector3(floor($this->location->x), floor($this->location->y - 0.001), floor($this->location->z));
		$this->supportCheckTicks = 0;
		return true;
	}

	public function hasMovementUpdate() : bool{ return false; }
	public function setMotion(Vector3 $motion) : bool{ return false; }
	protected function move(float $dx, float $dy, float $dz) : void{
		if($dx !== 0.0 || $dy !== 0.0 || $dz !== 0.0){
			$this->kill(); // A cushion pops rather than being pushed by a piston.
		}
	}

	public function attack(EntityDamageEvent $source) : void{
		if(!$this->isAlive()){
			return;
		}
		if($source instanceof EntityDamageByEntityEvent && ($attacker = $source->getDamager()) instanceof Player && ($attacker->isAdventure() || $attacker->isSpectator())){
			$source->cancel();
		}
		$source->setBaseDamage(1.0);
		parent::attack($source);
	}

	protected function applyDamage(EntityDamageEvent $source) : void{
		$this->kill(); // Includes zero-damage projectile hits, after cancellation checks.
	}

	protected function onDeath() : void{
		$this->dismount();
		$creative = $this->lastDamageCause instanceof EntityDamageByEntityEvent && ($attacker = $this->lastDamageCause->getDamager()) instanceof Player && !$attacker->hasFiniteResources();
		if(!$creative){
			$this->getWorld()->dropItem($this->location, $this->getPickedItem());
		}
		$this->getWorld()->addParticle($this->location->add(0, self::HEIGHT / 2, 0), new BlockBreakParticle(VanillaBlocks::WOOL()->setColor($this->getColor())));
		$this->getWorld()->addSound($this->location, new CushionBreakSound());
		parent::onDeath();
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if($this->rider !== null || self::getSeatFor($player) !== null || !$this->isAlive() || $this->isFlaggedForDespawn() || !$player->isAlive() || $player->getWorld() !== $this->getWorld() || $player->isSpectator() || $player->isSneakPressed()){
			return false;
		}
		$seatPosition = Position::fromObject($this->location->add(0, self::SEAT_HEIGHT, 0), $this->getWorld());
		if(!$player->repositionForCushion($this, $seatPosition)){
			return false;
		}
		// Recheck before assigning the seat in case repositioning failed or was overridden.
		if(!$this->isAlive() || $this->isFlaggedForDespawn() || $this->getRider() !== null || self::getSeatFor($player) !== null || $player->getWorld() !== $this->getWorld() || $player->getLocation()->distanceSquared($seatPosition) > 0.01){
			return false;
		}
		$this->rider = $player;
		self::$seats ??= new WeakMap();
		self::$seats[$player] = $this;
		$player->resetFallDistance();
		$player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, true);
		$player->getNetworkProperties()->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, new Vector3(0, self::SEAT_HEIGHT + $player->getEyeHeight() * self::SEATED_EYE_HEIGHT_FACTOR, 0));
		$player->getNetworkProperties()->setByte(EntityMetadataProperties::RIDER_ROTATION_LOCKED, 0);
		$player->getNetworkProperties()->setFloat(EntityMetadataProperties::RIDER_SEAT_ROTATION_OFFSET, -90);
		$player->sendData([...$player->getViewers(), $player]);
		$this->broadcastLink(EntityLink::TYPE_RIDER, $player);
		$this->getWorld()->addSound($this->location, new CushionSitSound());
		return true;
	}

	public function dismount(bool $reposition = true) : void{
		$player = $this->rider;
		if($player === null){
			return;
		}
		$this->rider = null;
		unset(self::$seats[$player]);
		if(!$player->isConnected()){
			return;
		}
		$player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, false);
		$player->getNetworkProperties()->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, Vector3::zero());
		$player->getNetworkProperties()->setFloat(EntityMetadataProperties::RIDER_SEAT_ROTATION_OFFSET, 0);
		$player->sendData([...$player->getViewers(), $player]);
		$this->broadcastLink(EntityLink::TYPE_REMOVE, $player);
		if($reposition && $player->isAlive() && $player->getWorld() === $this->getWorld()){
			$player->repositionForCushion($this, $this->findDismountPosition($player));
			$player->resetFallDistance();
		}
		$this->getWorld()->addSound($this->location, new CushionGetUpSound());
	}

	private function findDismountPosition(Player $player) : Position{
		$position = $player->getLocation();
		$offsets = [[0, self::HEIGHT, 0], [0, 0, 0], [1, 0, 0], [-1, 0, 0], [0, 0, 1], [0, 0, -1]];
		foreach($offsets as [$dx, $dy, $dz]){
			$target = $this->location->add($dx, $dy, $dz);
			if(!$this->getWorld()->isInLoadedTerrain($target)){
				continue;
			}
			$box = $player->boundingBox->offsetCopy($target->x - $position->x, $target->y - $position->y, $target->z - $position->z);
			if($this->getWorld()->getBlockCollisionBoxes($box, $player) === []){
				return Position::fromObject($target, $this->getWorld());
			}
		}
		return $position->asPosition(); // Never force a rider into a block if all exits are obstructed.
	}

	private function broadcastLink(int $type, Player $rider) : void{
		$targets = $this->getViewers();
		$targets[spl_object_id($rider)] = $rider;
		NetworkBroadcastUtils::broadcastPackets($targets, [SetActorLinkPacket::create(new EntityLink($this->getId(), $rider->getId(), $type, true, true, 0))]);
	}

	public function sendLink(Player $viewer) : void{
		if($this->rider !== null){
			$viewer->getNetworkSession()->sendDataPacket(SetActorLinkPacket::create(new EntityLink($this->getId(), $this->rider->getId(), EntityLink::TYPE_RIDER, true, false, 0)));
		}
	}

	protected function sendSpawnPacket(Player $player) : void{
		parent::sendSpawnPacket($player);
		$this->sendLink($player);
	}

	protected function onDispose() : void{
		$this->dismount(false);
		parent::onDispose();
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::VARIANT, DyeColorIdMap::getInstance()->toInvertedId($this->getColor()));
	}

}
