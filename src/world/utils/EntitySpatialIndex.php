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

use pocketmine\entity\Entity;
use pocketmine\math\AxisAlignedBB;
use function array_keys;
use function count;
use function floor;
use function intdiv;
use function max;
use function sort;
use function usort;
use const SORT_NUMERIC;

/** @internal */
final class EntitySpatialIndex{
	private const int CELL_SIZE = 4;
	private const int MAX_ENTITY_CELLS = 128;

	/** @var array<string, array<int, Entity>> */
	private array $cells = [];
	/** @var array<int, array<int, array<int, Entity>>> */
	private array $entitiesByChunk = [];
	/** @var array<int, Entity> */
	private array $oversizedEntities = [];
	/** @var array<int, array{int, int, int, int, int, int}> */
	private array $bounds = [];
	/** @var array<int, array{int, int, int}> */
	private array $order = [];
	/** @var list<array{int, int}>|null */
	private ?array $sortedChunks = null;
	private int $nextOrder = 0;

	/** @return array{int, int, int, int, int, int} */
	private static function cellBounds(AxisAlignedBB $bb) : array{
		return [
			(int) floor($bb->minX / self::CELL_SIZE), (int) floor($bb->maxX / self::CELL_SIZE),
			(int) floor($bb->minY / self::CELL_SIZE), (int) floor($bb->maxY / self::CELL_SIZE),
			(int) floor($bb->minZ / self::CELL_SIZE), (int) floor($bb->maxZ / self::CELL_SIZE)
		];
	}

	/** @param array{int, int, int, int, int, int} $bounds */
	private static function cellCount(array $bounds) : float{
		return (float) ($bounds[1] - $bounds[0] + 1) * ($bounds[3] - $bounds[2] + 1) * ($bounds[5] - $bounds[4] + 1);
	}

	public function update(Entity $entity, int $chunkX, int $chunkZ) : void{
		$id = $entity->getId();
		$order = $this->order[$id] ?? null;
		if($order === null || $order[0] !== $chunkX || $order[1] !== $chunkZ){
			if($order !== null){
				$this->removeFromChunk($id, $order[0], $order[1]);
			}
			if(!isset($this->entitiesByChunk[$chunkX][$chunkZ])){
				$this->sortedChunks = null;
			}
			$this->entitiesByChunk[$chunkX][$chunkZ][$id] = $entity;
			$this->order[$id] = [$chunkX, $chunkZ, $this->nextOrder++];
		}
		$bounds = self::cellBounds($entity->boundingBox);
		if(($this->bounds[$id] ?? null) === $bounds){
			return;
		}
		$this->removeCells($id);
		$this->bounds[$id] = $bounds;
		if(self::cellCount($bounds) > self::MAX_ENTITY_CELLS){
			$this->oversizedEntities[$id] = $entity;
			return;
		}
		[$minX, $maxX, $minY, $maxY, $minZ, $maxZ] = $bounds;
		for($x = $minX; $x <= $maxX; ++$x){
			for($z = $minZ; $z <= $maxZ; ++$z){
				for($y = $minY; $y <= $maxY; ++$y){
					$this->cells["$x:$z:$y"][$id] = $entity;
				}
			}
		}
	}

	private function removeCells(int $id) : void{
		if(isset($this->oversizedEntities[$id])){
			unset($this->oversizedEntities[$id]);
		}elseif(isset($this->bounds[$id])){
			[$minX, $maxX, $minY, $maxY, $minZ, $maxZ] = $this->bounds[$id];
			for($x = $minX; $x <= $maxX; ++$x){
				for($z = $minZ; $z <= $maxZ; ++$z){
					for($y = $minY; $y <= $maxY; ++$y){
						$key = "$x:$z:$y";
						unset($this->cells[$key][$id]);
						if($this->cells[$key] === []){
							unset($this->cells[$key]);
						}
					}
				}
			}
		}
	}

	public function remove(Entity $entity) : void{
		$id = $entity->getId();
		$this->removeCells($id);
		$order = $this->order[$id] ?? null;
		if($order !== null){
			$this->removeFromChunk($id, $order[0], $order[1]);
		}
		unset($this->bounds[$id], $this->order[$id]);
	}

	private function removeFromChunk(int $id, int $chunkX, int $chunkZ) : void{
		unset($this->entitiesByChunk[$chunkX][$chunkZ][$id]);
		if($this->entitiesByChunk[$chunkX][$chunkZ] === []){
			unset($this->entitiesByChunk[$chunkX][$chunkZ]);
			if($this->entitiesByChunk[$chunkX] === []){
				unset($this->entitiesByChunk[$chunkX]);
			}
			$this->sortedChunks = null;
		}
	}

	/** @return list<Entity> */
	public function getNearbyEntities(AxisAlignedBB $bb, ?Entity $excluded = null) : array{
		$bounds = self::cellBounds($bb);
		$fullScan = self::cellCount($bounds) > max(64, count($this->cells));
		$scanThreshold = max(64, intdiv(count($this->bounds), 2));
		$candidates = $this->oversizedEntities;
		if(!$fullScan){
			[$minX, $maxX, $minY, $maxY, $minZ, $maxZ] = $bounds;
			for($x = $minX; $x <= $maxX; ++$x){
				for($z = $minZ; $z <= $maxZ; ++$z){
					for($y = $minY; $y <= $maxY; ++$y){
						$cell = $this->cells["$x:$z:$y"] ?? [];
						if(count($cell) > $scanThreshold){
							return $this->getNearbyEntitiesInAllChunks($bb, $excluded);
						}
						$candidates += $cell;
					}
				}
			}
		}
		if($fullScan || count($candidates) > $scanThreshold){
			return $this->getNearbyEntitiesInAllChunks($bb, $excluded);
		}
		$nearby = [];
		foreach($candidates as $entity){
			if($entity !== $excluded && $entity->boundingBox->intersectsWith($bb)){
				$nearby[] = $entity;
			}
		}
		$this->sortEntities($nearby);
		return $nearby;
	}

	/** @return list<Entity> */
	private function getNearbyEntitiesInAllChunks(AxisAlignedBB $bb, ?Entity $excluded) : array{
		$chunks = $this->sortedChunks;
		if($chunks === null){
			$chunks = [];
			$xs = array_keys($this->entitiesByChunk);
			sort($xs, SORT_NUMERIC);
			foreach($xs as $x){
				$zs = array_keys($this->entitiesByChunk[$x]);
				sort($zs, SORT_NUMERIC);
				foreach($zs as $z){
					$chunks[] = [$x, $z];
				}
			}
			$this->sortedChunks = $chunks;
		}
		$nearby = [];
		foreach($chunks as [$x, $z]){
			foreach($this->entitiesByChunk[$x][$z] as $entity){
				if($entity !== $excluded && $entity->boundingBox->intersectsWith($bb)){
					$nearby[] = $entity;
				}
			}
		}
		return $nearby;
	}

	/** @param list<Entity> $entities */
	private function sortEntities(array &$entities) : void{
		// Preserve chunk iteration and insertion order, including item merge tie-breaking.
		for($i = 1, $count = count($entities); $i < $count; ++$i){
			if($this->order[$entities[$i - 1]->getId()] > $this->order[$entities[$i]->getId()]){
				usort($entities, fn(Entity $a, Entity $b) : int => $this->order[$a->getId()] <=> $this->order[$b->getId()]);
				break;
			}
		}
	}
}
