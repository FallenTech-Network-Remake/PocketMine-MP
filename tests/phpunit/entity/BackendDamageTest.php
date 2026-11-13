<?php

declare(strict_types=1);

namespace pocketmine\entity;

use PHPUnit\Framework\TestCase;
use pocketmine\entity\effect\EffectManager;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\ArmorInventory;

final class BackendDamageTest extends TestCase{
	private float $health = 20.0;
	private float $absorption = 0.0;
	/** @var string[] */
	private array $writes = [];

	private function living(int $armor = 0) : Living{
		$entity = $this->getMockBuilder(Living::class)->disableOriginalConstructor()->onlyMethods([
			"getHealth", "setHealth", "getAbsorption", "setAbsorption", "isAlive", "isFireProof",
			"getArmorPoints", "damageArmor", "doHitAnimation", "__destruct",
			"getName", "getInitialSizeInfo", "getNetworkTypeId"
		])->getMock();
		// No world was constructed, so disposal must not try to detach from one.
		(new \ReflectionProperty(Entity::class, "closed"))->setValue($entity, true);
		$entity->method("getHealth")->willReturnCallback(fn() => $this->health);
		$entity->method("getAbsorption")->willReturnCallback(fn() => $this->absorption);
		$entity->method("setHealth")->willReturnCallback(function(float $value) : void{
			$this->writes[] = "health";
			$this->health = $value;
		});
		$entity->method("setAbsorption")->willReturnCallback(function(float $value) : void{
			$this->writes[] = "absorption";
			$this->absorption = $value;
		});
		$entity->method("isAlive")->willReturnCallback(fn() => $this->health > 0);
		$entity->method("isFireProof")->willReturn(false);
		$entity->method("getArmorPoints")->willReturn($armor);
		$effects = $this->createStub(EffectManager::class);
		$effects->method("get")->willReturn(null);
		$effects->method("has")->willReturn(false);
		(new \ReflectionProperty(Living::class, "effectManager"))->setValue($entity, $effects);
		$inventory = $this->createStub(ArmorInventory::class);
		$inventory->method("getContents")->willReturn([]);
		(new \ReflectionProperty(Living::class, "armorInventory"))->setValue($entity, $inventory);
		return $entity;
	}

	private function event(Living $entity, float $damage, ?\Closure $listener = null, int $cause = EntityDamageEvent::CAUSE_CUSTOM) : EntityDamageEvent{
		return new class($entity, $cause, $damage, $listener) extends EntityDamageEvent{
			public function __construct(Living $entity, int $cause, float $damage, private ?\Closure $listener){
				parent::__construct($entity, $cause, $damage);
			}
			public function call() : void{
				($this->listener ?? static function() : void{})($this);
			}
		};
	}

	public function testProtectedDamageAccounting() : void{
		$event = $this->event($this->living(), 10);
		$event->setProtectedDamage(3);
		$event->addProtectedDamage(2);
		$event->setModifier(4, EntityDamageEvent::MODIFIER_PROTECTED_DAMAGE);
		self::assertSame(19.0, $event->getFinalDamage());
		self::assertSame(10.0, $event->getFinalDamageWithoutProtected());
		self::assertSame(5.0, $event->getProtectedDamage());
		$event->setModifier(-100, EntityDamageEvent::MODIFIER_ARMOR);
		self::assertSame(9.0, $event->getFinalDamage());
		$event->setModifier(-7, EntityDamageEvent::MODIFIER_ABSORPTION);
		self::assertSame(2.0, $event->getFinalDamage());
		$event->clearProtectedDamage();
		self::assertSame(0.0, $event->getProtectedDamage());
		self::assertSame(0.0, $event->getFinalDamage());
	}

	public function testArmorDoesNotReduceProtectedDamage() : void{
		$entity = $this->living(20);
		$event = $this->event($entity, 10);
		$event->addProtectedDamage(3);
		$entity->attack($event);
		self::assertSame(15.0, $this->health);
		self::assertSame(-8.0, $event->getModifier(EntityDamageEvent::MODIFIER_ARMOR));
	}

	public function testAbsorptionIsAppliedOnceBeforeHealth() : void{
		$this->absorption = 4;
		$entity = $this->living();
		$event = $this->event($entity, 7);
		$entity->attack($event);
		self::assertSame(17.0, $this->health);
		self::assertSame(0.0, $this->absorption);
		self::assertSame(["absorption", "health"], $this->writes);
		self::assertSame(3.0, $event->getFinalDamage());
	}

	public function testListenerChangesAreRepriced() : void{
		$this->absorption = 10;
		$entity = $this->living();
		$event = $this->event($entity, 1, function(EntityDamageEvent $event) : void{
			$this->absorption = 3;
			$event->setBaseDamage(8);
			$event->addProtectedDamage(2);
		});
		$entity->attack($event);
		self::assertSame(13.0, $this->health);
		self::assertSame(0.0, $this->absorption);
		self::assertSame(7.0, $event->getFinalDamage());
	}

	public function testAbsorptionCanCoverProtectedOnlyDamage() : void{
		$this->absorption = 8;
		$entity = $this->living();
		$event = $this->event($entity, 0);
		$event->addProtectedDamage(3);
		$entity->attack($event);
		self::assertSame(20.0, $this->health);
		self::assertSame(5.0, $this->absorption);
		self::assertSame(0.0, $event->getFinalDamage());
	}

	public function testCancelledHitDoesNotConsumeAbsorption() : void{
		$this->absorption = 4;
		$entity = $this->living();
		$entity->attack($this->event($entity, 7, static fn(EntityDamageEvent $event) => $event->cancel()));
		self::assertSame(20.0, $this->health);
		self::assertSame(4.0, $this->absorption);
		self::assertSame([], $this->writes);
	}

	public function testLethalHitStillConsumesAbsorption() : void{
		$this->absorption = 4;
		$entity = $this->living();
		$entity->attack($this->event($entity, 30));
		self::assertSame(0.0, $this->health);
		self::assertSame(0.0, $this->absorption);
		self::assertSame(["absorption", "health"], $this->writes);
	}

	public function testSuicideBypassesAbsorption() : void{
		$this->absorption = 4;
		$entity = $this->living();
		$entity->attack($this->event($entity, 20, null, EntityDamageEvent::CAUSE_SUICIDE));
		self::assertSame(0.0, $this->health);
		self::assertSame(4.0, $this->absorption);
	}

	public function testDirectDamageHelper() : void{
		$this->absorption = 5;
		$entity = $this->living();
		$entity->damageWithAbsorption(-3);
		$entity->damageWithAbsorption(0);
		self::assertSame([], $this->writes);
		$entity->damageWithAbsorption(2);
		self::assertSame(3.0, $this->absorption);
		$entity->damageWithAbsorption(3);
		self::assertSame(0.0, $this->absorption);
		self::assertSame(20.0, $this->health);
		$entity->damageWithAbsorption(2);
		self::assertSame(18.0, $this->health);
	}
}
